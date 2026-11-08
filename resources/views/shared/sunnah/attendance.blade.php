@extends('layouts.app')
@section('title', 'حضور الدورة - '.$course->title)
@section('content')
<x-page-header :title="'حضور الدورة: '.$course->title" :crumbs="[$course->title => route($area.'.sunnah.show', $course), 'الحضور والغياب']">
    <form method="GET" class="d-flex gap-2" data-no-loading>
        <input type="date" name="date" value="{{ $date }}" max="{{ now()->toDateString() }}" class="form-control" onchange="this.form.submit()">
    </form>
</x-page-header>

@if ($session)
    <div class="alert alert-info"><i class="bi bi-pencil-square ms-1"></i> يوجد حضور محفوظ لهذا اللقاء. الحفظ مرة أخرى يحدّث السجل نفسه.</div>
@endif

<div class="row g-3">
<div class="col-xl-8">
@if ($course->enrollments->isEmpty())
    <div class="card"><x-empty icon="people" message="لا يوجد طلاب في الدورة بعد." /></div>
@else
<form method="POST" action="{{ route($area.'.sunnah.attendance.store', $course) }}">
    @csrf
    <input type="hidden" name="date" value="{{ $date }}">
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <span>{{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('l d F Y') }} · {{ $course->enrollments->count() }} طالب</span>
            <div class="d-flex flex-wrap gap-2 no-print">
                <button type="button" class="btn btn-sm btn-outline-success" data-mark-all="present"><i class="bi bi-check2-all"></i> الكل حاضر</button>
                <button type="button" class="btn btn-sm btn-outline-danger" data-mark-all="absent"><i class="bi bi-x-lg"></i> الكل غائب</button>
            </div>
        </div>
        <div class="card-body border-bottom">
            <x-field name="topic" label="موضوع اللقاء (اختياري)" :value="$session?->topic" col="col-12" placeholder="مثال: شرح حديث «لا ضرر ولا ضرار»" />
        </div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th>#</th><th>الطالب</th><th>الحالة</th><th style="min-width:180px">ملاحظة</th></tr></thead>
                <tbody>
                @foreach ($course->enrollments as $i => $e)
                    @php($sid = $e->student_id)
                    @php($current = old("attendance.$sid.status", $existing[$sid]->status ?? 'present'))
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td><strong>{{ $e->student->full_name }}</strong><br><small class="text-muted">{{ $e->student->circle?->name ?? 'بلا حلقة' }}</small></td>
                        <td>
                            <div class="btn-group att-option flex-wrap" role="group">
                                @foreach (\App\Models\AttendanceRecord::STATUSES as $value => $label)
                                    <input type="radio" class="btn-check" name="attendance[{{ $sid }}][status]" id="att-{{ $sid }}-{{ $value }}" value="{{ $value }}" @checked($current === $value) autocomplete="off">
                                    <label class="btn btn-sm btn-outline-{{ \App\Models\AttendanceRecord::BADGES[$value] }}" for="att-{{ $sid }}-{{ $value }}">{{ $label }}</label>
                                @endforeach
                            </div>
                        </td>
                        <td><input type="text" name="attendance[{{ $sid }}][note]" value="{{ old("attendance.$sid.note", $existing[$sid]->note ?? '') }}" class="form-control form-control-sm" maxlength="255" placeholder="اختياري"></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div id="attendance-summary" class="d-flex flex-wrap gap-2">
            <span class="badge bg-success">حاضر: <span data-count="present">0</span></span>
            <span class="badge bg-warning text-dark">متأخر: <span data-count="late">0</span></span>
            <span class="badge bg-danger">غائب: <span data-count="absent">0</span></span>
            <span class="badge bg-info text-dark">بعذر: <span data-count="excused">0</span></span>
        </div>
        <button class="btn btn-primary px-4"><i class="bi bi-save ms-1"></i> حفظ الحضور</button>
    </div>
</form>
@endif
</div>
<div class="col-xl-4">
    <div class="card">
        <div class="card-header"><i class="bi bi-calendar-check text-success ms-1"></i> لقاءات الدورة ({{ $sessions->count() }})</div>
        <ul class="list-group list-group-flush small">
            @forelse ($sessions as $s)
                <li class="list-group-item d-flex justify-content-between gap-2 {{ $s->held_on->toDateString() === $date ? 'bg-green-soft' : '' }}">
                    <a href="{{ route($area.'.sunnah.attendance', [$course, 'date' => $s->held_on->toDateString()]) }}">{{ $s->held_on->format('Y/m/d') }}@if ($s->topic) <span class="text-muted d-block">{{ $s->topic }}</span>@endif</a>
                    <span class="text-nowrap">حضر {{ $s->present_count }} / {{ $course->enrollments->count() }}</span>
                </li>
            @empty
                <li class="list-group-item text-muted">لم يُسجَّل حضور بعد.</li>
            @endforelse
        </ul>
    </div>
</div>
</div>
@endsection
