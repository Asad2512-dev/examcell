<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeatAssignment extends Model
{
    protected $table = 'seat_assignments';

    protected $guarded = ['id'];

    protected $casts = [];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function seatingPlan()
    {
        return $this->belongsTo(SeatingPlan::class);
    }

    /** Row-letter seat label (e.g. C4) from the room's layout. */
    public function seatLabel(): string
    {
        static $layouts = [];
        $room = $this->room ?? $this->seatingPlan?->scheduleEntry?->room;
        if (! $room) {
            return (string) $this->seat_number;
        }

        return ($layouts[$room->id.':'.$room->updated_at] ??= $room->layout())->labelOf((int) $this->seat_number);
    }
}
