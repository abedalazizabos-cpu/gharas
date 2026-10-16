@extends('layouts.app')
@section('title', 'ملف الطالب: '.$student->full_name)
@section('content')
<x-page-header :title="$student->full_name" :crumbs="['الطلاب' => route('admin.students.index'), 'ملف الطالب']">
    <a href="{{ route('admin.students.edit', $student) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> تعديل</a>
    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#transferModal"><i class="bi bi-arrow-left-right"></i> نقل إلى حلقة</button>
    <a href="{{ route('admin.reports.student', $student) }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-bar-graph"></i> تقرير التقدم</a>
    <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">الحالة</button>
        <div class="dropdown-menu dropdown-menu-end p-2">
            @foreach (\App\Models\Student::STATUSES as $k => $v)
                @continue($k === $student->status)
                <x-delete-form :action="route('admin.students.status', $student)" method="PATCH" :label="$v" icon="{{ $k === 'active' ? 'check-circle' : ($k === 'withdrawn' ? 'box-arrow-left' : 'pause-circle') }}" class="dropdown-item" title="تغيير الحالة" :message="$k === 'withdrawn' ? 'سيتم تسجيل انسحاب الطالب وإخراجه من حلقته مع الاحتفاظ بجميع سجلاته.' : 'سيتم تغيير حالة الطالب إلى «'.$v.'».'">
                    <input type="hidden" name="status" value="{{ $k }}">
                </x-delete-form>
            @endforeach
            <hr class="dropdown-divider">
            <x-delete-form :action="route('admin.students.destroy', $student)" label="حذف (أرشفة)" class="dropdown-item text-danger" message="سيتم أرشفة الطالب مع الاحتفاظ بسجلاته التعليمية." />
        </div>
    </div>
</x-page-header>
@include('shared._guardians', ['prefix' => 'admin'])

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-body">
            <div class="d-flex justify-content-between mb-3"><span class="text-muted">رقم الطالب</span><strong>{{ $student->student_number }}</strong></div>
            <dl class="row small mb-0">
                <dt class="col-5">الحالة</dt><dd class="col-7"><x-status-badge :status="$student->status" :label="$student->statusLabel()" /></dd>
                <dt class="col-5">الحلقة</dt><dd class="col-7">@if ($student->circle)<a href="{{ route('admin.circles.show', $student->circle) }}">{{ $student->circle->name }}</a>@else — @endif</dd>
                <dt class="col-5">المحفظ</dt><dd class="col-7">{{ $student->circle?->teacher?->name ?? '—' }}</dd>
                <dt class="col-5">تاريخ التسجيل</dt><dd class="col-7">{{ $student->enrollment_date->format('Y/m/d') }}</dd>
                <dt class="col-5">الجنس</dt><dd class="col-7">{{ $student->genderLabel() ?? '—' }}</dd>
                <dt class="col-5">العمر</dt><dd class="col-7">{{ $student->age() ? $student->age().' سنة' : '—' }}</dd>
                <dt class="col-5">التواصل</dt><dd class="col-7" dir="ltr">{{ $student->phone ?? '—' }}</dd>
                <dt class="col-5">ولي الأمر</dt>
                <dd class="col-7">@forelse ($student->parents as $p)<a href="{{ route('admin.parents.show', $p) }}" class="d-block">{{ $p->name }}</a>@empty <span class="text-danger">غير مرتبط</span> @endforelse</dd>
            </dl>
            @if ($student->internal_notes)
                <div class="alert alert-secondary small mt-3 mb-0"><i class="bi bi-lock"></i> {{ $student->internal_notes }}</div>
            @endif
        </div></div>
    </div>
    <div class="col-lg-4">@include('shared._progress')</div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">سجل الانتقال بين الحلقات</div>
            <ul class="list-group list-group-flush small">
                @forelse ($student->enrollments as $e)
                    <li class="list-group-item">
                        <strong>{{ $e->circle->name }}</strong>
                        @if (! $e->left_at)<span class="badge bg-success">حالية</span>@endif
                        <div class="text-muted">من {{ $e->joined_at->format('Y/m/d') }} @if ($e->left_at) إلى {{ $e->left_at->format('Y/m/d') }} @endif</div>
                        @if ($e->reason)<div class="text-muted">السبب: {{ $e->reason }}</div>@endif
                    </li>
                @empty
                    <li class="list-group-item"><x-empty message="لا يوجد سجل انتقال." /></li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header p-0 border-0">
        <ul class="nav nav-tabs px-3 pt-2" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-mem">الحفظ والمراجعة</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-att">الحضور والغياب</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-ass">التقييمات</button></li>
        </ul>
    </div>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-mem">@include('shared._memorization-table', ['editRoute' => 'admin.memorization.edit', 'deleteRoute' => 'admin.memorization.destroy', 'showShared' => true])</div>
        <div class="tab-pane fade" id="tab-att">@include('shared._attendance-table')</div>
        <div class="tab-pane fade p-3" id="tab-ass">@include('shared._assessments')</div>
    </div>
</div>

<div class="modal fade" id="transferModal" tabindex="-1">
    <div class="modal-dialog"><form method="POST" action="{{ route('admin.students.transfer', $student) }}" class="modal-content">
        @csrf
        <div class="modal-header"><h5 class="modal-title">نقل الطالب إلى حلقة</h5><button type="button" class="btn-close ms-0" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <p class="small text-muted">ستبقى جميع سجلات الحضور والحفظ والتقييم السابقة محفوظة، ويُسجَّل تاريخ الانتقال.</p>
            <label class="form-label">الحلقة الجديدة</label>
            <select name="circle_id" class="form-select mb-3">
                <option value="">— إخراج من الحلقة (بدون حلقة) —</option>
                @foreach ($circles as $c)
                    <option value="{{ $c->id }}" @disabled($c->id === $student->current_circle_id)>{{ $c->name }} ({{ $c->students_count }}/{{ $c->capacity }}){{ $c->students_count >= $c->capacity ? ' - ممتلئة' : '' }}</option>
                @endforeach
            </select>
            <label class="form-label">سبب النقل</label>
            <input type="text" name="reason" class="form-control mb-3" maxlength="255" placeholder="مثال: الانتقال إلى مستوى أعلى">
            @if (\App\Models\Setting::get('allow_capacity_override') === '1')
                <div class="form-check"><input class="form-check-input" type="checkbox" name="override_capacity" value="1" id="ov"><label class="form-check-label small" for="ov">تجاوز السعة إن كانت الحلقة ممتلئة</label></div>
            @endif
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button><button class="btn btn-primary">تأكيد النقل</button></div>
    </form></div>
</div>
@endsection
