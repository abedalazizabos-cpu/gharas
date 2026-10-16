@extends('layouts.app')
@section('title', 'سير الحلقات')
@section('content')
@php
    $attChart = [
        'type' => 'line',
        'data' => ['labels' => $attendanceTrend['labels'], 'datasets' => [['label' => 'نسبة الحضور %', 'data' => $attendanceTrend['values'], 'borderColor' => '#1b6b50', 'backgroundColor' => 'rgba(47,158,118,.15)', 'fill' => true, 'tension' => .35, 'spanGaps' => true]]],
        'options' => ['plugins' => ['legend' => ['display' => false]], 'scales' => ['y' => ['min' => 0, 'max' => 100]]],
    ];
    $gradeChart = [
        'type' => 'doughnut',
        'data' => ['labels' => array_column($gradeDistribution, 'label'), 'datasets' => [['data' => array_column($gradeDistribution, 'value'), 'backgroundColor' => ['#1b6b50', '#2f9e76', '#c9a24a', '#e0b96a', '#c0504d']]]],
        'options' => ['plugins' => ['legend' => ['position' => 'bottom']]],
    ];
    $memChart = [
        'type' => 'bar',
        'data' => ['labels' => $memorizationTrend['labels'], 'datasets' => [['label' => 'آيات', 'data' => $memorizationTrend['values'], 'backgroundColor' => '#c9a24a', 'borderRadius' => 6]]],
        'options' => ['plugins' => ['legend' => ['display' => false]]],
    ];
@endphp
<x-page-header title="سير الحلقات في المركز">
    @if (auth()->user()->hasPermission('visitor.reports'))<a href="{{ route('visitor.reports.center') }}" class="btn btn-primary"><i class="bi bi-bar-chart"></i> التقرير الشامل</a>@endif
</x-page-header>
<div class="alert alert-info small"><i class="bi bi-eye"></i> مرحباً بكم. هذا الحساب للاطلاع فقط على سير الحلقات والتقارير، ولا يملك أي صلاحية للتعديل.</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><x-stat-card icon="people" label="الطلاب النشطون" :value="$summary['students_active']" /></div>
    <div class="col-6 col-xl-3"><x-stat-card icon="person-badge" label="المحفظون" :value="$summary['teachers']" color="gold" /></div>
    <div class="col-6 col-xl-3"><x-stat-card icon="bullseye" label="الحلقات النشطة" :value="$summary['circles_active']" color="blue" /></div>
    <div class="col-6 col-xl-3"><x-stat-card icon="calendar-check" label="نسبة حضور اليوم" :value="$summary['attendance_rate_today'] !== null ? $summary['attendance_rate_today'].'%' : '—'" color="green" :hint="'حاضر '.$summary['present_today'].' · غائب '.$summary['absent_today']" /></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8"><div class="card h-100"><div class="card-header">نسبة الحضور خلال آخر 14 يوماً</div><div class="card-body"><canvas height="110" data-chart='@json($attChart)'></canvas></div></div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-header">مستويات الطلاب (آخر 30 يوماً)</div><div class="card-body"><canvas height="220" data-chart='@json($gradeChart)'></canvas></div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">الحلقات ({{ $circles->count() }})</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>الحلقة</th><th>المحفظ</th><th>المستوى</th><th>الطلاب</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($circles as $c)
                        <tr>
                            <td>{{ $c->name }}</td>
                            <td>{{ $c->teacher?->name ?? '—' }}</td>
                            <td>{{ $c->level }}</td>
                            <td>{{ $c->students_count }}/{{ $c->capacity }}</td>
                            <td>@if (auth()->user()->hasPermission('visitor.reports'))<a href="{{ route('visitor.reports.circle', $c) }}" class="btn btn-sm btn-outline-secondary">التقرير</a>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3"><div class="card-header">التقدم في الحفظ (آيات جديدة أسبوعياً)</div><div class="card-body"><canvas height="150" data-chart='@json($memChart)'></canvas></div></div>
        <div class="card">
            <div class="card-header">آخر الجلسات المنعقدة</div>
            <ul class="list-group list-group-flush small">
                @forelse ($recentSessions as $s)
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ $s->circle?->name }} <span class="text-muted">· {{ $s->session_date->translatedFormat('l Y/m/d') }}</span></span>
                        <span>حضور {{ $s->present_count }}/{{ $s->attendance_count }}</span>
                    </li>
                @empty
                    <li class="list-group-item"><x-empty icon="calendar" message="لا توجد جلسات بعد." /></li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
@push('pre-scripts')<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>@endpush
