<?php

namespace App\Policies;

use App\Models\ExamCourse;
use App\Models\TeacherAssignment;
use App\Models\User;

class ExamCoursePolicy
{
    public function view(User $u, ExamCourse $c): bool
    {
        return $u->role === 'admin' || ($u->role === 'teacher' && TeacherAssignment::where('teacher_id', $u->id)->where('course_id', $c->course_id)->where('section_id', $c->section_id)->exists());
    }

    public function update(User $u, ExamCourse $c): bool
    {
        return $this->view($u, $c);
    }
}
