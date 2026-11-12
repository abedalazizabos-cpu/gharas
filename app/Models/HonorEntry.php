<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** One student of one circle on the honor board of a month. */
class HonorEntry extends Model
{
    protected $table = 'honor_board';

    protected $fillable = ['month', 'circle_id', 'student_id', 'points', 'details', 'picked_by'];

    protected function casts(): array
    {
        return ['details' => 'array', 'points' => 'decimal:2'];
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class)->withTrashed();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function picker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picked_by')->withTrashed();
    }

    /** "نوفمبر 2026" */
    public static function monthLabel(string $month): string
    {
        return Carbon::createFromFormat('Y-m-d', $month.'-01')->translatedFormat('F Y');
    }

    /** The student's name as the super admin chose to show it on the public login screen. */
    public function publicName(): string
    {
        $name = trim((string) $this->student?->full_name);
        $parts = preg_split('/\s+/u', $name);

        return match (Setting::get('honor_names', 'two')) {
            'full' => $name,
            'masked' => $this->student?->maskedName() ?? '',
            default => implode(' ', array_slice($parts, 0, 2)),
        };
    }
}
