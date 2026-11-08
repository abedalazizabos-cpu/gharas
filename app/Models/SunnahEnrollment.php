<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A student taking part in a Sunnah course, with the result once the sheikh grades it. */
class SunnahEnrollment extends Model
{
    public const STATUSES = ['enrolled' => 'لم يُقيَّم بعد', 'passed' => 'ناجح', 'failed' => 'لم يجتز'];

    public const STATUS_COLORS = ['enrolled' => 'secondary', 'passed' => 'success', 'failed' => 'danger'];

    protected $fillable = ['sunnah_course_id', 'student_id', 'status', 'percentage', 'grade', 'notes', 'evaluated_on', 'evaluated_by'];

    protected function casts(): array
    {
        return ['evaluated_on' => DateOnly::class, 'percentage' => 'float'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(SunnahCourse::class, 'sunnah_course_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function certificate(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Certificate::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(SunnahScore::class);
    }

    public function scopeGraded(Builder $query): Builder
    {
        return $query->where('status', '!=', 'enrolled');
    }

    public function isGraded(): bool
    {
        return $this->status !== 'enrolled';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'secondary';
    }

    public function gradeLabel(): ?string
    {
        return $this->grade ? (MemorizationRecord::GRADES[$this->grade] ?? $this->grade) : null;
    }

    public function percentLabel(): string
    {
        return $this->percentage === null ? '—' : rtrim(rtrim(number_format((float) $this->percentage, 2), '0'), '.').'%';
    }
}
