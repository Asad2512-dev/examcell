<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Examination extends Model
{
    protected $attributes = ['status' => 'draft', 'admit_enabled' => false];

    protected $table = 'examinations';

    protected $guarded = ['id'];

    protected $casts = ['admit_enabled' => 'boolean'];

    public function courses()
    {
        return $this->hasMany(ExamCourse::class);
    }

    public function gradingScheme()
    {
        return $this->belongsTo(GradingScheme::class);
    }

    public function batch()
    {
        return $this->hasOne(ResultBatch::class);
    }
}
