@extends('layouts.app')
@section('title', 'المشايخ (المحفظون)')
@section('content')
<x-page-header title="المشايخ (المحفظون)" :crumbs="['المشايخ']">
    <a href="{{ route('admin.teachers.create') }}" class="btn btn-primary"><i class="bi bi-person-plus"></i> إضافة محفظ</a>
</x-page-header>
<form method="GET" class="filter-bar row g-2" data-no-loading>
    <div class="col-md-6"><input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="بحث بالاسم أو البريد أو الجوال..."></div>
    <div class="col-6 col-md-3"><select name="status" class="form-select"><option value="">كل الحالات</option>@foreach (\App\Models\User::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="col-6 col-md-3"><button class="btn btn-primary w-100"><i class="bi bi-search"></i> بحث</button></div>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>الاسم</th><th>البريد</th><th>الجوال</th><th>المؤهل</th><th>الحلقات</th><th>الطلاب (عبء العمل)</th><th>الإنذارات</th><th>الحالة</th><th></th></tr></thead>
            <tbody>
            @forelse ($teachers as $t)
                <tr>
                    <td><a href="{{ route('admin.teachers.show', $t) }}" class="fw-semibold">{{ $t->name }}</a></td>
                    <td dir="ltr" class="text-end">{{ $t->email }}</td>
                    <td dir="ltr" class="text-end">{{ $t->phone }}</td>
                    <td>{{ $t->teacherProfile?->qualification ?? '—' }}</td>
                    <td>{{ $t->circles_count }}</td>
                    <td>{{ $studentCounts[$t->id] ?? 0 }}</td>
                    <td>@if ($t->warnings_count)<a href="{{ route('admin.teachers.show', $t) }}#warnings" class="badge text-bg-{{ $t->pending_warnings_count ? 'danger' : 'secondary' }} text-decoration-none" title="{{ $t->pending_warnings_count }} لم يطلع عليها">{{ $t->warnings_count }}</a>@else<span class="text-muted">—</span>@endif</td>
                    <td><x-status-badge :status="$t->status" :label="$t->statusLabel()" /></td>
                    <td class="table-actions">
                        <a href="{{ route('admin.teachers.show', $t) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.teachers.edit', $t) }}" class="btn btn-sm btn-outline-primary" title="تعديل"><i class="bi bi-pencil"></i></a>
                        @if ($t->circles_count)
                            <a href="{{ route('admin.teachers.show', $t) }}#reassign" class="btn btn-sm btn-outline-danger" title="حذف: انقل حلقاته أولاً ثم احذف الحساب"><i class="bi bi-trash"></i></a>
                        @else
                            <x-delete-form :action="route('admin.teachers.destroy', $t)" message="سيتم حذف حساب المحفظ مع الاحتفاظ بسجلاته التعليمية." />
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9"><x-empty icon="person-badge" message="لا يوجد محفظون مطابقون." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $teachers->links() }}</div>
@endsection
