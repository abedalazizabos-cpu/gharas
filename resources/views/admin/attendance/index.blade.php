@extends('layouts.app')
@section('title', 'الحضور والغياب')
@section('content')
<x-page-header title="تقارير الحضور والغياب" :crumbs="['الحضور والغياب']">
    <button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> طباعة</button>
</x-page-header>
<form method="GET" class="filter-bar row g-2 align-items-end" data-no-loading>
    <div class="col-6 col-md-2"><label class="form-label small">الفترة</label>
        <select name="period" class="form-select"><option value="day" @selected($period === 'day')>يومي</option><option value="week" @selected($period === 'week')>أسبوعي (7 أيام)</option><option value="month" @selected($period === 'month')>شهري</option></select></div>
    <div class="col-6 col-md-3"><label class="form-label small">التاريخ</label><input type="date" name="date" value="{{ $anchor->toDateString() }}" class="form-control"></div>
    <div class="col-6 col-md-3"><label class="form-label small">الحلقة</label><select name="circle" class="form-select"><option value="">كل الحلقات</option>@foreach ($circles as $c)<option value="{{ $c->id }}" @selected(request('circle') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label small">الحالة</label><select name="status" class="form-select"><option value="">الكل</option>@foreach (\App\Models\AttendanceRecord::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-funnel"></i> عرض</button></div>
</form>
<p class="text-muted small">الفترة: {{ $from->format('Y/m/d') }} @if ($from->ne($to)) - {{ $to->format('Y/m/d') }} @endif</p>
<div class="row g-3 mb-3">
    <div class="col-6 col-lg"><x-stat-card icon="percent" label="نسبة الحضور" :value="$rate !== null ? $rate.'%' : '—'" /></div>
    <div class="col-6 col-lg"><x-stat-card icon="check-circle" label="حاضر" :value="$counts['present']" /></div>
    <div class="col-6 col-lg"><x-stat-card icon="clock-history" label="متأخر" :value="$counts['late']" color="gold" /></div>
    <div class="col-6 col-lg"><x-stat-card icon="x-circle" label="غائب" :value="$counts['absent']" color="red" /></div>
    <div class="col-6 col-lg"><x-stat-card icon="info-circle" label="غائب بعذر" :value="$counts['excused']" color="blue" /></div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>التاريخ</th><th>الطالب</th><th>الحلقة</th><th>الحالة</th><th>ملاحظات</th><th>سجّله</th></tr></thead>
            <tbody>
            @forelse ($records as $r)
                <tr>
                    <td><a href="{{ route('admin.attendance.create', [$r->session->circle, 'date' => $r->session->session_date->toDateString()]) }}">{{ $r->session->session_date->format('Y/m/d') }}</a></td>
                    <td><a href="{{ route('admin.students.show', $r->student) }}">{{ $r->student->full_name }}</a></td>
                    <td>{{ $r->session->circle->name }}</td>
                    <td><span class="badge bg-{{ $r->badge() }}">{{ $r->statusLabel() }}</span></td>
                    <td class="small">{{ $r->notes ?: '—' }}</td>
                    <td class="small text-muted">{{ $r->recorder?->nameFor(auth()->user()) }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty icon="calendar-x" message="لا توجد سجلات حضور في هذه الفترة." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $records->links() }}</div>
@endsection
