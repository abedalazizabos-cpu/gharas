@php($unreadMessages = $authUser->receivedMessages()->whereNull('read_at')->count())
<nav class="nav flex-column py-2">
    @include('partials.nav-link', ['route' => 'parent.dashboard', 'icon' => 'house', 'label' => 'الرئيسية'])
    <div class="nav-section">أبنائي</div>
    @foreach ($authUser->children()->orderBy('full_name')->get(['students.id', 'full_name']) as $child)
        <a href="{{ route('parent.children.show', $child) }}" class="nav-link {{ request()->route('student')?->id === $child->id ? 'active' : '' }}">
            <i class="bi bi-person"></i><span>{{ $child->full_name }}</span>
        </a>
    @endforeach
    <div class="nav-section">التواصل</div>
    @if ($authUser->hasPermission('messages.send'))@include('partials.nav-link', ['route' => 'messages.index', 'active' => 'messages.*', 'icon' => 'chat-dots', 'label' => 'مراسلة المحفظ', 'badge' => $unreadMessages ?: null])@endif
    @include('partials.nav-link', ['route' => 'parent.announcements', 'icon' => 'megaphone', 'label' => 'الإعلانات'])
    @include('partials.nav-link', ['route' => 'notifications.index', 'icon' => 'bell', 'label' => 'التنبيهات'])
    @include('partials.nav-link', ['route' => 'profile.edit', 'icon' => 'person-gear', 'label' => 'حسابي'])
</nav>
