<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherProfile extends Model
{
    protected $fillable = ['qualification', 'ijazah', 'hire_date', 'notes'];

    protected function casts(): array
    {
        return ['hire_date' => DateOnly::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
