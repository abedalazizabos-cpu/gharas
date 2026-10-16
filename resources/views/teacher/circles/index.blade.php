@extends('layouts.app')
@section('title', 'حلقاتي')
@section('content')
<x-page-header title="حلقاتي وجدولها" :crumbs="['الحلقات']" />
<div class="card mb-3">
    <div class="card-header">الجدول الأسبوعي</div>
    <div class="table-responsive">
        <table class="table table-bordered mb-0 text-center small">
            <thead><tr>@foreach (\App\Models\Circle::STUDY_DAYS as $num)<th>{{ \App\Models\Circle::DAYS[$num] }}</th>@endforeach</tr></thead>
            <tbody><tr>
                @foreach (\App\Models\Circle::STUDY_DAYS as $num)
                    <td class="{{ now()->dayOfWeek === $num ? 'bg-green-soft' : '' }}" style="min-width:110px">
                        @foreach ($circles->where('status', 'active')->filter(fn ($c) => $c->meetsOn($num))->sortBy(fn ($c) => $c->sortKey()) as $c)
                            <div class="border rounded p-1 mb-1 bg-white"><strong>{{ $c->name }}</strong><br>{{ $c->timeLabel() }}</div>
                        @endforeach
                    </td>
                @endforeach
            </tr></tbody>
        </table>
    </div>
</div>
<div class="row g-3">
    @forelse ($circles as $c)
        <div class="col-md-6 col-xl-4">
            <div class="card h-100"><div class="card-body">
                <div class="d-flex justify-content-between"><h5 class="fw-bold">{{ $c->name }}</h5><x-status-badge :status="$c->status" :label="$c->statusLabel()" /></div>
                <ul class="list-unstyled small">
                    <li><i class="bi bi-bar-chart-steps text-success ms-1"></i> {{ $c->level ?? '—' }} · {{ $c->age_group }}</li>
                    <li><i class="bi bi-calendar3 text-success ms-1"></i> {{ $c->daysLabel() }}</li>
                    <li><i class="bi bi-clock text-success ms-1"></i> {{ $c->timeLabel() }} · {{ $c->location }}</li>
                    <li><i class="bi bi-people text-success ms-1"></i> {{ $c->students_count }} / {{ $c->capacity }} طالب</li>
                </ul>
            </div>
            <div class="card-footer bg-transparent d-flex flex-wrap gap-2">
                <a href="{{ route('teacher.circles.show', $c) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-people"></i> الطلاب</a>
                @if (auth()->user()->hasPermission('teacher.attendance'))<a href="{{ route('teacher.attendance.create', $c) }}" class="btn btn-sm btn-success"><i class="bi bi-calendar-check"></i> الحضور</a>@endif
                <a href="{{ route('teacher.circles.report', $c) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-bar-chart"></i> التقرير</a>
                @if (auth()->user()->hasPermission('teacher.circle.edit'))<a href="{{ route('teacher.circles.edit', $c) }}" class="btn btn-sm btn-outline-secondary" title="تعديل الاسم والموعد"><i class="bi bi-pencil"></i></a>@endif
            </div></div>
        </div>
    @empty
        <div class="col-12"><div class="card"><x-empty icon="bullseye" message="لم تُسند إليك حلقات بعد. تواصل مع الإدارة." /></div></div>
    @endforelse
</div>
@endsection
