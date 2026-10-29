<?php

namespace App\Services;

use App\Models as M;
use Illuminate\Database\Eloquent\Builder;

/** Read filters only: deliberately not a global model scope or an authorization rule. */
class AcademicContext
{
    public const SESSION_KEY = 'academic_context';

    public function current(?M\User $user = null): array
    {
        $user ??= auth()->user();
        $request = request();
        if ($user?->role !== 'admin' || ! $request->hasSession()) {
            return ['session_id' => null, 'semester_id' => null];
        }
        $cacheKey = 'academic_context.resolved.'.$user->id;
        if ($request->attributes->has($cacheKey)) {
            return $request->attributes->get($cacheKey);
        }
        $saved = $request->session()->get(self::SESSION_KEY, []);
        $session = ($saved['user_id'] ?? null) === $user->id
            ? M\AcademicSession::find($saved['session_id'] ?? 0) : null;
        if (! $session) {
            $session = M\AcademicSession::where('active', true)->whereDate('starts_on', '<=', today())
                ->whereDate('ends_on', '>=', today())->orderByDesc('starts_on')->orderByDesc('id')->first()
                ?? M\AcademicSession::where('active', true)->orderByDesc('starts_on')->orderByDesc('id')->first()
                ?? M\AcademicSession::orderByDesc('starts_on')->orderByDesc('id')->first();
            $saved = [];
        }
        $semester = $session && ! empty($saved['semester_id'])
            ? M\Semester::where('academic_session_id', $session->id)->find($saved['semester_id']) : null;
        $state = ['session_id' => $session?->id, 'semester_id' => $semester?->id];
        $request->session()->put(self::SESSION_KEY, $state + ['user_id' => $user->id]);
        $request->attributes->set($cacheKey, $state);

        return $state;
    }

    public function apply(Builder|\Illuminate\Database\Eloquent\Relations\Relation $query, ?M\User $user = null): Builder|\Illuminate\Database\Eloquent\Relations\Relation
    {
        $user ??= auth()->user();
        if ($user?->role !== 'admin') {
            return $query;
        }
        $state = $this->current($user);
        if (! $state['session_id']) {
            return $query;
        }
        $model = get_class($query->getModel());
        $sessionId = $state['session_id'];
        $semesterId = $state['semester_id'];
        $sections = $this->sections($state)->select('sections.id');
        $courses = $this->courses($state)->select('exam_courses.id');
        $exams = $this->examinations($state)->select('examinations.id');
        $schedules = M\ScheduleEntry::whereIn('exam_course_id', $courses)->select('id');
        $duties = M\DutyAssignment::whereIn('schedule_entry_id', $schedules)->select('id');
        $results = M\CalculatedResult::whereIn('exam_course_id', $courses)->select('id');
        $components = M\AssessmentComponent::whereIn('exam_course_id', $courses)->select('id');

        return match ($model) {
            M\Semester::class => $query->where('academic_session_id', $sessionId)->when($semesterId, fn ($q) => $q->whereKey($semesterId)),
            M\Section::class => $query->whereIn('id', $sections),
            M\StudentProfile::class, M\Enrollment::class, M\TeacherAssignment::class => $query->whereIn('section_id', $sections),
            M\Examination::class => $query->whereIn('id', $exams),
            M\ExamCourse::class => $query->whereIn('id', $courses),
            M\ScheduleEntry::class, M\AssessmentComponent::class, M\MarkSubmission::class,
            M\MarksSubmissionDeadline::class, M\CalculatedResult::class => $query->whereIn('exam_course_id', $courses),
            M\Mark::class => $query->whereIn('assessment_component_id', $components),
            M\SeatingPlan::class, M\DutyAssignment::class => $query->whereIn('schedule_entry_id', $schedules),
            M\SeatAssignment::class => $query->whereIn('seating_plan_id', M\SeatingPlan::whereIn('schedule_entry_id', $schedules)->select('id')),
            M\DutyReplacement::class, M\DutyRequest::class => $query->whereIn('duty_assignment_id', $duties),
            M\RecheckRequest::class => $query->whereIn('calculated_result_id', $results),
            M\ResultCorrection::class => $query->whereIn('mark_id', M\Mark::whereIn('assessment_component_id', $components)->select('id')),
            M\ResultBatch::class, M\AdmitGenerationRun::class => $query->whereIn('examination_id', $exams),
            M\AdmitCard::class => $query->whereIn('examination_id', $exams)->when($semesterId, fn ($q) => $q->whereIn('student_id', M\Enrollment::whereIn('section_id', $sections)->select('student_id'))),
            M\AnalysisFlag::class => $query->whereIn('examination_id', $exams)->when($semesterId, fn ($q) => $q->where(fn ($students) => $students->whereNull('student_id')->orWhereIn('student_id', M\Enrollment::whereIn('section_id', $sections)->select('student_id')))),
            M\AuditLog::class => $this->audit($query, $user),
            // Accounts, departments, course definitions, rooms, availability, grading,
            // backups and notifications are shared/global, not semester-owned records.
            default => $query,
        };
    }

    private function sections(array $state): Builder
    {
        return M\Section::whereIn('semester_id', M\Semester::where('academic_session_id', $state['session_id'])
            ->when($state['semester_id'], fn ($q) => $q->whereKey($state['semester_id']))->select('id'));
    }

    private function courses(array $state): Builder
    {
        return M\ExamCourse::whereIn('examination_id', M\Examination::where('academic_session_id', $state['session_id'])->select('id'))
            ->whereIn('section_id', $this->sections($state)->select('id'));
    }

    private function examinations(array $state): Builder
    {
        return M\Examination::where('academic_session_id', $state['session_id'])
            ->when($state['semester_id'], fn ($q) => $q->whereIn('id', $this->courses($state)->select('examination_id')));
    }

    private function audit(Builder $query, M\User $user): Builder
    {
        $types = [M\Examination::class, M\ExamCourse::class, M\Semester::class, M\Section::class,
            M\StudentProfile::class, M\Enrollment::class, M\TeacherAssignment::class, M\ScheduleEntry::class,
            M\AssessmentComponent::class, M\MarksSubmissionDeadline::class, M\MarkSubmission::class,
            M\Mark::class, M\CalculatedResult::class, M\SeatingPlan::class, M\SeatAssignment::class,
            M\DutyAssignment::class, M\DutyRequest::class, M\DutyReplacement::class, M\RecheckRequest::class,
            M\ResultCorrection::class, M\ResultBatch::class, M\AdmitGenerationRun::class, M\AdmitCard::class, M\AnalysisFlag::class];

        return $query->where(function ($q) use ($types, $user) {
            $q->whereNull('entity_type')->orWhereNotIn('entity_type', $types);
            foreach ($types as $type) {
                $q->orWhere(fn ($entry) => $entry->where('entity_type', $type)
                    ->whereIn('entity_id', $this->apply($type::query(), $user)->select('id')));
            }
        });
    }
}
