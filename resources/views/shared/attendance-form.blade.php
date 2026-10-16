@extends('layouts.app')
@section('title', 'تسجيل الحضور - '.$circle->name)
@section('content')
<x-page-header :title="'تسجيل الحضور: '.$circle->name" :crumbs="['تسجيل الحضور']">
    <form method="GET" class="d-flex gap-2" data-no-loading>
        <input type="date" name="date" value="{{ $date }}" max="{{ now()->toDateString() }}" class="form-control" onchange="this.form.submit()">
    </form>
</x-page-header>

@if (! $meetsToday)
    <div class="alert alert-warning"><i class="bi bi-info-circle ms-1"></i> هذا اليوم ليس من أيام الحلقة المعتادة ({{ $circle->daysLabel() }}). يمكنك التسجيل إن كانت هناك جلسة إضافية.</div>
@endif
@if ($session)
    <div class="alert alert-info"><i class="bi bi-pencil-square ms-1"></i> يوجد سجل حضور محفوظ لهذا اليوم. أي تغيير سيحدّث السجل نفسه دون تكرار، وتُوثَّق التعديلات.</div>
@endif

@if ($students->isEmpty())
    <div class="card"><x-empty icon="people" message="لا يوجد طلاب نشطون في هذه الحلقة." /></div>
@else
<form method="POST" action="{{ route($routePrefix.'.store', $circle) }}">
    @csrf
    <input type="hidden" name="date" value="{{ $date }}">
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <span>{{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('l d F Y') }} · {{ $students->count() }} طالب</span>
            <div class="d-flex flex-wrap gap-2 no-print">
                <button type="button" class="btn btn-sm btn-outline-success" data-mark-all="present"><i class="bi bi-check2-all"></i> الكل حاضر</button>
                <button type="button" class="btn btn-sm btn-outline-danger" data-mark-all="absent"><i class="bi bi-x-lg"></i> الكل غائب</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th>#</th><th>الطالب</th><th>الحالة</th><th style="min-width:200px">ملاحظة / سبب الغياب</th></tr></thead>
                <tbody>
                @foreach ($students as $i => $student)
                    @php($current = old("attendance.{$student->id}.status", $existing[$student->id]->status ?? 'present'))
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td><strong>{{ $student->full_name }}</strong><br><small class="text-muted">{{ $student->student_number }}</small></td>
                        <td>
                            <div class="btn-group att-option flex-wrap" role="group">
                                @foreach (\App\Models\AttendanceRecord::STATUSES as $value => $label)
                                    <input type="radio" class="btn-check" name="attendance[{{ $student->id }}][status]" id="att-{{ $student->id }}-{{ $value }}" value="{{ $value }}" @checked($current === $value) autocomplete="off">
                                    <label class="btn btn-sm btn-outline-{{ \App\Models\AttendanceRecord::BADGES[$value] }}" for="att-{{ $student->id }}-{{ $value }}">{{ $label }}</label>
                                @endforeach
                            </div>
                        </td>
                        <td><input type="text" name="attendance[{{ $student->id }}][notes]" value="{{ old("attendance.{$student->id}.notes", $existing[$student->id]->notes ?? '') }}" class="form-control form-control-sm" maxlength="255" placeholder="اختياري"></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body border-top">
            <label class="form-label">ملاحظات الجلسة</label>
            <textarea name="session_notes" rows="2" class="form-control" maxlength="1000">{{ old('session_notes', $session?->notes) }}</textarea>
        </div>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
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
@endsection
