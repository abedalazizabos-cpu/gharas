<nav class="nav flex-column py-2">
    @include('partials.nav-link', ['route' => 'visitor.dashboard', 'icon' => 'speedometer2', 'label' => 'سير الحلقات'])
    @if ($authUser->hasPermission('visitor.reports'))@include('partials.nav-link', ['route' => 'visitor.reports.center', 'active' => 'visitor.reports.*', 'icon' => 'bar-chart-line', 'label' => 'التقارير الشاملة'])@endif
    @if ($authUser->hasPermission('visitor.archive'))@include('partials.nav-link', ['route' => 'visitor.weeks.show', 'active' => 'visitor.weeks.*|visitor.archive.*', 'icon' => 'calendar-week', 'label' => 'السجل الأسبوعي والأرشيف'])@endif
    <div class="nav-section">صلاحية العرض</div>
    <div class="px-3 small" style="opacity:.75"><i class="bi bi-eye"></i> اطلاع فقط دون تعديل</div>
</nav>
