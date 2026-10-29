<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mark extends Model
{
    protected $table = 'marks';

    protected $guarded = ['id'];

    protected $casts = ['score' => 'decimal:2', 'absent' => 'boolean'];

    public function component()
    {
        return $this->belongsTo(AssessmentComponent::class, 'assessment_component_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
