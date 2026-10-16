<!doctype html>
<html lang="ar" dir="rtl">
<head>
    @include('partials.head')
    <title>@yield('title', \App\Models\Setting::get('center_name'))</title>
    <meta name="description" content="{{ \App\Models\Setting::get('center_about') }}">
</head>
<body>
@yield('body')
@include('partials.scripts')
</body>
</html>
