@extends('auth.layout')
@section('title', 'تعيين كلمة مرور جديدة')
@section('form')
    <h1 class="h5 fw-bold text-center mb-4">تعيين كلمة مرور جديدة</h1>
    <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="mb-3">
            <label class="form-label" for="email">البريد الإلكتروني</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" class="form-control @error('email') is-invalid @enderror" required dir="ltr">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">كلمة المرور الجديدة</label>
            <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" required dir="ltr">
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">8 أحرف على الأقل، تحتوي على حروف وأرقام.</div>
        </div>
        <div class="mb-4">
            <label class="form-label" for="password_confirmation">تأكيد كلمة المرور</label>
            <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required dir="ltr">
        </div>
        <button class="btn btn-primary w-100">حفظ كلمة المرور</button>
    </form>
@endsection
