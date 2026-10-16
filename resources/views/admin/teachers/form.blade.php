@extends('layouts.app')
@section('title', $teacher->exists ? 'تعديل محفظ' : 'إضافة محفظ')
@section('content')
<x-page-header :title="$teacher->exists ? 'تعديل بيانات: '.$teacher->name : 'إضافة محفظ جديد'" :crumbs="['المشايخ' => route('admin.teachers.index'), $teacher->exists ? 'تعديل' : 'إضافة']" />
<form method="POST" action="{{ $teacher->exists ? route('admin.teachers.update', $teacher) : route('admin.teachers.store') }}" class="card" novalidate>
    @csrf
    @if ($teacher->exists) @method('PUT') @endif
    <div class="card-body">
        <h6 class="fw-bold text-success mb-3">بيانات الحساب</h6>
        @include('admin._account-fields', ['user' => $teacher])
        <h6 class="fw-bold text-success mb-3">البيانات المهنية</h6>
        <div class="row g-3">
            <x-field name="qualification" label="المؤهل العلمي" :value="$teacher->teacherProfile?->qualification" col="col-md-4" />
            <x-field name="ijazah" label="الإجازة" :value="$teacher->teacherProfile?->ijazah" col="col-md-4" />
            <x-field name="hire_date" label="تاريخ التعيين" type="date" :value="optional($teacher->teacherProfile?->hire_date)->format('Y-m-d')" col="col-md-4" />
            <x-field name="notes" label="ملاحظات إدارية" type="textarea" :value="$teacher->teacherProfile?->notes" col="col-12" />
        </div>
        <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle"></i> يتم تعيين الحلقات للمحفظ من صفحة «الحلقات».</p>
    </div>
    <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
        <a href="{{ route('admin.teachers.index') }}" class="btn btn-light">إلغاء</a>
        <button class="btn btn-primary px-4"><i class="bi bi-save ms-1"></i> حفظ</button>
    </div>
</form>
@endsection
