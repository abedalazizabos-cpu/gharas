@extends('layouts.app')
@section('title', $admin->exists ? 'تعديل مدير' : 'إضافة مدير')
@section('content')
<x-page-header :title="$admin->exists ? 'تعديل حساب: '.$admin->name : 'إضافة حساب مدير'" :crumbs="['حسابات المديرين' => route('admin.admins.index'), $admin->exists ? 'تعديل' : 'إضافة']" />
<form method="POST" action="{{ $admin->exists ? route('admin.admins.update', $admin) : route('admin.admins.store') }}" class="card" novalidate>
    @csrf
    @if ($admin->exists) @method('PUT') @endif
    <div class="card-body">@include('admin._account-fields', ['user' => $admin])</div>
    <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
        <a href="{{ route('admin.admins.index') }}" class="btn btn-light">إلغاء</a>
        <button class="btn btn-primary px-4"><i class="bi bi-save ms-1"></i> حفظ</button>
    </div>
</form>
@endsection
