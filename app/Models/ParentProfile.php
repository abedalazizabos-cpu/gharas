<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentProfile extends Model
{
    protected $fillable = ['national_id', 'alt_phone', 'address'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
