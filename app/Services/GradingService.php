<?php

namespace App\Services;

use App\Models as M;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException as V;

class GradingService
{
    public function round(string $n, int $places = 2): string
    {
        $bump = '0.'.str_repeat('0', $places).'5';

        return bcadd($n, $bump, $places);
    }

    public function calculate(M\ExamCourse $c, int $student, ?Collection $marks = null): array
    {
        $scheme = $c->examination->gradingScheme;
        if ($scheme->status !== 'approved') {
            throw V::withMessages(['grading' => 'Scheme is not approved.']);
        }
        $weighted = '0';
        $absent = false;
        foreach ($c->components as $comp) {
            $m = $marks ? $marks->get($comp->id.':'.$student) : M\Mark::where('assessment_component_id', $comp->id)->where('student_id', $student)->first();
            if (! $m || ($m->score === null && ! $m->absent)) {
                throw V::withMessages(['marks' => 'Missing marks cannot be calculated as zero.']);
            }
            $absent = $absent || $m->absent;
            $score = $m->absent ? '0' : (string) $m->score;
            $weighted = bcadd($weighted, bcmul(bcdiv($score, (string) $comp->maximum, 12), (string) $comp->weight, 12), 12);
        }
        $percent = $this->round($weighted, $scheme->rounding_places);
        $boundary = $scheme->boundaries->sortByDesc('minimum')->first(fn ($boundary) => bccomp((string) $boundary->minimum, $percent, 2) <= 0);
        if (! $boundary) {
            throw V::withMessages(['grading' => 'No grade boundary covers this percentage.']);
        }
        $credits = (string) $c->course->credits;

        return ['percentage' => $percent, 'passed' => bccomp($percent, bcmul(bcdiv((string) $c->passing, (string) $c->maximum, 8), '100', 6), 2) >= 0, 'grade' => $boundary->grade, 'grade_points' => $boundary->grade_points, 'credits' => $credits, 'quality_points' => $this->round(bcmul($credits, (string) $boundary->grade_points, 6)), 'absent' => $absent];
    }

    public function academicGpa($results): ?string
    {
        $finals = $results->filter(fn ($r) => $r->examCourse->examination->type === 'final');
        if (Settings::get('cgpa_attempt_policy', config('examcell.cgpa_attempt_policy')) === 'latest') {
            $finals = $finals->sortByDesc(fn ($r) => $r->batch->published_at?->timestamp ?? 0)->unique(fn ($r) => $r->examCourse->course_id);
        }

        return $this->gpa($finals);
    }

    public function gpa($results): ?string
    {
        $credits = '0';
        $quality = '0';
        foreach ($results as $r) {
            $credits = bcadd($credits, (string) $r->credits, 2);
            $quality = bcadd($quality, (string) $r->quality_points, 2);
        }

        return bccomp($credits, '0', 2) > 0 ? $this->round(bcdiv($quality, $credits, 8)) : null;
    }
}
