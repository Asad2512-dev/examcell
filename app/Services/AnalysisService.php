<?php

namespace App\Services;

use App\Models as M;
use Illuminate\Support\Facades\DB;

class AnalysisService
{
    public function run(M\Examination $e, int $actor): M\AiAnalysisRun
    {
        return DB::transaction(function () use ($e, $actor) {
            $seen = [];
            $conditions = app(ResultsService::class)->complete($e);
            foreach ($e->courses as $c) {
                foreach ($c->eligible()->pluck('student_id') as $id) {
                    if (! M\CalculatedResult::where('exam_course_id', $c->id)->where('student_id', $id)->exists()) {
                        $conditions[] = ['course_id' => $c->id, 'student_id' => $id, 'type' => 'missing_result'];
                    }
                }
                foreach (M\Mark::whereIn('assessment_component_id', $c->components()->pluck('id'))->where('absent', true)->get() as $m) {
                    $conditions[] = ['course_id' => $c->id, 'student_id' => $m->student_id, 'component_id' => $m->assessment_component_id, 'type' => 'absent'];
                }
                $scores = M\CalculatedResult::where('exam_course_id', $c->id)->get();
                if ($scores->count() >= 5) {
                    $avg = $scores->avg('percentage');
                    $variance = $scores->avg(fn ($r) => pow((float) $r->percentage - $avg, 2));
                    $sd = sqrt($variance);
                    if ($sd > 0) {
                        foreach ($scores as $r) {
                            if (abs((float) $r->percentage - $avg) > 2 * $sd) {
                                $conditions[] = ['course_id' => $c->id, 'student_id' => $r->student_id, 'type' => 'statistical_outlier', 'mean' => round($avg, 2), 'standard_deviation' => round($sd, 2), 'rule' => 'absolute deviation > 2 population standard deviations; review only'];
                            }
                        }
                    }
                }
            }
            foreach ($conditions as $i) {
                $key = $e->id.':'.$i['type'].':'.($i['course_id'] ?? 0).':'.($i['student_id'] ?? 0).':'.($i['component_id'] ?? 0);
                $seen[] = $key;
                $flag = M\AnalysisFlag::firstOrNew(['record_key' => $key]);
                $flag->fill(['examination_id' => $e->id, 'student_id' => $i['student_id'] ?? null, 'type' => $i['type'], 'details' => $i]);
                if (! $flag->exists || $flag->status === 'resolved') {
                    $flag->status = 'open';
                }
                $flag->save();
            }M\AnalysisFlag::where('examination_id', $e->id)->where('status', 'open')->whereNotIn('record_key', $seen)->update(['status' => 'resolved']);
            $run = M\AiAnalysisRun::create(['examination_id' => $e->id, 'requested_by' => $actor, 'summary' => ['conditions' => count($conditions), 'rules' => 'Deterministic completeness and 2σ outlier review. No misconduct finding.']]);
            app(AuditService::class)->record('analysis.completed', $run);

            return $run;
        });
    }
}
