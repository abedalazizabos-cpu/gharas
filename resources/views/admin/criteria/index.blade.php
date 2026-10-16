@extends('layouts.app')
@section('title', 'معايير التقييم')
@section('content')
<x-page-header title="معايير التقييم" :crumbs="['معايير التقييم']" />
<div class="alert alert-info small"><i class="bi bi-info-circle"></i> يقيّم المحفظ الطالب حسب المعايير المفعّلة هنا. تُحفظ الدرجة العظمى لكل معيار مع كل تقييم، فلا تتأثر التقييمات السابقة بأي تعديل لاحق.</div>
<div class="card mb-3">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead><tr><th>المعيار</th><th style="width:140px">الدرجة العظمى</th><th style="width:110px">الترتيب</th><th style="width:100px">مفعّل</th><th style="width:170px"></th></tr></thead>
            <tbody>
            @forelse ($criteria as $c)
                <tr>
                    <td><input form="crit-{{ $c->id }}" name="name" value="{{ $c->name }}" class="form-control form-control-sm" required></td>
                    <td><input form="crit-{{ $c->id }}" type="number" name="max_score" value="{{ $c->max_score }}" min="1" max="100" class="form-control form-control-sm" required></td>
                    <td><input form="crit-{{ $c->id }}" type="number" name="sort_order" value="{{ $c->sort_order }}" min="0" class="form-control form-control-sm"></td>
                    <td><div class="form-check form-switch"><input form="crit-{{ $c->id }}" class="form-check-input" type="checkbox" name="is_active" value="1" @checked($c->is_active)></div></td>
                    <td class="table-actions">
                        <button form="crit-{{ $c->id }}" class="btn btn-sm btn-primary"><i class="bi bi-save"></i> حفظ</button>
                        <x-delete-form :action="route('admin.criteria.destroy', $c)" label="" message="إن كان المعيار مستخدماً في تقييمات سابقة فسيتم إيقافه بدلاً من حذفه." />
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty icon="sliders" message="لا توجد معايير. أضف معياراً من النموذج أدناه." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @foreach ($criteria as $c)<form method="POST" action="{{ route('admin.criteria.update', $c) }}" id="crit-{{ $c->id }}">@csrf @method('PUT')</form>@endforeach
    <div class="card-footer bg-transparent small text-muted">مجموع الدرجات العظمى للمعايير المفعّلة: {{ $criteria->where('is_active', true)->sum('max_score') }}</div>
</div>
<form method="POST" action="{{ route('admin.criteria.store') }}" class="card card-body">
    @csrf
    <h6 class="fw-bold">إضافة معيار</h6>
    <div class="row g-2 align-items-end">
        <x-field name="name" label="اسم المعيار" required col="col-md-5" />
        <x-field name="max_score" label="الدرجة العظمى" type="number" :value="10" required col="col-md-2" min="1" max="100" />
        <x-field name="sort_order" label="الترتيب" type="number" :value="$criteria->count() + 1" col="col-md-2" min="0" />
        <div class="col-md-1"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="new-active" checked><label class="form-check-label" for="new-active">مفعّل</label></div></div>
        <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> إضافة</button></div>
    </div>
</form>
@endsection
