@extends('layouts.app')
@section('title', $circle->exists ? 'تعديل حلقة' : 'إضافة حلقة')
@section('content')
<x-page-header :title="$circle->exists ? 'تعديل: '.$circle->name : 'إضافة حلقة جديدة'" :crumbs="['الحلقات' => route('admin.circles.index'), $circle->exists ? 'تعديل' : 'إضافة']" />
<form method="POST" action="{{ $circle->exists ? route('admin.circles.update', $circle) : route('admin.circles.store') }}" class="card" novalidate>
    @csrf
    @if ($circle->exists) @method('PUT') @endif
    <div class="card-body">
        <div class="row g-3">
            <x-field name="name" label="اسم الحلقة" :value="$circle->name" required col="col-md-6" />
            <x-field name="code" label="رمز الحلقة" :value="$circle->code" required col="col-md-3" dir="ltr" help="أحرف وأرقام فقط، مثل C-106" />
            <div class="col-md-3">
                <label class="form-label" for="status">الحالة <span class="text-danger">*</span></label>
                <select id="status" name="status" class="form-select">@foreach (\App\Models\Circle::STATUSES as $k => $v)<option value="{{ $k }}" @selected(old('status', $circle->status) === $k)>{{ $v }}</option>@endforeach</select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="teacher_id">المحفظ المسؤول</label>
                <select id="teacher_id" name="teacher_id" class="form-select @error('teacher_id') is-invalid @enderror">
                    <option value="">— بدون محفظ —</option>
                    @foreach ($teachers as $t)<option value="{{ $t->id }}" @selected(old('teacher_id', $circle->teacher_id) == $t->id)>{{ $t->name }}</option>@endforeach
                </select>
                @error('teacher_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <x-field name="level" label="المستوى" :value="$circle->level" col="col-md-3" placeholder="مبتدئ / متوسط / متقدم" />
            <x-field name="age_group" label="الفئة العمرية" :value="$circle->age_group" col="col-md-3" placeholder="مثال: 7 - 10 سنوات" />
            <div class="col-12">
                <label class="form-label">أيام الدراسة <span class="text-danger">*</span> <span class="small text-muted">(الجمعة إجازة)</span></label>
                @php($days = collect(old('days', $circle->days ?? []))->map(fn ($d) => (int) $d))
                <div class="d-flex flex-wrap gap-2">
                    @foreach (\App\Models\Circle::STUDY_DAYS as $num)
                        @php($day = \App\Models\Circle::DAYS[$num])
                        <input type="checkbox" class="btn-check" name="days[]" value="{{ $num }}" id="day-{{ $num }}" @checked($days->contains($num))>
                        <label class="btn btn-outline-primary btn-sm" for="day-{{ $num }}">{{ $day }}</label>
                    @endforeach
                </div>
                @error('days')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            @include('admin.circles._timing')
            <x-field name="capacity" label="السعة الاستيعابية" type="number" :value="$circle->capacity" required col="col-6 col-md-3" min="1" max="200" />
            <x-field name="location" label="المكان / القاعة" :value="$circle->location" col="col-6 col-md-3" />
            <x-field name="description" label="وصف الحلقة (يظهر في الصفحة العامة)" type="textarea" :value="$circle->description" col="col-12" />
            <div class="col-12">
                <div class="form-check form-switch">
                    <input type="hidden" name="is_public" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_public" name="is_public" value="1" @checked(old('is_public', $circle->is_public))>
                    <label class="form-check-label" for="is_public">عرض الحلقة في الصفحة الرئيسية العامة</label>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
        <a href="{{ route('admin.circles.index') }}" class="btn btn-light">إلغاء</a>
        <button class="btn btn-primary px-4"><i class="bi bi-save ms-1"></i> حفظ</button>
    </div>
</form>
@endsection
