@extends('layouts.app')
@section('title', 'التقرير الإجمالي للمركز')
@section('content')
@php($area = auth()->user()->isVisitor() ? 'visitor' : 'admin')
<x-page-header title="التقرير الإجمالي للمركز" :crumbs="$area === 'admin' ? ['التقارير' => route('admin.reports.index'), 'تقرير المركز'] : ['الرئيسية' => route('visitor.dashboard'), 'تقرير المركز']">
    @if (! auth()->user()->isVisitor() || auth()->user()->hasPermission('visitor.download'))<a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="btn btn-outline-primary"><i class="bi bi-filetype-pdf"></i> تنزيل PDF</a>@endif
    <button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> طباعة</button>
</x-page-header>
@include('shared._period-filter')
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-stat-card icon="people" label="الطلاب النشطون" :value="$all['students']" /></div>
    <div class="col-6 col-lg-3"><x-stat-card icon="calendar-check" label="نسبة الحضور" :value="$all['attendance_rate'] !== null ? $all['attendance_rate'].'%' : '—'" color="blue" /></div>
    <div class="col-6 col-lg-3"><x-stat-card icon="book" label="صفحات حفظ جديد (مجتازة)" :value="\App\Support\Pages::num($all['new_pages'])" color="gold" /></div>
    <div class="col-6 col-lg-3"><x-stat-card icon="journal-check" label="جلسات التسميع" :value="$all['sessions']" /></div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>الحلقة</th><th>المحفظ</th><th>الطلاب</th><th>حاضر</th><th>غائب</th><th>نسبة الحضور</th><th>حفظ جديد (صفحة)</th><th>مراجعة (صفحة)</th><th>متوسط الدرجة</th><th class="no-print"></th></tr></thead>
            <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td>{{ $r['circle']->name }} @if ($r['circle']->status !== 'active')<span class="badge bg-secondary">متوقفة</span>@endif</td>
                    <td>@if ($r['circle']->teacher)<a href="{{ route($area.'.reports.teacher', [$r['circle']->teacher, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">{{ $r['circle']->teacher->name }}</a>@else — @endif</td>
                    <td>{{ $r['students'] }}</td>
                    <td>{{ $r['attendance']['present'] + $r['attendance']['late'] }}</td>
                    <td>{{ $r['attendance']['absent'] + $r['attendance']['excused'] }}</td>
                    <td>{{ $r['attendance_rate'] !== null ? $r['attendance_rate'].'%' : '—' }}</td>
                    <td>{{ \App\Support\Pages::num($r['new_pages']) }}</td>
                    <td>{{ \App\Support\Pages::num($r['revision_pages']) }}</td>
                    <td>{{ $r['avg_score'] ? round($r['avg_score'], 1) : '—' }}</td>
                    <td class="no-print"><a href="{{ route($area.'.reports.circle', [$r['circle'], 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="btn btn-sm btn-outline-secondary">التفاصيل</a></td>
                </tr>
            @empty
                <tr><td colspan="10"><x-empty icon="bullseye" message="لا توجد حلقات." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center mt-4 mb-2 gap-2">
    <h5 class="mb-0"><i class="bi bi-journal-bookmark text-success"></i> دورات قسم السنة في الفترة</h5>
    <div class="small d-flex flex-wrap gap-2">
        @foreach (\App\Models\CertificateTemplate::KINDS as $k => $label)
            <span class="badge text-bg-light border fw-normal"><i class="bi bi-{{ \App\Models\Certificate::ICONS[$k] }} text-success"></i> {{ $label }}: <strong>{{ $certificates[$k] ?? 0 }}</strong></span>
        @endforeach
    </div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>الدورة</th><th>الشيخ</th><th>الحالة</th><th>الطلاب</th><th>نسبة الحضور</th><th>قُيّم</th><th>نسبة النجاح</th><th>متوسط النتيجة</th><th>شهادات</th></tr></thead>
            <tbody>
            @forelse ($courses as $r)
                @php($c = $r['course'])
                <tr>
                    <td>@if ($area === 'admin')<a href="{{ route('admin.sunnah.show', $c) }}">{{ $c->title }}</a>@else{{ $c->title }}@endif <small class="text-muted d-block">{{ $c->categoryLabel() }} · {{ $c->periodLabel() }}</small></td>
                    <td>{{ $c->teacher?->name ?? '—' }}</td>
                    <td><span class="badge text-bg-{{ $c->statusColor() }}">{{ $c->statusLabel() }}</span></td>
                    <td>{{ $r['summary']['students'] }}</td>
                    <td>{{ $r['attendance_rate'] !== null ? $r['attendance_rate'].'%' : '—' }}</td>
                    <td>{{ $r['summary']['graded'] }}</td>
                    <td>{{ $r['summary']['pass_rate'] !== null ? $r['summary']['pass_rate'].'%' : '—' }}</td>
                    <td>{{ $r['summary']['average'] !== null ? $r['summary']['average'].'%' : '—' }}</td>
                    <td>{{ $r['certificates'] }}</td>
                </tr>
            @empty
                <tr><td colspan="9"><x-empty icon="journal-bookmark" message="لا توجد دورات في هذه الفترة." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
