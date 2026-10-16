@extends('layouts.app')
@section('title', 'السجل الأسبوعي')
@section('content')
<x-page-header title="السجل الأسبوعي" :crumbs="['السجل الأسبوعي']">
    <a href="{{ route($prefix.'.archive.index') }}" class="btn btn-outline-primary"><i class="bi bi-archive"></i> أرشيف الأسابيع السابقة</a>
    @if ($archive) @if ($prefix !== 'visitor' || auth()->user()->hasPermission('visitor.download'))<a href="{{ route($prefix.'.archive.download', $archive) }}" class="btn btn-outline-secondary"><i class="bi bi-download"></i> تنزيل ملف الأسبوع</a>@endif @endif
    <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> طباعة</button>
</x-page-header>
@if (! $circle)
    <div class="card"><x-empty icon="bullseye" message="لا توجد حلقات." /></div>
@else
    <form method="GET" class="filter-bar row g-2 align-items-center no-print" data-no-loading>
        <input type="hidden" name="week" value="{{ $start->toDateString() }}">
        <div class="col-md-5"><select name="circle" class="form-select" onchange="this.form.submit()" aria-label="الحلقة">
            @foreach ($circles as $c)<option value="{{ $c->id }}" @selected($c->id === $circle->id)>{{ $c->name }} · {{ $c->teacher?->name ?? 'بدون محفظ' }}</option>@endforeach
        </select></div>
        <div class="col-md-7">@include('shared._week-nav', ['url' => fn ($d) => route($prefix.'.weeks.show', ['circle' => $circle->id, 'week' => $d->toDateString()])])</div>
    </form>
    <p class="small text-muted">يبدأ الأسبوع يوم الأحد. عند انتهاء كل أسبوع يُحفظ ملفه في النظام تلقائياً، ويمكن الرجوع إليه من «أرشيف الأسابيع السابقة».</p>
    @include('shared._week-tables', ['w' => $w, 'showStudent' => true, 'showShared' => $prefix !== 'visitor'])
@endif
@endsection
