@extends('layouts.app')
@section('title', 'تقييم طالب')
@section('content')
<x-page-header title="تقييم طالب" :crumbs="['التقييمات' => route('teacher.assessments.index'), 'تقييم جديد']" />
@if ($criteria->isEmpty())
    <div class="card"><x-empty icon="sliders" message="لا توجد معايير تقييم مفعّلة. تواصل مع الإدارة." /></div>
@elseif ($students->isEmpty())
    <div class="card"><x-empty icon="people" message="لا يوجد طلاب نشطون في حلقاتك." /></div>
@else
<form method="POST" action="{{ route('teacher.assessments.store') }}" class="card" novalidate>
    @csrf
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label" for="student_id">الطالب <span class="text-danger">*</span></label>
                <select id="student_id" name="student_id" class="form-select @error('student_id') is-invalid @enderror" required>
                    <option value="">— اختر الطالب —</option>
                    @foreach ($students as $s)<option value="{{ $s->id }}" @selected(old('student_id', $selected) == $s->id)>{{ $s->full_name }}</option>@endforeach
                </select>
                @error('student_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <x-field name="assessed_on" label="تاريخ التقييم" type="date" :value="now()->toDateString()" required col="col-md-3" :max="now()->toDateString()" />
        </div>
        <h6 class="fw-bold text-success">معايير التقييم</h6>
        <div class="row g-3 mb-3">
            @foreach ($criteria as $c)
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="score-{{ $c->id }}">{{ $c->name }} <small class="text-muted">(من {{ $c->max_score }})</small></label>
                    <input id="score-{{ $c->id }}" type="number" name="scores[{{ $c->id }}]" value="{{ old("scores.{$c->id}") }}" min="0" max="{{ $c->max_score }}" data-criterion-max="{{ $c->max_score }}" class="form-control @error("scores.{$c->id}") is-invalid @enderror" required>
                    @error("scores.{$c->id}")<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            @endforeach
        </div>
        <div class="alert alert-light border">المجموع: <strong id="assessment-total">0</strong></div>
        <div class="row g-3">
            <x-field name="notes" label="ملاحظات المحفظ" type="textarea" col="col-12" />
            <div class="col-12"><div class="form-check form-switch"><input type="hidden" name="shared_with_parent" value="0"><input class="form-check-input" type="checkbox" role="switch" name="shared_with_parent" value="1" id="swp" @checked(old('shared_with_parent', true))><label class="form-check-label" for="swp">مشاركة التقييم والملاحظات مع ولي الأمر</label></div></div>
        </div>
    </div>
    <div class="card-footer bg-transparent d-flex justify-content-end"><button class="btn btn-primary px-4"><i class="bi bi-save ms-1"></i> حفظ التقييم</button></div>
</form>
@endif
@endsection
