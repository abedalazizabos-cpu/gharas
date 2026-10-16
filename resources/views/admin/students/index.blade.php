@extends('layouts.app')
@section('title', 'الطلاب')
@section('content')
<x-page-header title="إدارة الطلاب" :crumbs="['الطلاب']">
    <a href="{{ route('admin.students.create') }}" class="btn btn-primary"><i class="bi bi-person-plus"></i> إضافة طالب</a>
</x-page-header>

<form method="GET" class="filter-bar row g-2" data-no-loading>
    <div class="col-md-4"><input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="بحث بالاسم أو رقم الطالب..."></div>
    <div class="col-6 col-md-2">
        <select name="circle" class="form-select"><option value="">كل الحلقات</option><option value="none" @selected(request('circle') === 'none')>بدون حلقة</option>
            @foreach ($circles as $c)<option value="{{ $c->id }}" @selected(request('circle') == $c->id)>{{ $c->name }}</option>@endforeach</select>
    </div>
    <div class="col-6 col-md-2">
        <select name="teacher" class="form-select"><option value="">كل المحفظين</option>
            @foreach ($teachers as $t)<option value="{{ $t->id }}" @selected(request('teacher') == $t->id)>{{ $t->name }}</option>@endforeach</select>
    </div>
    <div class="col-6 col-md-2">
        <select name="status" class="form-select"><option value="">كل الحالات</option>
            @foreach (\App\Models\Student::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select>
    </div>
    <div class="col-6 col-md-2 d-flex gap-1">
        <button class="btn btn-primary flex-grow-1"><i class="bi bi-search"></i> بحث</button>
        <a href="{{ route('admin.students.index') }}" class="btn btn-light" title="مسح"><i class="bi bi-x-lg"></i></a>
    </div>
</form>

@php($sortLink = fn ($col, $label) => '<a class="text-reset text-decoration-none" href="'.request()->fullUrlWithQuery(['sort' => $col, 'dir' => request('sort') === $col && request('dir') !== 'desc' ? 'desc' : 'asc']).'">'.$label.(request('sort', 'full_name') === $col ? (request('dir') === 'desc' ? ' <i class="bi bi-sort-down"></i>' : ' <i class="bi bi-sort-up"></i>') : '').'</a>')
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th>{!! $sortLink('student_number', 'رقم الطالب') !!}</th><th>{!! $sortLink('full_name', 'الاسم') !!}</th>
                <th>الحلقة</th><th>المحفظ</th><th>ولي الأمر</th><th>{!! $sortLink('enrollment_date', 'تاريخ التسجيل') !!}</th><th>{!! $sortLink('status', 'الحالة') !!}</th><th></th>
            </tr></thead>
            <tbody>
            @forelse ($students as $s)
                <tr>
                    <td class="text-muted">{{ $s->student_number }}</td>
                    <td><a href="{{ route('admin.students.show', $s) }}" class="fw-semibold">{{ $s->full_name }}</a></td>
                    <td>{{ $s->circle?->name ?? '—' }}</td>
                    <td>{{ $s->circle?->teacher?->name ?? '—' }}</td>
                    <td>{{ $s->parents->pluck('name')->implode('، ') ?: '—' }}</td>
                    <td>{{ $s->enrollment_date->format('Y/m/d') }}</td>
                    <td><x-status-badge :status="$s->status" :label="$s->statusLabel()" /></td>
                    <td class="table-actions">
                        <a href="{{ route('admin.students.show', $s) }}" class="btn btn-sm btn-outline-secondary" title="عرض"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.students.edit', $s) }}" class="btn btn-sm btn-outline-primary" title="تعديل"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty icon="search" message="لا توجد نتائج مطابقة لبحثك." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $students->links() }}</div>
@endsection
