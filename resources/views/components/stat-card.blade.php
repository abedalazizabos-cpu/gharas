@props(['icon', 'label', 'value', 'color' => 'green', 'hint' => null])
<div class="card stat-card stat-{{ $color }} h-100">
    <div class="card-body d-flex align-items-center gap-3">
        <div class="stat-icon"><i class="bi bi-{{ $icon }}"></i></div>
        <div>
            <div class="stat-value">{{ $value }}</div>
            <div class="stat-label">{{ $label }}</div>
            @if ($hint)<small class="text-muted">{{ $hint }}</small>@endif
        </div>
    </div>
</div>
