@extends('layouts.app')
@section('title', $student->full_name)
@section('content')
<x-page-header :title="$student->full_name" :crumbs="['أبنائي' => route('parent.dashboard'), $student->full_name]">
    @if ($student->circle?->teacher)
        @if (auth()->user()->hasPermission('messages.send'))<a href="{{ route('messages.create', ['to' => $student->circle->teacher_id, 'student' => $student->id]) }}" class="btn btn-outline-primary"><i class="bi bi-chat-dots"></i> مراسلة المحفظ</a>@endif
    @endif
    @if (auth()->user()->hasPermission('parent.weeks'))<a href="{{ route('parent.children.weeks', $student) }}" class="btn btn-primary"><i class="bi bi-calendar-week"></i> السجلات الأسبوعية والسابقة</a>@endif
    <button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> طباعة</button>
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">الحلقة والمحفظ</div>
            <div class="card-body small">
                @if ($student->circle)
                    <dl class="row mb-0">
                        <dt class="col-5">الحلقة</dt><dd class="col-7">{{ $student->circle->name }}</dd>
                        <dt class="col-5">المحفظ</dt><dd class="col-7">{{ $student->circle->teacher?->name ?? '—' }}</dd>
                        <dt class="col-5">المستوى</dt><dd class="col-7">{{ $student->circle->level ?? '—' }}</dd>
                        <dt class="col-5">الأيام</dt><dd class="col-7">{{ $student->circle->daysLabel() }}</dd>
                        <dt class="col-5">الموعد</dt><dd class="col-7">{{ $student->circle->timeLabel() }}</dd>
                        <dt class="col-5">المكان</dt><dd class="col-7">{{ $student->circle->location ?? '—' }}</dd>
                        <dt class="col-5">رقم الطالب</dt><dd class="col-7">{{ $student->student_number }}</dd>
                    </dl>
                @else
                    <x-empty icon="bullseye" message="الطالب غير مسجل في حلقة حالياً." />
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-7">@include('shared._progress')</div>
</div>

<div class="card">
    <div class="card-header p-0 border-0">
        <ul class="nav nav-tabs px-3 pt-2">
            @foreach (['memorization' => 'الحفظ والمراجعة', 'attendance' => 'الحضور والغياب', 'assessments' => 'التقييمات', 'summary' => 'الملخص الشهري'] as $key => $label)
                <li class="nav-item"><a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('parent.children.show', [$student, 'tab' => $key]) }}">{{ $label }}</a></li>
            @endforeach
        </ul>
    </div>
    <div>
        @if ($tab === 'memorization')
            @include('shared._memorization-table')
        @elseif ($tab === 'attendance')
            @include('shared._attendance-table')
        @elseif ($tab === 'assessments')
            <div class="p-3">@include('shared._assessments')</div>
        @else
            <div class="p-3">
                <form method="GET" class="d-flex gap-2 align-items-center mb-3 no-print" data-no-loading>
                    <input type="hidden" name="tab" value="summary">
                    <label class="small">الشهر:</label>
                    <input type="month" name="month" value="{{ $month->format('Y-m') }}" max="{{ now()->format('Y-m') }}" class="form-control form-control-sm" style="max-width:180px" onchange="this.form.submit()">
                </form>
                <h6 class="fw-bold">ملخص شهر {{ $month->translatedFormat('F Y') }}</h6>
                <div class="row g-3">
                    <div class="col-6 col-lg-3"><x-stat-card icon="calendar-check" label="نسبة الحضور" :value="$summary['attendance_rate'] !== null ? $summary['attendance_rate'].'%' : '—'" /></div>
                    <div class="col-6 col-lg-3"><x-stat-card icon="x-circle" label="أيام الغياب" :value="$summary['attendance']['absent'] + $summary['attendance']['excused']" color="red" :hint="'منها بعذر: '.$summary['attendance']['excused']" /></div>
                    <div class="col-6 col-lg-3"><x-stat-card icon="book" label="صفحات حفظ جديد" :value="\App\Support\Pages::num($summary['new_pages'])" color="gold" /></div>
                    <div class="col-6 col-lg-3"><x-stat-card icon="arrow-repeat" label="صفحات مراجعة" :value="\App\Support\Pages::num($summary['revision_pages'])" color="blue" :hint="$summary['avg_score'] ? 'متوسط الدرجات: '.$summary['avg_score'] : null" /></div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
