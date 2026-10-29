<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DutyAssignment extends Model
{
    protected $attributes = ['status' => 'assigned', 'reserve' => false];

    protected $table = 'duty_assignments';

    protected $guarded = ['id'];

    protected $casts = ['reserve' => 'boolean'];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function scheduleEntry()
    {
        return $this->belongsTo(ScheduleEntry::class);
    }
}
