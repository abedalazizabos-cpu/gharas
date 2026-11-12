<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\WebPushService;
use Illuminate\Notifications\Notification;

/** Sends a SystemNotification to the user's phones as well (when they turned notifications on). */
class WebPushChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! method_exists($notification, 'toArray')) {
            return;
        }
        $data = $notification->toArray($notifiable);
        app(WebPushService::class)->toUser($notifiable, $data['title'] ?? '', $data['body'] ?? '', $data['url'] ?? null);
    }
}
