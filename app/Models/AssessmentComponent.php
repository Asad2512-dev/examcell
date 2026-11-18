<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentComponent extends Model
{
    protected $table = 'assessment_components';

    protected $guarded = ['id'];

    protected $casts = ['maximum' => 'decimal:2', 'weight' => 'decimal:2'];

    public function examCourse()
    {
        return $this->belongsTo(ExamCourse::class);
    }

    public function marks()
    {
        return $this->hasMany(Mark::class);
    }
}
