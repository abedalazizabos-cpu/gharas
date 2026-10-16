{{-- Logo and name as set by the super admin in Settings; the logo falls back to public/images/logo.png --}}
@php($S = \App\Models\Setting::class)
@php($logo = $S::get('center_logo'))
@php($px = ($S::LOGO_SIZES[$S::get('logo_size')] ?? $S::LOGO_SIZES['medium'])[1])
@php($radius = ['circle' => '50%', 'square' => '2px'][$S::get('logo_shape')] ?? '10px')
<a href="{{ $href ?? route('home') }}" class="brand">
    <img src="{{ asset($logo ?: 'images/logo.png') }}" alt="شعار المركز" class="brand-logo"
        style="width:{{ $px }}px;height:{{ $px }}px;border-radius:{{ $radius }};{{ $S::get('logo_background') === '1' ? '' : 'background:transparent;padding:0' }}">
    <span>
        <span class="brand-name d-block">{{ $S::get('brand_title') ?: 'مركز غراس' }}</span>
        @if ($S::get('brand_subtitle'))<span class="brand-sub d-block">{{ $S::get('brand_subtitle') }}</span>@endif
    </span>
</a>
