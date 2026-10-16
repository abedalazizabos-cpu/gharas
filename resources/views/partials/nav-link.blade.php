<a href="{{ route($route, $params ?? []) }}" class="nav-link {{ request()->routeIs(...explode('|', $active ?? $route)) ? 'active' : '' }}">
    <i class="bi bi-{{ $icon }}"></i><span>{{ $label }}</span>
    @if (!empty($badge))<span class="badge bg-warning text-dark rounded-pill">{{ $badge }}</span>@endif
</a>
