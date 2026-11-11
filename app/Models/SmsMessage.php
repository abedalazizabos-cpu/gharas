<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsMessage extends Model
{
    public const PURPOSES = ['manual' => 'رسالة من الإدارة', 'absence' => 'تنبيه غياب', 'day_off' => 'لا تحفيظ اليوم', 'certificate' => 'شهادة جديدة', 'test' => 'رسالة تجربة'];

    protected $fillable = ['purpose', 'text', 'phones', 'phones_count', 'status', 'error', 'gateway_id', 'sent_by'];

    protected function casts(): array
    {
        return ['phones' => 'array'];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by')->withTrashed();
    }

    public function purposeLabel(): string
    {
        return self::PURPOSES[$this->purpose] ?? $this->purpose;
    }
}
