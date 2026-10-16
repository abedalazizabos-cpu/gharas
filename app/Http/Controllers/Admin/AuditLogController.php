<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.audit.index', [
            'logs' => AuditLog::with('user')
                ->when($request->query('user'), fn ($q, $u) => $q->where('user_id', $u))
                ->when($request->query('action'), fn ($q, $a) => $q->where('action', 'like', $a.'%'))
                ->when($request->query('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                ->when($request->query('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
                ->latest('created_at')->latest('id')->paginate(30)->withQueryString(),
            'users' => User::whereIn('role', [User::ROLE_SUPERADMIN, User::ROLE_ADMIN, User::ROLE_TEACHER])->orderBy('name')->get(['id', 'name']),
            'actions' => AuditLog::query()->selectRaw('DISTINCT action')->orderBy('action')->pluck('action')
                ->map(fn ($a) => explode('.', $a)[0])->unique()->values(),
        ]);
    }
}
