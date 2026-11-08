@extends('layouts.app')
@section('title', 'الشهادات')
@section('content')
<x-page-header title="الشهادات" :crumbs="['الشهادات']" />

<div class="alert alert-light border small"><i class="bi bi-info-circle text-success"></i> لا تصدر أي شهادة إلا بأمرك: اختر من قائمة المستحقين (الناجحين في الدورات والاختبارات المنفردة والمجتمعة) ثم اضغط «إصدار». نصوص كل شهادة تكتبها في تبويب «نصوص الشهادات»، وتُحفظ مع الشهادة عند إصدارها فلا يغيّرها تعديل لاحق. يرى ولي الأمر شهادات أبنائه ويرى المحفظ شهادات طلابه، ويحمّلونها PDF.</div>

<ul class="nav nav-pills mb-3 gap-1">
    <li class="nav-item"><a class="nav-link {{ $tab === 'eligible' ? 'active' : '' }}" href="{{ route('admin.certificates.index', ['tab' => 'eligible']) }}"><i class="bi bi-hourglass-split"></i> بانتظار الإصدار <span class="badge text-bg-warning">{{ $eligibleCount }}</span></a></li>
    <li class="nav-item"><a class="nav-link {{ $tab === 'issued' ? 'active' : '' }}" href="{{ route('admin.certificates.index', ['tab' => 'issued']) }}"><i class="bi bi-award"></i> الشهادات الصادرة <span class="badge text-bg-light">{{ $issuedCount }}</span></a></li>
    <li class="nav-item"><a class="nav-link {{ $tab === 'templates' ? 'active' : '' }}" href="{{ route('admin.certificates.index', ['tab' => 'templates']) }}"><i class="bi bi-pencil-square"></i> نصوص الشهادات</a></li>
</ul>

@if ($tab !== 'templates')
<form method="GET" class="card card-body mb-3" data-no-loading>
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="row g-2 align-items-end">
        <div class="col-md-5"><label class="form-label small">بحث</label><input name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ $tab === 'issued' ? 'اسم الطالب أو رقم الشهادة' : 'اسم الطالب أو رقمه' }}"></div>
        <div class="col-md-4"><label class="form-label small">نوع الشهادة</label><select name="kind" class="form-select"><option value="">الكل</option>@foreach (\App\Models\CertificateTemplate::KINDS as $k => $v)<option value="{{ $k }}" @selected($kind === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-3"><button class="btn btn-outline-primary w-100"><i class="bi bi-funnel"></i> تصفية</button></div>
    </div>
</form>
@endif

@if ($tab === 'eligible')
    <form method="POST" action="{{ route('admin.certificates.issue') }}">
        @csrf
        <div class="card">
            <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <span><i class="bi bi-person-check"></i> نتائج ناجحة بلا شهادة ({{ $eligible->count() }})</span>
                <div class="d-flex gap-2 align-items-center">
                    <label class="small text-muted">تاريخ الإصدار</label>
                    <input type="date" name="issued_on" value="{{ old('issued_on', now()->toDateString()) }}" class="form-control form-control-sm" style="width:auto" required>
                    <button class="btn btn-sm btn-primary" @disabled($eligible->isEmpty())><i class="bi bi-award"></i> إصدار الشهادات المحددة</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th style="width:2rem"><input type="checkbox" class="form-check-input" data-check-all=".issue-box" title="تحديد الكل"></th><th>الطالب</th><th>الشهادة</th><th>عن</th><th>التقدير</th><th>التاريخ</th></tr></thead>
                    <tbody>
                    @forelse ($eligible as $item)
                        <tr>
                            <td><input type="checkbox" name="items[]" value="{{ $item['key'] }}" class="form-check-input issue-box" id="i-{{ $item['key'] }}"></td>
                            <td><label for="i-{{ $item['key'] }}" class="fw-semibold">{{ $item['student']->full_name }}</label><small class="text-muted d-block">{{ $item['student']->circle?->name }}</small></td>
                            <td class="small"><i class="bi bi-{{ \App\Models\Certificate::ICONS[$item['kind']] }} text-success"></i> {{ \App\Models\CertificateTemplate::KINDS[$item['kind']] }}</td>
                            <td>{{ $item['subject'] }}</td>
                            <td>{{ $item['grade'] }} <small class="text-muted">{{ $item['percent'] }}</small></td>
                            <td class="small">{{ $item['date'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty icon="award" message="لا توجد نتائج ناجحة بانتظار شهادة." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </form>
@elseif ($tab === 'issued')
    <div class="card"><div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>رقم الشهادة</th><th>الطالب</th><th>الشهادة</th><th>عن</th><th>صدرت</th><th></th></tr></thead>
            <tbody>
            @forelse ($issued as $c)
                <tr>
                    <td class="small text-nowrap">{{ $c->number }}</td>
                    <td class="fw-semibold">{{ $c->content['student'] }}<small class="text-muted d-block">{{ $c->student?->circle?->name }}</small></td>
                    <td class="small"><i class="bi bi-{{ $c->icon() }} text-success"></i> {{ $c->kindLabel() }}</td>
                    <td>{{ $c->subject() }}</td>
                    <td class="small">{{ $c->issued_on?->format('Y/m/d') }}<span class="text-muted d-block">{{ $c->issuer?->nameFor(auth()->user()) }}</span></td>
                    <td class="table-actions text-nowrap">
                        <a href="{{ route('certificates.download', $c) }}" class="btn btn-sm btn-outline-success" data-no-loading><i class="bi bi-file-earmark-pdf"></i> <span class="d-none d-xl-inline">PDF</span></a>
                        <x-delete-form :action="route('admin.certificates.destroy', $c)" label="سحب" icon="x-circle" title="سحب الشهادة" message="تُسحب الشهادة فلا يراها ولي الأمر ولا المحفظ، وتعود النتيجة إلى قائمة المستحقين." />
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty icon="award" message="لم تصدر أي شهادة بعد." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $issued->links() }}</div>
@else
    <div class="card mb-3"><div class="card-body small">
        <div class="fw-semibold mb-2"><i class="bi bi-braces"></i> كلمات تُستبدل تلقائياً عند الإصدار (اكتبها بين القوسين كما هي):</div>
        <div class="d-flex flex-wrap gap-2">
            @foreach (\App\Models\CertificateTemplate::PLACEHOLDERS as $code => $desc)
                <span class="badge text-bg-light border fw-normal"><code dir="rtl">{{ $code }}</code> {{ $desc }}</span>
            @endforeach
        </div>
        <div class="text-muted mt-2">اسم الطالب يُكتب بخط كبير في وسط الشهادة تلقائياً بعد «المقدمة».</div>
    </div></div>

    @foreach ($templates as $t)
        <form method="POST" action="{{ route('admin.certificates.templates.update', $t) }}" class="card mb-3" id="template-{{ $t->kind }}">
            @csrf @method('PUT')
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-{{ \App\Models\Certificate::ICONS[$t->kind] }}" style="color: {{ $t->color }}"></i> {{ $t->label() }}</span>
                <a href="{{ route('admin.certificates.templates.preview', $t) }}" class="btn btn-sm btn-outline-secondary" data-no-loading><i class="bi bi-eye"></i> معاينة PDF</a>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <x-field col="col-md-8" label="عنوان الشهادة" name="title" :value="$t->title" required />
                    <div class="col-md-4">
                        <label class="form-label">لون الإطار</label>
                        <select name="color" class="form-select">@foreach (\App\Models\CertificateTemplate::COLORS as $hex => $name)<option value="{{ $hex }}" @selected($t->color === $hex)>{{ $name }}</option>@endforeach</select>
                    </div>
                    <x-field col="col-12" label="المقدمة (قبل اسم الطالب)" name="intro" :value="$t->intro" />
                    <div class="col-12">
                        <label class="form-label">نص الشهادة (بعد اسم الطالب)</label>
                        <textarea name="body" rows="3" class="form-control @error('body') is-invalid @enderror" required>{{ old('body', $t->body) }}</textarea>@error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <x-field col="col-12" label="الخاتمة (دعاء أو تهنئة)" name="closing" :value="$t->closing" />
                    <x-field col="col-md-3" label="صفة الموقّع الأول" name="signer1_title" :value="$t->signer1_title" />
                    <x-field col="col-md-3" label="اسم الموقّع الأول" name="signer1_name" :value="$t->signer1_name" />
                    <x-field col="col-md-3" label="صفة الموقّع الثاني" name="signer2_title" :value="$t->signer2_title" />
                    <x-field col="col-md-3" label="اسم الموقّع الثاني" name="signer2_name" :value="$t->signer2_name" />
                    <x-field col="col-12" label="سطر التذييل" name="footer" :value="$t->footer" />
                </div>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary"><i class="bi bi-check2"></i> حفظ النصوص</button></div>
        </form>
    @endforeach
@endif
@endsection
