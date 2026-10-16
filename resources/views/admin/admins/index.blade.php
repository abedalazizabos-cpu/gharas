@extends('layouts.app')
@section('title', 'حسابات المديرين')
@section('content')
<x-page-header title="حسابات المديرين" :crumbs="['حسابات المديرين']">
    <a href="{{ route('admin.admins.create') }}" class="btn btn-primary"><i class="bi bi-shield-plus"></i> إضافة مدير</a>
</x-page-header>
<div class="alert alert-info small"><i class="bi bi-shield-lock"></i> لا يمكن إنشاء حساب إداري إلا من هذه الصفحة بواسطة مدير حالي؛ لا يوجد تسجيل عام في النظام.</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>الاسم</th><th>البريد</th><th>الحالة</th><th>آخر دخول</th><th></th></tr></thead>
            <tbody>
            @foreach ($admins as $a)
                <tr>
                    <td>{{ $a->name }} @if ($a->is(auth()->user()))<span class="badge bg-light text-dark border">أنت</span>@endif</td>
                    <td dir="ltr" class="text-end">{{ $a->email }}</td>
                    <td><x-status-badge :status="$a->status" :label="$a->statusLabel()" /></td>
                    <td>{{ $a->last_login_at?->diffForHumans() ?? '—' }}</td>
                    <td class="table-actions">
                        <a href="{{ route('admin.admins.edit', $a) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        @unless ($a->is(auth()->user()))<x-delete-form :action="route('admin.admins.destroy', $a)" message="سيتم حذف حساب المدير." label="" />@endunless
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $admins->links() }}</div>
@endsection
