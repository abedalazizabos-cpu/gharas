<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AuditLogger
{
    /** Attributes that must never be written to the audit log. */
    private const HIDDEN = ['password', 'remember_token'];

    public static function log(string $action, string $description, ?Model $model = null, array $old = [], array $new = []): AuditLog
    {
        return AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'description' => $description,
            'auditable_type' => $model ? $model::class : null,
            'auditable_id' => $model?->getKey(),
            'old_values' => Arr::except($old, self::HIDDEN) ?: null,
            'new_values' => Arr::except($new, self::HIDDEN) ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }

    /** Log only the attributes that actually changed on a saved model. */
    public static function logChanges(string $action, string $description, Model $model): ?AuditLog
    {
        $changes = Arr::except($model->getChanges(), ['updated_at']);
        if ($changes === []) {
            return null;
        }
        // After save() the original already holds the new values; getPrevious() has the old ones.
        $old = array_intersect_key($model->getPrevious(), $changes);

        return self::log($action, $description, $model, $old, $changes);
    }
}
