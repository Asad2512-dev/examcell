<?php

namespace App\Services;

use App\Models as M;
use App\Support\Resources;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException as V;

class AcademicService
{
    public function save(string $resource, array $data, ?int $id = null)
    {
        return DB::transaction(function () use ($resource, $data, $id) {
            [$class] = Resources::get($resource);
            $m = $id ? $class::lockForUpdate()->findOrFail($id) : new $class;
            if ($m->exists) {
                if ($m instanceof M\GradeBoundary && M\GradingScheme::findOrFail($m->grading_scheme_id)->status !== 'draft') {
                    throw V::withMessages(['record' => 'An approved boundary cannot be moved or edited; create a new grading version.']);
                }
                if ($m instanceof M\AssessmentComponent && M\ExamCourse::findOrFail($m->exam_course_id)->examination->status !== 'draft') {
                    throw V::withMessages(['record' => 'An approved assessment cannot be moved or edited.']);
                }
                if (($m instanceof M\Enrollment || $m instanceof M\TeacherAssignment) && M\ExamCourse::where('course_id', $m->course_id)->where('section_id', $m->section_id)->whereHas('examination', fn ($q) => $q->where('status', '!=', 'draft'))->exists()) {
                    throw V::withMessages(['record' => 'Historical enrollment and teaching assignments cannot be moved.']);
                }
                $history = match (true) {
                    $m instanceof M\AcademicSession => M\Examination::where('academic_session_id', $m->id),
                    $m instanceof M\Section => M\Examination::whereHas('courses', fn ($q) => $q->where('section_id', $m->id)),
                    $m instanceof M\Semester => M\Examination::whereHas('courses.section', fn ($q) => $q->where('semester_id', $m->id)),
                    $m instanceof M\Department => M\Examination::whereHas('courses.course', fn ($q) => $q->where('department_id', $m->id)),
                    default => null,
                };
                if ($history && $history->where('status', '!=', 'draft')->exists()) {
                    throw V::withMessages(['record' => 'Academic settings used by approved examinations are immutable. Create a new academic version.']);
                }
            }
            if ($m instanceof M\User) {
                if (empty($data['password'])) {
                    unset($data['password']);
                }
                if ($m->id === auth()->id() && (($data['active'] ?? true) == false || ($data['role'] ?? 'admin') !== 'admin')) {
                    throw V::withMessages(['active' => 'You cannot deactivate or demote your own administrator account.']);
                }
                if ($m->exists && $m->role !== $data['role']) {
                    throw V::withMessages(['role' => 'Existing account roles cannot change; provision a separate identity to preserve records.']);
                }
            }
            foreach (['student_id' => 'student', 'user_id' => 'student', 'teacher_id' => 'teacher'] as $f => $role) {
                if (isset($data[$f]) && ! M\User::whereKey($data[$f])->where('role', $role)->exists()) {
                    throw V::withMessages([$f => 'Select a '.$role.' account.']);
                }
            }
            if ($m instanceof M\GradeBoundary) {
                $scheme = M\GradingScheme::lockForUpdate()->findOrFail($data['grading_scheme_id']);
                if ($scheme->status !== 'draft') {
                    throw V::withMessages(['grading_scheme_id' => 'Only draft schemes can change.']);
                }
            }
            if ($m instanceof M\GradingScheme && $m->exists && M\Examination::where('grading_scheme_id', $m->id)->exists()) {
                throw V::withMessages(['status' => 'A scheme used by an examination is immutable; create a new version.']);
            }
            if ($m instanceof M\GradingScheme && ($data['status'] ?? '') === 'approved') {
                $previous = '-1';
                foreach ($m->boundaries()->orderBy('minimum')->get() as $boundary) {
                    if (bccomp((string) $boundary->grade_points, $previous, 2) < 0) {
                        throw V::withMessages(['status' => 'Grade points must not decrease as percentage thresholds increase.']);
                    }
                    $previous = (string) $boundary->grade_points;
                }
                if (! $m->exists || ! $m->boundaries()->where('minimum', 0)->exists()) {
                    throw V::withMessages(['status' => 'Add boundaries including a zero threshold before approval.']);
                }
            }
            $course = $m instanceof M\AssessmentComponent ? M\ExamCourse::findOrFail($data['exam_course_id']) : ($m instanceof M\ExamCourse ? $m : null);
            if ($course && $course->exists && $course->examination->status !== 'draft') {
                throw V::withMessages(['examination_id' => 'Published or approved examinations are locked.']);
            }
            if ($m instanceof M\ExamCourse) {
                $e = M\Examination::findOrFail($data['examination_id']);
                if ($e->status !== 'draft') {
                    throw V::withMessages(['examination_id' => 'Only draft examinations can change.']);
                }
                $s = M\Section::findOrFail($data['section_id']);
                if ($s->semester->academic_session_id !== $e->academic_session_id) {
                    throw V::withMessages(['section_id' => 'Section is outside this examination session.']);
                }
                if (M\Course::findOrFail($data['course_id'])->department_id !== $s->department_id) {
                    throw V::withMessages(['course_id' => 'Course and section departments must match.']);
                }
            }
            if ($m instanceof M\Enrollment && M\Course::findOrFail($data['course_id'])->department_id !== M\Section::findOrFail($data['section_id'])->department_id) {
                throw V::withMessages(['course_id' => 'Enrollment department mismatch.']);
            }
            if ($m instanceof M\AssessmentComponent) {
                M\ExamCourse::whereKey($course->id)->lockForUpdate()->first();
                if (bccomp(bcadd((string) $course->components()->where('id', '!=', $id ?? 0)->sum('weight'), (string) $data['weight'], 2), '100', 2) > 0) {
                    throw V::withMessages(['weight' => 'Component weights cannot exceed 100%.']);
                }
            }
            if ($m instanceof M\Examination && $m->exists && $m->status !== 'draft') {
                throw V::withMessages(['name' => 'Approved examinations are locked; create a new examination version.']);
            }
            if ($m instanceof M\Enrollment || $m instanceof M\TeacherAssignment) {
                $locked = M\ExamCourse::where('course_id', $data['course_id'])->where('section_id', $data['section_id'])->whereHas('examination', fn ($q) => $q->where('status', '!=', 'draft'))->exists();
                if ($locked) {
                    throw V::withMessages(['record' => 'Assignments and enrollment for approved examinations are locked. Create a new academic section or examination version.']);
                }
            }
            if ($m instanceof M\StudentProfile && $m->exists && M\ExamCourse::whereIn('course_id', M\Enrollment::where('student_id', $m->user_id)->pluck('course_id'))->whereHas('examination', fn ($q) => $q->where('status', '!=', 'draft'))->exists()) {
                throw V::withMessages(['record' => 'Profiles referenced by approved examinations are immutable; keep their academic history.']);
            }
            if ($m instanceof M\Course && $m->exists && M\ExamCourse::where('course_id', $m->id)->whereHas('examination', fn ($q) => $q->where('status', '!=', 'draft'))->exists()) {
                throw V::withMessages(['record' => 'This course is used by approved examinations; credit and identity changes require a new course version.']);
            }
            $before = $m->exists ? $m->getAttributes() : [];
            $m->fill($data)->save();
            app(AuditService::class)->record($resource.($id ? '.updated' : '.created'), $m, ['before' => array_diff_key($before, array_flip(['password', 'remember_token'])), 'after' => $m->toArray()]);
            if ($m instanceof M\Enrollment) {
                foreach (M\Examination::whereHas('courses', fn ($q) => $q->where('course_id', $m->course_id)->where('section_id', $m->section_id))->get() as $exam) {
                    app(AnalysisService::class)->run($exam, auth()->id());
                }
            }

            return $m;
        });
    }
}
