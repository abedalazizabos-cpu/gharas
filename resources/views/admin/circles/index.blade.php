@extends('layouts.app')
@section('title', 'الحلقات')
@section('content')
<x-page-header title="إدارة الحلقات" :crumbs="['الحلقات']">
    @if (auth()->user()->isSuperAdmin())
        <a href="{{ route('admin.trash.index') }}" class="btn btn-outline-danger"><i class="bi bi-trash3"></i> سلة المحذوفات</a>
    @endif
    <a href="{{ route('admin.circles.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> إضافة حلقة</a>
</x-page-header>
<form method="GET" class="filter-bar row g-2" data-no-loading>
    <div class="col-md-5"><input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="بحث باسم الحلقة أو رمزها..."></div>
    <div class="col-6 col-md-3"><select name="teacher" class="form-select"><option value="">كل المحفظين</option>@foreach ($teachers as $t)<option value="{{ $t->id }}" @selected(request('teacher') == $t->id)>{{ $t->name }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><select name="status" class="form-select"><option value="">كل الحالات</option>@foreach (\App\Models\Circle::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-search"></i> بحث</button></div>
</form>
<div class="row g-3">
    @forelse ($circles as $c)
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div><h5 class="fw-bold mb-0"><a href="{{ route('admin.circles.show', $c) }}" class="text-reset">{{ $c->name }}</a></h5><small class="text-muted">{{ $c->code }} · {{ $c->level ?? '—' }}</small></div>
                        <x-status-badge :status="$c->status" :label="$c->statusLabel()" />
                    </div>
                    <ul class="list-unstyled small mb-3">
                        <li><i class="bi bi-person-badge text-success ms-1"></i> {{ $c->teacher?->name ?? 'بدون محفظ' }}</li>
                        <li><i class="bi bi-calendar3 text-success ms-1"></i> {{ $c->daysLabel() }}</li>
                        <li><i class="bi bi-clock text-success ms-1"></i> {{ $c->timeLabel() }} @if ($c->location)· {{ $c->location }}@endif</li>
                    </ul>
                    <div class="d-flex align-items-center gap-2">
                        <div class="progress flex-grow-1"><div class="progress-bar {{ $c->students_count >= $c->capacity ? 'bg-danger' : '' }}" style="width: {{ min(100, $c->students_count / max(1, $c->capacity) * 100) }}%"></div></div>
                        <small>{{ $c->students_count }}/{{ $c->capacity }}</small>
                    </div>
                </div>
                <div class="card-footer bg-transparent d-flex gap-2">
                    <a href="{{ route('admin.circles.show', $c) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> عرض</a>
                    <a href="{{ route('admin.attendance.create', $c) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-calendar-check"></i> الحضور</a>
                    <button type="button" class="btn btn-sm btn-outline-warning ms-auto" title="تغيير المحفظ" data-bs-toggle="modal" data-bs-target="#teacherModal"
                        data-action="{{ route('admin.circles.teacher', $c) }}" data-circle="{{ $c->name }}" data-teacher="{{ $c->teacher_id }}"><i class="bi bi-arrow-left-right"></i> <span class="d-none d-xxl-inline">المحفظ</span></button>
                    <a href="{{ route('admin.circles.edit', $c) }}" class="btn btn-sm btn-outline-primary" title="تعديل"><i class="bi bi-pencil"></i></a>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12"><div class="card"><x-empty icon="bullseye" message="لا توجد حلقات مطابقة." /></div></div>
    @endforelse
</div>
<div class="mt-3">{{ $circles->links() }}</div>

<div class="modal fade" id="teacherModal" tabindex="-1" aria-labelledby="teacherModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="teacherModalTitle">تغيير محفظ <span data-circle-name></span></h5>
                <button type="button" class="btn-close ms-0 me-auto" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
                <label for="tm-teacher" class="form-label">المحفظ</label>
                <select name="teacher_id" id="tm-teacher" class="form-select">
                    <option value="">بدون محفظ (إزالة المحفظ)</option>
                    @foreach ($teachers as $t)<option value="{{ $t->id }}" @disabled($t->status !== 'active')>{{ $t->name }}{{ $t->status !== 'active' ? ' (موقوف)' : '' }}</option>@endforeach
                </select>
                <div class="form-text">يصل إشعار للمحفظ الجديد والسابق، وتبقى السجلات السابقة كما هي.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button class="btn btn-primary"><i class="bi bi-check-lg"></i> حفظ</button>
            </div>
        </form>
    </div>
</div>
<script>
document.getElementById('teacherModal').addEventListener('show.bs.modal', function (e) {
    var b = e.relatedTarget, f = this.querySelector('form');
    f.action = b.dataset.action;
    this.querySelector('[data-circle-name]').textContent = b.dataset.circle;
    f.teacher_id.value = b.dataset.teacher || '';
});
</script>
@endsection
