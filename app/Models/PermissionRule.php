<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A role-wide (user_id null) or per-user permission switch set by the super admin. */
class PermissionRule extends Model
{
    protected $fillable = ['role', 'user_id', 'permission', 'allowed'];

    protected function casts(): array
    {
        return ['allowed' => 'boolean'];
    }
}
