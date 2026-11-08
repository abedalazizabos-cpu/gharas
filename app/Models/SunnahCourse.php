<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A course of قسم السنة, given by one sheikh to students chosen from the whole center. */
class SunnahCourse extends Model
{
    use SoftDeletes;

    public const CATEGORIES = [
        'quran_adab' => 'آداب القرآن',
        'ijaz' => 'الإعجاز القرآني',
        'hadith' => 'الأحاديث النبوية',
        'salah' => 'الصلاة: فرائضها وواجباتها وآدابها',
        'taharah' => 'الطهارة: فرائضها وواجباتها وآدابها',
        'seerah' => 'السيرة النبوية',
        'sahaba' => 'سير الصحابة',
        'other' => 'أخرى',
    ];

    public const CATEGORY_ICONS = [
        'quran_adab' => 'book-half', 'ijaz' => 'stars', 'hadith' => 'chat-quote', 'salah' => 'moon-stars',
        'taharah' => 'droplet', 'seerah' => 'compass', 'sahaba' => 'people', 'other' => 'journal-bookmark',
    ];

    public const STATUSES = ['planned' => 'لم تبدأ', 'running' => 'قائمة', 'finished' => 'انتهت'];

    public const STATUS_COLORS = ['planned' => 'secondary', 'running' => 'success', 'finished' => 'primary'];

    /** Grading rules suggested for a new course; the admin can change all of them. */
    public const DEFAULT_CRITERIA = [
        'hadith' => [['حفظ الأحاديث المقررة', 40], ['فهم المعاني والفوائد', 30], ['الحضور والانضباط', 15], ['المشاركة', 15]],
        'salah' => [['الاختبار النظري', 40], ['التطبيق العملي للصلاة', 30], ['الحضور والانضباط', 15], ['المشاركة', 15]],
        'taharah' => [['الاختبار النظري', 40], ['التطبيق العملي للوضوء', 30], ['الحضور والانضباط', 15], ['المشاركة', 15]],
        'default' => [['الاختبار النهائي', 50], ['الحضور والانضباط', 20], ['المشاركة والتفاعل', 15], ['الواجبات والبحوث', 15]],
    ];

    protected $fillable = [
        'title', 'category', 'description', 'objectives', 'teacher_id', 'starts_on', 'ends_on',
        'schedule', 'location', 'status', 'pass_score', 'results_to_parents',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => DateOnly::class,
            'ends_on' => DateOnly::class,
            'pass_score' => 'integer',
            'results_to_parents' => 'boolean',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id')->withTrashed();
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(SunnahCriterion::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Participants whose student record still exists. */
    public function enrollments(): HasMany
    {
        return $this->hasMany(SunnahEnrollment::class)->whereHas('student');
    }

    /** Instructions and homework of the sheikh, newest first. */
    public function posts(): HasMany
    {
        return $this->hasMany(SunnahPost::class)->latest()->latest('id');
    }

    public function scopeFor(Builder $query, User $teacher): Builder
    {
        return $query->where('teacher_id', $teacher->id);
    }

    public static function defaultCriteria(?string $category): array
    {
        return self::DEFAULT_CRITERIA[$category] ?? self::DEFAULT_CRITERIA['default'];
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function categoryIcon(): string
    {
        return self::CATEGORY_ICONS[$this->category] ?? 'journal-bookmark';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'secondary';
    }

    /** @return array<int, string> */
    public function objectiveList(): array
    {
        return array_values(array_filter(array_map(fn ($l) => trim(ltrim(trim($l), '-•*0123456789.)( ')), preg_split('/\r?\n/', (string) $this->objectives))));
    }

    public function periodLabel(): string
    {
        if (! $this->starts_on && ! $this->ends_on) {
            return '—';
        }

        return trim(($this->starts_on?->format('Y/m/d') ?? '…').' إلى '.($this->ends_on?->format('Y/m/d') ?? '…'));
    }

    public function maxTotal(): int
    {
        return (int) $this->criteria->sum('max_score');
    }
}
