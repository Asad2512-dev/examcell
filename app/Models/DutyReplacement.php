<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DutyReplacement extends Model
{
    protected $table = 'duty_replacements';

    protected $guarded = ['id'];

    protected $casts = [];

    public function request()
    {
        return $this->belongsTo(DutyRequest::class, 'duty_request_id');
    }

    public function duty()
    {
        return $this->belongsTo(DutyAssignment::class, 'duty_assignment_id');
    }

    public function candidate()
    {
        return $this->belongsTo(User::class, 'candidate_id');
    }
}
