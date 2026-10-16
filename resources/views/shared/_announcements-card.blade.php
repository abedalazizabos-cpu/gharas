<div class="card h-100">
    <div class="card-header d-flex justify-content-between"><span><i class="bi bi-megaphone text-gold ms-1"></i> أحدث الإعلانات</span>
        @isset($moreRoute)<a href="{{ route($moreRoute) }}" class="small">عرض الكل</a>@endisset</div>
    <div class="list-group list-group-flush">
        @forelse ($announcements as $a)
            <div class="list-group-item">
                <div class="fw-semibold">{{ $a->title }}</div>
                <small class="text-muted">{{ \Illuminate\Support\Str::limit($a->body, 110) }}</small>
            </div>
        @empty
            <x-empty icon="megaphone" message="لا توجد إعلانات." />
        @endforelse
    </div>
</div>
