@extends('layouts.app')
@section('title', $visitor->exists ? 'تعديل زائر' : 'إضافة زائر')
@section('content')
<x-page-header :title="$visitor->exists ? 'تعديل حساب: '.$visitor->name : 'إضافة حساب زائر / داعم'" :crumbs="['الزوار والداعمون' => route('admin.visitors.index'), $visitor->exists ? 'تعديل' : 'إضافة']" />
<form method="POST" action="{{ $visitor->exists ? route('admin.visitors.update', $visitor) : route('admin.visitors.store') }}" class="card" novalidate>
    @csrf
    @if ($visitor->exists) @method('PUT') @endif
    <div class="card-body">@include('admin._account-fields', ['user' => $visitor])</div>
    <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
        <a href="{{ route('admin.visitors.index') }}" class="btn btn-light">إلغاء</a>
        <button class="btn btn-primary px-4"><i class="bi bi-save ms-1"></i> حفظ</button>
    </div>
</form>
@endsection
