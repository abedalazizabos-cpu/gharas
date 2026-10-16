@extends('layouts.app')
@section('title', 'المستخدمون وصلاحياتهم')
@section('content')
<x-page-header title="المستخدمون وصلاحياتهم" :crumbs="['الأدوار والصلاحيات' => route('admin.access.roles'), 'المستخدمون']" />
<form method="GET" class="filter-bar row g-2 align-items-center">
    <div class="col-md-6"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="ابحث بالاسم أو البريد أو الجوال"></div>
    <div class="col-md-4"><select name="role" class="form-select" onchange="this.form.submit()">
        <option value="">كل الأدوار</option>
        @foreach (\App\Models\User::ROLES as $k => $v)<option value="{{ $k }}" @selected(request('role') === $k)>{{ $v }}</option>@endforeach
    </select></div>
    <div class="col-md-2"><button class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> بحث</button></div>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>الاسم</th><th>اسم الدخول</th><th>الدور</th><th>الحالة</th><th>صلاحيات خاصة</th><th></th></tr></thead>
            <tbody>
            @forelse ($users as $u)
                <tr>
                    <td class="fw-semibold">{{ $u->name }} @if ($u->is(auth()->user()))<span class="badge bg-light text-dark border">أنت</span>@endif</td>
                    <td dir="ltr" class="text-end small">{{ $u->email ?: $u->phone }}</td>
                    <td><span class="badge {{ $u->isSuperAdmin() ? 'text-bg-warning' : 'text-bg-light border' }}">{{ $u->roleLabel() }}</span></td>
                    <td><x-status-badge :status="$u->status" :label="$u->statusLabel()" /></td>
                    <td>{{ $u->permission_rules_count ?: '—' }}</td>
                    <td class="text-end"><a href="{{ route('admin.access.users.edit', $u) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-shield-lock"></i> الدور والصلاحيات</a></td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty icon="people" message="لا يوجد مستخدمون مطابقون." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $users->links() }}</div>
@endsection
