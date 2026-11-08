<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A certificate the admin issued, with its wording frozen at issue time. */
class Certificate extends Model
{
    public const ICONS = ['course' => 'journal-bookmark-fill', 'individual' => 'patch-check-fill', 'combined' => 'stack'];

    protected $fillable = ['number', 'student_id', 'kind', 'sunnah_enrollment_id', 'quality_test_id', 'issued_on', 'issued_by', 'content'];

    protected function casts(): array
    {
        return ['issued_on' => DateOnly::class, 'content' => 'array'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(SunnahEnrollment::class, 'sunnah_enrollment_id');
    }

    public function qualityTest(): BelongsTo
    {
        return $this->belongsTo(QualityTest::class)->withTrashed();
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by')->withTrashed();
    }

    public function kindLabel(): string
    {
        return CertificateTemplate::KINDS[$this->kind] ?? $this->kind;
    }

    public function icon(): string
    {
        return self::ICONS[$this->kind] ?? 'award';
    }

    /** One line about what the certificate is for. */
    public function subject(): string
    {
        return $this->content['subject'] ?? '';
    }
}
