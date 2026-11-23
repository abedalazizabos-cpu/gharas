<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A request to enroll a new student: from a parent on the website, or from a teacher for his own circle. */
class RegistrationRequest extends Model
{
    public const STATUSES = ['pending' => 'بانتظار الرد', 'accepted' => 'مقبول', 'rejected' => 'مرفوض'];

    public const STATUS_COLORS = ['pending' => 'warning', 'accepted' => 'success', 'rejected' => 'secondary'];

    public const RELATIONSHIPS = ['father' => 'الأب', 'mother' => 'الأم', 'guardian' => 'وليّ أمر آخر'];

    protected $fillable = ['student_name', 'gender', 'birth_date', 'memorized_juz', 'guardian_name', 'guardian_phone', 'relationship', 'notes', 'ip'];

    protected function casts(): array
    {
        return ['birth_date' => DateOnly::class, 'handled_at' => 'datetime', 'memorized_juz' => 'integer'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class)->withTrashed();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by')->withTrashed();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function age(): ?int
    {
        return $this->birth_date?->age;
    }
}
