@extends('layouts.app')
@section('title', 'طلب إضافة طالب')
@section('content')
<x-page-header title="طلب إضافة طالب جديد" :crumbs="['حلقاتي' => route('teacher.circles.index'), $circle->name => route('teacher.circles.show', $circle), 'طلب إضافة طالب']" />

<div class="alert {{ $freeSeats ? 'alert-light border' : 'alert-warning' }} small">
    <i class="bi bi-info-circle text-success"></i>
    يُرسل الطلب إلى إدارة المركز، ويُضاف الطالب إلى «{{ $circle->name }}» بعد موافقة المدير فقط.
    سعة الحلقة {{ $circle->capacity }} طالباً، والمقاعد المتاحة الآن <strong>{{ $freeSeats }}</strong> (بعد احتساب الطلبات المنتظرة).
</div>

@if ($freeSeats)
<form method="POST" action="{{ route('teacher.student-requests.store', $circle) }}" class="card" novalidate>
    @csrf
    <div class="card-body"><div class="row g-3">
        <x-field name="student_name" label="اسم الطالب (ثلاثي على الأقل)" required col="col-md-6" />
        <div class="col-md-3">
            <label class="form-label" for="gender">الجنس <span class="text-danger">*</span></label>
            <select id="gender" name="gender" class="form-select @error('gender') is-invalid @enderror">
                <option value="male" @selected(old('gender', 'male') === 'male')>ذكر</option>
                <option value="female" @selected(old('gender') === 'female')>أنثى</option>
            </select>
            @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <x-field name="birth_date" label="تاريخ الميلاد" type="date" required col="col-md-3" />
        <div class="col-md-6">
            <label class="form-label" for="memorized_juz">الأجزاء المحفوظة قبل الالتحاق <span class="text-danger">*</span></label>
            <select id="memorized_juz" name="memorized_juz" class="form-select @error('memorized_juz') is-invalid @enderror">
                @foreach (range(0, 30) as $n)
                    <option value="{{ $n }}" @selected((int) old('memorized_juz', 0) === $n)>{{ $n === 0 ? 'لا شيء (يبدأ من جزء عمّ)' : \App\Support\Juz::count($n).' ('.($n === 1 ? 'الجزء 30' : 'من 30 إلى '.(31 - $n)).')' }}</option>
                @endforeach
            </select>
            @error('memorized_juz')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12"><hr class="my-1"></div>
        <x-field name="guardian_name" label="اسم وليّ الأمر" required col="col-md-5" />
        <x-field name="guardian_phone" label="جوال وليّ الأمر" required col="col-md-4" dir="ltr" placeholder="05xxxxxxxx" help="إن كان مسجلاً من قبل يُضاف الطالب إلى حسابه مع إخوته." />
        <div class="col-md-3">
            <label class="form-label" for="relationship">صلة القرابة</label>
            <select id="relationship" name="relationship" class="form-select">
                @foreach (\App\Models\RegistrationRequest::RELATIONSHIPS as $k => $v)<option value="{{ $k }}" @selected(old('relationship', 'father') === $k)>{{ $v }}</option>@endforeach
            </select>
        </div>
        <x-field name="notes" label="ملاحظات للإدارة (اختياري)" type="textarea" col="col-12" maxlength="500" />
    </div></div>
    <div class="card-footer d-flex justify-content-end gap-2">
        <a href="{{ route('teacher.circles.show', $circle) }}" class="btn btn-outline-secondary">إلغاء</a>
        <button class="btn btn-primary"><i class="bi bi-send"></i> إرسال الطلب للإدارة</button>
    </div>
</form>
@else
    <div class="card"><x-empty icon="people" message="الحلقة مكتملة، فلا يمكن طلب إضافة طلاب جدد إليها. تواصل مع الإدارة إن احتجت زيادة السعة." /></div>
@endif
@endsection
