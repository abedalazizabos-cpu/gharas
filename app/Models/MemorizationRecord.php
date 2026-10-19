<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemorizationRecord extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES = [
        'new' => 'حفظ جديد',
        'revision' => 'مراجعة',
        'consolidation' => 'تثبيت',
    ];

    public const TYPE_BADGES = [
        'new' => 'success',
        'revision' => 'primary',
        'consolidation' => 'warning',
    ];

    public const GRADES = [
        'excellent' => 'ممتاز',
        'very_good' => 'جيد جداً',
        'good' => 'جيد',
        'acceptable' => 'مقبول',
        'weak' => 'ضعيف',
    ];

    protected $fillable = [
        'student_id', 'circle_id', 'teacher_id', 'recorded_on', 'type', 'surah_id',
        'from_ayah', 'to_ayah', 'pages', 'score', 'mistakes_count', 'notes',
        'recommendations', 'shared_with_parent',
    ];

    protected function casts(): array
    {
        return [
            'recorded_on' => DateOnly::class,
            'shared_with_parent' => 'boolean',
            'pages' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (MemorizationRecord $record) {
            $record->grade = self::gradeForScore((int) $record->score);
        });
    }

    public static function gradeForScore(int $score): string
    {
        return match (true) {
            $score >= 90 => 'excellent',
            $score >= 80 => 'very_good',
            $score >= 70 => 'good',
            $score >= 60 => 'acceptable',
            default => 'weak',
        };
    }

    /** Parents who opened their child's page after this record was shared. */
    public function parentViews(): HasMany
    {
        return $this->hasMany(MemorizationRecordView::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class)->withTrashed();
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id')->withTrashed();
    }

    public function surah(): BelongsTo
    {
        return $this->belongsTo(Surah::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function gradeLabel(): string
    {
        return self::GRADES[$this->grade] ?? $this->grade;
    }

    /** Amount in pages as teachers say it: ربع صفحة، نصف صفحة، صفحة، صفحتان، 3 صفحات... */
    public static function formatPages(float|string|null $pages): string
    {
        $p = round((float) $pages, 2);

        return match (true) {
            $p <= 0 => '—',
            $p == 0.25 => 'ربع صفحة',
            $p == 0.5 => 'نصف صفحة',
            $p == 0.75 => 'ثلاثة أرباع صفحة',
            $p == 1.0 => 'صفحة',
            $p == 2.0 => 'صفحتان',
            $p >= 3 && $p <= 10 && floor($p) == $p => (int) $p.' صفحات',
            default => rtrim(rtrim(number_format($p, 2, '.', ''), '0'), '.').' صفحة',
        };
    }

    public function pagesLabel(): string
    {
        return self::formatPages($this->pages);
    }

    public function rangeLabel(): string
    {
        return ($this->surah?->name ?? '').' ('.$this->from_ayah.' - '.$this->to_ayah.')';
    }
}
