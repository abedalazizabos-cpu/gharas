@extends('layouts.app')
@section('title', 'لوحة المحفظ')
@section('content')
@php
    $attChart = ['type' => 'line', 'data' => ['labels' => $attendanceTrend['labels'], 'datasets' => [['label' => '%', 'data' => $attendanceTrend['values'], 'borderColor' => '#1b6b50', 'backgroundColor' => 'rgba(47,158,118,.15)', 'fill' => true, 'tension' => .35, 'spanGaps' => true]]], 'options' => ['plugins' => ['legend' => ['display' => false]], 'scales' => ['y' => ['min' => 0, 'max' => 100]]]];
    $memChart = ['type' => 'bar', 'data' => ['labels' => $memorizationTrend['labels'], 'datasets' => [['label' => 'صفحات', 'data' => $memorizationTrend['values'], 'backgroundColor' => '#c9a24a', 'borderRadius' => 6]]], 'options' => ['plugins' => ['legend' => ['display' => false]]]];
@endphp
<x-page-header :title="'مرحباً، '.auth()->user()->name">
    @if (auth()->user()->hasPermission('teacher.memorization'))<a href="{{ route('teacher.memorization.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> تسجيل تسميع</a>@endif
    @if (auth()->user()->hasPermission('teacher.assessments'))<a href="{{ route('teacher.assessments.create') }}" class="btn btn-outline-primary"><i class="bi bi-award"></i> تقييم طالب</a>@endif
</x-page-header>

@foreach ($warnings as $w)
    <div class="alert alert-light border warning-banner d-flex flex-wrap align-items-start gap-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill text-danger fs-4"></i>
        <div class="flex-grow-1">
            <div><span class="badge text-bg-{{ $w->levelColor() }}">{{ $w->levelLabel() }}</span> <strong>من إدارة المركز</strong>
                <small class="text-muted">· {{ $w->created_at->format('Y/m/d') }}@if ($w->circle) · {{ $w->circle->name }}@endif</small></div>
            <div class="mt-1">{{ $w->reason }}</div>
        </div>
        <form method="POST" action="{{ route('teacher.warnings.acknowledge', $w) }}">@csrf<button class="btn btn-sm btn-outline-success"><i class="bi bi-check2"></i> اطلعت</button></form>
    </div>
@endforeach

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><x-stat-card icon="bullseye" label="حلقاتي" :value="$circles->count()" /></div>
    <div class="col-6 col-xl-3"><x-stat-card icon="people" label="طلابي النشطون" :value="$studentsCount" color="blue" /></div>
    <div class="col-6 col-xl-3"><x-stat-card icon="calendar-check" label="نسبة الحضور (7 أيام)" :value="$weekRate !== null ? $weekRate.'%' : '—'" color="gold" /></div>
    <div class="col-6 col-xl-3"><x-stat-card icon="journal-check" label="سجلات التسميع (7 أيام)" :value="$recordsThisWeek" :hint="$unreadMessages ? $unreadMessages.' رسالة غير مقروءة' : null" /></div>
</div>

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-calendar-day text-success ms-1"></i> حلقات اليوم ({{ now()->translatedFormat('l d F') }})</div>
    <div class="card-body">
        <div class="row g-3">
            @forelse ($todayCircles as $c)
                <div class="col-md-6 col-xl-4">
                    <div class="border rounded-3 p-3 h-100 {{ $takenToday->contains($c->id) ? 'bg-green-soft' : '' }}">
                        <div class="d-flex justify-content-between"><strong>{{ $c->name }}</strong><small>{{ $c->timeLabel() }}</small></div>
                        <small class="text-muted">{{ $c->students_count }} طالب · {{ $c->location }}</small>
                        <div class="mt-2 d-flex gap-2">
                            @if (auth()->user()->hasPermission('teacher.attendance'))<a href="{{ route('teacher.attendance.create', $c) }}" class="btn btn-sm {{ $takenToday->contains($c->id) ? 'btn-outline-success' : 'btn-success' }}">
                                <i class="bi bi-{{ $takenToday->contains($c->id) ? 'check2-circle' : 'calendar-check' }}"></i> {{ $takenToday->contains($c->id) ? 'تم التحضير (تعديل)' : 'تسجيل الحضور' }}
                            </a>@endif
                            <a href="{{ route('teacher.circles.show', $c) }}" class="btn btn-sm btn-outline-secondary">الطلاب</a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12"><x-empty icon="calendar" message="لا توجد حلقات مجدولة اليوم." /></div>
            @endforelse
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-header">نسبة الحضور في حلقاتي (14 يوماً)</div>
            <div class="card-body"><canvas height="150" data-chart='@json($attChart)'></canvas></div></div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-header">صفحات الحفظ الجديد أسبوعياً</div>
            <div class="card-body"><canvas height="150" data-chart='@json($memChart)'></canvas></div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span>سجلات هذا الأسبوع <small class="text-muted">({{ \App\Services\WeeklyArchiveService::label(\App\Services\WeeklyArchiveService::weekStart()) }})</small></span>@if (auth()->user()->hasPermission('teacher.archive'))<a href="{{ route('teacher.weeks.show') }}" class="small">الأسابيع السابقة والأرشيف</a>@endif</div>
            <ul class="list-group list-group-flush small" style="max-height:420px;overflow:auto">
                @forelse ($recentRecords as $r)
                    <li class="list-group-item d-flex justify-content-between">
                        <span><a href="{{ route('teacher.students.show', $r->student) }}">{{ $r->student->full_name }}</a> · {{ $r->typeLabel() }} · {{ $r->rangeLabel() }}</span>
                        <span class="text-muted">{{ $r->score }} · {{ $r->recorded_on->format('m/d') }}</span>
                    </li>
                @empty
                    <li class="list-group-item"><x-empty icon="book" message="لم تسجل أي تسميع هذا الأسبوع." /></li>
                @endforelse
            </ul>
        </div>
    </div>
    <div class="col-lg-5">@include('shared._announcements-card', ['moreRoute' => 'teacher.announcements'])</div>
</div>
@endsection
@push('pre-scripts')<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>@endpush
