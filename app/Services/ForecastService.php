<?php

namespace App\Services;

use App\Models as M;
use Illuminate\Validation\ValidationException as V;

class ForecastService
{
    public function forecast(M\User $actor, int $student, int $course): M\ForecastRun
    {
        if ($actor->role === 'student' && $actor->id !== $student) {
            abort(403);
        }
        $history = app(ScopedQueries::class)->results($actor)->where('student_id', $student)->whereHas('examCourse', fn ($q) => $q->where('course_id', $course)->whereHas('examination', fn ($e) => $e->where('type', 'final')))->whereHas('batch', fn ($q) => $q->where('status', 'published'))->with('examCourse.course')->select('calculated_results.*')->join('result_batches', 'result_batches.id', '=', 'calculated_results.result_batch_id')->orderByDesc('result_batches.published_at')->orderByDesc('calculated_results.id')->limit(8)->get()->reverse()->values();
        if ($history->isEmpty()) {
            throw V::withMessages(['forecast' => 'No authorized published final-exam history exists for this student/course. Publish real results first.']);
        }
        $ys = $history->pluck('percentage')->map(fn ($y) => (float) $y)->all();
        $n = count($ys);
        $mean = array_sum($ys) / $n;
        $prediction = $ys[$n - 1];
        $low = 0;
        $high = 100;
        $method = 'Last observed score baseline';
        $confidence = 'very low';
        $slope = 0;
        if ($n >= 3) {
            $xbar = ($n + 1) / 2;
            $sxx = 0;
            $sxy = 0;
            foreach ($ys as $i => $y) {
                $x = $i + 1;
                $sxx += ($x - $xbar) ** 2;
                $sxy += ($x - $xbar) * ($y - $mean);
            }$slope = $sxy / $sxx;
            $intercept = $mean - $slope * $xbar;
            $prediction = $intercept + $slope * ($n + 1);
            $sse = 0;
            foreach ($ys as $i => $y) {
                $sse += ($y - ($intercept + $slope * ($i + 1))) ** 2;
            }$sigma = sqrt($sse / ($n - 2));
            $t = [1 => 12.706, 2 => 4.303, 3 => 3.182, 4 => 2.776, 5 => 2.571, 6 => 2.447][$n - 2];
            $margin = max(5, $t * $sigma * sqrt(1 + 1 / $n + (($n + 1 - $xbar) ** 2) / $sxx));
            $low = max(0, $prediction - $margin);
            $high = min(100, $prediction + $margin);
            $method = 'Least-squares trend across the last '.$n.' final-exam attempts';
            $confidence = $n >= 6 ? 'moderate' : 'low';
        }
        $evidence = ['method' => $method, 'version' => 'trend-v1', 'history' => $history->map(fn ($r) => ['result_id' => $r->id, 'percentage' => $r->percentage, 'published_at' => $r->batch->published_at?->toIso8601String()])->all(), 'sample_size' => $n, 'predicted_percentage' => round(max(0, min(100, $prediction)), 2), 'lower' => round($low, 2), 'upper' => round($high, 2), 'slope' => round($slope, 2), 'confidence' => $confidence, 'range_method' => $n >= 3 ? 'Approximate 95% Student-t prediction interval; assumes comparable attempts and independent residuals; minimum 5-point uncertainty floor.' : 'Insufficient history for a fitted trend; uncertainty spans 0–100.', 'notice' => 'Advisory estimate only. No predicted grade, pass decision or official result is saved or changed. Repeated attempts may not be comparable.'];
        $run = M\ForecastRun::create(['requested_by' => $actor->id, 'student_id' => $student, 'course_id' => $course, 'evidence' => $evidence]);
        app(AuditService::class)->record('forecast.generated', $run, ['sample_size' => $n, 'method' => 'trend-v1']);

        return $run;
    }
}
