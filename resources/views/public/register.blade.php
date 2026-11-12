@extends('layouts.public')
@section('title', 'تسجيل طالب جديد | '.\App\Models\Setting::get('center_name'))
@section('body')
<div class="auth-wrap">
    <div class="card auth-card register-card shadow-lg">
        <div class="card-body p-4 p-md-5">
            <div class="d-flex justify-content-center mb-3">@include('partials.brand')</div>
            <h1 class="h5 fw-bold text-center mb-1">طلب تسجيل طالب جديد</h1>
            <p class="text-center text-muted small mb-4">املأ البيانات، وستتواصل معكم إدارة المركز لتحديد الحلقة المناسبة.</p>

            @if (session('registered'))
                <div class="text-center py-4">
                    <i class="bi bi-check-circle-fill text-success" style="font-size:3rem"></i>
                    <h2 class="h5 fw-bold mt-2">وصلنا طلبكم، جزاكم الله خيراً</h2>
                    <p class="text-muted">ستراجع الإدارة الطلب وتتواصل معكم على الجوال الذي كتبتموه. وعند القبول يصلكم حساب وليّ الأمر لمتابعة ابنكم.</p>
                    <a href="{{ route('home') }}" class="btn btn-primary">العودة للصفحة الرئيسية</a>
                </div>
            @else
                @if ($errors->any())<div class="alert alert-danger small">يرجى تصحيح الحقول المظللة بالأحمر.</div>@endif
                <form method="POST" action="{{ route('registration.store') }}" novalidate>
                    @csrf
                    <div class="d-none" aria-hidden="true"><label>الموقع <input name="website" tabindex="-1" autocomplete="off"></label></div>
                    <div class="row g-3">
                        <div class="col-12 fw-semibold text-success small"><i class="bi bi-person"></i> بيانات الطالب</div>
                        <x-field name="student_name" label="اسم الطالب الثلاثي" required col="col-12" maxlength="150" />
                        <div class="col-6">
                            <label class="form-label" for="gender">الجنس <span class="text-danger">*</span></label>
                            <select id="gender" name="gender" class="form-select @error('gender') is-invalid @enderror" required>
                                <option value="">اختر…</option>
                                <option value="male" @selected(old('gender') === 'male')>ذكر</option>
                                <option value="female" @selected(old('gender') === 'female')>أنثى</option>
                            </select>
                            @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <x-field name="birth_date" label="تاريخ الميلاد" type="date" required col="col-6" :max="now()->subDay()->toDateString()" />
                        <div class="col-12">
                            <label class="form-label" for="memorized_juz">كم جزءاً يحفظ الآن؟ <span class="text-danger">*</span></label>
                            <select id="memorized_juz" name="memorized_juz" class="form-select @error('memorized_juz') is-invalid @enderror" required>
                                @for ($j = 0; $j <= 30; $j++)<option value="{{ $j }}" @selected((string) old('memorized_juz', '0') === (string) $j)>{{ \App\Support\Juz::count($j) }}</option>@endfor
                            </select>
                            <div class="form-text">من جزء عمّ صعوداً.</div>
                        </div>
                        <div class="col-12 fw-semibold text-success small mt-4"><i class="bi bi-house-heart"></i> بيانات وليّ الأمر</div>
                        <x-field name="guardian_name" label="اسم وليّ الأمر" required col="col-12" maxlength="150" />
                        <x-field name="guardian_phone" label="رقم الجوال" required col="col-7" dir="ltr" inputmode="numeric" placeholder="05xxxxxxxx" help="يكون رقم الدخول إلى حسابكم بعد القبول." />
                        <div class="col-5">
                            <label class="form-label" for="relationship">صلة القرابة</label>
                            <select id="relationship" name="relationship" class="form-select">
                                @foreach (\App\Models\RegistrationRequest::RELATIONSHIPS as $k => $v)<option value="{{ $k }}" @selected(old('relationship', 'father') === $k)>{{ $v }}</option>@endforeach
                            </select>
                        </div>
                        <x-field name="notes" label="ملاحظات (اختياري)" type="textarea" col="col-12" rows="2" maxlength="500" placeholder="مثل: الوقت المناسب، أو إخوته المسجلون في المركز" />
                    </div>
                    <button class="btn btn-primary w-100 py-2 mt-4"><i class="bi bi-send ms-1"></i> إرسال الطلب</button>
                </form>
                <p class="text-center small mt-3 mb-0"><a href="{{ route('home') }}">العودة للصفحة الرئيسية</a> · <a href="{{ route('login') }}">تسجيل الدخول</a></p>
            @endif
        </div>
    </div>
</div>
@endsection
