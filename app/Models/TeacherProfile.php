<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherProfile extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['reserve' => 'boolean'];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
