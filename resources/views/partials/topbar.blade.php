@php($unread = $authUser->unreadNotifications()->latest()->limit(6)->get())
@php($unreadCount = $authUser->unreadNotifications()->count())
<header class="topbar d-flex align-items-center gap-2">
    <button class="btn btn-light d-lg-none" type="button" data-sidebar-toggle aria-label="فتح القائمة"><i class="bi bi-list fs-5"></i></button>
    <div class="fw-bold d-none d-md-block text-truncate">@yield('title', 'لوحة التحكم')</div>

    <div class="ms-auto d-flex align-items-center gap-2">
        <div class="dropdown">
            <button class="btn btn-light position-relative" data-bs-toggle="dropdown" aria-label="الإشعارات">
                <i class="bi bi-bell fs-5"></i>
                @if ($unreadCount)<span class="badge rounded-pill bg-danger notif-dot">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>@endif
            </button>
            <div class="dropdown-menu dropdown-menu-end notif-menu p-0 shadow">
                <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                    <strong>الإشعارات</strong>
                    @if ($unreadCount)
                        <form method="POST" action="{{ route('notifications.read-all') }}" data-no-loading>@csrf
                            <button class="btn btn-link btn-sm p-0">تعليم الكل كمقروء</button>
                        </form>
                    @endif
                </div>
                @forelse ($unread as $n)
                    <form method="POST" action="{{ route('notifications.read', $n->id) }}" data-no-loading>@csrf
                        <button class="dropdown-item py-2 text-wrap d-flex gap-2">
                            <i class="bi bi-{{ $n->data['icon'] ?? 'bell' }} text-success fs-5"></i>
                            <span><span class="fw-semibold d-block">{{ $n->data['title'] ?? '' }}</span>
                            <small class="text-muted">{{ $n->data['body'] ?? '' }}</small>
                            <small class="d-block text-muted">{{ $n->created_at->diffForHumans() }}</small></span>
                        </button>
                    </form>
                @empty
                    <div class="text-center text-muted py-4 small"><i class="bi bi-bell-slash d-block fs-3 mb-1"></i>لا توجد إشعارات جديدة</div>
                @endforelse
                <a href="{{ route('notifications.index') }}" class="d-block text-center p-2 border-top small">عرض كل الإشعارات</a>
            </div>
        </div>

        <div class="dropdown">
            <button class="btn btn-light d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <span class="avatar">{{ mb_substr($authUser->name, 0, 1) }}</span>
                <span class="d-none d-sm-inline text-start lh-sm">
                    <span class="d-block fw-semibold small">{{ $authUser->name }}</span>
                    <small class="text-muted">{{ $authUser->roleLabel() }}</small>
                </span>
                <i class="bi bi-chevron-down small"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person-gear ms-2"></i>الملف الشخصي</a></li>
                <li><a class="dropdown-item" href="{{ route('home') }}"><i class="bi bi-house ms-2"></i>الموقع العام</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}" data-no-loading>@csrf
                        <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-left ms-2"></i>تسجيل الخروج</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
