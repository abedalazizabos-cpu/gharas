<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/** A form sent later from a teacher's phone after being filled in offline (see App\Http\Middleware\OfflineSync). */
class OfflineSubmission extends Model
{
    use MassPrunable;

    protected $fillable = ['uuid', 'user_id', 'path'];

    /** A phone never holds a form for months; old receipts are removed by model:prune. */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(90));
    }
}
