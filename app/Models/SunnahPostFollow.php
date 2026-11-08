<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A parent's follow-up of one instruction or homework for one child. */
class SunnahPostFollow extends Model
{
    protected $fillable = ['sunnah_post_id', 'student_id', 'parent_id', 'seen_at', 'done_at'];

    protected function casts(): array
    {
        return ['seen_at' => 'datetime', 'done_at' => 'datetime'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(SunnahPost::class, 'sunnah_post_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id')->withTrashed();
    }
}
