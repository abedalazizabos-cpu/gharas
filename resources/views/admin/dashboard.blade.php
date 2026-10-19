@extends('layouts.app')
@section('title', 'لوحة تحكم الإدارة')
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
                'data' => ['labels' => $memorizationTrend['labels'], 'datasets' => [['label' => 'صفحات', 'data' => $memorizationTrend['values'], 'backgroundColor' => '#c9a24a', 'borderRadius' => 6]]],
                'options' => ['plugins' => ['legend' => ['display' => false]]],
            ];
@endphp
<x-page-header title="لوحة تحكم الإدارة">
    @if (auth()->user()->hasPermission('students.manage'))<a href="{{ route('admin.students.create') }}" class="btn btn-primary"><i class="bi bi-person-plus"></i> طالب جديد</a>@endif
    @if (auth()->user()->hasPermission('reports.view'))<a href="{{ route('admin.reports.center') }}" class="btn btn-outline-primary"><i class="bi bi-bar-chart"></i> تقرير المركز</a>@endif
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><x-stat-card icon="people" label="إجمالي الطلاب" :value="$summary['students_total']" :hint="'النشطون: '.$summary['students_active']" /></div>
    <div class="col-6 col-xl-3"><x-stat-card icon="person-badge" label="المحفظون" :value="$summary['teachers']" color="gold" :hint="'أولياء الأمور: '.$summary['parents']" /></div>
    <div class="col-6 col-xl-3"><x-stat-card icon="bullseye" label="الحلقات النشطة" :value="$summary['circles_active']" color="blue" :hint="'من أصل '.$summary['circles_total']" /></div>
    <div class="col-6 col-xl-3"><x-stat-card icon="calendar-check" label="نسبة حضور اليوم" :value="$summary['attendance_rate_today'] !== null ? $summary['attendance_rate_today'].'%' : '—'" color="green" :hint="'حاضر '.$summary['present_today'].' · غائب '.$summary['absent_today']" /></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">نسبة الحضور خلال آخر 14 يوماً</div>
            <div class="card-body"><canvas height="110" data-chart='@json($attChart)'></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">مستويات الطلاب (آخر 30 يوماً)</div>
            <div class="card-body"><canvas height="220" data-chart='@json($gradeChart)'></canvas></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">التقدم في الحفظ (صفحات حفظ جديد مجتازة أسبوعياً)</div>
            <div class="card-body"><canvas height="150" data-chart='@json($memChart)'></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span>الحلقات النشطة والسعة</span>@if (auth()->user()->hasPermission('circles.manage'))<a href="{{ route('admin.circles.index') }}" class="small">إدارة الحلقات</a>@endif</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>الحلقة</th><th>المحفظ</th><th>الطلاب / السعة</th></tr></thead>
                    <tbody>
                    @forelse ($circles as $c)
                        <tr>
                            <td>@if (auth()->user()->hasPermission('circles.manage'))<a href="{{ route('admin.circles.show', $c) }}">{{ $c->name }}</a>@else{{ $c->name }}@endif</td>
                            <td>{{ $c->teacher?->name ?? '—' }}</td>
                            <td style="min-width:140px">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1"><div class="progress-bar {{ $c->students_count >= $c->capacity ? 'bg-danger' : '' }}" style="width: {{ min(100, $c->students_count / max(1, $c->capacity) * 100) }}%"></div></div>
                                    <small>{{ $c->students_count }}/{{ $c->capacity }}</small>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><x-empty message="لا توجد حلقات نشطة." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span>أحدث الأنشطة</span>@if (auth()->user()->hasPermission('audit.view'))<a href="{{ route('admin.audit.index') }}" class="small">سجل العمليات</a>@endif</div>
            <ul class="list-group list-group-flush">
                @forelse ($activity as $log)
                    <li class="list-group-item d-flex gap-2">
                        <i class="bi bi-dot fs-4 text-success"></i>
                        <div><div>{{ $log->description }}</div><small class="text-muted">{{ $log->user?->name ?? 'النظام' }} · {{ $log->created_at->diffForHumans() }}</small></div>
                    </li>
                @empty
                    <li class="list-group-item"><x-empty icon="activity" message="لا توجد أنشطة بعد." /></li>
                @endforelse
            </ul>
        </div>
    </div>
    <div class="col-lg-5">@include('shared._announcements-card', ['moreRoute' => 'admin.announcements.index'])</div>
</div>
@endsection
@push('pre-scripts')<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>@endpush
