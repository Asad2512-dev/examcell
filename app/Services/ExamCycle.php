<?php

namespace App\Services;

use App\Models as M;
use Illuminate\Support\Collection;

/**
 * Plain-language "what do I do next?" for the administrator.
 *
 * Reads existing records only (nothing is stored). Every step reports a status, a one-line
 * explanation, a button and a list of things that need attention, so a non-technical
 * administrator can follow the whole examination cycle from one place.
 */
class ExamCycle
{
    public const DONE = 'done';

    public const CURRENT = 'current';

    public const TODO = 'todo';

    /** @return array{steps: array<int, array>, next: ?array, exam: ?M\Examination, exams: Collection, done: int, total: int, percent: int} */
    public function overview(?int $examId = null): array
    {
        $context = app(AcademicContext::class);
        $exams = $context->apply(M\Examination::query())->where('status', '!=', 'archived')->orderByDesc('id')->get(['id', 'name', 'status', 'type', 'admit_enabled']);
        $exam = ($examId ? $exams->firstWhere('id', $examId) : null) ?? $exams->first();

        $steps = [
            $this->university(),
            $this->people(),
            $this->rooms(),
            $this->exam($exam, $exams->count()),
            $this->dateSheet($exam),
            $this->seatingAndDuties($exam),
            $this->publish($exam),
            $this->admitCards($exam),
            $this->marks($exam),
            $this->results($exam),
        ];

        $current = null;
        foreach ($steps as $i => &$step) {
            $step['number'] = $i + 1;
            if ($step['done']) {
                $step['status'] = self::DONE;
            } elseif ($step['optional'] ?? false) {
                $step['status'] = self::TODO;
            } elseif ($current === null) {
                $step['status'] = self::CURRENT;
                $current = $i;
            } else {
                $step['status'] = self::TODO;
            }
        }
        unset($step);

        $done = count(array_filter($steps, fn ($s) => $s['done']));

        return [
            'steps' => $steps,
            'next' => $current !== null ? $steps[$current] : null,
            'exam' => $exam,
            'exams' => $exams,
            'done' => $done,
            'total' => count($steps),
            'percent' => (int) round($done / count($steps) * 100),
        ];
    }

    /** Things to check before a given stage of one examination (shown as a plain checklist). */
    public function readiness(M\Examination $exam): array
    {
        $papers = $exam->courses()->with(['course:id,code,name', 'section:id,name', 'schedule.seatingPlan', 'components:id,exam_course_id,weight'])->get();
        $issues = [];
        if ($papers->isEmpty()) {
            $issues[] = $this->issue('No papers added yet.', '/admin/exams/new?exam='.$exam->id, 'Add papers');

            return $issues;
        }
        foreach ($papers as $p) {
            $label = ($p->course?->code ?? 'Paper').' · '.($p->section?->name ?? '');
            $weight = (float) $p->components->sum('weight');
            if ($p->components->isEmpty()) {
                $issues[] = $this->issue($label.' has no marks breakdown.', '/admin/records?resource=components', 'Add breakdown');
            } elseif (abs($weight - 100) > 0.01) {
                $issues[] = $this->issue($label.' marks breakdown adds up to '.rtrim(rtrim(number_format($weight, 2), '0'), '.').'%, not 100%.', '/admin/records?resource=components', 'Fix breakdown');
            }
        }
        $unscheduled = $papers->filter(fn ($p) => ! $p->schedule);
        if ($unscheduled->isNotEmpty()) {
            $issues[] = $this->issue($unscheduled->count().' of '.$papers->count().' papers have no date and room yet.', '/admin/exams?exam='.$exam->id.'&tab=datesheet', 'Make date sheet');
        }
        $counts = M\Enrollment::where('eligible', true)->whereIn('course_id', $papers->pluck('course_id'))->whereIn('section_id', $papers->pluck('section_id'))
            ->groupBy('course_id', 'section_id')->selectRaw('course_id, section_id, COUNT(*) AS n')->get()->mapWithKeys(fn ($r) => [$r->course_id.':'.$r->section_id => (int) $r->n]);
        $perPaper = $papers->map(fn ($p) => $counts[$p->course_id.':'.$p->section_id] ?? 0);
        $students = $perPaper->sum();
        if ($students === 0) {
            $issues[] = $this->issue('No students are enrolled in these papers.', '/admin/academic-setup?step=students', 'Add students');
        }
        $capacity = (int) M\Room::where('active', true)->sum('capacity');
        $largest = $perPaper->max() ?? 0;
        if ($capacity < $largest) {
            $issues[] = $this->issue('Your rooms seat '.$capacity.' in total but one paper has '.$largest.' students.', '/admin/rooms', 'Add rooms');
        }
        $unseated = $papers->filter(fn ($p) => $p->schedule && ! $p->schedule->seatingPlan);
        if ($unseated->isNotEmpty() && $unscheduled->isEmpty()) {
            $issues[] = $this->issue($unseated->count().' papers still need seating.', '/admin/seating', 'Do seating');
        }

        return $issues;
    }

    /** "240 students" style wording for confirm dialogs, so the admin sees who a click will reach. */
    public function audience(M\Examination $exam): string
    {
        $students = M\Enrollment::whereIn('section_id', $exam->courses()->select('section_id'))->where('eligible', true)->distinct()->count('student_id');

        return $students.' student'.($students === 1 ? '' : 's').' will be notified';
    }

    private function issue(string $text, string $url, string $action): array
    {
        return ['text' => $text, 'url' => $url, 'action' => $action];
    }

    private function step(string $key, string $title, string $why, bool $done, string $doneText, string $todoText, string $url, string $button, array $issues = [], string $icon = 'check'): array
    {
        return [
            'key' => $key, 'title' => $title, 'why' => $why, 'done' => $done,
            'text' => $done ? $doneText : $todoText, 'url' => $url, 'button' => $done ? 'Review' : $button,
            'issues' => $issues, 'icon' => $icon,
        ];
    }

    private function university(): array
    {
        $done = (bool) Settings::get('university.configured', false);

        $step = $this->step('university', 'Your university', 'Name, time zone and terms, so every page uses your words.', $done,
            (string) Settings::get('university.name', 'Saved'), 'Takes one minute. Add your university name and how your terms work.',
            '/admin/university', 'Set up university', [], 'home');
        // Recommended first, but never blocks an installation that already holds academic records.
        $step['optional'] = M\AcademicSession::exists();

        return $step;
    }

    private function people(): array
    {
        $session = app(AcademicContext::class)->current()['session_id'];
        $issues = [];
        if (! $session) {
            $issues[] = $this->issue('Choose or create an academic session.', '/admin/academic-setup?step=session', 'Choose session');
        }
        $departments = M\Department::where('active', true)->count();
        $courses = M\Course::where('active', true)->count();
        $teachers = M\User::where('role', 'teacher')->where('active', true)->count();
        $students = M\StudentProfile::count();
        $assigned = M\TeacherAssignment::count();
        if ($session) {
            $departments || $issues[] = $this->issue('No departments yet.', '/admin/academic-setup?step=departments', 'Add department');
            $courses || $issues[] = $this->issue('No courses yet.', '/admin/academic-setup?step=courses', 'Add courses');
            $teachers || $issues[] = $this->issue('No teachers yet.', '/admin/import?kind=teachers', 'Add teachers');
            $students || $issues[] = $this->issue('No students yet.', '/admin/import?kind=students', 'Add students');
            ($teachers && ! $assigned) && $issues[] = $this->issue('Teachers are not linked to any course.', '/admin/academic-setup?step=teachers', 'Link teachers');
        }
        $done = $session && $departments && $courses && $teachers && $students && $assigned;

        return $this->step('people', 'Classes, teachers and students', 'Who teaches what, and who sits which exam.', (bool) $done,
            $teachers.' teachers · '.$students.' students · '.$courses.' courses', 'Add your departments, courses, teachers and students. You can upload a spreadsheet.',
            $issues[0]['url'] ?? '/admin/academic-setup', 'Open setup', $issues, 'users');
    }

    private function rooms(): array
    {
        $rooms = M\Room::where('active', true)->get(['id', 'capacity']);
        $issues = [];
        if ($rooms->isEmpty()) {
            $issues[] = $this->issue('Add at least one room.', '/admin/rooms', 'Add room');
        }

        return $this->step('rooms', 'Exam rooms', 'Where students will sit.', $rooms->isNotEmpty(),
            $rooms->count().' rooms · '.$rooms->sum('capacity').' seats', 'Add your exam halls and how many students each can seat.', '/admin/rooms', 'Add rooms', $issues, 'home');
    }

    private function exam(?M\Examination $exam, int $count): array
    {
        $papers = $exam ? $exam->courses()->pluck('id') : collect();
        $issues = [];
        if ($exam && $papers->isEmpty()) {
            $issues[] = $this->issue('No papers added yet.', '/admin/exams/new?exam='.$exam->id, 'Add papers');
        } elseif ($papers->isNotEmpty()) {
            $weights = M\AssessmentComponent::whereIn('exam_course_id', $papers)->groupBy('exam_course_id')->selectRaw('exam_course_id, SUM(weight) AS total')->pluck('total', 'exam_course_id');
            $bad = $papers->filter(fn ($id) => ! isset($weights[$id]) || abs((float) $weights[$id] - 100) > 0.01)->count();
            if ($bad) {
                $issues[] = $this->issue($bad.' papers need a marks breakdown that adds up to 100%.', '/admin/records?resource=components', 'Fix breakdown');
            }
        }
        $done = $exam && $papers->isNotEmpty() && ! $issues;

        return $this->step('exam', 'Create the examination', 'Pick the classes and papers that will sit this exam.', (bool) $done,
            $exam ? $exam->name.' · '.$papers->count().' papers' : '', 'Pick classes and the wizard creates all their papers in one go.',
            $exam && $papers->isEmpty() ? '/admin/exams/new?exam='.$exam->id : '/admin/exams/new', $exam ? 'Fix papers' : 'Create examination', $issues, 'edit');
    }

    private function dateSheet(?M\Examination $exam): array
    {
        if (! $exam) {
            return $this->step('datesheet', 'Date sheet', 'Dates, times and rooms for every paper.', false, '', 'Appears after you create the examination.', '/admin/exams/new', 'Create examination', [], 'calendar');
        }
        $total = $exam->courses()->count();
        $scheduled = M\ScheduleEntry::whereIn('exam_course_id', $exam->courses()->select('id'))->count();
        $ready = $total > 0 && $scheduled === $total;
        $issues = $total && $scheduled < $total
            ? [$this->issue(($total - $scheduled).' papers need a date and room.', '/admin/exams?exam='.$exam->id.'&tab=datesheet', 'Make date sheet')] : [];

        return $this->step('datesheet', 'Date sheet', 'Dates, times and rooms for every paper.', $ready,
            $scheduled.' of '.$total.' papers scheduled', 'One click makes a date sheet and picks free rooms for you.',
            '/admin/exams?exam='.$exam->id.'&tab=datesheet', 'Make date sheet', $issues, 'calendar');
    }

    private function publish(?M\Examination $exam): array
    {
        $published = $exam && in_array($exam->status, ['approved', 'published'], true);
        $isPublished = $exam && $exam->status === 'published';
        $issues = $exam && $exam->status === 'draft' ? [$this->issue('Approve, then publish. Students are notified when you publish.', '/admin/exams?exam='.$exam->id.'&tab=datesheet', 'Approve and publish')] : [];

        return $this->step('publish', 'Approve and publish', 'Students and teachers see the date sheet once you publish.', (bool) $isPublished,
            'Published to students', $published ? 'Approved. One more click publishes it and notifies students.' : 'When seating and invigilators are ready, approve the date sheet and publish it.',
            '/admin/exams?'.($exam ? 'exam='.$exam->id.'&' : '').'tab=datesheet', $published ? 'Publish' : 'Approve and publish', $issues, 'upload');
    }

    private function seatingAndDuties(?M\Examination $exam): array
    {
        $entries = $exam ? M\ScheduleEntry::whereIn('exam_course_id', $exam->courses()->select('id'))->withCount(['seatingPlan', 'duties'])->get() : collect();
        $total = $entries->count();
        $seated = $entries->filter(fn ($e) => $e->seating_plan_count > 0)->count();
        $covered = $entries->filter(fn ($e) => $e->duties_count > 0)->count();
        $issues = [];
        if ($total && $seated < $total) {
            $issues[] = $this->issue(($total - $seated).' papers have no seating plan.', '/admin/seating', 'Do seating');
        }
        if ($total && $covered < $total) {
            $issues[] = $this->issue(($total - $covered).' papers have no invigilator.', '/admin/invigilation', 'Assign invigilators');
        }
        $done = $total > 0 && $seated === $total && $covered === $total;

        return $this->step('seating', 'Seating and invigilators', 'Seat every student and put a teacher in every room.', $done,
            $seated.' of '.$total.' seated · '.$covered.' covered', 'Seating is automatic. Then assign a teacher to each room.',
            $total && $seated < $total ? '/admin/seating' : '/admin/invigilation', $total && $seated < $total ? 'Do seating' : 'Assign invigilators', $issues, 'grid');
    }

    private function admitCards(?M\Examination $exam): array
    {
        $cards = $exam ? M\AdmitCard::where('examination_id', $exam->id)->count() : 0;
        $done = $cards > 0;

        return $this->step('admit', 'Admit cards', 'One PDF per student with date, room and seat.', $done,
            $cards.' admit cards ready', 'Generate admit cards for every eligible student in one click.',
            '/admin/exams?'.($exam ? 'exam='.$exam->id.'&' : '').'tab=admit', 'Generate cards', [], 'download');
    }

    private function marks(?M\Examination $exam): array
    {
        $total = $exam ? $exam->courses()->count() : 0;
        $submitted = $exam ? M\MarkSubmission::whereIn('exam_course_id', $exam->courses()->select('id'))->where('status', 'submitted')->count() : 0;
        $issues = $total && $submitted < $total
            ? [$this->issue(($total - $submitted).' papers are waiting for teachers\' marks.', '/admin/marks', 'See who is late')] : [];

        return $this->step('marks', 'Teachers submit marks', 'Teachers enter marks. You set deadlines and chase late papers.', $total > 0 && $submitted === $total,
            $submitted.' of '.$total.' papers submitted', 'Set a deadline and teachers are reminded. Late papers show here.', '/admin/marks', 'Track submissions', $issues, 'edit');
    }

    private function results(?M\Examination $exam): array
    {
        $batch = $exam?->batch;
        $published = $batch?->status === 'published';

        return $this->step('results', 'Calculate and publish results', 'Grades are worked out for you. Review, then release to students.', $published,
            'Results are published', $batch ? 'Status: '.str_replace('_', ' ', $batch->status).'. Review and publish.' : 'Opens when all marks are in.',
            '/admin/marks?tab=processing', $batch ? 'Review results' : 'Calculate results', [], 'award');
    }
}
