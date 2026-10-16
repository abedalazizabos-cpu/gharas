<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use SoftDeletes;

    public const AUDIENCES = [
        'all' => 'جميع المستخدمين',
        'teachers' => 'المحفظون',
        'parents' => 'أولياء الأمور',
        'public' => 'عام (الصفحة الرئيسية)',
    ];

    protected $fillable = ['title', 'body', 'audience', 'publish_at', 'expires_at'];

    protected function casts(): array
    {
        return ['publish_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query
            ->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** Announcements visible to the given user (or to the public when null). */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        $audiences = match (true) {
            $user === null => ['public'],
            $user->isAdmin() => array_keys(self::AUDIENCES),
            $user->isTeacher() => ['all', 'teachers', 'public'],
            $user->isParent() => ['all', 'parents', 'public'],
            default => ['public'],
        };

        return $query->whereIn('audience', $audiences);
    }

    public function audienceLabel(): string
    {
        return self::AUDIENCES[$this->audience] ?? $this->audience;
    }

    public function isCurrent(): bool
    {
        return ($this->publish_at === null || $this->publish_at->isPast())
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
