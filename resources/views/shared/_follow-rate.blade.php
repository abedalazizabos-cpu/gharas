{{-- A parent's follow-up of shared daily records. Needs $rate = ['seen', 'total', 'percent']; optional $bar (show a progress bar). --}}
@php($pct = $rate['percent'] ?? null)
@php($color = \App\Services\ParentFollowService::color($pct))
@if ($pct === null)
    <span class="text-muted small" title="لا توجد سجلات مشتركة خلال آخر {{ \App\Services\ParentFollowService::WINDOW_DAYS }} يوماً">—</span>
@elseif (!empty($bar))
    <div class="d-flex justify-content-between small mb-1"><span>السجلات المطّلع عليها: {{ $rate['seen'] }} من {{ $rate['total'] }}</span><strong class="text-{{ $color }}-emphasis">{{ $pct }}%</strong></div>
    <div class="progress" style="height:8px" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-{{ $color }}" style="width: {{ max($pct, 2) }}%"></div></div>
@else
    <span class="badge bg-{{ $color }}-subtle text-{{ $color }}-emphasis" title="السجلات المطّلع عليها: {{ $rate['seen'] }} من {{ $rate['total'] }} خلال آخر {{ \App\Services\ParentFollowService::WINDOW_DAYS }} يوماً">{{ $pct }}%</span>
@endif
