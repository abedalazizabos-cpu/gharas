@props(['title', 'crumbs' => []])
<div class="page-header">
    <div>
        <h1>{{ $title }}</h1>
        @if ($crumbs)
            <nav aria-label="breadcrumb"><ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                @foreach ($crumbs as $label => $url)
                    @if (is_string($label))
                        <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
                    @else
                        <li class="breadcrumb-item active">{{ $url }}</li>
                    @endif
                @endforeach
            </ol></nav>
        @endif
    </div>
    <div class="d-flex flex-wrap gap-2 no-print">{{ $slot }}</div>
</div>
