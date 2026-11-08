<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One meeting of a Sunnah course, with its attendance. */
class SunnahSession extends Model
{
    protected $fillable = ['sunnah_course_id', 'held_on', 'topic', 'taken_by'];

    protected function casts(): array
    {
        return ['held_on' => DateOnly::class];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(SunnahCourse::class, 'sunnah_course_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(SunnahAttendance::class);
    }
}
