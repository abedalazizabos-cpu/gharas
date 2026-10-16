@extends('layouts.app')
@section('title', $parent->exists ? 'تعديل ولي أمر' : 'إضافة ولي أمر')
@section('content')
<x-page-header :title="$parent->exists ? 'تعديل بيانات: '.$parent->name : 'إضافة ولي أمر'" :crumbs="['أولياء الأمور' => route('admin.parents.index'), $parent->exists ? 'تعديل' : 'إضافة']" />
<form method="POST" action="{{ $parent->exists ? route('admin.parents.update', $parent) : route('admin.parents.store') }}" class="card" novalidate>
    @csrf
    @if ($parent->exists) @method('PUT') @endif
    <div class="card-body">
        <h6 class="fw-bold text-success mb-3">بيانات الحساب</h6>
        @include('admin._account-fields', ['user' => $parent, 'loginByPhone' => true])
        <h6 class="fw-bold text-success mb-3">بيانات التواصل</h6>
        <div class="row g-3 mb-4">
            <x-field name="alt_phone" label="جوال بديل" :value="$parent->parentProfile?->alt_phone" col="col-md-4" dir="ltr" />
            <x-field name="national_id" label="رقم الهوية (اختياري)" :value="$parent->parentProfile?->national_id" col="col-md-4" />
            <x-field name="address" label="العنوان (اختياري)" :value="$parent->parentProfile?->address" col="col-md-4" />
        </div>
        <h6 class="fw-bold text-success mb-3">الأبناء المرتبطون</h6>
        <div class="row g-3">
            @php($selected = collect(old('student_ids', $parent->exists ? $parent->children->pluck('id')->all() : []))->map(fn ($v) => (int) $v))
            <div class="col-md-8">
                <select name="student_ids[]" class="form-select" multiple size="7">
                    @foreach ($students as $s)<option value="{{ $s->id }}" @selected($selected->contains($s->id))>{{ $s->full_name }} ({{ $s->student_number }})</option>@endforeach
                </select>
                <div class="form-text">اضغط Ctrl (أو ⌘) لاختيار أكثر من طالب.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">صلة القرابة</label>
                @php($rel = old('relationship', $parent->exists ? ($parent->children->first()?->pivot->relationship ?? 'father') : 'father'))
                <select name="relationship" class="form-select">
                    <option value="father" @selected($rel === 'father')>الأب</option>
                    <option value="mother" @selected($rel === 'mother')>الأم</option>
                    <option value="guardian" @selected($rel === 'guardian')>وليّ آخر</option>
                </select>
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
        <a href="{{ route('admin.parents.index') }}" class="btn btn-light">إلغاء</a>
        <button class="btn btn-primary px-4"><i class="bi bi-save ms-1"></i> حفظ</button>
    </div>
</form>
@endsection
