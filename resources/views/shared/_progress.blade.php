{{-- $progress (ProgressService::surahProgress), $quranPercent --}}
<div class="card h-100">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-graph-up-arrow ms-1 text-success"></i> التقدم في الحفظ</span>
        <span class="badge bg-green-soft text-success-emphasis">{{ $quranPercent }}% من القرآن</span>
    </div>
    <div class="card-body">
        <div class="progress mb-3" role="progressbar" aria-valuenow="{{ $quranPercent }}" aria-valuemin="0" aria-valuemax="100" style="height:.9rem">
            <div class="progress-bar" style="width: {{ max($quranPercent, 0.5) }}%"></div>
        </div>
        <p class="small text-muted mb-3">تُحتسب الصفحات محفوظة فقط عند تسجيل «حفظ جديد» بدرجة {{ \App\Models\Setting::get('pass_score') }} فأكثر، والنسبة من 604 صفحات (المصحف كاملاً).</p>
        @forelse ($progress as $row)
            <div class="mb-2">
                <div class="d-flex justify-content-between small">
                    <span class="fw-semibold">{{ $row['surah']->name }}</span>
                    <span class="text-muted">@if ($row['percent'] >= 100) مكتملة @else {{ round($row['percent']) }}% من السورة @endif</span>
                </div>
                <div class="progress"><div class="progress-bar" style="width: {{ $row['percent'] }}%"></div></div>
            </div>
        @empty
            <x-empty icon="book" message="لم يُسجَّل أي حفظ بعد." />
        @endforelse
    </div>
</div>
