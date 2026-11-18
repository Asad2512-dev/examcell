<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeatingPlan extends Model
{
    protected $table = 'seating_plans';

    protected $guarded = ['id'];

    protected $casts = [];

    public function seats()
    {
        return $this->hasMany(SeatAssignment::class);
    }

    public function scheduleEntry()
    {
        return $this->belongsTo(ScheduleEntry::class);
    }
}
