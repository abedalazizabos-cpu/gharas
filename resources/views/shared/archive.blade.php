@extends('layouts.app')
@section('title', 'أرشيف الأسابيع')
@section('content')
<x-page-header title="أرشيف الأسابيع السابقة" :crumbs="['السجل الأسبوعي' => route($prefix.'.weeks.show'), 'الأرشيف']" />
<form method="GET" class="filter-bar row g-2" data-no-loading>
    <div class="col-md-6"><select name="circle" class="form-select" onchange="this.form.submit()" aria-label="الحلقة">
        <option value="">كل الحلقات</option>
        @foreach ($circles as $c)<option value="{{ $c->id }}" @selected(request('circle') == $c->id)>{{ $c->name }}</option>@endforeach
    </select></div>
</form>
<p class="small text-muted">يُحفظ لكل حلقة ملف عن كل أسبوع منتهٍ (الحضور وسجلات التسميع)، بصيغة PDF. اضغط «عرض» لرؤية الأسبوع داخل النظام.</p>
@forelse ($months as $month => $weeks)
    @php($m = \Illuminate\Support\Carbon::createFromFormat('Y-m', $month))
    <details class="card mb-3" @if ($loop->first) open @endif>
        <summary class="card-header d-flex justify-content-between" style="cursor:pointer"><span><i class="bi bi-folder2-open text-warning"></i> {{ $m->translatedFormat('F Y') }}</span><span class="text-muted small">{{ $weeks->count() }} أسبوع</span></summary>
        @foreach ($weeks as $week => $files)
            @php($ws = \Illuminate\Support\Carbon::parse($week))
            <div class="border-top">
                <div class="px-3 pt-2 fw-semibold small"><i class="bi bi-calendar-week text-success"></i> الأسبوع {{ \App\Services\WeeklyArchiveService::label($ws) }}</div>
                <div class="table-responsive"><table class="table table-sm mb-0 align-middle small">
                    <tbody>
                    @foreach ($files as $a)
                        <tr>
                            <td>{{ $a->circle?->name }} <span class="text-muted">· {{ $a->circle?->teacher?->name ?? 'بدون محفظ' }}</span></td>
                            <td class="text-nowrap text-muted">{{ $a->records_count }} تسميع · {{ $a->attendance_count }} حضور</td>
                            <td class="text-nowrap text-end">
                                <a href="{{ route($prefix.'.weeks.show', ['circle' => $a->circle_id, 'week' => $week]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> عرض</a>
                                @if ($prefix !== 'visitor' || auth()->user()->hasPermission('visitor.download'))<a href="{{ route($prefix.'.archive.download', $a) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i> الملف</a>@endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>
        @endforeach
    </details>
@empty
    <div class="card"><x-empty icon="archive" message="لا توجد أسابيع منتهية محفوظة بعد. يُحفظ أول ملف بعد انتهاء أول أسبوع فيه سجلات." /></div>
@endforelse
@endsection
