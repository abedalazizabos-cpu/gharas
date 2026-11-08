{{-- Students who moved to the next juz without passing the previous juz test. Needs $qualityAlerts and $studentRoute. --}}
@if ($qualityAlerts->isNotEmpty())
<div class="card mb-3 border-danger">
    <div class="card-header d-flex justify-content-between align-items-center bg-danger-subtle">
        <span><i class="bi bi-exclamation-triangle-fill text-danger ms-1"></i> انتقلوا إلى جزء جديد دون اختبار الجودة ({{ $qualityAlerts->count() }})</span>
        @isset($moreUrl)<a href="{{ $moreUrl }}" class="btn btn-sm btn-outline-danger">إرسالهم للاختبار</a>@endisset
    </div>
    <ul class="list-group list-group-flush small">
        @foreach ($qualityAlerts->take(8) as $row)
            <li class="list-group-item d-flex flex-wrap justify-content-between gap-2">
                <span><a href="{{ route($studentRoute, $row['student']) }}" class="fw-semibold">{{ $row['student']->full_name }}</a> <span class="text-muted">· {{ $row['student']->circle?->name }}</span></span>
                <span>
                    @foreach ($row['missing_individual'] as $juz => $pending)
                        <span class="badge text-bg-{{ $pending ? 'warning' : 'danger' }}" title="{{ $pending ? 'عند '.$pending->examiner?->name : 'لم يُرسل للاختبار' }}">{{ \App\Support\Juz::label($juz) }}{{ $pending ? ' · عند '.$pending->examiner?->name : '' }}</span>
                    @endforeach
                    <span class="text-muted">· يحفظ الآن الجزء {{ $row['current_juz'] }}</span>
                </span>
            </li>
        @endforeach
        @if ($qualityAlerts->count() > 8)<li class="list-group-item text-muted">و{{ $qualityAlerts->count() - 8 }} طالباً آخرين…</li>@endif
    </ul>
</div>
@endif
