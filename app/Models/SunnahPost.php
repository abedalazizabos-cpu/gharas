<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** An instruction or a homework the sheikh gives in a Sunnah course. */
class SunnahPost extends Model
{
    use SoftDeletes;

    public const TYPES = ['instruction' => 'تعليمات', 'homework' => 'واجب'];

    public const TYPE_ICONS = ['instruction' => 'info-circle', 'homework' => 'pencil-square'];

    protected $fillable = ['sunnah_course_id', 'author_id', 'type', 'title', 'body', 'due_on'];

    protected function casts(): array
    {
        return ['due_on' => DateOnly::class];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(SunnahCourse::class, 'sunnah_course_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id')->withTrashed();
    }

    public function follows(): HasMany
    {
        return $this->hasMany(SunnahPostFollow::class);
    }

    public function isHomework(): bool
    {
        return $this->type === 'homework';
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function typeIcon(): string
    {
        return self::TYPE_ICONS[$this->type] ?? 'info-circle';
    }

    public function isOverdue(): bool
    {
        return $this->isHomework() && $this->due_on && $this->due_on->lt(now()->startOfDay());
    }
}
