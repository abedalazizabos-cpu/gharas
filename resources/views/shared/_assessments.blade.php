{{-- $assessments (paginator) --}}
@forelse ($assessments as $as)
    <div class="border rounded-3 p-3 mb-2">
        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
            <div><strong>{{ $as->assessed_on->format('Y/m/d') }}</strong> <small class="text-muted">· {{ $as->teacher?->name }}</small></div>
            <div><span class="badge bg-success fs-6">{{ rtrim(rtrim(number_format($as->percentage, 1), '0'), '.') }}%</span> <small class="text-muted">{{ $as->gradeLabel() }}</small></div>
        </div>
        <div class="row g-2 small">
            @foreach ($as->scores as $score)
                <div class="col-sm-6 col-lg-3">
                    <div class="bg-light rounded p-2 h-100">{{ $score->criterion?->name }}<br><strong>{{ $score->score }}</strong> / {{ $score->max_score }}</div>
                </div>
            @endforeach
        </div>
        @if ($as->notes)<p class="small mb-0 mt-2"><i class="bi bi-chat-left-quote text-success"></i> {{ $as->notes }}</p>@endif
    </div>
@empty
    <x-empty icon="award" message="لا توجد تقييمات." />
@endforelse
<div>{{ $assessments->links() }}</div>
