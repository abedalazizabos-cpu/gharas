<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assessment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'student_id', 'circle_id', 'teacher_id', 'assessed_on', 'percentage', 'notes', 'shared_with_parent',
    ];

    protected function casts(): array
    {
        return [
            'assessed_on' => DateOnly::class,
            'percentage' => 'float',
            'shared_with_parent' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class)->withTrashed();
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id')->withTrashed();
    }

    public function scores(): HasMany
    {
        return $this->hasMany(AssessmentScore::class);
    }

    public function gradeLabel(): string
    {
        return MemorizationRecord::GRADES[MemorizationRecord::gradeForScore((int) round($this->percentage))];
    }
}
