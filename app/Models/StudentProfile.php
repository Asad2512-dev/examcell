<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentProfile extends Model
{
    protected $attributes = ['eligible' => true];

    protected $table = 'student_profiles';

    protected $guarded = ['id'];

    protected $casts = ['eligible' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }
}
