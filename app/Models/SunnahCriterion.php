<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A grading rule of one Sunnah course, set by the admin. */
class SunnahCriterion extends Model
{
    protected $table = 'sunnah_criteria';

    protected $fillable = ['sunnah_course_id', 'name', 'max_score', 'sort_order'];

    protected function casts(): array
    {
        return ['max_score' => 'integer'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(SunnahCourse::class, 'sunnah_course_id');
    }
}
