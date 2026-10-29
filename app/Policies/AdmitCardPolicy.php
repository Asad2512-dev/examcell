<?php

namespace App\Policies;

use App\Models\AdmitCard;
use App\Models\User;

class AdmitCardPolicy
{
    public function view(User $u, AdmitCard $a): bool
    {
        return $u->role === 'admin' || ($u->role === 'student' && $u->id === $a->student_id && $a->examination->status === 'published');
    }
}
