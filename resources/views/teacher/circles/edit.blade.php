@extends('layouts.app')
@section('title', 'تعديل الحلقة')
@section('content')
<x-page-header :title="'تعديل: '.$circle->name" :crumbs="['حلقاتي' => route('teacher.circles.index'), $circle->name => route('teacher.circles.show', $circle), 'تعديل']" />
<form method="POST" action="{{ route('teacher.circles.update', $circle) }}" class="card" novalidate>
    @csrf @method('PUT')
    <div class="card-body">
        <div class="row g-3">
            <x-field name="name" label="اسم الحلقة" :value="$circle->name" required col="col-md-6" />
            <div class="col-md-6">
                <label class="form-label">أيام الدراسة</label>
                <input class="form-control" value="{{ $circle->daysLabel() }} (الجمعة إجازة)" disabled>
            </div>
            @include('admin.circles._timing', ['timingHelp' => 'يظهر الموعد الجديد للطلاب وأولياء الأمور والإدارة فوراً.'])
        </div>
    </div>
    <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
        <a href="{{ route('teacher.circles.show', $circle) }}" class="btn btn-light">إلغاء</a>
        <button class="btn btn-primary px-4"><i class="bi bi-save ms-1"></i> حفظ</button>
    </div>
</form>
@endsection
