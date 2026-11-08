<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A quality committee test: one juz (individual) or a stage of the combined ladder. */
class QualityTest extends Model
{
    use SoftDeletes;

    public const KINDS = ['individual' => 'اختبار منفرد', 'combined' => 'اختبار مجتمع'];

    public const STATUSES = ['pending' => 'بانتظار الاختبار', 'passed' => 'ناجح', 'failed' => 'لم يجتز'];

    public const STATUS_COLORS = ['pending' => 'warning', 'passed' => 'success', 'failed' => 'danger'];

    protected $fillable = [
        'student_id', 'kind', 'juz', 'stage_id', 'from_juz', 'to_juz', 'examiner_id', 'assigned_by',
        'assigned_on', 'status', 'tested_on', 'percentage', 'grade', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_on' => DateOnly::class,
            'tested_on' => DateOnly::class,
            'percentage' => 'float',
            'juz' => 'integer',
            'from_juz' => 'integer',
            'to_juz' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function examiner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'examiner_id')->withTrashed();
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CombinedTestStage::class, 'stage_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(QualityTestScore::class);
    }

    public function scopePassed(Builder $query): Builder
    {
        return $query->where('status', 'passed');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function isIndividual(): bool
    {
        return $this->kind === 'individual';
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
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

    /** "الجزء 30 (عمّ)" or "الأجزاء 30 - 28". */
    public function subjectLabel(): string
    {
        return $this->isIndividual()
            ? \App\Support\Juz::label((int) $this->juz)
            : \App\Support\Juz::rangeLabel($this->from_juz, $this->to_juz).($this->stage?->name ? ' ('.$this->stage->name.')' : '');
    }
}
