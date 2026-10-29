<?php

namespace App\Jobs;

use App\Models as M;
use App\Services\AI as AI;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class RunAiTask implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $runId) {}

    public function handle(): void
    {
        $run = M\AiRun::findOrFail($this->runId);
        $run->update(['status' => 'running', 'progress' => 10, 'message' => 'Asking the AI…']);
        $progress = fn (int $i, int $max) => $run->update(['progress' => 10 + (int) (80 * ($i - 1) / $max), 'message' => $i === 1 ? 'AI is drafting a proposal…' : "Fixing validation problems (attempt $i of $max)…"]);
        $in = $run->input;
        try {
            $result = match ($run->feature) {
                'datesheet' => app(AI\DateSheetService::class)->generate(M\Examination::findOrFail($in['exam_id']), $in['constraints'] ?? null, $run->user_id, $in['previous'] ?? null, $in['instruction'] ?? null, $progress),
                'seating' => app(AI\SeatingAiService::class)->generate(M\ScheduleEntry::findOrFail($in['entry_id']), $run->user_id, $progress),
                'duties' => app(AI\DutyAiService::class)->generate(M\Examination::findOrFail($in['exam_id']), $run->user_id, $progress),
                'import' => $this->import($run, $in),
            };
            $run->update(['status' => 'needs_review', 'progress' => 100, 'message' => 'Ready for your review.', 'result' => $result]);
        } catch (\Throwable $e) {
            $safe = $e instanceof AI\AiException || $e instanceof \Illuminate\Validation\ValidationException;
            $run->update(['status' => 'failed', 'progress' => 100, 'message' => $safe ? ($e instanceof \Illuminate\Validation\ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage()) : 'Something went wrong. Nothing was changed.']);
            if (! $safe) {
                report($e);
            }
        } finally {
            if ($run->feature === 'import' && ! empty($in['path'])) {
                Storage::disk('local')->delete($in['path']);
            }
        }
    }

    private function import(M\AiRun $run, array $in): array
    {
        $svc = app(AI\ImportAiService::class);
        $table = $svc->table(Storage::disk('local')->path($in['path']), $in['ext'], $run->user_id);
        $run->update(['progress' => 50, 'message' => 'Mapping columns…']);

        return $svc->analyze($table, $run->user_id, $in['course_id'] ?? null, $in['target'] ?? null);
    }
}
