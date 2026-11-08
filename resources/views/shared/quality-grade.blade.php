@extends('layouts.app')
@section('title', 'رصد نتيجة اختبار الجودة')
@section('content')
@php($s = $test->student)
@php($old = $test->scores->keyBy('quality_criterion_id'))
<x-page-header title="رصد نتيجة اختبار الجودة" :crumbs="['اختبارات الجودة' => $back, 'رصد النتيجة']" />

<div class="card mb-3"><div class="card-body d-flex flex-wrap gap-4">
    <div><small class="text-muted d-block">الطالب</small><strong>{{ $s->full_name }}</strong> <small class="text-muted">{{ $s->circle?->name }}</small></div>
    <div><small class="text-muted d-block">{{ $test->kindLabel() }}</small><strong>{{ $test->subjectLabel() }}</strong></div>
    <div><small class="text-muted d-block">شيخ الجودة</small><strong>{{ $test->examiner?->name ?? '—' }}</strong></div>
    <div><small class="text-muted d-block">الحالة</small><span class="badge text-bg-{{ $test->statusColor() }}">{{ $test->statusLabel() }}</span></div>
</div></div>

@if ($criteria->isEmpty())
    <div class="card"><x-empty icon="sliders" message="لا توجد ضوابط تقييم مفعّلة. يضيفها المدير من «لجنة الجودة ← الضوابط»." /></div>
@else
<form method="POST" action="{{ $action }}" class="card" novalidate>
    @csrf
    <div class="card-body">
        <div class="row g-3 mb-3">
            <x-field name="tested_on" label="تاريخ الاختبار" type="date" :value="optional($test->tested_on)->format('Y-m-d') ?? now()->toDateString()" required col="col-md-3" :max="now()->toDateString()" />
        </div>
        <h6 class="fw-bold text-success">ضوابط التقييم</h6>
        <div class="row g-3 mb-3">
            @foreach ($criteria as $c)
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="score-{{ $c->id }}">{{ $c->name }} <small class="text-muted">(من {{ $c->max_score }})</small></label>
                    <input id="score-{{ $c->id }}" type="number" name="scores[{{ $c->id }}]" value="{{ old("scores.{$c->id}", $old[$c->id]->score ?? null) }}" min="0" max="{{ $c->max_score }}" data-criterion-max="{{ $c->max_score }}" class="form-control @error("scores.{$c->id}") is-invalid @enderror" required>
                    @error("scores.{$c->id}")<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            @endforeach
        </div>
        <div class="alert alert-light border">المجموع: <strong id="assessment-total">0</strong> · درجة النجاح {{ \App\Services\QualityService::passScore() }}%</div>
        <x-field name="notes" label="ملاحظات شيخ الجودة (تظهر للإدارة والمحفظ)" type="textarea" :value="$test->notes" col="col-12" />
    </div>
    <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
        <a href="{{ $back }}" class="btn btn-light">رجوع</a>
        <button class="btn btn-primary px-4"><i class="bi bi-save ms-1"></i> حفظ النتيجة</button>
    </div>
</form>
@endif
@endsection
