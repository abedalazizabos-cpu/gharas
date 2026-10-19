<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.rtl.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/gharas.css') }}?v={{ filemtime(public_path('css/gharas.css')) }}">
@php($themeColor = \App\Models\Setting::get('theme_color'))
@if ($themeColor && preg_match('/^#[0-9a-fA-F]{6}$/', $themeColor) && strtolower($themeColor) !== '#1b6b50')
    {{-- Main colour chosen by the super admin; darker/lighter shades are mixed from it. --}}
    <style>:root { --g-green-700: {{ $themeColor }}; --g-green-800: color-mix(in srgb, {{ $themeColor }} 80%, #000); --g-green-900: color-mix(in srgb, {{ $themeColor }} 60%, #000); --g-green-500: color-mix(in srgb, {{ $themeColor }} 75%, #fff); --g-green-100: color-mix(in srgb, {{ $themeColor }} 14%, #fff); --g-green-50: color-mix(in srgb, {{ $themeColor }} 6%, #fff); --bs-primary: {{ $themeColor }}; --bs-primary-rgb: {{ implode(', ', sscanf($themeColor, '#%02x%02x%02x')) }}; }</style>
@endif
<link rel="icon" href="{{ asset((\App\Models\Setting::get('logo_favicon') === '1' ? \App\Models\Setting::get('center_logo') : null) ?: 'images/favicon.png') }}">
