<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // The super admin passes wherever admins do.
        $role = $user?->isSuperAdmin() && in_array('admin', $roles, true) ? 'admin' : $user?->role;
        if (! $user || ! in_array($role, $roles, true)) {
            abort(403, 'ليس لديك صلاحية للوصول إلى هذه الصفحة.');
        }

        return $next($request);
    }
}
