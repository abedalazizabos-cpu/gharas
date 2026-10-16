{{-- $start (Carbon week start), $url (closure: Carbon => url) --}}
@php($thisWeek = \App\Services\WeeklyArchiveService::weekStart())
<div class="d-flex flex-wrap align-items-center gap-2 week-nav">
    <a href="{{ $url($start->copy()->subWeek()) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-chevron-right"></i> الأسبوع السابق</a>
    <div class="fw-bold px-2"><i class="bi bi-calendar-week text-success"></i> {{ \App\Services\WeeklyArchiveService::label($start) }}
        @if ($start->equalTo($thisWeek))<span class="badge text-bg-success ms-1">هذا الأسبوع</span>@endif</div>
    @if ($start->lessThan($thisWeek))
        <a href="{{ $url($start->copy()->addWeek()) }}" class="btn btn-outline-secondary btn-sm">الأسبوع التالي <i class="bi bi-chevron-left"></i></a>
        <a href="{{ $url($thisWeek) }}" class="btn btn-link btn-sm">العودة لهذا الأسبوع</a>
    @endif
</div>
