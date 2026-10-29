<?php

namespace App\Jobs;

use App\Models as M;
use App\Notifications\WorkflowNotice;
use App\Services as S;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateAdmitCards implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $runId) {}

    public function handle(): void
    {
        $run = M\AdmitGenerationRun::findOrFail($this->runId);
        $actor = M\User::findOrFail($run->requested_by);
        $run->update(['status' => 'processing']);
        $e = M\Examination::findOrFail($run->examination_id);
        try {
            $ids = collect();
            foreach ($e->courses as $c) {
                $ids = $ids->merge($c->eligible()->pluck('student_id'));
            }
            foreach ($ids->unique() as $id) {
                app(S\ReportService::class)->admit($e, $id);
                $run->increment('generated');
            }
            $run->update(['status' => 'completed']);
            app(S\AuditService::class)->record('admit.bulk_completed', $run, ['requested_by' => $actor->id, 'generated' => $run->generated]);
            $actor->notify(new WorkflowNotice('Admit card generation completed', $run->generated.' private PDFs generated.', '/admin/admit-card-generation'));
        } catch (\Throwable $error) {
            $run->update(['status' => 'failed', 'failure' => 'Generation failed. Check published examination eligibility and private storage permissions. Already generated cards remain available.']);
            $actor->notify(new WorkflowNotice('Admit card generation failed', $run->failure, '/admin/admit-card-generation'));
            throw new \RuntimeException($run->failure);
        }
    }

    public function failed(?\Throwable $e): void
    {
        M\AdmitGenerationRun::whereKey($this->runId)->whereNotIn('status', ['completed', 'failed'])->update(['status' => 'failed', 'failure' => 'Worker interrupted or timed out. Review generated cards before retrying.']);
    }
}
