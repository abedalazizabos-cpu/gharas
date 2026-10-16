@extends('layouts.app')
@section('title', 'سجلات '.$student->full_name.' الأسبوعية')
@section('content')
<x-page-header :title="'السجلات الأسبوعية: '.$student->full_name" :crumbs="[$student->full_name => route('parent.children.show', $student), 'السجلات الأسبوعية']">
    <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> طباعة</button>
</x-page-header>
<div class="row g-3">
    <div class="col-lg-9">
        <div class="card card-body mb-3 no-print">@include('shared._week-nav', ['url' => fn ($d) => route('parent.children.weeks', [$student, 'week' => $d->toDateString()])])</div>
        @include('shared._week-tables', ['w' => $w])
    </div>
    <div class="col-lg-3 no-print">
        <div class="card">
            <div class="card-header"><i class="bi bi-archive"></i> كل الأسابيع</div>
            <div class="list-group list-group-flush small">
                @foreach ($months as $month => $weeks)
                    <div class="list-group-item bg-light fw-bold">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->translatedFormat('F Y') }}</div>
                    @foreach ($weeks as $week)
                        <a href="{{ route('parent.children.weeks', [$student, 'week' => $week]) }}" class="list-group-item list-group-item-action {{ $week === $start->toDateString() ? 'active' : '' }}">
                            {{ \App\Services\WeeklyArchiveService::label(\Illuminate\Support\Carbon::parse($week)) }}
                        </a>
                    @endforeach
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
