@extends('layouts.app')
@section('title', 'لوحة الشرف')
@section('content')
<x-page-header title="لوحة الشرف" :crumbs="['لوحة الشرف']">
    <form method="POST" action="{{ route('admin.honor.rechoose') }}" data-confirm="يُعاد اختيار طالب كل حلقة تلقائياً، ويُلغى ما غيّرته بيدك لهذا الشهر." data-confirm-title="إعادة الاختيار التلقائي" data-confirm-button="نعم، أعد الاختيار">
        @csrf <input type="hidden" name="month" value="{{ $month }}">
        <button class="btn btn-outline-secondary"><i class="bi bi-arrow-repeat"></i> إعادة الاختيار التلقائي</button>
    </form>
</x-page-header>

<div class="alert alert-light border small"><i class="bi bi-info-circle text-success"></i>
    في أول كل شهر يُختار تلقائياً طالب واحد من كل حلقة عن الشهر السابق، ويظهر في شاشة تسجيل الدخول طوال الشهر. يُحسب الاختيار من: متوسط درجات التسميع ({{ \App\Services\HonorBoardService::WEIGHTS['average'] }}٪)، ونسبة الحضور ({{ \App\Services\HonorBoardService::WEIGHTS['attendance'] }}٪)، وصفحات الحفظ الجديد مقارنة بزملائه في الحلقة ({{ \App\Services\HonorBoardService::WEIGHTS['pages'] }}٪). يمكنك تغيير الطالب في أي حلقة، ويصل وليّ أمره إشعار بالتهنئة.
    @if (auth()->user()->isSuperAdmin())طريقة عرض الأسماء في شاشة الدخول تختارها من <a href="{{ route('admin.settings.edit') }}#honor">الإعدادات</a>.@endif
</div>

<form method="GET" class="d-flex flex-wrap gap-2 align-items-center mb-3" data-no-loading>
    <label class="small text-muted">الشهر</label>
    <select name="month" class="form-select w-auto" onchange="this.form.submit()">
        @foreach ($months as $m)<option value="{{ $m }}" @selected($m === $month)>{{ \App\Models\HonorEntry::monthLabel($m) }}{{ $m === $current ? ' (المعروضة الآن)' : '' }}</option>@endforeach
    </select>
</form>

<div class="row g-3">
@foreach ($rows as $row)
    @php($c = $row['circle'])
    @php($e = $row['entry'])
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center gap-2">
                <span class="fw-semibold text-truncate"><i class="bi bi-bullseye text-success"></i> {{ $c->name }}</span>
                <small class="text-muted text-truncate">{{ $c->teacher?->name }}</small>
            </div>
            <div class="card-body">
                @if ($e)
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="honor-medal"><i class="bi bi-trophy-fill"></i></span>
                        <div class="min-w-0">
                            <div class="fw-bold text-truncate">{{ $e->student?->full_name }}</div>
                            <small class="text-muted">
                                @if ($e->picked_by)<i class="bi bi-hand-index"></i> اختاره {{ $e->picker?->nameFor(auth()->user()) }}@else<i class="bi bi-cpu"></i> اختيار تلقائي · {{ rtrim(rtrim(number_format((float) $e->points, 2), '0'), '.') }} نقطة@endif
                            </small>
                        </div>
                    </div>
                    @php($d = $e->details ?? [])
                    <div class="small text-muted mb-3">
                        الحضور {{ isset($d['attendance']) ? $d['attendance'].'٪' : '—' }} · الحفظ الجديد {{ \App\Models\MemorizationRecord::formatPages($d['new_pages'] ?? 0) }} · متوسط التسميع {{ $d['average'] ?? '—' }}
                    </div>
                @else
                    <p class="text-muted small mb-3">لا يوجد طالب لهذا الشهر (لم يُسجَّل تسميع في الحلقة).</p>
                @endif
                @if ($c->students->isNotEmpty())
                    <form method="POST" action="{{ $e ? route('admin.honor.replace', $e) : route('admin.honor.store') }}" class="d-flex gap-1">
                        @csrf @if (! $e)<input type="hidden" name="month" value="{{ $month }}">@endif
                        <select name="student_id" class="form-select form-select-sm" required>
                            <option value="">{{ $e ? 'تغيير إلى…' : 'اختر طالباً…' }}</option>
                            @php($ranked = $row['ranking']->pluck('student.id')->all())
                            @foreach ($row['ranking'] as $r)
                                @continue($e && $r['student']->id === $e->student_id)
                                <option value="{{ $r['student']->id }}">{{ $r['student']->full_name }} ({{ rtrim(rtrim(number_format($r['points'], 2), '0'), '.') }})</option>
                            @endforeach
                            @foreach ($c->students->sortBy('full_name') as $s)
                                @continue(in_array($s->id, $ranked, true) || ($e && $s->id === $e->student_id))
                                <option value="{{ $s->id }}">{{ $s->full_name }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-sm btn-outline-primary text-nowrap">حفظ</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endforeach
</div>
@endsection
