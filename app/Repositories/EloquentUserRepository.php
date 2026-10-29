<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function findActiveByEmail(string $email): ?User
    {
        return User::query()
            ->where('email', strtolower(trim($email)))
            ->where('active', true)
            ->first();
    }
}
