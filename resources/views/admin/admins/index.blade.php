@extends('layouts.app')
@section('title', 'حسابات المديرين')
@section('content')
<x-page-header title="حسابات المديرين" :crumbs="['حسابات المديرين']">
    <a href="{{ route('admin.admins.create') }}" class="btn btn-primary"><i class="bi bi-shield-plus"></i> إضافة مدير</a>
</x-page-header>
<div class="alert alert-info small"><i class="bi bi-shield-lock"></i> لا يمكن إنشاء حساب إداري إلا من هذه الصفحة بواسطة مدير حالي؛ لا يوجد تسجيل عام في النظام.
    @if (auth()->user()->isSuperAdmin()) من هنا توقف المدير أو تفعّله بضغطة، وتضبط ما يستطيع فعله من زر «الصلاحيات».@endif
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>الاسم</th><th>البريد / الجوال</th><th>الحالة</th>@if (auth()->user()->isSuperAdmin())<th>صلاحيات خاصة</th>@endif<th>آخر دخول</th><th></th></tr></thead>
            <tbody>
            @forelse ($admins as $a)
                <tr>
                    <td class="fw-semibold">{{ $a->name }} @if ($a->is(auth()->user()))<span class="badge bg-light text-dark border">أنت</span>@endif</td>
                    <td dir="ltr" class="text-end small">{{ $a->email }}@if ($a->phone)<br><span class="text-muted">{{ $a->phone }}</span>@endif</td>
                    <td><x-status-badge :status="$a->status" :label="$a->statusLabel()" /></td>
                    @if (auth()->user()->isSuperAdmin())<td>{{ $a->permission_rules_count ?: '—' }}</td>@endif
                    <td>{{ $a->last_login_at?->diffForHumans() ?? 'لم يدخل بعد' }}</td>
                    <td class="table-actions text-nowrap">
                        <a href="{{ route('admin.admins.edit', $a) }}" class="btn btn-sm btn-outline-primary" title="تعديل البيانات وكلمة المرور"><i class="bi bi-pencil"></i></a>
                        @if (auth()->user()->isSuperAdmin())
                            <a href="{{ route('admin.access.users.edit', $a) }}" class="btn btn-sm btn-outline-secondary" title="الصلاحيات"><i class="bi bi-shield-lock"></i> <span class="d-none d-xl-inline">الصلاحيات</span></a>
                        @endif
                        @unless ($a->is(auth()->user()))
                            @if ($a->status === 'active')
                                <x-delete-form :action="route('admin.admins.status', $a)" method="PATCH" label="إيقاف" icon="pause-circle" class="btn btn-sm btn-outline-warning" title="إيقاف الحساب" message="لن يتمكن {{ $a->name }} من الدخول حتى تعيد تفعيله."><input type="hidden" name="status" value="suspended"></x-delete-form>
                            @else
                                <x-delete-form :action="route('admin.admins.status', $a)" method="PATCH" label="تفعيل" icon="check-circle" class="btn btn-sm btn-outline-success" title="تفعيل الحساب" message="سيتمكن {{ $a->name }} من الدخول مرة أخرى."><input type="hidden" name="status" value="active"></x-delete-form>
                            @endif
                            <x-delete-form :action="route('admin.admins.destroy', $a)" :message="auth()->user()->isSuperAdmin() ? 'سيتم حذف حساب المدير. يمكنك استرجاعه من سلة المحذوفات.' : 'سيتم حذف حساب المدير.'" label="" />
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty icon="shield-lock" message="لا يوجد مديرون بعد. أضف أول مدير من الزر أعلاه." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $admins->links() }}</div>
@endsection
