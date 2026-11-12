<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;

/**
 * Notifications on the phone's lock screen (Web Push), for users who installed the site as an app or
 * allowed notifications in the browser. Messages are collected during the request and sent after the
 * page has been returned, so a slow push service never slows the site down.
 */
class WebPushService
{
    /** @var array<int, array{sub: PushSubscription, payload: string}> */
    protected array $queue = [];

    protected bool $flushRegistered = false;

    /** The site's VAPID key pair, made once and kept in the settings (the private key encrypted). */
    public function keys(): ?array
    {
        $public = Setting::get('vapid_public');
        $private = Setting::get('vapid_private');
        if (! $public || ! $private) {
            try {
                $keys = VAPID::createVapidKeys();
            } catch (\Throwable $e) {
                Log::warning('Web push keys could not be created: '.$e->getMessage());

                return null;
            }
            Setting::put('vapid_public', $public = $keys['publicKey']);
            Setting::put('vapid_private', $private = Crypt::encryptString($keys['privateKey']));
        }
        try {
            return ['publicKey' => $public, 'privateKey' => Crypt::decryptString($private)];
        } catch (\Throwable) {
            return null;
        }
    }

    public function publicKey(): ?string
    {
        return $this->keys()['publicKey'] ?? null;
    }

    public function subscribe(User $user, array $sub, ?string $device = null): PushSubscription
    {
        return PushSubscription::updateOrCreate(['endpoint_hash' => hash('sha256', $sub['endpoint'])], [
            'user_id' => $user->id, 'endpoint' => $sub['endpoint'], 'public_key' => $sub['keys']['p256dh'], 'auth_token' => $sub['keys']['auth'],
            'content_encoding' => $sub['contentEncoding'] ?? 'aes128gcm', 'device' => $device ? mb_substr($device, 0, 150) : null,
        ]);
    }

    public function unsubscribe(string $endpoint): void
    {
        PushSubscription::where('endpoint_hash', hash('sha256', $endpoint))->delete();
    }

    /** Queues a notification to every device of the user; it is sent once the response is out. */
    public function toUser(User $user, string $title, string $body, ?string $url = null): void
    {
        $subs = PushSubscription::where('user_id', $user->id)->get();
        if ($subs->isEmpty()) {
            return;
        }
        $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url ?: route('dashboard'), 'icon' => route('pwa.icon', 192)], JSON_UNESCAPED_UNICODE);
        foreach ($subs as $sub) {
            $this->queue[] = ['sub' => $sub, 'payload' => $payload];
        }
        if (! $this->flushRegistered) {
            $this->flushRegistered = true;
            app()->terminating(fn () => $this->flush());
        }
    }

    public function pending(): array
    {
        return $this->queue;
    }

    /** Sends everything queued. Devices the push service no longer knows are removed. */
    public function flush(): int
    {
        $queue = $this->queue;
        $this->queue = [];
        $keys = $queue ? $this->keys() : null;
        if (! $queue || ! $keys) {
            return 0;
        }
        try {
            $push = new WebPush(['VAPID' => ['subject' => url('/'), 'publicKey' => $keys['publicKey'], 'privateKey' => $keys['privateKey']]], ['TTL' => 86400], 10);
            foreach ($queue as $item) {
                $s = $item['sub'];
                $push->queueNotification(Subscription::create(['endpoint' => $s->endpoint, 'publicKey' => $s->public_key, 'authToken' => $s->auth_token, 'contentEncoding' => $s->content_encoding]), $item['payload']);
            }
            $sent = 0;
            foreach ($push->flush() as $report) {
                if ($report->isSuccess()) {
                    $sent++;
                } elseif ($report->isSubscriptionExpired()) {
                    $this->unsubscribe($report->getEndpoint());
                }
            }
            PushSubscription::whereIn('id', collect($queue)->pluck('sub.id'))->update(['last_used_at' => now()]);

            return $sent;
        } catch (\Throwable $e) {
            Log::warning('Web push failed: '.$e->getMessage());

            return 0;
        }
    }
}
