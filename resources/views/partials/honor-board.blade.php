{{-- لوحة الشرف on the login screen: one student from each circle, chosen for last month. --}}
@if ($honor->isNotEmpty())
<section class="card honor-board shadow-lg" aria-label="لوحة الشرف">
    <div class="honor-head">
        <i class="bi bi-trophy-fill"></i>
        <div>
            <div class="honor-title">لوحة الشرف</div>
            <div class="honor-sub">المتميزون في حلقاتهم لشهر {{ \App\Models\HonorEntry::monthLabel($honorMonth) }}</div>
        </div>
    </div>
    <div class="honor-list">
        @foreach ($honor as $e)
            <div class="honor-item">
                <span class="honor-medal"><i class="bi bi-star-fill"></i></span>
                <span class="min-w-0">
                    <span class="honor-name d-block text-truncate">{{ $e->publicName() }}</span>
                    <span class="honor-circle d-block text-truncate">{{ $e->circle?->name }}</span>
                </span>
            </div>
        @endforeach
    </div>
    <div class="honor-foot">بارك الله فيهم وجعلهم من أهل القرآن</div>
</section>
@endif
