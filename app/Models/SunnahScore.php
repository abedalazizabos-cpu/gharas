<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SunnahScore extends Model
{
    public $timestamps = false;

    protected $fillable = ['sunnah_enrollment_id', 'sunnah_criterion_id', 'max_score', 'score'];

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(SunnahCriterion::class, 'sunnah_criterion_id');
    }
}
