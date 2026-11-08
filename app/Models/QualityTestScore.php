<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityTestScore extends Model
{
    public $timestamps = false;

    protected $fillable = ['quality_test_id', 'quality_criterion_id', 'max_score', 'score'];

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(QualityCriterion::class, 'quality_criterion_id');
    }
}
