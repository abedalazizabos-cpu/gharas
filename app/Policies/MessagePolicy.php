<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    public function view(User $user, Message $message): bool
    {
        return in_array($user->id, [$message->sender_id, $message->recipient_id], true);
    }
}
