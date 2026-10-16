<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    public const STATUSES = [
        'present' => 'حاضر',
        'late' => 'متأخر',
        'absent' => 'غائب',
        'excused' => 'غائب بعذر',
    ];

    public const BADGES = [
        'present' => 'success',
        'late' => 'warning',
        'absent' => 'danger',
        'excused' => 'info',
    ];

    protected $fillable = ['circle_session_id', 'student_id', 'status', 'notes', 'recorded_by'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CircleSession::class, 'circle_session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withTrashed();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function badge(): string
    {
        return self::BADGES[$this->status] ?? 'secondary';
    }
}
