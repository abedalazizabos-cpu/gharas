<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = [
        'active' => 'نشط',
        'suspended' => 'موقوف',
        'withdrawn' => 'منسحب',
    ];

    public const GENDERS = [
        'male' => 'ذكر',
        'female' => 'أنثى',
    ];

    // current_circle_id and status change only through EnrollmentService / explicit actions.
    protected $fillable = [
        'student_number', 'full_name', 'gender', 'birth_date', 'phone',
        'enrollment_date', 'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => DateOnly::class,
            'enrollment_date' => DateOnly::class,
            'withdrawn_at' => DateOnly::class,
        ];
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'current_circle_id')->withTrashed();
    }

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_student', 'student_id', 'parent_id')
            ->withPivot('relationship')
            ->withTimestamps();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CircleEnrollment::class)->latest('joined_at');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function memorizationRecords(): HasMany
    {
        return $this->hasMany(MemorizationRecord::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! filled($term)) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('full_name', 'like', "%{$term}%")
            ->orWhere('student_number', 'like', "%{$term}%"));
    }

    /** "يوسف أحمد السبيعي" becomes "يوسف أ." for read-only external viewers. */
    public function maskedName(): string
    {
        $parts = preg_split('/\s+/u', trim((string) $this->full_name));

        return $parts[0].(isset($parts[1]) ? ' '.mb_substr($parts[1], 0, 1).'.' : '');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function genderLabel(): ?string
    {
        return self::GENDERS[$this->gender] ?? null;
    }

    public function age(): ?int
    {
        return $this->birth_date?->age;
    }

    public static function generateNumber(): string
    {
        $year = now()->format('Y');
        $last = static::withTrashed()->where('student_number', 'like', "G{$year}%")->max('student_number');
        $next = $last ? ((int) substr($last, 5)) + 1 : 1;

        return 'G'.$year.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
