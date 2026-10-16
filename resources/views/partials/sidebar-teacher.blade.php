@php($unreadMessages = $authUser->receivedMessages()->whereNull('read_at')->count())
<nav class="nav flex-column py-2">
    @include('partials.nav-link', ['route' => 'teacher.dashboard', 'icon' => 'speedometer2', 'label' => 'لوحة التحكم'])
    <div class="nav-section">حلقاتي</div>
    @include('partials.nav-link', ['route' => 'teacher.circles.index', 'active' => 'teacher.circles.*|teacher.attendance.*|teacher.students.*', 'icon' => 'bullseye', 'label' => 'الحلقات والجدول'])
    @if ($authUser->hasPermission('teacher.memorization'))@include('partials.nav-link', ['route' => 'teacher.memorization.create', 'icon' => 'plus-circle', 'label' => 'تسجيل تسميع'])@endif
    @if ($authUser->hasPermission('teacher.memorization'))@include('partials.nav-link', ['route' => 'teacher.memorization.index', 'icon' => 'book', 'label' => 'سجل الحفظ والمراجعة'])@endif
    @if ($authUser->hasPermission('teacher.archive'))@include('partials.nav-link', ['route' => 'teacher.weeks.show', 'active' => 'teacher.weeks.*|teacher.archive.*', 'icon' => 'calendar-week', 'label' => 'السجل الأسبوعي والأرشيف'])@endif
    @if ($authUser->hasPermission('teacher.assessments'))@include('partials.nav-link', ['route' => 'teacher.assessments.index', 'active' => 'teacher.assessments.*', 'icon' => 'award', 'label' => 'التقييمات'])@endif
    <div class="nav-section">التواصل</div>
    @if ($authUser->hasPermission('messages.send'))@include('partials.nav-link', ['route' => 'messages.index', 'active' => 'messages.*', 'icon' => 'chat-dots', 'label' => 'الرسائل', 'badge' => $unreadMessages ?: null])@endif
    @include('partials.nav-link', ['route' => 'teacher.announcements', 'icon' => 'megaphone', 'label' => 'الإعلانات'])
    @include('partials.nav-link', ['route' => 'notifications.index', 'icon' => 'bell', 'label' => 'الإشعارات'])
</nav>
