<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleEntry extends Model
{
    protected $table = 'schedule_entries';

    protected $guarded = ['id'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function examCourse()
    {
        return $this->belongsTo(ExamCourse::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function seatingPlan()
    {
        return $this->hasOne(SeatingPlan::class);
    }

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'schedule_rooms')->withTimestamps();
    }

    public function allocatedRooms()
    {
        return $this->rooms->isNotEmpty() ? $this->rooms : collect([$this->room]);
    }

    public function getRoomLabelAttribute()
    {
        return $this->allocatedRooms()->pluck('name')->implode(', ');
    }

    public function roomForStudent(int $student): string
    {
        $seat = $this->seatingPlan?->seats()->with('room')->where('student_id', $student)->first();

        return $seat?->room?->name ?? ($this->allocatedRooms()->count() === 1 ? $this->room->name : 'Seat allocation pending');
    }

    public function duties()
    {
        return $this->hasMany(DutyAssignment::class);
    }
}
