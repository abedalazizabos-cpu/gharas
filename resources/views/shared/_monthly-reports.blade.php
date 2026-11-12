{{-- Monthly report PDFs of one student (newest first). --}}
@php($reportMonths = app(\App\Services\MonthlyReportService::class)->months($student))
<div class="dropdown">
    <button class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-file-earmark-pdf"></i> التقرير الشهري</button>
    <ul class="dropdown-menu dropdown-menu-end shadow">
        @foreach ($reportMonths as $m)
            <li><a class="dropdown-item" href="{{ route('monthly-reports.download', [$student, 'month' => $m]) }}" data-no-loading><i class="bi bi-download ms-1"></i> {{ \App\Models\HonorEntry::monthLabel($m) }}@if ($m === now()->format('Y-m')) <small class="text-muted">(حتى اليوم)</small>@endif</a></li>
        @endforeach
    </ul>
</div>
