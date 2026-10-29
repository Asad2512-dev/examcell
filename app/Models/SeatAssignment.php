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
}
