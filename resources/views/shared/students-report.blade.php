@extends('layouts.app')
@section('title', $title)
@section('content')
@php($viewer = auth()->user())
<x-page-header :title="$title" :crumbs="['التقارير' => $viewer->isAdmin() ? route('admin.reports.index') : ($viewer->isVisitor() ? route('visitor.reports.center') : route('teacher.circles.index')), $title]">
    @if (! auth()->user()->isVisitor() || auth()->user()->hasPermission('visitor.download'))<a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="btn btn-outline-primary {{ $viewer->isAdmin() || $viewer->isVisitor() ? '' : 'd-none' }}"><i class="bi bi-filetype-pdf"></i> تنزيل PDF</a>@endif
    <button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> طباعة</button>
</x-page-header>
@include('shared._period-filter')
@if ($viewer->isVisitor())<div class="alert alert-info small"><i class="bi bi-eye"></i> عرض للاطلاع فقط. تظهر أسماء الطلاب مختصرة حفاظاً على خصوصيتهم.</div>@endif

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-stat-card icon="people" label="عدد الطلاب" :value="$rows->count()" /></div>
    <div class="col-6 col-lg-3"><x-stat-card icon="calendar-check" label="نسبة الحضور" :value="$totals['attendance_rate'] !== null ? $totals['attendance_rate'].'%' : '—'" color="blue" /></div>
    <div class="col-6 col-lg-3"><x-stat-card icon="book" label="صفحات حفظ جديد (مجتازة)" :value="\App\Support\Pages::num($totals['new_pages'])" color="gold" /></div>
    <div class="col-6 col-lg-3"><x-stat-card icon="arrow-repeat" label="صفحات مراجعة وتثبيت" :value="\App\Support\Pages::num($totals['revision_pages'])" /></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th>الطالب</th><th>حاضر</th><th>متأخر</th><th>غائب</th><th>بعذر</th><th>نسبة الحضور</th>
                <th>حفظ جديد (صفحة)</th><th>مراجعة (صفحة)</th><th>جلسات</th><th>متوسط الدرجة</th><th>متوسط التقييم</th>
            </tr></thead>
            <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td>
                        @if ($viewer->isAdmin())
                            <a href="{{ route('admin.reports.student', [$r['student'], 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">{{ $r['student']->full_name }}</a>
                        @elseif ($viewer->isVisitor())
                            {{ $r['student']->full_name }}
                        @else
                            <a href="{{ route('teacher.students.show', $r['student']) }}">{{ $r['student']->full_name }}</a>
                        @endif
                        @if ($r['student']->student_number)<br><small class="text-muted">{{ $r['student']->student_number }}</small>@endif
                    </td>
                    <td>{{ $r['attendance']['present'] }}</td><td>{{ $r['attendance']['late'] }}</td>
                    <td>{{ $r['attendance']['absent'] }}</td><td>{{ $r['attendance']['excused'] }}</td>
                    <td>{{ $r['attendance_rate'] !== null ? $r['attendance_rate'].'%' : '—' }}</td>
                    <td>{{ \App\Support\Pages::num($r['new_pages']) }}</td><td>{{ \App\Support\Pages::num($r['revision_pages']) }}</td><td>{{ $r['sessions'] }}</td>
                    <td>{{ $r['avg_score'] ?? '—' }}</td>
                    <td>{{ $r['avg_assessment'] !== null ? round($r['avg_assessment'], 1).'%' : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="11"><x-empty icon="people" message="لا يوجد طلاب في هذا التقرير." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
