@extends('layouts.app')
@section('title', 'المحفظ: '.$teacher->name)
@section('content')
<x-page-header :title="$teacher->name" :crumbs="['المشايخ' => route('admin.teachers.index'), 'ملف المحفظ']">
    <a href="{{ route('admin.teachers.edit', $teacher) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> تعديل</a>
    <a href="{{ route('admin.reports.teacher', $teacher) }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-bar-graph"></i> تقرير المحفظ</a>
    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#warnModal"><i class="bi bi-exclamation-triangle"></i> إنذار</button>
    @if ($teacher->status === 'active')
        <x-delete-form :action="route('admin.teachers.status', $teacher)" method="PATCH" label="إيقاف الحساب" icon="pause-circle" class="btn btn-outline-warning" title="إيقاف الحساب" message="لن يتمكن المحفظ من الدخول إلى النظام."><input type="hidden" name="status" value="suspended"></x-delete-form>
    @else
        <x-delete-form :action="route('admin.teachers.status', $teacher)" method="PATCH" label="تفعيل الحساب" icon="check-circle" class="btn btn-outline-success" title="تفعيل الحساب" message="سيتمكن المحفظ من الدخول إلى النظام."><input type="hidden" name="status" value="active"></x-delete-form>
    @endif
    <x-delete-form :action="route('admin.teachers.destroy', $teacher)" class="btn btn-outline-danger" message="سيتم حذف حساب المحفظ مع الاحتفاظ بسجلاته التعليمية." />
</x-page-header>
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-stat-card icon="bullseye" label="الحلقات" :value="$circles->count()" /></div>
    <div class="col-6 col-lg-3"><x-stat-card icon="people" label="الطلاب النشطون" :value="$studentsCount" color="blue" /></div>
    <div class="col-6 col-lg-3"><x-stat-card icon="calendar-check" label="نسبة الحضور (30 يوماً)" :value="$totals['attendance_rate'] !== null ? $totals['attendance_rate'].'%' : '—'" color="gold" /></div>
    <div class="col-6 col-lg-3"><x-stat-card icon="book" label="آيات حفظ جديد (30 يوماً)" :value="$totals['new_ayahs']" /></div>
</div>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card"><div class="card-body small">
            <dl class="row mb-0">
                <dt class="col-5">البريد</dt><dd class="col-7" dir="ltr">{{ $teacher->email }}</dd>
                <dt class="col-5">الجوال</dt><dd class="col-7" dir="ltr">{{ $teacher->phone ?? '—' }}</dd>
                <dt class="col-5">الحالة</dt><dd class="col-7"><x-status-badge :status="$teacher->status" :label="$teacher->statusLabel()" /></dd>
                <dt class="col-5">المؤهل</dt><dd class="col-7">{{ $teacher->teacherProfile?->qualification ?? '—' }}</dd>
                <dt class="col-5">الإجازة</dt><dd class="col-7">{{ $teacher->teacherProfile?->ijazah ?? '—' }}</dd>
                <dt class="col-5">آخر دخول</dt><dd class="col-7">{{ $teacher->last_login_at?->diffForHumans() ?? 'لم يدخل بعد' }}</dd>
            </dl>
        </div></div>
        <div class="card mt-3" id="reassign">
            <div class="card-header"><i class="bi bi-arrow-left-right"></i> نقل حلقات المحفظ</div>
            <div class="card-body">
                @if ($circles->isEmpty())
                    <p class="small text-muted mb-0">لا توجد حلقات مسندة. يمكن حذف الحساب مباشرة إن لزم.</p>
                @else
                    <form method="POST" action="{{ route('admin.teachers.reassign', $teacher) }}" data-confirm="ستُنقل كل حلقات المحفظ ({{ $circles->count() }}) مع طلابها إلى المحفظ المختار." data-confirm-title="نقل الحلقات" data-confirm-button="نعم، انقل">
                        @csrf
                        <label for="target_id" class="form-label small">نقل حلقاته ({{ $circles->count() }}) إلى</label>
                        <select name="target_id" id="target_id" class="form-select mb-2">
                            <option value="">بدون محفظ (تبقى الحلقات بلا محفظ)</option>
                            @foreach ($otherTeachers as $t)<option value="{{ $t->id }}">{{ $t->name }} ({{ $t->circles_count }} حلقة)</option>@endforeach
                        </select>
                        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="delete_account" value="1" id="del-after"><label class="form-check-label small text-danger" for="del-after">حذف حساب المحفظ بعد النقل</label></div>
                        <button class="btn btn-outline-primary w-100"><i class="bi bi-arrow-left-right"></i> نقل الحلقات</button>
                    </form>
                    <div class="form-text">لتغيير محفظ حلقة واحدة فقط، افتح الحلقة واختر «تغيير».</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        @include('admin.partials.warnings-list')
        @forelse ($circles as $c)
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between"><a href="{{ route('admin.circles.show', $c) }}">{{ $c->name }}</a><small class="text-muted">{{ $c->daysLabel() }} · {{ $c->timeLabel() }} · {{ $c->students->count() }}/{{ $c->capacity }}</small></div>
                <div class="card-body small">
                    @forelse ($c->students as $s)<a href="{{ route('admin.students.show', $s) }}" class="badge bg-light text-dark border me-1 mb-1 text-decoration-none">{{ $s->full_name }}</a>@empty <span class="text-muted">لا يوجد طلاب.</span> @endforelse
                </div>
            </div>
        @empty
            <div class="card"><x-empty icon="bullseye" message="لم تُسند حلقات لهذا المحفظ بعد." /></div>
        @endforelse
    </div>
</div>
@include('admin.partials.warn-modal', ['circleId' => null])
@endsection
