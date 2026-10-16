<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CircleEnrollment extends Model
{
    protected $fillable = ['student_id', 'circle_id', 'joined_at', 'left_at', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['joined_at' => DateOnly::class, 'left_at' => DateOnly::class];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
