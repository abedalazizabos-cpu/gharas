@extends('layouts.app')
@section('title', 'لجنة الجودة')
@section('content')
<x-page-header title="لجنة الجودة والاختبارات" :crumbs="['لجنة الجودة']">
    <a href="{{ route('admin.quality.settings') }}" class="btn btn-outline-primary"><i class="bi bi-sliders"></i> أعضاء اللجنة والضوابط</a>
</x-page-header>

@if ($examiners->isEmpty())
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> لم تختر بعد مشايخ لجنة الجودة. <a href="{{ route('admin.quality.settings') }}">اخترهم من هنا</a> ثم أرسل إليهم الطلاب.</div>
@endif

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link {{ $tab === 'alerts' ? 'active' : '' }}" href="{{ route('admin.quality.index', ['tab' => 'alerts']) }}"><i class="bi bi-exclamation-triangle"></i> الاختبارات المنفردة المتأخرة @if ($alertCount)<span class="badge text-bg-danger">{{ $alertCount }}</span>@endif</a></li>
    <li class="nav-item"><a class="nav-link {{ $tab === 'combined' ? 'active' : '' }}" href="{{ route('admin.quality.index', ['tab' => 'combined']) }}"><i class="bi bi-collection"></i> المستحقون للاختبارات المجتمعة @if ($dueCount)<span class="badge text-bg-warning">{{ $dueCount }}</span>@endif</a></li>
    <li class="nav-item"><a class="nav-link {{ $tab === 'tests' ? 'active' : '' }}" href="{{ route('admin.quality.index', ['tab' => 'tests']) }}"><i class="bi bi-list-check"></i> كل الاختبارات والنتائج</a></li>
</ul>

@if ($tab === 'alerts')
    <div class="alert alert-light border small"><i class="bi bi-info-circle text-success"></i> يظهر هنا كل طالب انتقل إلى الجزء التالي ولم ينجح بعد في اختبار الجزء الذي أنهاه. أرسله إلى أحد مشايخ الجودة (لا يشترط أن يكون محفظه في الحلقة). الأجزاء التي كان يحفظها عند تسجيله لا تظهر هنا، ويمكنك إسناد اختبارها من ملف الطالب.</div>
    <div class="card"><div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>الطالب</th><th>الحلقة</th><th>يحفظ الآن</th><th>الجزء الذي لم يُختبر فيه</th></tr></thead>
            <tbody>
            @forelse ($rows as $row)
                @php($s = $row['student'])
                <tr>
                    <td><a href="{{ route('admin.students.show', $s) }}" class="fw-semibold">{{ $s->full_name }}</a><div class="small text-muted">محفوظه: {{ \App\Support\Juz::count($row['memorized_count']) }}</div></td>
                    <td class="small">{{ $s->circle?->name ?? '—' }}<div class="text-muted">{{ $s->circle?->teacher?->name }}</div></td>
                    <td>{{ $row['current_juz'] ? \App\Support\Juz::label($row['current_juz']) : 'أتم المصحف' }}</td>
                    <td>
                        @foreach ($row['missing_individual'] as $juz => $pending)
                            <div class="d-flex flex-wrap align-items-center gap-2 {{ $loop->last ? '' : 'mb-2' }}">
                                <span class="badge text-bg-danger">{{ \App\Support\Juz::label($juz) }}</span>
                                @if ($pending)
                                    <span class="small text-muted">عند {{ $pending->examiner?->name }} منذ {{ $pending->assigned_on->format('Y/m/d') }}</span>
                                @endif
                                @include('shared._quality-assign', ['student' => $s, 'juz' => $juz, 'selectedExaminer' => $pending?->examiner_id])
                            </div>
                        @endforeach
                    </td>
                </tr>
            @empty
                <tr><td colspan="4"><x-empty icon="patch-check" message="لا يوجد طالب انتقل إلى جزء جديد دون اختبار. ما شاء الله." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
@elseif ($tab === 'combined')
    <div class="alert alert-light border small"><i class="bi bi-info-circle text-success"></i> الطالب يستحق الاختبار المجتمع حين ينهي كل أجزائه (مثلاً 30 - 28 بعد إنهاء الأجزاء 30 و29 و28). ترتيب المراحل يعدّل من <a href="{{ route('admin.quality.settings') }}#stages">الضوابط</a>.</div>
    <div class="card"><div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>الطالب</th><th>الحلقة</th><th>المحفوظ</th><th>الاختبارات المجتمعة المستحقة</th></tr></thead>
            <tbody>
            @forelse ($rows as $row)
                @php($s = $row['student'])
                <tr>
                    <td><a href="{{ route('admin.students.show', $s) }}" class="fw-semibold">{{ $s->full_name }}</a></td>
                    <td class="small">{{ $s->circle?->name ?? '—' }}</td>
                    <td>{{ \App\Support\Juz::count($row['memorized_count']) }}</td>
                    <td>
                        @foreach ($row['due_combined'] as $due)
                            <div class="d-flex flex-wrap align-items-center gap-2 {{ $loop->last ? '' : 'mb-2' }}">
                                <span class="badge text-bg-warning">{{ $due['stage']->label() }}</span>
                                @if ($due['pending'])<span class="small text-muted">عند {{ $due['pending']->examiner?->name }}</span>@endif
                                @include('shared._quality-assign', ['student' => $s, 'stage' => $due['stage'], 'selectedExaminer' => $due['pending']?->examiner_id])
                            </div>
                        @endforeach
                    </td>
                </tr>
            @empty
                <tr><td colspan="4"><x-empty icon="collection" message="لا يوجد طالب مستحق لاختبار مجتمع حالياً." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
@else
    <form method="GET" class="filter-bar row g-2" data-no-loading>
        <input type="hidden" name="tab" value="tests">
        <div class="col-md-4"><input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="اسم أو رقم الطالب"></div>
        <div class="col-md-2"><select name="kind" class="form-select"><option value="">كل الأنواع</option>@foreach (\App\Models\QualityTest::KINDS as $k => $v)<option value="{{ $k }}" @selected(request('kind') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-2"><select name="status" class="form-select"><option value="">كل الحالات</option>@foreach (\App\Models\QualityTest::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-2"><select name="examiner" class="form-select"><option value="">كل المشايخ</option>@foreach ($examiners as $e)<option value="{{ $e->id }}" @selected(request('examiner') == $e->id)>{{ $e->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-search"></i> بحث</button></div>
    </form>
    <div class="card"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>الطالب</th><th>الاختبار</th><th>شيخ الجودة</th><th>أُسند في</th><th>الحالة</th><th>النتيجة</th><th></th></tr></thead>
            <tbody>
            @forelse ($tests as $t)
                <tr>
                    <td><a href="{{ route('admin.students.show', $t->student) }}">{{ $t->student->full_name }}</a><div class="small text-muted">{{ $t->student->circle?->name }}</div></td>
                    <td><span class="small text-muted d-block">{{ $t->kindLabel() }}</span>{{ $t->subjectLabel() }}</td>
                    <td>{{ $t->examiner?->name ?? '—' }}</td>
                    <td class="small">{{ $t->assigned_on->format('Y/m/d') }}</td>
                    <td><span class="badge text-bg-{{ $t->statusColor() }}">{{ $t->statusLabel() }}</span></td>
                    <td>@if ($t->percentage !== null)<strong>{{ rtrim(rtrim(number_format($t->percentage, 2), '0'), '.') }}%</strong> <small class="text-muted">{{ $t->gradeLabel() }} · {{ $t->tested_on?->format('Y/m/d') }}</small>@else — @endif</td>
                    <td class="table-actions text-nowrap">
                        <a href="{{ route('admin.quality.grade', $t) }}" class="btn btn-sm btn-outline-primary" title="رصد النتيجة"><i class="bi bi-pencil-square"></i></a>
                        <x-delete-form :action="route('admin.quality.destroy', $t)" label="" message="سيُلغى هذا الاختبار ونتيجته (يبقى في سجل العمليات)." />
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty icon="list-check" message="لا توجد اختبارات بعد." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $tests->links() }}</div>
@endif
<p class="small text-muted mt-3">درجة النجاح في اختبارات الجودة: {{ $passScore }}% (تعدّل من الضوابط).</p>
@endsection
