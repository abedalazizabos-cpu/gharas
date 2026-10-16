@php($u = auth()->user())
@php($newContacts = $u->hasPermission('contact.manage') ? \App\Models\ContactMessage::whereNull('read_at')->count() : 0)
<nav class="nav flex-column py-2">
    @include('partials.nav-link', ['route' => 'admin.dashboard', 'icon' => 'speedometer2', 'label' => 'لوحة التحكم'])
    <div class="nav-section">إدارة المركز</div>
    @if ($u->hasPermission('students.manage'))@include('partials.nav-link', ['route' => 'admin.students.index', 'active' => 'admin.students.*', 'icon' => 'people', 'label' => 'الطلاب'])@endif
    @if ($u->hasPermission('circles.manage'))@include('partials.nav-link', ['route' => 'admin.circles.index', 'active' => 'admin.circles.*', 'icon' => 'bullseye', 'label' => 'الحلقات'])@endif
    @if ($u->hasPermission('teachers.manage'))@include('partials.nav-link', ['route' => 'admin.teachers.index', 'active' => 'admin.teachers.*', 'icon' => 'person-badge', 'label' => 'المشايخ (المحفظون)'])@endif
    @if ($u->hasPermission('parents.manage'))@include('partials.nav-link', ['route' => 'admin.parents.index', 'active' => 'admin.parents.*', 'icon' => 'house-heart', 'label' => 'أولياء الأمور'])@endif
    <div class="nav-section">المتابعة التعليمية</div>
    @if ($u->hasPermission('archive.view'))@include('partials.nav-link', ['route' => 'admin.weeks.show', 'active' => 'admin.weeks.*|admin.archive.*', 'icon' => 'calendar-week', 'label' => 'السجل الأسبوعي والأرشيف'])@endif
    @if ($u->hasPermission('attendance.manage'))@include('partials.nav-link', ['route' => 'admin.attendance.index', 'active' => 'admin.attendance.*', 'icon' => 'calendar-check', 'label' => 'الحضور والغياب'])@endif
    @if ($u->hasPermission('memorization.manage'))@include('partials.nav-link', ['route' => 'admin.memorization.index', 'active' => 'admin.memorization.*', 'icon' => 'book', 'label' => 'الحفظ والمراجعة'])@endif
    @if ($u->hasPermission('assessments.manage'))@include('partials.nav-link', ['route' => 'admin.assessments.index', 'active' => 'admin.assessments.*', 'icon' => 'award', 'label' => 'التقييمات'])@endif
    @if ($u->hasPermission('reports.view'))@include('partials.nav-link', ['route' => 'admin.reports.index', 'active' => 'admin.reports.*', 'icon' => 'bar-chart-line', 'label' => 'التقارير'])@endif
    <div class="nav-section">التواصل</div>
    @if ($u->hasPermission('announcements.manage'))@include('partials.nav-link', ['route' => 'admin.announcements.index', 'active' => 'admin.announcements.*', 'icon' => 'megaphone', 'label' => 'الإعلانات'])@endif
    @if ($u->hasPermission('contact.manage'))@include('partials.nav-link', ['route' => 'admin.contact.index', 'icon' => 'envelope', 'label' => 'رسائل التواصل', 'badge' => $newContacts ?: null])@endif
    <div class="nav-section">النظام</div>
    @if ($u->hasPermission('admins.manage'))@include('partials.nav-link', ['route' => 'admin.admins.index', 'active' => 'admin.admins.*', 'icon' => 'shield-lock', 'label' => 'حسابات المديرين'])@endif
    @if ($u->hasPermission('visitors.manage'))@include('partials.nav-link', ['route' => 'admin.visitors.index', 'active' => 'admin.visitors.*', 'icon' => 'eye', 'label' => 'الزوار والداعمون'])@endif
    @if ($u->hasPermission('assessments.manage'))@include('partials.nav-link', ['route' => 'admin.criteria.index', 'icon' => 'sliders', 'label' => 'معايير التقييم'])@endif
    @if ($u->hasPermission('audit.view'))@include('partials.nav-link', ['route' => 'admin.audit.index', 'icon' => 'journal-text', 'label' => 'سجل العمليات'])@endif
    @if ($u->isSuperAdmin())
        <div class="nav-section">المدير الأعلى</div>
        @include('partials.nav-link', ['route' => 'admin.access.roles', 'active' => 'admin.access.*', 'icon' => 'shield-check', 'label' => 'الأدوار والصلاحيات'])
        @include('partials.nav-link', ['route' => 'admin.settings.edit', 'icon' => 'gear', 'label' => 'الإعدادات'])
    @endif
</nav>
