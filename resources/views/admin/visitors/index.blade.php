@extends('layouts.app')
@section('title', 'الزوار والداعمون')
@section('content')
<x-page-header title="حسابات الزوار والداعمين" :crumbs="['الزوار والداعمون']">
    <a href="{{ route('admin.visitors.create') }}" class="btn btn-primary"><i class="bi bi-person-plus"></i> إضافة زائر</a>
</x-page-header>
<div class="alert alert-info small"><i class="bi bi-eye"></i> حساب الزائر للوفود الخارجية والداعمين: يطّلع على سير جميع الحلقات والتقارير الشاملة دون أي صلاحية للتعديل، وتظهر له أسماء الطلاب مختصرة.</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>الاسم</th><th>البريد</th><th>الجوال</th><th>الحالة</th><th>آخر دخول</th><th></th></tr></thead>
            <tbody>
            @forelse ($visitors as $v)
                <tr>
                    <td>{{ $v->name }}</td>
                    <td dir="ltr" class="text-end">{{ $v->email }}</td>
                    <td dir="ltr" class="text-end">{{ $v->phone ?? '—' }}</td>
                    <td><x-status-badge :status="$v->status" :label="$v->statusLabel()" /></td>
                    <td>{{ $v->last_login_at?->diffForHumans() ?? '—' }}</td>
                    <td class="table-actions">
                        <a href="{{ route('admin.visitors.edit', $v) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <x-delete-form :action="route('admin.visitors.destroy', $v)" message="سيتم حذف حساب الزائر." label="" />
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty icon="eye" message="لا توجد حسابات زوار بعد." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $visitors->links() }}</div>
@endsection
