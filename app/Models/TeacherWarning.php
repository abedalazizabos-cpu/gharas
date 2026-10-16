<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherWarning extends Model
{
    /** Escalating levels; the color is a Bootstrap contextual class. */
    public const LEVELS = [
        'notice' => ['تنبيه', 'info'],
        'first' => ['إنذار أول', 'warning'],
        'second' => ['إنذار ثانٍ', 'orange'],
        'final' => ['إنذار نهائي', 'danger'],
    ];

    protected $fillable = ['teacher_id', 'issued_by', 'circle_id', 'level', 'reason', 'acknowledged_at'];

    protected function casts(): array
    {
        return ['acknowledged_at' => 'datetime'];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by')->withTrashed();
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class)->withTrashed();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('acknowledged_at');
    }

    public function levelLabel(): string
    {
        return self::LEVELS[$this->level][0] ?? $this->level;
    }

    public function levelColor(): string
    {
        return self::LEVELS[$this->level][1] ?? 'secondary';
    }
}
