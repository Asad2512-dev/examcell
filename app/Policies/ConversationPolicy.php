<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $u, Conversation $c): bool
    {
        return $u->id === $c->user_id;
    }
}
