<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemorizationRecordView extends Model
{
    public $timestamps = false;

    protected $fillable = ['memorization_record_id', 'parent_id', 'seen_at'];

    protected function casts(): array
    {
        return ['seen_at' => 'datetime'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id')->withTrashed();
    }
}
