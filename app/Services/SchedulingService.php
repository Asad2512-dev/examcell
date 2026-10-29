<?php

namespace App\Services;

use App\Models as M;
use App\Notifications\WorkflowNotice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException as V;

class SchedulingService
{
    public function conflicts(M\ExamCourse $c, M\Room $room, Carbon $start, Carbon $end, array $extra = []): array
    {
        $rooms = collect([$room, ...$extra])->unique('id');
        $errors = [];
        $students = $c->eligible()->pluck('student_id');
        if ($rooms->contains(fn ($r) => ! $r->active) || $rooms->sum('capacity') < $students->count()) {
            $errors[] = 'Active room capacity is below eligible enrollment.';
        }
        if ($start->diffInMinutes($end) != $c->duration_minutes) {
            $errors[] = 'Duration must match the examination course.';
        }
        $session = M\AcademicSession::findOrFail($c->examination->academic_session_id);
        if ($start->toDateString() < $session->starts_on->toDateString() || $end->toDateString() > $session->ends_on->toDateString()) {
            $errors[] = 'Schedule falls outside the academic session.';
        }
        foreach ($rooms as $r) {
            if (M\RoomUnavailability::where('room_id', $r->id)->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()) {
                $errors[] = $r->name.' is unavailable during this interval.';
            }
        }
        $overlaps = M\ScheduleEntry::with('examCourse', 'rooms')->where('exam_course_id', '!=', $c->id)->whereHas('examCourse.examination', fn ($q) => $q->where('status', '!=', 'archived')->when($c->examination->supersedes_id, fn ($q) => $q->where('id', '!=', $c->examination->supersedes_id)))->where('starts_at', '<', $end)->where('ends_at', '>', $start)->get();
        foreach ($overlaps as $other) {
            if ($other->allocatedRooms()->pluck('id')->intersect($rooms->pluck('id'))->isNotEmpty()) {
                $errors[] = 'Room overlaps schedule #'.$other->id;
            }if ($other->examCourse->eligible()->whereIn('student_id', $students)->exists()) {
                $errors[] = 'Shared students overlap schedule #'.$other->id;
            }
        }

        return array_unique($errors);
    }

    public function entryConflicts(M\ScheduleEntry $entry): array
    {
        return $this->conflicts($entry->examCourse, $entry->room, $entry->starts_at, $entry->ends_at, $entry->allocatedRooms()->where('id', '!=', $entry->room_id)->all());
    }

    public function schedule(M\ExamCourse $course, int $roomId, string $starts, array $extras = [])
    {
        return DB::transaction(function () use ($course, $roomId, $starts, $extras) {
            M\Room::orderBy('id')->lockForUpdate()->get();
            $course = M\ExamCourse::lockForUpdate()->findOrFail($course->id);
            $exam = M\Examination::lockForUpdate()->findOrFail($course->examination_id);
            if ($exam->status !== 'draft') {
                throw V::withMessages(['schedule' => 'Only draft schedules may change.']);
            }$ids = collect([$roomId, ...$extras])->map(fn ($id) => (int) $id)->unique()->values();
            $rooms = M\Room::whereIn('id', $ids)->get();
            if ($rooms->count() !== $ids->count()) {
                throw V::withMessages(['rooms' => 'One or more rooms no longer exist.']);
            }$room = $rooms->firstWhere('id', $roomId);
            $start = Carbon::parse($starts, config('app.timezone'))->setTimezone(config('app.timezone'));
            $end = $start->copy()->addMinutes($course->duration_minutes);
            $errors = $this->conflicts($course, $room, $start, $end, $rooms->where('id', '!=', $roomId)->all());
            if ($errors) {
                throw V::withMessages(['schedule' => $errors]);
            }if ($course->schedule?->seatingPlan || $course->schedule?->duties()->exists()) {
                throw V::withMessages(['schedule' => 'Allocated seats or duties lock this draft schedule. Create a revision instead of invalidating assignments.']);
            }$entry = M\ScheduleEntry::updateOrCreate(['exam_course_id' => $course->id], ['room_id' => $roomId, 'starts_at' => $start, 'ends_at' => $end]);
            $entry->rooms()->sync($ids);
            app(AuditService::class)->record('schedule.saved', $entry, ['rooms' => $ids->all(), ...$entry->toArray()]);

            return $entry->fresh('rooms');
        });
    }

    public function generate(M\Examination $exam, string $from, int $days): array
    {
        return DB::transaction(function () use ($exam, $from, $days) {
            M\Examination::whereKey($exam->id)->lockForUpdate()->first();
            $saved = [];
            foreach ($exam->courses()->orderBy('id')->get() as $c) {
                if ($c->schedule) {
                    continue;
                }
                $found = false;
                for ($day = 0; $day < $days && ! $found; $day++) {
                    foreach ([9, 13, 17] as $hour) {
                        $start = Carbon::parse($from)->startOfDay()->addDays($day)->setHour($hour);
                        $end = $start->copy()->addMinutes($c->duration_minutes);
                        $pool = [];
                        foreach (M\Room::where('active', true)->orderByDesc('capacity')->orderBy('id')->get() as $room) {
                            $errors = $this->conflicts($c, $room, $start, $end);
                            $blocking = array_filter($errors, fn ($e) => ! str_contains($e, 'capacity'));
                            if (! $blocking) {
                                $pool[] = $room;
                            }
                            if (collect($pool)->sum('capacity') >= $c->eligible()->count() && $pool) {
                                $saved[] = $this->schedule($c, $pool[0]->id, $start->toDateTimeString(), array_map(fn ($r) => $r->id, array_slice($pool, 1)));
                                $found = true;
                                break;
                            }
                        }
                        if ($found) {
                            break;
                        }
                    }
                }
                if (! $found) {
                    throw V::withMessages(['schedule' => 'No conflict-free slot found for course #'.$c->id.'. Entire generation rolled back; widen dates or add rooms.']);
                }
            }

            return $saved;
        });
    }

    public function revision(M\Examination $exam): M\Examination
    {
        return DB::transaction(function () use ($exam) {
            $exam = M\Examination::lockForUpdate()->findOrFail($exam->id);
            if ($exam->status !== 'published' || M\Examination::where('supersedes_id', $exam->id)->whereIn('status', ['draft', 'approved'])->exists()) {
                throw V::withMessages(['revision' => 'Select a published examination with no pending revision.']);
            }
            $revision = $exam->replicate(['status']);
            $revision->status = 'draft';
            $revision->supersedes_id = $exam->id;
            $revision->name = $exam->name.' — revision';
            $revision->save();
            foreach ($exam->courses()->with('components', 'schedule')->get() as $course) {
                $copy = $course->replicate();
                $copy->examination_id = $revision->id;
                $copy->save();
                foreach ($course->components as $comp) {
                    $cc = $comp->replicate();
                    $cc->exam_course_id = $copy->id;
                    $cc->save();
                }
                if ($course->schedule) {
                    $ss = $course->schedule->replicate();
                    $ss->exam_course_id = $copy->id;
                    $ss->save();
                    $ss->rooms()->sync($course->schedule->allocatedRooms()->pluck('id'));
                }
            }
            app(AuditService::class)->record('schedule.revision_created', $revision, ['previous_examination' => $exam->id]);

            return $revision;
        });
    }

    public function transition(M\Examination $exam, string $status): void
    {
        DB::transaction(function () use ($exam, $status) {
            M\Room::orderBy('id')->lockForUpdate()->get();
            $exam = M\Examination::lockForUpdate()->findOrFail($exam->id);
            $next = ['draft' => 'approved', 'approved' => 'published'];
            if (($next[$exam->status] ?? null) !== $status) {
                throw V::withMessages(['status' => 'Invalid examination transition.']);
            }
            if (! $exam->courses()->exists()) {
                throw V::withMessages(['status' => 'Add examination courses first.']);
            }
            if ($exam->gradingScheme->status !== 'approved') {
                throw V::withMessages(['status' => 'An approved grading scheme is required.']);
            }
            foreach ($exam->courses()->with('schedule.room')->get() as $c) {
                if (! $c->schedule) {
                    throw V::withMessages(['status' => 'Every course requires a schedule.']);
                }
                if (bccomp((string) $c->components()->sum('weight'), '100', 2) !== 0) {
                    throw V::withMessages(['status' => 'Component weights must total 100%.']);
                }
                $errors = $this->entryConflicts($c->schedule);
                if ($errors) {
                    throw V::withMessages(['status' => $errors]);
                }
                if ($status === 'published') {
                    if (! M\TeacherAssignment::where('course_id', $c->course_id)->where('section_id', $c->section_id)->whereHas('teacher', fn ($q) => $q->where('active', true))->exists()) {
                        throw V::withMessages(['status' => 'Assign an active course teacher before publishing '.$c->course->code.'.']);
                    }
                    $expected = $c->eligible()->pluck('student_id')->sort()->values()->all();
                    $seats = $c->schedule->seatingPlan?->seats()->with('room')->get() ?? collect();
                    if (! $expected || $seats->pluck('student_id')->sort()->values()->all() !== $expected) {
                        throw V::withMessages(['status' => 'Allocate a valid seat for every eligible student before publishing '.$c->course->code.'.']);
                    }
                    foreach ($seats as $seat) {
                        if (! $c->schedule->allocatedRooms()->contains('id', $seat->room_id) || $seat->seat_number > $seat->room->capacity) {
                            throw V::withMessages(['status' => 'Seating changed or room capacity is invalid; regenerate before publication.']);
                        }
                    }
                    foreach ($c->schedule->allocatedRooms() as $room) {
                        $cover = $c->schedule->duties()->where('room_id', $room->id)->where('reserve', false)->where('status', 'assigned')->get();
                        if ($cover->isEmpty()) {
                            throw V::withMessages(['status' => 'Assign primary invigilation cover to '.$room->name.' before publication.']);
                        }foreach ($cover as $duty) {
                            if (app(DutyService::class)->blocked($duty->teacher_id, $c->schedule, $duty->id)) {
                                throw V::withMessages(['status' => 'Invigilator is unavailable, inactive or overlapping another commitment.']);
                            }
                        }
                    }
                }
            }
            if ($status === 'published' && $exam->supersedes_id) {
                $previous = M\Examination::lockForUpdate()->findOrFail($exam->supersedes_id);
                $previous->update(['status' => 'archived']);
                app(AuditService::class)->record('schedule.superseded', $previous, ['new_examination' => $exam->id]);
            }
            $exam->update(['status' => $status]);
            app(AuditService::class)->record('examination.'.$status, $exam);
            if ($status === 'published') {
                $ids = M\Enrollment::whereIn('section_id', $exam->courses()->pluck('section_id'))->where('eligible', true)->pluck('student_id');
                M\User::whereIn('id', $ids)->get()->each(fn ($u) => $u->notify(new WorkflowNotice('Exam schedule published', $exam->name, '/student/examination-schedule')));
            }
        });
    }
}
