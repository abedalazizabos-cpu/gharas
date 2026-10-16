<!doctype html>
<html lang="ar" dir="rtl">
<head>@include('partials.head')<title>@yield('code') | مركز غراس</title></head>
<body>
<div class="auth-wrap"><div class="card auth-card text-center"><div class="card-body p-5">
    <div class="display-4 fw-bold text-success">@yield('code')</div>
    <h1 class="h5 fw-bold my-3">@yield('message')</h1>
    <a href="{{ url('/') }}" class="btn btn-primary">العودة للرئيسية</a>
    @auth <a href="{{ route('dashboard') }}" class="btn btn-outline-primary">لوحة التحكم</a> @endauth
</div></div></div>
</body>
</html>
