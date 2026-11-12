@extends('layouts.app')
@section('title', 'طلبات التسجيل')
@section('content')
<x-page-header title="طلبات تسجيل الطلاب الجدد" :crumbs="['الطلاب' => route('admin.students.index'), 'طلبات التسجيل']">
    <a href="{{ route('registration.create') }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-box-arrow-up-left"></i> نموذج التسجيل في الموقع</a>
</x-page-header>

<div class="alert alert-light border small"><i class="bi bi-info-circle text-success"></i> يملأ وليّ الأمر نموذج التسجيل في الموقع، فيصل الطلب هنا. عند القبول يُضاف الطالب إلى الحلقة التي تختارها، ويُربط بحساب وليّ الأمر إن كان رقمه مسجلاً (مع إخوته)، أو يُنشأ له حساب جديد بكلمة مرور مؤقتة تظهر لك لتسلّمها له@if (app(\App\Services\SmsService::class)->autoEnabled('registration'))، وتصله رسالة SMS بالقبول وبيانات الدخول@endif.</div>

<ul class="nav nav-pills mb-3 gap-1">
    @foreach (\App\Models\RegistrationRequest::STATUSES as $k => $v)
        <li class="nav-item"><a class="nav-link {{ $status === $k ? 'active' : '' }}" href="{{ route('admin.registrations.index', ['status' => $k]) }}">{{ $v }} <span class="badge text-bg-{{ $status === $k ? 'light' : \App\Models\RegistrationRequest::STATUS_COLORS[$k] }}">{{ $counts[$k] ?? 0 }}</span></a></li>
    @endforeach
</ul>

@forelse ($requests as $r)
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between gap-2 align-items-center">
            <span class="fw-semibold"><i class="bi bi-person-plus text-success"></i> {{ $r->student_name }} <small class="text-muted">· {{ $r->gender === 'female' ? 'أنثى' : 'ذكر' }}@if ($r->age() !== null) · {{ $r->age() }} سنة@endif · {{ $r->memorized_juz ? 'يحفظ '.\App\Support\Juz::count($r->memorized_juz) : 'لم يحفظ أجزاء بعد' }}</small></span>
            <small class="text-muted">أُرسل {{ $r->created_at->diffForHumans() }} · {{ $r->created_at->format('Y/m/d') }}</small>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-5 small">
                    <div><i class="bi bi-house-heart text-muted"></i> {{ \App\Models\RegistrationRequest::RELATIONSHIPS[$r->relationship] ?? '' }}: <strong>{{ $r->guardian_name }}</strong> · <a href="tel:{{ $r->guardian_phone }}" dir="ltr">{{ $r->guardian_phone }}</a></div>
                    @if (isset($parents[$r->guardian_phone]))<div class="text-success mt-1"><i class="bi bi-link-45deg"></i> الرقم مسجل لوليّ الأمر {{ $parents[$r->guardian_phone] }}، فيُضاف الطالب مع إخوته في حسابه.</div>@endif
                    @if ($r->birth_date)<div class="mt-1 text-muted">تاريخ الميلاد {{ $r->birth_date->format('Y/m/d') }}</div>@endif
                    @if ($r->notes)<div class="mt-2 p-2 bg-light rounded">{{ $r->notes }}</div>@endif
                </div>
                <div class="col-lg-7">
                    @if ($r->status === 'pending')
                        <form method="POST" action="{{ route('admin.registrations.accept', $r) }}" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-sm-8">
                                <label class="form-label small">الحلقة</label>
                                <select name="circle_id" class="form-select form-select-sm">
                                    <option value="">بدون حلقة الآن</option>
                                    @foreach ($circles as $c)<option value="{{ $c->id }}">{{ $c->name }} · {{ $c->teacher?->name ?? 'بلا محفظ' }} ({{ $c->students_count }}/{{ $c->capacity }})</option>@endforeach
                                </select>
                                @if (\App\Models\Setting::get('allow_capacity_override') === '1')<div class="form-check small mt-1"><input class="form-check-input" type="checkbox" name="override_capacity" value="1" id="ov-{{ $r->id }}"><label class="form-check-label" for="ov-{{ $r->id }}">تجاوز سعة الحلقة</label></div>@endif
                            </div>
                            <div class="col-sm-4"><button class="btn btn-success btn-sm w-100"><i class="bi bi-check2"></i> قبول وإضافة</button></div>
                        </form>
                        <form method="POST" action="{{ route('admin.registrations.reject', $r) }}" class="d-flex gap-2 mt-2">
                            @csrf
                            <input name="reject_reason" class="form-control form-control-sm" maxlength="255" placeholder="سبب الرفض (اختياري، للسجل)">
                            <button class="btn btn-outline-danger btn-sm text-nowrap"><i class="bi bi-x-lg"></i> رفض</button>
                        </form>
                    @else
                        <div class="small">
                            <span class="badge text-bg-{{ \App\Models\RegistrationRequest::STATUS_COLORS[$r->status] }}">{{ $r->statusLabel() }}</span>
                            {{ $r->handled_at?->format('Y/m/d') }} · {{ $r->handler?->nameFor(auth()->user()) }}
                            @if ($r->student)<div class="mt-2"><a href="{{ route('admin.students.show', $r->student) }}"><i class="bi bi-person"></i> ملف الطالب</a>@if ($r->student->circle) · {{ $r->student->circle->name }}@endif</div>@endif
                            @if ($r->reject_reason)<div class="mt-2 text-muted">السبب: {{ $r->reject_reason }}</div>@endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@empty
    <div class="card"><x-empty icon="inbox" message="لا توجد طلبات هنا." /></div>
@endforelse
{{ $requests->links() }}
@endsection
