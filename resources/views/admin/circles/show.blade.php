@extends('layouts.app')
@section('title', 'الحلقة: '.$circle->name)
@section('content')
<x-page-header :title="$circle->name" :crumbs="['الحلقات' => route('admin.circles.index'), $circle->name]">
    <a href="{{ route('admin.attendance.create', $circle) }}" class="btn btn-success"><i class="bi bi-calendar-check"></i> تسجيل الحضور</a>
    <a href="{{ route('admin.reports.circle', $circle) }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-bar-graph"></i> تقرير الحلقة</a>
    <a href="{{ route('admin.circles.edit', $circle) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> تعديل</a>
    <x-delete-form :action="route('admin.circles.destroy', $circle)" class="btn btn-outline-danger" message="سيتم حذف الحلقة مع الاحتفاظ بسجلاتها. لا يمكن حذف حلقة بها طلاب." />
</x-page-header>
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-stat-card icon="person-badge" label="المحفظ" :value="$circle->teacher?->name ?? '—'" /></div>
    <div class="col-6 col-lg-3"><x-stat-card icon="people" label="الطلاب / السعة" :value="$circle->students->where('status', 'active')->count().' / '.$circle->capacity" color="blue" /></div>
    <div class="col-6 col-lg-3"><x-stat-card icon="calendar3" label="الأيام" :value="count($circle->days)" color="gold" :hint="$circle->daysLabel()" /></div>
    <div class="col-6 col-lg-3"><x-stat-card icon="clock" label="الموعد" :value="$circle->timeLabel()" :hint="$circle->location" /></div>
</div>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header">طلاب الحلقة</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>الطالب</th><th>ولي الأمر</th><th>الحالة</th></tr></thead>
                    <tbody>
                    @forelse ($circle->students as $s)
                        <tr><td><a href="{{ route('admin.students.show', $s) }}">{{ $s->full_name }}</a><br><small class="text-muted">{{ $s->student_number }}</small></td>
                            <td>{{ $s->parents->pluck('name')->implode('، ') ?: '—' }}</td>
                            <td><x-status-badge :status="$s->status" :label="$s->statusLabel()" /></td></tr>
                    @empty
                        <tr><td colspan="3"><x-empty icon="people" message="لا يوجد طلاب في الحلقة. أضف الطلاب من ملف الطالب ← نقل إلى حلقة." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header">أحدث سجلات التسميع</div>
            <ul class="list-group list-group-flush small">
                @forelse ($recentRecords as $r)
                    <li class="list-group-item d-flex justify-content-between"><span>{{ $r->student->full_name }} · {{ $r->typeLabel() }} · {{ $r->rangeLabel() }}</span><span class="text-muted">{{ $r->score }} · {{ $r->recorded_on->format('m/d') }}</span></li>
                @empty
                    <li class="list-group-item"><x-empty icon="book" message="لا توجد سجلات." /></li>
                @endforelse
            </ul>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3" id="teacher-control">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-person-badge"></i> محفظ الحلقة</span>
                @if ($circle->teacher)
                    <span>
                        <a href="{{ route('admin.teachers.show', $circle->teacher) }}" class="btn btn-sm btn-outline-secondary">ملفه</a>
                        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#warnModal"><i class="bi bi-exclamation-triangle"></i> إنذار</button>
                    </span>
                @endif
            </div>
            <div class="card-body">
                <p class="mb-2">المحفظ الحالي: <strong>{{ $circle->teacher?->name ?? 'بدون محفظ' }}</strong>
                    @if ($pendingWarnings)<span class="badge text-bg-danger ms-1">{{ $pendingWarnings }} إنذار لم يطلع عليه</span>@endif</p>
                <form method="POST" action="{{ route('admin.circles.teacher', $circle) }}" class="d-flex gap-2" data-confirm="سيُنقل الإشراف على الحلقة وطلابها وسجلاتها القادمة إلى المحفظ المختار، ويصل إشعار للمحفظين." data-confirm-title="تغيير محفظ الحلقة" data-confirm-button="نعم، غيّر المحفظ">
                    @csrf
                    <select name="teacher_id" class="form-select" aria-label="المحفظ البديل">
                        <option value="">بدون محفظ (إزالة المحفظ)</option>
                        @foreach ($teachers as $t)<option value="{{ $t->id }}" @selected($t->id === $circle->teacher_id)>{{ $t->name }} ({{ $t->circles_count }} حلقة)</option>@endforeach
                    </select>
                    <button class="btn btn-primary text-nowrap" data-no-loading><i class="bi bi-arrow-left-right"></i> تغيير</button>
                </form>
                <div class="form-text">تبقى سجلات الحضور والتسميع السابقة باسم من سجّلها.</div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header">آخر الجلسات</div>
            <ul class="list-group list-group-flush small">
                @forelse ($sessions as $session)
                    <li class="list-group-item d-flex justify-content-between">
                        <a href="{{ route('admin.attendance.create', [$circle, 'date' => $session->session_date->toDateString()]) }}">{{ $session->session_date->translatedFormat('l Y/m/d') }}</a>
                        <span>{{ $session->present_count }}/{{ $session->attendance_count }} حاضر</span>
                    </li>
                @empty
                    <li class="list-group-item"><x-empty icon="calendar" message="لم تُسجل جلسات بعد." /></li>
                @endforelse
            </ul>
        </div>
        <div class="card">
            <div class="card-header">طلاب انتقلوا من الحلقة</div>
            <ul class="list-group list-group-flush small">
                @forelse ($history as $h)
                    <li class="list-group-item">{{ $h->student->full_name }} <span class="text-muted">· غادر {{ $h->left_at->format('Y/m/d') }} @if ($h->reason)({{ $h->reason }})@endif</span></li>
                @empty
                    <li class="list-group-item text-muted">لا يوجد.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@if ($circle->teacher)
    @include('admin.partials.warn-modal', ['teacher' => $circle->teacher, 'circles' => $circle->teacher->circles, 'circleId' => $circle->id])
@endif
@endsection
