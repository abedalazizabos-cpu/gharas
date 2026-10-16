<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Circle extends Model
{
    use HasFactory, SoftDeletes;

    public const DAYS = [
        0 => 'الأحد',
        1 => 'الاثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
        6 => 'السبت',
    ];

    /** Study days for every circle: Saturday to Thursday, Friday is off (center decision). */
    public const STUDY_DAYS = [6, 0, 1, 2, 3, 4];

    public const STATUSES = [
        'active' => 'نشطة',
        'inactive' => 'متوقفة',
    ];

    public const TIME_TYPES = [
        'clock' => 'ساعة محددة',
        'prayer' => 'بعد صلاة',
        'custom' => 'توقيت آخر',
    ];

    /** Prayers a circle can follow, with a rough clock time used only for ordering. */
    public const PRAYERS = [
        'fajr' => ['الفجر', '05:00'],
        'dhuhr' => ['الظهر', '12:15'],
        'asr' => ['العصر', '15:30'],
        'maghrib' => ['المغرب', '18:00'],
        'isha' => ['العشاء', '19:30'],
    ];

    protected $fillable = [
        'name', 'code', 'teacher_id', 'level', 'age_group', 'days', 'time_type', 'prayer', 'time_note', 'start_time',
        'end_time', 'capacity', 'location', 'status', 'description', 'is_public',
    ];

    protected function casts(): array
    {
        return [
            'days' => 'array',
            'is_public' => 'boolean',
            'capacity' => 'integer',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id')->withTrashed();
    }

    /** Students whose current circle is this one. */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'current_circle_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CircleEnrollment::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CircleSession::class);
    }

    public function memorizationRecords(): HasMany
    {
        return $this->hasMany(MemorizationRecord::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function activeStudentsCount(): int
    {
        return $this->students()->where('status', 'active')->count();
    }

    public function isFull(): bool
    {
        return $this->activeStudentsCount() >= $this->capacity;
    }

    public function daysLabel(): string
    {
        $days = array_map('intval', $this->days ?? []);
        if (! array_diff(self::STUDY_DAYS, $days) && ! in_array(5, $days, true)) {
            return 'من السبت إلى الخميس';
        }

        return collect(self::STUDY_DAYS)->intersect($days)->map(fn ($d) => self::DAYS[$d])->implode('، ');
    }

    public function timeLabel(): string
    {
        $label = match ($this->time_type) {
            'prayer' => 'بعد صلاة '.(self::PRAYERS[$this->prayer][0] ?? ''),
            'custom' => (string) $this->time_note,
            default => trim(substr((string) $this->start_time, 0, 5).' - '.substr((string) $this->end_time, 0, 5), ' -'),
        };

        if ($this->time_type === 'prayer' && $this->time_note) {
            $label .= ' · '.$this->time_note;
        }

        return $label !== '' ? $label : '—';
    }

    /** Approximate start ("HH:MM") so circles of mixed timing kinds sort sensibly. */
    public function sortKey(): string
    {
        return match ($this->time_type) {
            'prayer' => self::PRAYERS[$this->prayer][1] ?? '99:99',
            'custom' => '99:99',
            default => substr((string) $this->start_time, 0, 5) ?: '99:99',
        };
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function meetsOn(int $weekday): bool
    {
        return in_array($weekday, array_map('intval', $this->days ?? []), true);
    }
}
