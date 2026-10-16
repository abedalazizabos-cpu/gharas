@extends('auth.layout')
@section('title', 'استعادة كلمة المرور')
@section('form')
    <h1 class="h5 fw-bold text-center mb-2">استعادة كلمة المرور</h1>
    <p class="text-muted small text-center mb-4">أدخل بريدك الإلكتروني المسجل وسنرسل لك رابطاً لإعادة تعيين كلمة المرور.<br>أولياء الأمور الذين يدخلون برقم الجوال: تواصلوا مع إدارة المركز لإعادة تعيين كلمة المرور.</p>
    @if (session('success'))<div class="alert alert-success small">{{ session('success') }}</div>@endif
    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf
        <div class="mb-3">
            <label class="form-label" for="email">البريد الإلكتروني</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required dir="ltr">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <button class="btn btn-primary w-100">إرسال الرابط</button>
    </form>
    <p class="text-center small mt-3 mb-0"><a href="{{ route('login') }}">العودة لتسجيل الدخول</a></p>
@endsection
