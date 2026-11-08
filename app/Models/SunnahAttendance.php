<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SunnahAttendance extends Model
{
    protected $table = 'sunnah_attendance';

    protected $fillable = ['sunnah_session_id', 'student_id', 'status', 'note'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(SunnahSession::class, 'sunnah_session_id');
    }

    public function statusLabel(): string
    {
        return AttendanceRecord::STATUSES[$this->status] ?? $this->status;
    }

    public function badge(): string
    {
        return AttendanceRecord::BADGES[$this->status] ?? 'secondary';
    }
}
