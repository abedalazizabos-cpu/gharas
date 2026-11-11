@extends('layouts.app')
@section('title', 'متابعة تسجيل المحفظين')
@section('content')
<x-page-header title="متابعة تسجيل المحفظين" :crumbs="['متابعة التسجيل']" />

<div class="alert alert-light border small"><i class="bi bi-info-circle text-success"></i> يظهر هنا كل يوم دراسي انتهى ولم يسجّل فيه المحفظ حضور حلقته أو تسميع طلابها. لا تُحسب الجمعة ولا أيام العطل ولا الأيام التي بيّن المحفظ أو الإدارة أن الحلقة لم تنعقد فيها. وإذا غاب كل الطلاب لا يُطلب تسميع ذلك اليوم. يصل المحفظَ إشعارٌ صباح كل يوم بما فاته أمس، ويرى القائمة نفسها في لوحته.</div>

<form method="GET" class="card card-body mb-3" data-no-loading>
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small">من</label><input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label small">إلى</label><input type="date" name="to" value="{{ $to->toDateString() }}" max="{{ now()->subDay()->toDateString() }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label small">المحفظ</label><select name="teacher" class="form-select"><option value="">الكل</option>@foreach ($teachers as $t)<option value="{{ $t->id }}" @selected($teacherId === $t->id)>{{ $t->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><button class="btn btn-outline-primary w-100"><i class="bi bi-funnel"></i> عرض</button></div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><x-stat-card icon="exclamation-octagon" label="أيام لم تُسجَّل" :value="$gaps->count()" color="gold" /></div>
    <div class="col-6 col-xl-3"><x-stat-card icon="person-badge" label="محفظون عليهم أيام" :value="$byTeacher->count()" color="blue" /></div>
    <div class="col-6 col-xl-3"><x-stat-card icon="calendar-x" label="بلا حضور" :value="$gaps->where('attendance', true)->count()" /></div>
    <div class="col-6 col-xl-3"><x-stat-card icon="book" label="بلا تسميع" :value="$gaps->where('memorization', true)->count()" /></div>
</div>

@forelse ($byTeacher as $rows)
    @php($teacher = $rows->first()['teacher'])
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="fw-semibold"><i class="bi bi-person-badge text-danger"></i> {{ $teacher?->name ?? '—' }} @php($n = $rows->count())<span class="badge text-bg-danger">{{ match (true) { $n === 1 => 'يوم واحد', $n === 2 => 'يومان', $n < 11 => $n.' أيام', default => $n.' يوماً' } }}</span></span>
            @if ($teacher && auth()->user()->hasPermission('teachers.manage'))<a href="{{ route('admin.teachers.show', $teacher) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-exclamation-triangle"></i> ملف المحفظ والتنبيهات</a>@endif
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>اليوم</th><th>الحلقة</th><th>لم يُسجَّل</th><th></th></tr></thead>
                <tbody>
                @foreach ($rows as $g)
                    <tr>
                        <td class="text-nowrap">{{ \App\Models\Circle::DAYS[$g['date']->dayOfWeek] }} {{ $g['date']->format('Y/m/d') }}</td>
                        <td>{{ $g['circle']->name }}</td>
                        <td><span class="text-danger">{{ \App\Services\RecordingGapService::missingLabel($g) }}</span></td>
                        <td class="text-nowrap">
                            <form method="POST" action="{{ route('admin.day-offs.store') }}" class="d-flex gap-1 justify-content-end">
                                @csrf <input type="hidden" name="circle_id" value="{{ $g['circle']->id }}"><input type="hidden" name="date" value="{{ $g['date']->toDateString() }}">
                                <select name="reason" class="form-select form-select-sm" style="width:auto" required><option value="">لم تنعقد بسبب…</option>@foreach (\App\Models\CircleDayOff::REASONS as $r)<option value="{{ $r }}">{{ $r }}</option>@endforeach</select>
                                <button class="btn btn-sm btn-outline-secondary" title="لا يُحسب هذا اليوم على المحفظ">حفظ</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@empty
    <div class="card mb-3"><x-empty icon="check2-circle" message="كل المحفظين سجّلوا الحضور والتسميع في هذه الفترة." /></div>
@endforelse

<div class="row g-3">
    <div class="col-lg-5">
        <form method="POST" action="{{ route('admin.day-offs.store') }}" class="card h-100">
            @csrf
            <div class="card-header"><i class="bi bi-calendar2-x text-success"></i> عطلة للمركز كله</div>
            <div class="card-body row g-2">
                <div class="col-6"><label class="form-label small">من تاريخ</label><input type="date" name="date" class="form-control" required></div>
                <div class="col-6"><label class="form-label small">إلى تاريخ (اختياري)</label><input type="date" name="date_to" class="form-control"></div>
                <div class="col-12"><label class="form-label small">السبب</label><input name="reason" class="form-control" maxlength="150" placeholder="مثل: عطلة عيد الأضحى" required></div>
                <div class="col-12 small text-muted">أيام العطلة لا تُحسب على أي محفظ. يمكن تسجيلها مسبقاً.</div>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary"><i class="bi bi-check2"></i> حفظ العطلة</button></div>
        </form>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-calendar-minus"></i> أيام لم تنعقد فيها الحلقات (الفترة وما بعدها بشهر)</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>اليوم</th><th>الحلقة</th><th>السبب</th><th>سجّله</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($offs as $o)
                        <tr>
                            <td class="text-nowrap small">{{ \App\Models\Circle::DAYS[$o->off_on->dayOfWeek] }} {{ $o->off_on->format('Y/m/d') }}</td>
                            <td>{{ $o->circle ? $o->circle->name : 'المركز كله' }}@if ($o->circle?->teacher)<small class="text-muted d-block">{{ $o->circle->teacher->name }}</small>@endif</td>
                            <td class="small">{{ $o->reason }}</td>
                            <td class="small text-muted">{{ $o->creator?->nameFor(auth()->user()) }}</td>
                            <td><x-delete-form :action="route('admin.day-offs.destroy', $o)" label="إلغاء" icon="x-lg" title="إلغاء اليوم" message="يُحسب اليوم على المحفظ إن لم يسجّل فيه الحضور والتسميع." /></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted small p-3">لا توجد أيام مسجلة.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
