<?php

namespace App\Services;

use App\Models as M;
use App\Services\AI\SeatingAiService;
use Illuminate\Support\Collection;

/** Builds the role-scoped data the graphical seat map renders. Read-only. */
class SeatMapService
{
    /** Okabe–Ito colour-blind-safe palette; patterns + class abbreviations add a non-colour signal. */
    public const COLORS = ['#E69F00', '#56B4E9', '#009E73', '#F0E442', '#0072B2', '#D55E00', '#CC79A7', '#999933', '#882255', '#44AA99'];

    public const PATTERNS = ['solid', 'diag', 'dots', 'horiz', 'cross', 'vert', 'rdiag', 'grid'];

    /** Entries this user may open on the seat map. */
    public function visibleEntries(M\User $u): Collection
    {
        $q = app(ScopedQueries::class)->schedules($u)->with('examCourse.course', 'examCourse.section', 'rooms', 'room')->when($u->role !== 'admin', fn ($q) => $q->whereHas('seatingPlan'));
        if ($u->role === 'admin') {
            $q = app(AcademicContext::class)->apply($q, $u);
        }
        $entries = $q->get();
        if ($u->role === 'teacher') {
            $duty = M\ScheduleEntry::with('examCourse.course', 'examCourse.section', 'rooms', 'room')->whereHas('seatingPlan')->whereHas('duties', fn ($d) => $d->where('teacher_id', $u->id)->where('status', 'assigned'))->get();
            $entries = $entries->merge($duty)->unique('id');
        }
        if ($u->role === 'student') {
            $entries = $entries->filter(fn ($e) => M\SeatAssignment::where('student_id', $u->id)->whereHas('seatingPlan', fn ($p) => $p->where('schedule_entry_id', $e->id))->exists());
        }

        return $entries->sortBy('starts_at')->values();
    }

    /** One picker option per slot group (date + time + papers). */
    public function slots(M\User $u): array
    {
        $seen = [];
        $out = [];
        $seating = app(SeatingService::class);
        foreach ($this->visibleEntries($u) as $e) {
            if (isset($seen[$e->id])) {
                continue;
            }
            $group = $seating->slotGroup($e);
            foreach ($group as $g) {
                $seen[$g->id] = true;
            }
            $out[] = ['entry' => $e->id, 'label' => $e->starts_at->format('D d M Y · H:i').' — '.$group->map(fn ($g) => $g->examCourse->course->code.' ('.$g->examCourse->section->name.')')->implode(', '), 'date' => $e->starts_at->format('Y-m-d'), 'time' => $e->starts_at->format('H:i')];
        }

        return $out;
    }

    public static function abbreviation(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        $first = preg_replace('/^BS/i', '', $parts[0] ?? '');
        $last = count($parts) > 1 ? preg_replace('/[^A-Za-z0-9]/', '', end($parts)) : '';

        return strtoupper(substr($first, 0, 3).$last) ?: strtoupper(substr($name, 0, 4));
    }

    /**
     * @param  array|null  $rows  override assignment rows (AI proposal preview); null = saved seats
     */
    public function data(M\User $u, M\ScheduleEntry $entry, ?array $rows = null): array
    {
        abort_unless($u->role === 'admin' || $this->visibleEntries($u)->contains('id', $entry->id), 404);
        $seating = app(SeatingService::class);
        $ai = app(SeatingAiService::class);
        $group = $seating->slotGroup($entry);
        $ctx = $ai->context($entry);
        $rows ??= $ai->currentRows($entry);
        $rows = array_values(array_filter($rows, fn ($r) => isset($ctx['layouts'][$r['room_id']]) && $ctx['layouts'][$r['room_id']]->position((int) $r['seat_number'])));
        // Each seat belongs to the paper of its own plan (students may have lost eligibility after seating).
        $entryOf = collect($ctx['students'])->pluck('entry_id', 'id')->all();
        foreach ($rows as $r) {
            if (isset($r['entry_id'])) {
                $entryOf[$r['student_id']] = $r['entry_id'];
            }
        }
        $entryOf = collect($entryOf);
        $eligibleIds = collect($ctx['students'])->pluck('id')->flip();
        $entriesById = $group->keyBy('id');
        $users = M\User::with('profile')->whereIn('id', collect($rows)->pluck('student_id'))->get()->keyBy('id');

        // Which rooms this user may see, and with how much detail.
        $allRooms = collect($ctx['rooms'])->pluck('id');
        $full = $u->role === 'admin';
        $visibleRooms = $allRooms;
        if ($u->role === 'teacher') {
            $own = $group->filter(fn ($e) => app(ScopedQueries::class)->courses($u)->whereKey($e->exam_course_id)->exists());
            $duty = M\DutyAssignment::whereIn('schedule_entry_id', $group->pluck('id'))->where('teacher_id', $u->id)->where('status', 'assigned')->get();
            $visibleRooms = $own->flatMap(fn ($e) => $e->allocatedRooms()->pluck('id'))->merge($duty->map(fn ($d) => $d->room_id ?? $entriesById[$d->schedule_entry_id]->room_id))->unique()->values();
            $full = true;
        }
        $mine = collect($rows)->firstWhere('student_id', $u->id);
        if ($u->role === 'student') {
            $visibleRooms = collect($mine ? [$mine['room_id']] : []);
        }

        $sections = $group->map(fn ($e) => $e->examCourse->section)->unique('id')->values();
        $palette = $sections->values()->map(function ($s, $i) use ($rows, $entryOf, $entriesById) {
            $count = collect($rows)->filter(fn ($r) => ($entriesById[$entryOf[$r['student_id']] ?? 0] ?? null)?->examCourse->section_id === $s->id)->count();

            return ['key' => $s->id, 'name' => $s->name, 'abbr' => self::abbreviation($s->name), 'color' => self::COLORS[$i % count(self::COLORS)], 'pattern' => self::PATTERNS[($i + intdiv($i, count(self::COLORS))) % count(self::PATTERNS)], 'count' => $count];
        })->values();
        $abbrs = [];
        $palette = $palette->map(function ($p) use (&$abbrs) {
            $a = $p['abbr'];
            for ($i = 2; isset($abbrs[$a]); $i++) {
                $a = $p['abbr'].$i;
            }
            $abbrs[$a] = true;

            return ['abbr' => $a] + $p;
        })->all();

        $roomModels = M\Room::whereIn('id', $visibleRooms)->get()->keyBy('id');
        $rooms = collect($ctx['rooms'])->filter(fn ($r) => $visibleRooms->contains($r['id']))->map(function ($r) use ($ctx, $roomModels, $rows, $entryOf, $entriesById) {
            $layout = $ctx['layouts'][$r['id']];
            $in = collect($rows)->where('room_id', $r['id']);

            return ['id' => $r['id'], 'name' => $roomModels[$r['id']]->name, 'capacity' => $layout->capacity(), 'used' => $in->count(), 'accessible' => $r['accessible_seats'], 'by_section' => $in->groupBy(fn ($x) => ($entriesById[$entryOf[$x['student_id']] ?? 0] ?? null)?->examCourse->section_id)->map->count()->all()] + $layout->toArray();
        })->values()->all();

        $seats = collect($rows)->filter(fn ($r) => $visibleRooms->contains($r['room_id']))->map(function ($r) use ($u, $full, $entryOf, $entriesById, $users, $ctx, $eligibleIds) {
            $e = $entriesById[$entryOf[$r['student_id']] ?? 0] ?? null;
            $label = $ctx['layouts'][$r['room_id']]->labelOf($r['seat_number']);
            $isMine = $r['student_id'] === $u->id;
            $base = ['seat' => $r['seat_number'], 'room' => $r['room_id'], 'label' => $label, 'mine' => $isMine];
            if ($full || $isMine) {
                $base['paper'] = $entryOf[$r['student_id']] ?? null;
            }
            if (! $full && ! $isMine) {
                return $base;
            }
            $s = $users[$r['student_id']] ?? null;

            return $base + ['student' => $r['student_id'], 'section' => $e?->examCourse->section_id, 'name' => $s?->name, 'roll' => $s?->profile?->roll_number, 'course' => $e ? $e->examCourse->course->code.' · '.$e->examCourse->course->name : null, 'special' => (bool) $s?->profile?->special_needs, 'ineligible' => ! $eligibleIds->has($r['student_id'])];
        })->values()->all();

        $pairs = $full ? array_values(array_filter($ai->adjacency($rows, $ctx), fn ($p) => $visibleRooms->contains($p['room']))) : [];
        $locked = $group->contains(fn ($e) => in_array($e->examCourse->examination->status, ['published', 'archived']));
        $directions = null;
        if ($mine) {
            $roomName = M\Room::find($mine['room_id'])?->name;
            $layout = $ctx['layouts'][$mine['room_id']];
            $directions = $roomName.' · seat '.$layout->labelOf($mine['seat_number']).' — '.$layout->directions($mine['seat_number']).' ('.($layout->door === 'left' ? 'door on the left wall' : 'door on the right wall').').';
        }

        return [
            'entry' => $entry->id,
            'mode' => $u->role,
            'title' => $entry->starts_at->format('D d M Y · H:i'),
            'papers' => $group->map(fn ($e) => ['id' => $e->id, 'course' => $e->examCourse->course->code, 'name' => $e->examCourse->course->name, 'section' => $e->examCourse->section->name, 'exam' => $e->examCourse->examination->name, 'ends' => $e->ends_at->format('H:i'), 'room_ids' => $e->allocatedRooms()->pluck('id')->all()])->values()->all(),
            'editable' => $u->role === 'admin' && ! $locked,
            'locked' => $locked,
            'rooms' => $rooms,
            'seats' => $seats,
            'sections' => $u->role === 'student' ? [] : $palette,
            'pairs' => $pairs,
            'directions' => $directions,
            'summary' => $u->role === 'student' ? null : [
                'students' => count($ctx['students']),
                'seats_used' => count($rows),
                'seats_total' => collect($rooms)->sum('capacity'),
                'rooms_used' => collect($rows)->pluck('room_id')->unique()->count(),
                'classes' => count($palette),
                'mixed_rooms' => collect($rooms)->filter(fn ($r) => count(array_filter($r['by_section'])) > 1)->count(),
                'clashes' => count(array_filter($pairs, fn ($p) => $p['kind'] === 'side')),
                'front_back' => count(array_filter($pairs, fn ($p) => $p['kind'] === 'front_back')),
                'unseated' => collect($ctx['students'])->pluck('id')->diff(collect($rows)->pluck('student_id'))->count(),
                'not_eligible' => collect($rows)->filter(fn ($r) => ! $eligibleIds->has($r['student_id']))->count(),
            ],
        ];
    }
}
