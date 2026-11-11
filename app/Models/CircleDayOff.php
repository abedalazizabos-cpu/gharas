<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A day a circle (or, with no circle, the whole center) did not meet, with the reason. */
class CircleDayOff extends Model
{
    public const REASONS = ['عطلة رسمية', 'مرض المحفظ', 'ظرف طارئ للمحفظ', 'ظرف في مكان الحلقة', 'نشاط أو رحلة للمركز', 'ظرف جوي', 'أخرى'];

    protected $fillable = ['circle_id', 'off_on', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['off_on' => DateOnly::class];
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
