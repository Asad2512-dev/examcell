<?php

namespace App\Services\AI;

use Carbon\Carbon;

/**
 * Deterministic, offline proposals used when OpenAI is missing or unavailable.
 * These outputs still pass through every normal validator and approval screen.
 */
class DemoAiService
{
    public function structured(string $feature, string|array $user): array
    {
        $data = is_string($user) ? json_decode($user, true) : null;

        return match ($feature) {
            'settings.test' => ['ok' => true],
            'datesheet.rules' => $this->rules(is_string($user) ? $user : ''),
            'datesheet.generate' => $this->dateSheet($data ?? []),
            'seating.generate' => $this->seating($data ?? []),
            'duties.generate' => $this->duties($data ?? []),
            'duties.replacement' => $this->replacement($data ?? []),
            'import.map' => $this->mapping($data ?? []),
            'report.plan' => $this->report($data ?? []),
            'notification.draft' => $this->announcement($data ?? []),
            'triage.recheck' => $this->rechecks($data ?? []),
            'triage.anomaly' => $this->anomalies($data ?? []),
            'assistant.command' => $this->command($data ?? []),
            'import.extract' => ['rows' => []],
            default => throw new AiException('This feature needs a valid API key and has no safe offline demo.'),
        };
    }

    private function rules(string $text): array
    {
        $lower = strtolower($text);
        $blocked = [];
        if (str_contains($lower, 'friday') && (str_contains($lower, 'afternoon') || str_contains($lower, 'after 12'))) {
            $blocked[] = ['weekday' => 5, 'from_hour' => 12, 'to_hour' => 18];
        }
        preg_match('/(?:max(?:imum)?|only)\s+(\d+)\s+(?:paper|exam)/i', $text, $max);

        return ['blocked_slots' => $blocked, 'max_papers_per_day_per_section' => isset($max[1]) ? (int) $max[1] : null, 'min_gap_days_before_major' => null, 'major_course_codes' => [], 'notes' => 'Offline demo interpreted the supported scheduling constraints.'];
    }

    private function dateSheet(array $payload): array
    {
        $ctx = $payload['data'] ?? [];
        $courses = $ctx['courses'] ?? [];
        $rooms = $ctx['rooms'] ?? [];
        $previousStart = $payload['previous_proposal']['entries'][0]['starts_at'] ?? null;
        $date = Carbon::parse($previousStart ?: ($ctx['session']['starts_on'] ?? now()->toDateString()))->startOfDay()->addDays($previousStart ? 3 : 0);
        while ($date->isWeekend()) {
            $date->addDay();
        }
        $entries = [];
        foreach ($courses as $course) {
            $needed = (int) ($course['eligible_students'] ?? 0);
            $chosen = [];
            $capacity = 0;
            foreach ($rooms as $room) {
                $chosen[] = (int) $room['id'];
                $capacity += (int) $room['capacity'];
                if ($capacity >= $needed) {
                    break;
                }
            }
            $entries[] = ['exam_course_id' => (int) $course['id'], 'room_ids' => $chosen, 'starts_at' => $date->format('Y-m-d').' 09:00'];
            do {
                $date->addDay();
            } while ($date->isWeekend());
        }

        return ['entries' => $entries, 'explanation' => 'Offline demo placed one paper per weekday at 09:00 and selected enough active room capacity. Review before approval.'];
    }

    private function seating(array $data): array
    {
        $students = collect($data['students'] ?? [])->keyBy('id');
        $unassigned = $students->keys()->map(fn ($id) => (int) $id)->all();
        $allowed = collect($data['entries'] ?? [])->mapWithKeys(fn ($entry) => [(int) $entry['id'] => array_map('intval', $entry['room_ids'] ?? [])]);
        $assignments = [];
        foreach ($data['rooms'] ?? [] as $room) {
            foreach ($room['seat_grid'] ?? [] as $row) {
                $previousSection = null;
                foreach ($row as $seat) {
                    if ($seat === null) {
                        $previousSection = null;

                        continue;
                    }
                    $candidates = collect($unassigned)->filter(function ($id) use ($students, $allowed, $room, $previousSection, $seat) {
                        $student = $students[$id];
                        $accessible = array_map('intval', $room['accessible_seats'] ?? []);

                        return in_array((int) $room['id'], $allowed[(int) $student['entry_id']] ?? [], true)
                            && (int) $student['section_id'] !== $previousSection
                            && (! $student['special_needs'] || ! $accessible || in_array((int) $seat, $accessible, true));
                    });
                    if ($candidates->isEmpty()) {
                        $candidates = collect($unassigned)->filter(function ($id) use ($students, $allowed, $room, $seat) {
                            $accessible = array_map('intval', $room['accessible_seats'] ?? []);

                            return in_array((int) $room['id'], $allowed[(int) $students[$id]['entry_id']] ?? [], true)
                                && (! $students[$id]['special_needs'] || ! $accessible || in_array((int) $seat, $accessible, true));
                        });
                    }
                    if ($candidates->isEmpty()) {
                        continue;
                    }
                    $id = (int) $candidates->sortByDesc(fn ($candidate) => collect($unassigned)->filter(fn ($other) => (int) $students[$other]['section_id'] === (int) $students[$candidate]['section_id'])->count())->first();
                    $assignments[] = ['student_id' => $id, 'room_id' => (int) $room['id'], 'seat_number' => (int) $seat];
                    $previousSection = (int) $students[$id]['section_id'];
                    $unassigned = array_values(array_diff($unassigned, [$id]));
                }
            }
        }

        return ['assignments' => $assignments, 'explanation' => 'Offline demo alternated classes across each row and respected the configured rooms and usable seat grid.'];
    }

    private function duties(array $data): array
    {
        $teachers = collect($data['teachers'] ?? [])->sortBy(fn ($teacher) => [(bool) ($teacher['reserve_staff'] ?? false), (int) ($teacher['existing_duties'] ?? 0), (int) $teacher['id']])->values();
        $loads = $teachers->pluck('existing_duties', 'id')->all();
        $picked = [];
        foreach ($data['slots'] ?? [] as $slot) {
            $candidate = $teachers->sortBy(fn ($teacher) => [$loads[$teacher['id']] ?? 0, (bool) ($teacher['reserve_staff'] ?? false)])->first();
            if (! $candidate) {
                continue;
            }
            $picked[] = ['schedule_entry_id' => (int) $slot['schedule_entry_id'], 'room_id' => (int) $slot['room_id'], 'teacher_id' => (int) $candidate['id']];
            $loads[$candidate['id']] = ($loads[$candidate['id']] ?? 0) + 1;
        }

        return ['duties' => $picked, 'explanation' => 'Offline demo assigned the currently least-loaded active teacher and kept reserve staff later in the order.'];
    }

    private function replacement(array $data): array
    {
        $candidate = collect($data['candidates'] ?? [])->sortBy(fn ($row) => [(int) ($row['duties_that_day'] ?? 0), (int) ($row['assigned_duties'] ?? 0), (int) $row['id']])->first();

        return ['candidate_id' => (int) ($candidate['id'] ?? 0), 'explanation' => 'Offline demo selected the eligible teacher with the lightest duty load.'];
    }

    private function mapping(array $data): array
    {
        $rows = $data['sample_rows'] ?? [];
        $headers = array_map(fn ($value) => preg_replace('/[^a-z0-9]/', '', strtolower((string) $value)), $rows[0] ?? []);
        $target = ($data['forced_target'] ?? null) ?: $this->detectTarget($headers);
        $aliases = [
            'roll_number' => ['roll', 'rollno', 'rollnumber', 'reg', 'regno', 'registrationnumber'], 'name' => ['name', 'fullname', 'studentname', 'teachername', 'coursename'],
            'email' => ['email', 'mail', 'emailaddress'], 'section' => ['section', 'class', 'classname'], 'department_code' => ['department', 'departmentcode', 'dept', 'deptcode'],
            'code' => ['code', 'coursecode'], 'credits' => ['credits', 'credit'], 'score' => ['score', 'marks', 'mark'], 'absent' => ['absent', 'absence'],
        ];
        $fields = ImportAiService::TARGETS[$target] ?? [];
        $mappings = [];
        foreach ($headers as $index => $header) {
            $field = collect($fields)->first(fn ($candidate) => in_array($header, $aliases[$candidate] ?? [$candidate], true)) ?? 'ignore';
            $mappings[] = ['column_index' => $index, 'field' => $field];
        }

        return ['target' => $target, 'header_rows' => 1, 'mappings' => $mappings, 'notes' => 'Offline demo matched common spreadsheet column names.'];
    }

    private function detectTarget(array $headers): string
    {
        if (array_intersect($headers, ['marks', 'mark', 'score', 'absent'])) {
            return 'marks';
        }
        if (array_intersect($headers, ['roll', 'rollno', 'rollnumber', 'regno'])) {
            return 'students';
        }
        if (array_intersect($headers, ['credits', 'coursecode'])) {
            return 'courses';
        }

        return 'teachers';
    }

    private function report(array $data): array
    {
        $question = strtolower((string) ($data['question'] ?? ''));
        $metric = str_contains($question, 'pass') ? 'pass_rate' : (str_contains($question, 'count') ? 'result_count' : (str_contains($question, 'grade point') ? 'average_grade_points' : 'average_percentage'));
        $group = collect(['department', 'course', 'section', 'grade', 'examination'])->first(fn ($item) => str_contains($question, $item)) ?? 'course';

        return ['title' => ucwords(str_replace('_', ' ', $metric)).' by '.ucfirst($group), 'metric' => $metric, 'group_by' => $group, 'chart' => 'bar', 'examination_id' => null, 'department_code' => null, 'course_code' => null, 'summary' => 'Offline demo selected the closest supported report.'];
    }

    private function announcement(array $data): array
    {
        $kind = $data['kind'] ?? 'schedule';
        $facts = $data['facts'] ?? [];
        $exam = $facts['examination'] ?? 'Examination';
        $message = match ($kind) {
            'results' => "$exam results have been updated. Please sign in to review the published information.",
            'duties' => "$exam invigilation duties are ready. Please sign in and review your assignment.",
            default => "$exam schedule is available. Please sign in to review your paper dates, times and rooms.",
        };

        return ['title' => $exam.' '.($kind === 'results' ? 'results' : ($kind === 'duties' ? 'duties' : 'schedule')), 'message' => $message];
    }

    private function rechecks(array $rows): array
    {
        return ['summary' => 'Offline demo prepared pending requests for human review.', 'items' => array_map(fn ($row) => ['request_id' => (int) $row['request_id'], 'recommendation' => 'needs_review', 'rationale' => 'Review the request reason and original marking before deciding.'], $rows)];
    }

    private function anomalies(array $rows): array
    {
        return ['summary' => 'Offline demo prioritised open statistical flags for review.', 'items' => array_map(fn ($row) => ['flag_id' => (int) $row['flag_id'], 'priority' => 'medium', 'comment' => 'Check the underlying marks and assessment evidence.'], $rows)];
    }

    private function command(array $data): array
    {
        $text = strtolower((string) ($data['request'] ?? ''));
        $exam = collect($data['examinations'] ?? [])->first();
        $entry = collect($data['scheduled_papers'] ?? [])->first();
        $action = 'answer';
        $navigate = null;
        foreach (['seating', 'duties', 'report', 'announcement', 'datesheet'] as $candidate) {
            if (str_contains($text, $candidate) || ($candidate === 'duties' && str_contains($text, 'invigil'))) {
                $action = $candidate;
                break;
            }
        }
        foreach (CommandService::NAV as $destination) {
            if (str_contains($text, str_replace('-', ' ', $destination)) && $action === 'answer') {
                $action = 'navigate';
                $navigate = $destination;
                break;
            }
        }

        return ['action' => $action, 'exam_id' => in_array($action, ['datesheet', 'duties', 'announcement'], true) ? ($exam['id'] ?? null) : null, 'entry_id' => $action === 'seating' ? ($entry['id'] ?? null) : null, 'announcement_kind' => $action === 'announcement' ? 'schedule' : null, 'report_question' => $action === 'report' ? ($data['request'] ?? '') : null, 'navigate_to' => $navigate, 'reply' => $action === 'answer' ? 'Demo assistant is ready. Ask it to generate a date sheet, seating, duties, a report, or open an admin section.' : 'Offline demo prepared this action for your review.'];
    }
}
