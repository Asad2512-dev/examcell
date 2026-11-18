<?php

namespace App\Services;

use App\Models\User;

class AnalyticsService
{
    public function aggregates(User $user): array
    {
        $q = app(ScopedQueries::class)->results($user);
        $totals = (clone $q)->selectRaw('COUNT(*) as records, AVG(percentage) as average_percentage, SUM(passed=1) as passed, SUM(passed=0) as failed, SUM(absent=1) as absent')->first();
        $grades = (clone $q)->selectRaw('grade, COUNT(*) as records')->groupBy('grade')->orderBy('grade')->get()->map(fn ($r) => ['grade' => $r->grade, 'records' => $r->records])->all();
        $courses = (clone $q)->selectRaw('exam_course_id, COUNT(*) as records, AVG(percentage) as average_percentage, SUM(passed=0) as failed')->groupBy('exam_course_id')->with('examCourse.course')->get()->map(fn ($r) => ['course' => $r->examCourse->course->code, 'records' => $r->records, 'average' => number_format((float) $r->average_percentage, 2), 'failed' => $r->failed])->all();

        return ['records' => (int) $totals->records, 'average' => $totals->average_percentage === null ? null : number_format((float) $totals->average_percentage, 2), 'passed' => (int) $totals->passed, 'failed' => (int) $totals->failed, 'absent' => (int) $totals->absent, 'grades' => $grades, 'courses' => $courses];
    }
}
