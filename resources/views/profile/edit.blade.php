@extends('layouts.app')
@section('title', 'الملف الشخصي')
@section('content')
<x-page-header title="الملف الشخصي وإعدادات الحساب" :crumbs="['الملف الشخصي']" />
<div class="row g-3">
    <div class="col-lg-6">
        <form method="POST" action="{{ route('profile.update') }}" class="card" novalidate>
            @csrf @method('PUT')
            <div class="card-header">البيانات الشخصية</div>
            <div class="card-body"><div class="row g-3">
                <x-field name="name" label="الاسم" :value="$user->name" required col="col-12" />
                <x-field name="email" label="البريد الإلكتروني" type="email" :value="$user->email" :required="! $user->isParent()" col="col-12" dir="ltr" />
                <x-field name="phone" :label="$user->isParent() ? 'رقم الجوال (اسم الدخول)' : 'رقم الجوال'" :value="$user->phone" :required="$user->isParent()" col="col-12" dir="ltr" />
                <div class="col-12 small text-muted">نوع الحساب: {{ $user->roleLabel() }} — لا يمكن تغيير الدور أو الصلاحيات من هنا.</div>
            </div></div>
            <div class="card-footer bg-transparent text-end"><button class="btn btn-primary">حفظ البيانات</button></div>
        </form>
    </div>
    <div class="col-lg-6">
        <form method="POST" action="{{ route('profile.password') }}" class="card" novalidate>
            @csrf @method('PUT')
            <div class="card-header">تغيير كلمة المرور</div>
            <div class="card-body"><div class="row g-3">
                <x-field name="current_password" label="كلمة المرور الحالية" type="password" required col="col-12" dir="ltr" autocomplete="current-password" />
                <x-field name="password" label="كلمة المرور الجديدة" type="password" required col="col-12" dir="ltr" autocomplete="new-password" help="8 أحرف على الأقل، تحتوي على حروف وأرقام." />
                <x-field name="password_confirmation" label="تأكيد كلمة المرور" type="password" required col="col-12" dir="ltr" autocomplete="new-password" />
            </div></div>
            <div class="card-footer bg-transparent text-end"><button class="btn btn-primary">تغيير كلمة المرور</button></div>
        </form>
    </div>
</div>
@endsection
