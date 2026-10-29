<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DutyRequest extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['requested_at' => 'datetime', 'deadline_at' => 'datetime'];

    public function duty()
    {
        return $this->belongsTo(DutyAssignment::class, 'duty_assignment_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function proposal()
    {
        return $this->hasOne(DutyReplacement::class);
    }
}
