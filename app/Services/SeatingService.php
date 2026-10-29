<?php

namespace App\Services;

use App\Models as M;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException as V;

class SeatingService
{
    public function preview(M\ScheduleEntry $entry): array
    {
        $errors = app(SchedulingService::class)->entryConflicts($entry);
        $proposed = $this->assignments($entry);

        return ['schedule_id' => $entry->id, 'rooms' => $entry->allocatedRooms()->map(fn ($r) => ['name' => $r->name, 'capacity' => $r->capacity])->all(), 'conflicts' => $errors, 'proposed' => $proposed, 'current' => $entry->seatingPlan?->seats()->with('room')->get()->toArray() ?? []];
    }

    private function assignments(M\ScheduleEntry $entry): array
    {
        $students = $entry->examCourse->eligible()->with('student.profile')->orderBy('student_id')->get()->values();
        $rows = [];
        $offset = 0;
        foreach ($entry->allocatedRooms()->sortBy('id') as $room) {
            for ($seat = 1; $seat <= $room->capacity && $offset < $students->count(); $seat++) {
                $en = $students[$offset++];
                $rows[] = ['student_id' => $en->student_id, 'student' => $en->student->name, 'roll' => $en->student->profile?->roll_number, 'room_id' => $room->id, 'room' => $room->name, 'seat_number' => $seat];
            }
        }

        return $rows;
    }

    public function allocate(M\ScheduleEntry $entry, int $actor): M\SeatingPlan
    {
        return DB::transaction(function () use ($entry, $actor) {
            M\Room::orderBy('id')->lockForUpdate()->get();
            $entry = M\ScheduleEntry::lockForUpdate()->findOrFail($entry->id);
            $exam = M\Examination::lockForUpdate()->findOrFail($entry->examCourse->examination_id);
            $existing = $entry->seatingPlan;
            if (in_array($exam->status, ['published', 'archived'])) {
                throw V::withMessages(['seats' => 'Published plans are immutable; create a new examination version.']);
            }
            $errors = app(SchedulingService::class)->entryConflicts($entry);
            if ($errors) {
                throw V::withMessages(['seats' => $errors]);
            }
            $students = $entry->examCourse->eligible()->orderBy('student_id')->pluck('student_id');
            if ($students->isEmpty()) {
                throw V::withMessages(['seats' => 'No eligible students.']);
            }
            $plan = $existing ?? M\SeatingPlan::create(['schedule_entry_id' => $entry->id, 'created_by' => $actor]);
            $old = $plan->seats()->get()->toArray();
            if ($existing) {
                $plan->seats()->delete();
                $plan->increment('revision');
            }
            foreach ($this->assignments($entry) as $seat) {
                $plan->seats()->create(collect($seat)->only('student_id', 'room_id', 'seat_number')->all());
            }
            app(AuditService::class)->record('seating.allocated', $plan, ['previous' => $old, 'revision' => $plan->revision, 'count' => $students->count()]);

            return $plan;
        });
    }
}
