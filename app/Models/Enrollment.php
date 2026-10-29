<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    protected $attributes = ['eligible' => true];

    protected $table = 'enrollments';

    protected $guarded = ['id'];

    protected $casts = ['eligible' => 'boolean'];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }
}
