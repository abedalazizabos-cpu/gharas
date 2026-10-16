@extends('layouts.app')
@section('title', 'الحفظ والمراجعة')
@section('content')
<x-page-header title="سجلات الحفظ والمراجعة" :crumbs="['الحفظ والمراجعة']" />
<form method="GET" class="filter-bar row g-2 align-items-end" data-no-loading>
    <div class="col-md-3"><label class="form-label small">الطالب</label><input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="اسم أو رقم الطالب"></div>
    <div class="col-6 col-md-2"><label class="form-label small">الحلقة</label><select name="circle" class="form-select"><option value="">الكل</option>@foreach ($circles as $c)<option value="{{ $c->id }}" @selected(request('circle') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label small">المحفظ</label><select name="teacher" class="form-select"><option value="">الكل</option>@foreach ($teachers as $t)<option value="{{ $t->id }}" @selected(request('teacher') == $t->id)>{{ $t->name }}</option>@endforeach</select></div>
    <div class="col-6 col-md-1"><label class="form-label small">النوع</label><select name="type" class="form-select"><option value="">الكل</option>@foreach (\App\Models\MemorizationRecord::TYPES as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label small">من</label><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
    <div class="col-6 col-md-1"><label class="form-label small">إلى</label><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
    <div class="col-6 col-md-1"><button class="btn btn-primary w-100"><i class="bi bi-search"></i></button></div>
</form>
<div class="card">@include('shared._memorization-table', ['showStudent' => true, 'showShared' => true, 'editRoute' => 'admin.memorization.edit', 'deleteRoute' => 'admin.memorization.destroy'])</div>
@endsection
