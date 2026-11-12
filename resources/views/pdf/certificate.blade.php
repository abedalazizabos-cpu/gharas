@php($color = preg_match('/^#[0-9A-Fa-f]{6}$/', $c['color'] ?? '') ? $c['color'] : '#1b6b50')
<html dir="rtl"><head><meta charset="utf-8"><style>
    @page { margin: 0; }
    body { font-family: xbriyaz; color: #1d2b25; direction: rtl; margin: 0; }
    .page { margin: 9mm; border: 2.5mm solid {{ $color }}; height: 186mm; }
    .inner { margin: 2mm; border: 0.6mm solid {{ $color }}; height: 177mm; padding: 6mm 16mm 4mm; text-align: center; }
    .center { font-size: 15pt; font-weight: bold; color: {{ $color }}; margin-top: 1mm; }
    .title { font-size: 34pt; font-weight: bold; color: {{ $color }}; margin: 3mm 0 2mm; }
    .rule { width: 60mm; margin: 0 auto 4mm; border-top: 0.5mm solid {{ $color }}; }
    .intro { font-size: 16pt; margin-bottom: 2mm; }
    .student { font-size: 30pt; font-weight: bold; color: #13241c; margin: 2mm 0 3mm; }
    .body { font-size: 16pt; line-height: 1.7; margin: 0 6mm; }
    .closing { font-size: 14pt; color: #444; margin-top: 4mm; }
    table.sign { width: 100%; margin-top: 10mm; }
    table.sign td { width: 50%; text-align: center; vertical-align: top; font-size: 13pt; }
    .sign-title { font-weight: bold; color: {{ $color }}; }
    .sign-line { margin-top: 8mm; color: #888; }
    .footer { font-size: 9.5pt; color: #777; margin-top: 4mm; }
    .preview { color: #b02a37; font-size: 11pt; font-weight: bold; }
</style></head><body>
<div class="page"><div class="inner">
    @if (! empty($c['preview']))<div class="preview">معاينة ببيانات تجريبية</div>@endif
    @if ($logo)<img src="{{ $logo }}" height="62">@endif
    <div class="center">{{ $c['center'] ?? '' }}</div>
    <div class="title">{{ $c['title'] ?? '' }}</div>
    <div class="rule"></div>
    @if (! empty($c['intro']))<div class="intro">{{ $c['intro'] }}</div>@endif
    <div class="student">{{ $c['student'] ?? '' }}</div>
    <div class="body">{{ $c['body'] ?? '' }}</div>
    @if (! empty($c['closing']))<div class="closing">{{ $c['closing'] }}</div>@endif
    <table class="sign"><tr>
        <td>@if (! empty($c['signer1_title']) || ! empty($c['signer1_name']))<div class="sign-title">{{ $c['signer1_title'] ?? '' }}</div><div>{{ $c['signer1_name'] ?? '' }}</div><div class="sign-line">..................................</div>@endif</td>
        <td>@if (! empty($c['signer2_title']) || ! empty($c['signer2_name']))<div class="sign-title">{{ $c['signer2_title'] ?? '' }}</div><div>{{ $c['signer2_name'] ?? '' }}</div><div class="sign-line">..................................</div>@endif</td>
    </tr></table>
    @php($ltr = fn ($text) => preg_replace('/GH-\d{4}-\d+/', '<span dir="ltr">$0</span>', e($text)))
    <div class="footer">{!! $ltr($c['footer'] ?? '') !!}@if (empty($c['footer']) && ! empty($c['number'])) رقم الشهادة <span dir="ltr">{{ $c['number'] }}</span> · {{ $c['date'] ?? '' }}@if (! empty($c['hijri'])) - {{ $c['hijri'] }}@endif @endif
    @if (! empty($c['footer']) && ! empty($c['hijri']) && ! str_contains($c['footer'], 'هـ'))<div>{{ $c['hijri'] }}</div>@endif</div>
</div></div>
</body></html>
