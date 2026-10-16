@extends('layouts.app')
@section('title', 'الأدوار والصلاحيات')
@section('content')
<x-page-header title="الأدوار والصلاحيات" :crumbs="['الأدوار والصلاحيات']">
    <a href="{{ route('admin.access.users') }}" class="btn btn-outline-primary"><i class="bi bi-people"></i> المستخدمون وصلاحياتهم</a>
</x-page-header>
<div class="alert alert-info small"><i class="bi bi-shield-check"></i> أطفئ أي صلاحية لتمنعها عن كل أصحاب الدور. يمكنك بعدها السماح بها أو منعها لمستخدم بعينه من صفحة «المستخدمون وصلاحياتهم». المدير الأعلى يملك كل الصلاحيات دائماً، والإعدادات والنسخ الاحتياطي له وحده.</div>
<form method="POST" action="{{ route('admin.access.roles.update') }}">
    @csrf @method('PUT')
    <div class="row g-3">
        @foreach ($matrix as $role => $perms)
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-bold">{{ \App\Models\User::ROLES[$role] }}</span>
                        <span class="badge text-bg-light border">{{ $counts[$role] ?? 0 }} حساب</span>
                    </div>
                    <div class="card-body">
                        @foreach ($perms as $key => $p)
                            <div class="form-check form-switch mb-2">
                                <input type="hidden" name="perm[{{ $role }}][{{ $key }}]" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="p-{{ $role }}-{{ $key }}" name="perm[{{ $role }}][{{ $key }}]" value="1" @checked($p['on'])>
                                <label class="form-check-label" for="p-{{ $role }}-{{ $key }}">{{ $p['label'] }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="d-flex justify-content-end mt-3"><button class="btn btn-primary px-4"><i class="bi bi-save ms-1"></i> حفظ الصلاحيات</button></div>
</form>
@endsection
