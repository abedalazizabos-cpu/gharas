@extends('layouts.app')
@section('title', 'صلاحيات '.$user->name)
@section('content')
<x-page-header :title="'الدور والصلاحيات: '.$user->name" :crumbs="['الأدوار والصلاحيات' => route('admin.access.roles'), 'المستخدمون' => route('admin.access.users'), $user->name]" />
<form method="POST" action="{{ route('admin.access.users.update', $user) }}">
    @csrf @method('PUT')
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header fw-bold">الحساب</div>
                <div class="card-body">
                    <div class="small text-muted mb-3" dir="ltr" style="text-align:right">{{ $user->email ?: $user->phone }}</div>
                    <label class="form-label" for="role">الدور</label>
                    <select id="role" name="role" class="form-select mb-3" @disabled($user->is(auth()->user()))>
                        @foreach (\App\Models\User::ROLES as $k => $v)<option value="{{ $k }}" @selected($user->role === $k)>{{ $v }}</option>@endforeach
                    </select>
                    @if ($user->is(auth()->user()))<input type="hidden" name="role" value="{{ $user->role }}">@endif
                    <label class="form-label" for="status">الحالة</label>
                    <select id="status" name="status" class="form-select" @disabled($user->is(auth()->user()))>
                        @foreach (\App\Models\User::STATUSES as $k => $v)<option value="{{ $k }}" @selected($user->status === $k)>{{ $v }}</option>@endforeach
                    </select>
                    @if ($user->is(auth()->user()))<input type="hidden" name="status" value="{{ $user->status }}"><div class="form-text">لا يمكنك تغيير دورك أو إيقاف حسابك بنفسك.</div>@endif
                    <div class="form-text mt-2">عند تغيير الدور تُمسح الصلاحيات الخاصة القديمة، ثم يمكنك ضبط صلاحيات الدور الجديد.</div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header fw-bold">الصلاحيات الخاصة بهذا المستخدم</div>
                @if ($user->isSuperAdmin())
                    <div class="card-body text-muted"><i class="bi bi-stars"></i> المدير الأعلى يملك كل الصلاحيات.</div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>الصلاحية</th><th class="text-center">حسب الدور</th><th class="text-center">سماح</th><th class="text-center">منع</th><th class="text-center">النتيجة</th></tr></thead>
                            <tbody>
                            @foreach ($perms as $key => $p)
                                @php($choice = $p['override'] === null ? 'inherit' : ($p['override'] ? 'allow' : 'deny'))
                                @php($effective = $p['override'] ?? $p['role'])
                                <tr>
                                    <td>{{ $p['label'] }}</td>
                                    <td class="text-center"><input class="form-check-input" type="radio" name="perm[{{ $key }}]" value="inherit" @checked($choice === 'inherit') aria-label="حسب الدور">
                                        <div class="small text-muted">{{ $p['role'] ? 'مسموح' : 'ممنوع' }}</div></td>
                                    <td class="text-center"><input class="form-check-input" type="radio" name="perm[{{ $key }}]" value="allow" @checked($choice === 'allow') aria-label="سماح"></td>
                                    <td class="text-center"><input class="form-check-input" type="radio" name="perm[{{ $key }}]" value="deny" @checked($choice === 'deny') aria-label="منع"></td>
                                    <td class="text-center">{!! $effective ? '<span class="badge text-bg-success">مسموح</span>' : '<span class="badge text-bg-danger">ممنوع</span>' !!}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-2 mt-3">
        <a href="{{ route('admin.access.users') }}" class="btn btn-light">رجوع</a>
        <button class="btn btn-primary px-4"><i class="bi bi-save ms-1"></i> حفظ</button>
    </div>
</form>
@endsection
