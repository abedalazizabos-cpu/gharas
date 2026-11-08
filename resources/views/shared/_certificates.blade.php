{{-- The student's issued certificates with a PDF download for each. Expects $certificates. --}}
@if ($certificates->isNotEmpty())
<div class="card mb-3" id="certificates">
    <div class="card-header"><i class="bi bi-award-fill text-warning"></i> الشهادات ({{ $certificates->count() }})</div>
    <div class="list-group list-group-flush">
        @foreach ($certificates as $c)
            <div class="list-group-item d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <div>
                    <i class="bi bi-{{ $c->icon() }} text-success ms-1"></i>
                    <span class="fw-semibold">{{ $c->content['title'] ?? $c->kindLabel() }}</span>
                    <span class="text-muted">· {{ $c->subject() }}</span>
                    <small class="text-muted d-block">رقم {{ $c->number }} · صدرت {{ $c->issued_on?->format('Y/m/d') }}</small>
                </div>
                <a href="{{ route('certificates.download', $c) }}" class="btn btn-sm btn-success" data-no-loading><i class="bi bi-file-earmark-pdf"></i> تحميل PDF</a>
            </div>
        @endforeach
    </div>
</div>
@endif
