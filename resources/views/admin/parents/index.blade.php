@extends('layouts.app')
@section('title', 'أولياء الأمور')
@section('content')
<x-page-header title="إدارة أولياء الأمور" :crumbs="['أولياء الأمور']">
    <a href="{{ route('admin.parents.create') }}" class="btn btn-primary"><i class="bi bi-person-plus"></i> إضافة ولي أمر</a>
</x-page-header>
<form method="GET" class="filter-bar row g-2" data-no-loading>
    <div class="col-md-5"><input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="بحث بالاسم أو البريد أو الجوال..."></div>
    <div class="col-6 col-md-2"><select name="status" class="form-select"><option value="">كل الحالات</option>@foreach (\App\Models\User::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="col-6 col-md-3"><select name="linked" class="form-select"><option value="">حالة الارتباط</option><option value="yes" @selected(request('linked') === 'yes')>مرتبط بأبناء</option><option value="no" @selected(request('linked') === 'no')>غير مرتبط</option></select></div>
    <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-search"></i> بحث</button></div>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>الاسم</th><th>الجوال</th><th>البريد</th><th>الأبناء المرتبطون</th><th>الحالة</th><th></th></tr></thead>
            <tbody>
            @forelse ($parents as $p)
                <tr>
                    <td><a href="{{ route('admin.parents.show', $p) }}" class="fw-semibold">{{ $p->name }}</a></td>
                    <td dir="ltr" class="text-end">{{ $p->phone }}</td>
                    <td dir="ltr" class="text-end">{{ $p->email ?? '—' }}</td>
                    <td>@forelse ($p->children as $c)<span class="badge bg-green-soft text-success-emphasis me-1">{{ $c->full_name }}</span>@empty <span class="badge bg-danger-subtle text-danger-emphasis">غير مرتبط</span> @endforelse</td>
                    <td><x-status-badge :status="$p->status" :label="$p->statusLabel()" /></td>
                    <td class="table-actions">
                        <a href="{{ route('admin.parents.show', $p) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.parents.edit', $p) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty icon="house-heart" message="لا يوجد أولياء أمور مطابقون." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $parents->links() }}</div>
@endsection
