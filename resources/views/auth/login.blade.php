@extends('auth.layout')
@section('title', 'تسجيل الدخول')
@section('form')
    <h1 class="h5 fw-bold text-center mb-4">تسجيل الدخول إلى النظام</h1>
    @if (session('success'))<div class="alert alert-success small">{{ session('success') }}</div>@endif
    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf
        <div class="mb-3">
            <label class="form-label" for="login">رقم الجوال أو البريد الإلكتروني</label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="bi bi-phone"></i></span>
                <input id="login" type="text" name="login" value="{{ old('login') }}" class="form-control @error('login') is-invalid @enderror" required autofocus autocomplete="username" inputmode="text" dir="ltr" placeholder="05xxxxxxxx">
                @error('login')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="form-text">أولياء الأمور: ادخلوا برقم الجوال المسجّل لدى المركز.</div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">كلمة المرور</label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="current-password" dir="ltr">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                <label class="form-check-label" for="remember">تذكرني</label>
            </div>
            <a href="{{ route('password.request') }}" class="small">نسيت كلمة المرور؟</a>
        </div>
        <button class="btn btn-primary w-100 py-2"><i class="bi bi-box-arrow-in-left ms-1"></i> دخول</button>
    </form>
    <p class="text-center text-muted small mt-4 mb-0">الحسابات تُنشأ من قبل إدارة المركز فقط.<br><a href="{{ route('home') }}">العودة للصفحة الرئيسية</a></p>
@endsection
