@extends('layouts.app')
@section('title', 'سجل الحفظ والمراجعة')
@section('content')
<x-page-header title="سجل الحفظ والمراجعة" :crumbs="['سجل الحفظ']">
    <a href="{{ route('teacher.memorization.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> تسجيل تسميع</a>
</x-page-header>
<form method="GET" class="filter-bar row g-2" data-no-loading>
    <div class="col-md-5"><select name="student" class="form-select"><option value="">كل الطلاب</option>@foreach ($students as $s)<option value="{{ $s->id }}" @selected(request('student') == $s->id)>{{ $s->full_name }}</option>@endforeach</select></div>
    <div class="col-md-4"><select name="type" class="form-select"><option value="">كل الأنواع</option>@foreach (\App\Models\MemorizationRecord::TYPES as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="col-md-3"><button class="btn btn-primary w-100"><i class="bi bi-funnel"></i> تصفية</button></div>
</form>
<p class="small text-muted"><i class="bi bi-info-circle"></i> يمكنك تعديل سجلاتك خلال {{ \App\Models\Setting::get('teacher_edit_days') }} أيام من إنشائها، وتُوثَّق التعديلات. الحذف من صلاحية الإدارة.</p>
<div class="card">@include('shared._memorization-table', ['showStudent' => true, 'showShared' => true, 'editRoute' => 'teacher.memorization.edit'])</div>
@endsection
