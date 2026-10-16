<html dir="rtl"><head><meta charset="utf-8"><style>
    body { font-family: xbriyaz; font-size: 11pt; color: #1d2b25; direction: rtl; }
    .head { border-bottom: 2px solid #0F3D2E; padding-bottom: 6px; margin-bottom: 10px; }
    .head td { border: none; vertical-align: middle; }
    .center { font-size: 13pt; font-weight: bold; color: #0F3D2E; }
    h1 { font-size: 16pt; margin: 2px 0; color: #0F3D2E; }
    .sub { color: #555; font-size: 10pt; }
    h2 { font-size: 12.5pt; color: #0F3D2E; margin: 12px 0 5px; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th { background: #E6EFEA; color: #0F3D2E; font-weight: bold; border: 1px solid #b9cbc1; padding: 4px 5px; font-size: 10pt; }
    table.data td { border: 1px solid #d3ddd7; padding: 4px 5px; font-size: 10pt; text-align: center; }
    table.data td.s { text-align: right; }
    table.data tr.even td { background: #F7FAF8; }
    .empty { color: #777; font-size: 10pt; padding: 6px 0; }
    .printed { color: #888; font-size: 8.5pt; margin-top: 10px; }
</style></head><body>
<table class="head" width="100%"><tr>
    <td width="70">@if ($logo)<img src="{{ $logo }}" height="56">@endif</td>
    <td><div class="center">{{ $center }}</div><h1>{{ $title }}</h1>@if ($subtitle)<div class="sub">{{ $subtitle }}</div>@endif</td>
</tr></table>
@foreach ($sections as $section)
    @if (! empty($section['title']))<h2>{{ $section['title'] }}</h2>@endif
    @php($rows = collect($section['rows'])->values())
    @if ($rows->isEmpty())
        <div class="empty">{{ $section['empty'] ?? 'لا توجد بيانات.' }}</div>
    @else
        <table class="data"><thead><tr>@foreach ($section['headers'] as $h)<th>{{ $h }}</th>@endforeach</tr></thead><tbody>
        @foreach ($rows as $i => $row)
            <tr class="{{ $i % 2 ? 'even' : '' }}">@foreach (array_values((array) $row) as $c => $cell)<td class="{{ in_array($c, $section['text_cols'] ?? [0], true) ? 's' : '' }}">{{ $cell }}</td>@endforeach</tr>
        @endforeach
        </tbody></table>
    @endif
@endforeach
<div class="printed">طُبع في {{ $printedAt }}</div>
</body></html>
