<?php

namespace App\Policies;

use App\Models\CalculatedResult;
use App\Models\User;

class CalculatedResultPolicy
{
    public function view(User $u, CalculatedResult $r): bool
    {
        return $u->role === 'admin' || ($u->role === 'student' && $u->id === $r->student_id && $r->batch->status === 'published') || ($u->role === 'teacher' && (new ExamCoursePolicy)->view($u, $r->examCourse));
    }
}
