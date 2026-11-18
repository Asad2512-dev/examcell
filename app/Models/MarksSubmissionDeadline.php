<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarksSubmissionDeadline extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['marks_due_at' => 'immutable_datetime'];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
