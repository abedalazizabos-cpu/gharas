<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One stored file per circle per week (Sunday to Saturday). */
class WeeklyArchive extends Model
{
    protected $fillable = ['circle_id', 'week_start', 'path', 'records_count', 'attendance_count', 'generated_at'];

    protected function casts(): array
    {
        return ['week_start' => DateOnly::class, 'generated_at' => 'datetime'];
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class)->withTrashed();
    }

    public function downloadName(): string
    {
        return 'أسبوع-'.$this->week_start->format('Y-m-d').'-'.($this->circle?->code ?? $this->circle_id).'.pdf';
    }
}
