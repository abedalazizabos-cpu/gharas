<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CircleSession extends Model
{
    protected $fillable = ['circle_id', 'teacher_id', 'session_date', 'start_time', 'notes'];

    protected function casts(): array
    {
        return ['session_date' => DateOnly::class];
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class)->withTrashed();
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id')->withTrashed();
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
