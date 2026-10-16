<!doctype html>
<html lang="ar" dir="rtl">
<head>
    @include('partials.head')
    <title>@yield('title', 'لوحة التحكم') | {{ \App\Models\Setting::get('center_name') }}</title>
</head>
<body>
@php($authUser = auth()->user())
<aside class="sidebar" aria-label="القائمة الجانبية">
    @include('partials.brand', ['href' => route('dashboard')])
    @include('partials.sidebar-'.$authUser->role)
</aside>
<div class="sidebar-backdrop"></div>

<div class="main">
    @include('partials.topbar')
    <main class="content">
        <div class="print-header mb-3 text-center">
            <h4 class="mb-0">{{ \App\Models\Setting::get('center_name') }}</h4>
            <small>تاريخ الطباعة: {{ now()->format('Y/m/d H:i') }}</small>
        </div>
        @if ($errors->any())
            <div class="alert alert-danger d-flex gap-2 no-print" role="alert">
                <i class="bi bi-exclamation-octagon-fill fs-5"></i>
                <div>
                    <strong>يرجى تصحيح الأخطاء التالية:</strong>
                    <ul class="mb-0 mt-1 ps-0 pe-3">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            </div>
        @endif
        @yield('content')
    </main>
    <footer class="text-center text-muted small py-3 no-print">
        © {{ now()->year }} {{ \App\Models\Setting::get('center_name') }} - جميع الحقوق محفوظة
    </footer>
</div>
@include('partials.scripts')
</body>
</html>
