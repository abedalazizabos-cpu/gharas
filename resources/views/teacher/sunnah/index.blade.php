@extends('layouts.app')
@section('title', 'دوراتي في قسم السنة')
@section('content')
<x-page-header title="دوراتي في قسم السنة" :crumbs="['دوراتي في قسم السنة']" />
<div class="row g-3">
    @forelse ($courses as $c)
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="feature-icon mb-0"><i class="bi bi-{{ $c->categoryIcon() }}"></i></span>
                        <span class="badge text-bg-{{ $c->statusColor() }}">{{ $c->statusLabel() }}</span>
                    </div>
                    <h5 class="fw-bold mb-1">{{ $c->title }}</h5>
                    <div class="small text-muted mb-2">{{ $c->categoryLabel() }}</div>
                    <div class="small"><i class="bi bi-calendar3 text-success"></i> {{ $c->periodLabel() }}</div>
                    @if ($c->schedule)<div class="small"><i class="bi bi-clock text-success"></i> {{ $c->schedule }}</div>@endif
                    <div class="small mt-2">{{ $c->enrollments_count }} طالباً · قُيّم {{ $c->graded_count }}</div>
                </div>
                <div class="card-footer bg-transparent d-flex gap-2">
                    <a href="{{ route('teacher.sunnah.show', $c) }}" class="btn btn-sm btn-outline-primary flex-grow-1"><i class="bi bi-eye"></i> الطلاب والنتائج</a>
                    <a href="{{ route('teacher.sunnah.grade', $c) }}" class="btn btn-sm btn-primary"><i class="bi bi-pencil-square"></i> رصد الدرجات</a>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12"><div class="card"><x-empty icon="journal-bookmark" message="لم تُكلَّف بعد بأي دورة في قسم السنة." /></div></div>
    @endforelse
</div>
@endsection
