<?php

namespace App\Services;

use App\Models as M;
use App\Notifications\WorkflowNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException as V;

class MarksService
{
    public function save(M\ExamCourse $c, array $rows, int $actor, bool $submit = false): M\MarkSubmission
    {
        Gate::authorize('update', $c);

        return DB::transaction(function () use ($c, $rows, $actor, $submit) {
            M\Examination::whereKey($c->examination_id)->lockForUpdate()->first();
            $c = M\ExamCourse::lockForUpdate()->findOrFail($c->id);
            $batch = M\ResultBatch::where('examination_id', $c->examination_id)->lockForUpdate()->first();
            if ($batch && in_array($batch->status, ['approved', 'published'])) {
                throw V::withMessages(['marks' => 'Approved and published results are locked.']);
            }
            $s = M\MarkSubmission::firstOrCreate(['exam_course_id' => $c->id], ['teacher_id' => $actor]);
            if ($s->status === 'submitted') {
                throw V::withMessages(['marks' => 'Submitted marks are locked; an administrator must return them for correction.']);
            }
            if ($s->teacher_id !== $actor && auth()->user()->role !== 'admin') {
                throw V::withMessages(['marks' => 'Another teacher owns this submission.']);
            }
            $eligible = $c->eligible()->pluck('student_id')->all();
            $components = $c->components()->get()->keyBy('id');
            $seen = [];
            foreach ($rows as $row) {
                $key = $row['assessment_component_id'].':'.$row['student_id'];
                if (isset($seen[$key])) {
                    throw V::withMessages(['marks' => 'Duplicate student/component entry.']);
                }
                $seen[$key] = true;
                $comp = $components->get($row['assessment_component_id']);
                if (! $comp || ! in_array((int) $row['student_id'], $eligible, true)) {
                    throw V::withMessages(['marks' => 'Student or component outside this assignment.']);
                }
                $absent = (bool) ($row['absent'] ?? false);
                $score = $row['score'] ?? null;
                if ($score === '') {
                    $score = null;
                }
                if ($absent && $score !== null) {
                    throw V::withMessages(['marks' => 'Absent is distinct from a scored or missing mark; leave score blank.']);
                }
                if ($score !== null && (! is_numeric($score) || ! preg_match('/^\d+(?:\.\d{1,2})?$/', (string) $score) || bccomp((string) $score, '0', 2) < 0 || bccomp((string) $score, (string) $comp->maximum, 2) > 0)) {
                    throw V::withMessages(['marks' => 'Score must be between zero and the component maximum.']);
                }M\Mark::updateOrCreate(['assessment_component_id' => $comp->id, 'student_id' => $row['student_id']], ['score' => $score, 'absent' => $absent, 'entered_by' => $actor]);
            }
            if ($submit) {
                $missing = $this->missing($c);
                if ($missing) {
                    throw V::withMessages(['marks' => 'Cannot submit: '.count($missing).' missing component mark(s).']);
                }
                if ($components->isEmpty()) {
                    throw V::withMessages(['marks' => 'Configure components before submission.']);
                }
                $s->update(['status' => 'submitted', 'return_reason' => null]);
            } else {
                $s->update(['status' => 'draft']);
            }
            app(AuditService::class)->record($submit ? 'marks.submitted' : 'marks.saved', $s, ['row_count' => count($rows)]);
            app(AnalysisService::class)->run($c->examination, $actor);

            return $s;
        });
    }

    public function missing(M\ExamCourse $c): array
    {
        $missing = [];
        $marks = M\Mark::whereIn('assessment_component_id', $c->components()->pluck('id'))->get()->keyBy(fn ($m) => $m->assessment_component_id.':'.$m->student_id);
        foreach ($c->eligible()->pluck('student_id') as $student) {
            foreach ($c->components as $comp) {
                $m = $marks->get($comp->id.':'.$student);
                if (! $m || ($m->score === null && ! $m->absent)) {
                    $missing[] = ['student_id' => $student, 'component_id' => $comp->id, 'type' => $m ? 'missing_score' : 'missing_record'];
                }
            }
        }

        return $missing;
    }

    public function return(M\ExamCourse $c, string $reason): void
    {
        DB::transaction(function () use ($c, $reason) {
            M\ExamCourse::whereKey($c->id)->lockForUpdate()->first();
            $b = $c->examination->batch;
            if ($b && in_array($b->status, ['approved', 'published'])) {
                throw V::withMessages(['marks' => 'Use the published correction approval workflow.']);
            }
            $s = M\MarkSubmission::where('exam_course_id', $c->id)->firstOrFail();
            $s->update(['status' => 'returned', 'return_reason' => $reason]);
            if ($b) {
                $b->update(['status' => 'returned']);
            }
            app(AuditService::class)->record('marks.returned', $s, ['reason' => $reason]);
            M\User::find($s->teacher_id)->notify(new WorkflowNotice('Marks returned', $reason, '/teacher/marks-entry'));
        });
    }
}
