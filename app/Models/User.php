<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;

class User extends \Illuminate\Foundation\Auth\User
{
    use Notifiable;

    protected $attributes = ['active' => true];

    protected $table = 'users';

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['active' => 'boolean', 'password' => 'hashed'];

    public function teacherProfile()
    {
        return $this->hasOne(TeacherProfile::class, 'teacher_id');
    }

    public function profile()
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'student_id');
    }

    public function assignments()
    {
        return $this->hasMany(TeacherAssignment::class, 'teacher_id');
    }
}
