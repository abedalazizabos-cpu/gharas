{{-- Warnings issued to a teacher, newest first. --}}
<div class="card mb-3" id="warnings">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-exclamation-triangle text-warning"></i> الإنذارات والتنبيهات</span>
        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#warnModal"><i class="bi bi-plus-lg"></i> إنذار جديد</button>
    </div>
    <ul class="list-group list-group-flush small">
        @forelse ($warnings as $w)
            <li class="list-group-item">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <span class="badge text-bg-{{ $w->levelColor() }}">{{ $w->levelLabel() }}</span>
                        @if ($w->circle)<span class="text-muted">· {{ $w->circle->name }}</span>@endif
                        <div class="mt-1">{{ $w->reason }}</div>
                        <small class="text-muted">{{ $w->created_at->format('Y/m/d') }} · {{ $w->issuer?->name ?? '—' }} ·
                            @if ($w->acknowledged_at)<span class="text-success"><i class="bi bi-check2-all"></i> اطلع عليه {{ $w->acknowledged_at->diffForHumans() }}</span>
                            @else<span class="text-danger"><i class="bi bi-hourglass"></i> لم يطلع بعد</span>@endif
                        </small>
                    </div>
                    <x-delete-form :action="route('admin.warnings.destroy', $w)" label="إلغاء" icon="x-lg" title="إلغاء الإنذار" message="سيُحذف الإنذار من سجل المحفظ." />
                </div>
            </li>
        @empty
            <li class="list-group-item"><x-empty icon="shield-check" message="لا توجد إنذارات لهذا المحفظ." /></li>
        @endforelse
    </ul>
</div>
