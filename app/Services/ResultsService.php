<?php

namespace App\Services;

use App\Models as M;
use App\Notifications\WorkflowNotice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException as V;

class ResultsService
{
    public function complete(M\Examination $e): array
    {
        $issues = [];
        foreach ($e->courses()->with('submission', 'components')->get() as $c) {
            if (! $c->components()->exists()) {
                $issues[] = ['course_id' => $c->id, 'type' => 'missing_components'];
            }
            if (bccomp((string) $c->components()->sum('weight'), '100', 2) !== 0) {
                $issues[] = ['course_id' => $c->id, 'type' => 'invalid_component_weights'];
            }
            if ($c->submission?->status !== 'submitted') {
                $issues[] = ['course_id' => $c->id, 'type' => 'unsubmitted'];
            }
            foreach (app(MarksService::class)->missing($c) as $x) {
                $issues[] = ['course_id' => $c->id, ...$x];
            }
        }
        if (! $e->courses()->exists()) {
            $issues[] = ['type' => 'missing_courses'];
        }

        return $issues;
    }

    public function calculate(M\Examination $e): M\ResultBatch
    {
        return DB::transaction(function () use ($e) {
            $e = M\Examination::lockForUpdate()->findOrFail($e->id);
            $b = M\ResultBatch::firstOrCreate(['examination_id' => $e->id]);
            if (in_array($b->status, ['approved', 'published'])) {
                throw V::withMessages(['results' => 'Approved/published batches cannot be recalculated directly.']);
            }
            $issues = $this->complete($e);
            if ($issues) {
                throw V::withMessages(['results' => count($issues).' completeness issue(s); inspect analysis before calculating.']);
            }
            foreach ($e->courses()->with('components', 'course', 'examination.gradingScheme.boundaries')->get() as $c) {
                $marks = M\Mark::whereIn('assessment_component_id', $c->components->pluck('id'))->get()->keyBy(fn ($m) => $m->assessment_component_id.':'.$m->student_id);
                foreach ($c->eligible()->pluck('student_id') as $id) {
                    $this->calculateStudent($b, $c, $id, $marks);
                }
            }
            app(AuditService::class)->record('results.calculated', $b, ['count' => $b->results()->count()]);
            app(AnalysisService::class)->run($e, auth()->id());

            return $b;
        });
    }

    private function calculateStudent(M\ResultBatch $b, M\ExamCourse $c, int $id, ?Collection $marks = null): M\CalculatedResult
    {
        return M\CalculatedResult::updateOrCreate(['exam_course_id' => $c->id, 'student_id' => $id], ['result_batch_id' => $b->id, ...app(GradingService::class)->calculate($c, $id, $marks)]);
    }

    public function transition(M\ResultBatch $batch, string $status, int $actor): void
    {
        DB::transaction(function () use ($batch, $status, $actor) {
            M\Examination::whereKey($batch->examination_id)->lockForUpdate()->first();
            $b = M\ResultBatch::lockForUpdate()->findOrFail($batch->id);
            $next = ['draft' => 'submitted', 'returned' => 'submitted', 'submitted' => 'under_review', 'under_review' => 'approved', 'approved' => 'published'];
            if (($next[$b->status] ?? null) !== $status) {
                throw V::withMessages(['status' => 'Invalid result state transition.']);
            }
            if ($this->complete($b->examination)) {
                throw V::withMessages(['status' => 'Results are incomplete.']);
            }
            $expected = 0;
            foreach ($b->examination->courses as $c) {
                $expected += $c->eligible()->count();
            }
            if ($b->results()->count() !== $expected) {
                throw V::withMessages(['status' => 'Calculate every eligible result first.']);
            }
            $data = ['status' => $status];
            if ($status === 'approved') {
                // Recalculate on approval so returned/resubmitted marks cannot publish stale draft results.
                $this->calculate($b->examination);
                $data['approved_by'] = $actor;
            }
            if ($status === 'published') {
                $data['published_at'] = now();
                if ($b->examination->status !== 'published') {
                    throw V::withMessages(['status' => 'Publish the examination schedule before results.']);
                }
            }
            $b->update($data);
            app(AuditService::class)->record('results.'.$status, $b);
            if ($status === 'published') {
                M\User::whereIn('id', $b->results()->pluck('student_id'))->get()->each(fn ($u) => $u->notify(new WorkflowNotice('Results published', $b->examination->name, '/student/my-results')));
            }
        });
    }

    public function requestCorrection(M\Mark $mark, ?string $score, bool $absent, string $reason, int $actor): M\ResultCorrection
    {
        $max = $mark->component->maximum;
        if (($absent && $score !== null) || (! $absent && ($score === null || ! is_numeric($score) || bccomp($score, '0', 2) < 0 || bccomp($score, (string) $max, 2) > 0))) {
            throw V::withMessages(['score' => 'Provide a valid score or an absence, not both.']);
        }
        if ($mark->component->examCourse->examination->batch?->status !== 'published') {
            throw V::withMessages(['correction' => 'Published corrections require a published result batch.']);
        }
        $p = M\ResultCorrection::create(['mark_id' => $mark->id, 'old_score' => $mark->score, 'new_score' => $score, 'old_absent' => $mark->absent, 'new_absent' => $absent, 'reason' => $reason, 'requested_by' => $actor]);
        app(AuditService::class)->record('correction.requested', $p);

        return $p;
    }

    public function approveCorrection(M\ResultCorrection $p, int $actor): void
    {
        DB::transaction(function () use ($p, $actor) {
            $p = M\ResultCorrection::lockForUpdate()->findOrFail($p->id);
            if ($p->status !== 'pending' || $p->requested_by === $actor) {
                throw V::withMessages(['correction' => 'A different administrator must approve a pending correction.']);
            }
            $m = M\Mark::lockForUpdate()->findOrFail($p->mark_id);
            if ($m->score !== $p->old_score || $m->absent !== $p->old_absent) {
                throw V::withMessages(['correction' => 'Record changed since proposal. Create a fresh request.']);
            }
            $c = $m->component->examCourse;
            $b = M\ResultBatch::lockForUpdate()->where('examination_id', $c->examination_id)->firstOrFail();
            $before = M\CalculatedResult::where('exam_course_id', $c->id)->where('student_id', $m->student_id)->firstOrFail()->toArray();
            $m->update(['score' => $p->new_score, 'absent' => $p->new_absent, 'entered_by' => $actor]);
            $after = $this->calculateStudent($b, $c, $m->student_id);
            $p->update(['status' => 'approved', 'approved_by' => $actor]);
            app(AuditService::class)->record('correction.approved', $p, ['before' => $before, 'after' => $after->toArray(), 'reason' => $p->reason]);
            $m->student->notify(new WorkflowNotice('Published result corrected', 'An approved correction has updated your result.', '/student/my-results'));
        });
    }

    public function recheck(M\CalculatedResult $result, int $student, string $reason): M\RecheckRequest
    {
        if ($result->student_id !== $student || $result->batch->status !== 'published') {
            abort(403);
        }
        $r = M\RecheckRequest::create(['calculated_result_id' => $result->id, 'student_id' => $student, 'reason' => $reason]);
        app(AuditService::class)->record('recheck.requested', $r);

        return $r;
    }

    public function decide(M\RecheckRequest $r, string $status, string $reason, int $actor): void
    {
        DB::transaction(function () use ($r, $status, $reason, $actor) {
            $r = M\RecheckRequest::lockForUpdate()->findOrFail($r->id);
            if (! in_array($r->status, ['pending', 'under_review']) || ! in_array($status, ['under_review', 'approved', 'rejected'])) {
                throw V::withMessages(['status' => 'Invalid recheck transition.']);
            }
            $r->update(['status' => $status, 'decision_reason' => $reason, 'decided_by' => $actor]);
            app(AuditService::class)->record('recheck.'.$status, $r, ['reason' => $reason]);
            $r->student->notify(new WorkflowNotice('Recheck updated', 'Your request is '.$status.'. Approval does not automatically change marks.', '/student/recheck-requests'));
        });
    }
}
