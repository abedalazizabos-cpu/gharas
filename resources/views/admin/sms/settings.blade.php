@extends('layouts.app')
@section('title', 'إعدادات رسائل SMS')
@section('content')
<x-page-header title="ربط جوال المركز لإرسال SMS" :crumbs="['رسائل SMS' => route('admin.sms.index'), 'إعدادات الربط']" />

<div class="card mb-3"><div class="card-body small">
    <div class="fw-semibold mb-2"><i class="bi bi-phone"></i> تصل الرسائل لأولياء الأمور من رقم الشريحة الموجودة في جوال المركز. خطوات الربط مرة واحدة:</div>
    <ol class="mb-0">
        <li>على جوال أندرويد فيه شريحة رقم المركز ورصيد رسائل، ثبّت تطبيق <strong>SMS Gateway for Android</strong> من Google Play (مطوّره capcom6، وموقعه sms-gate.app).</li>
        <li>افتح التطبيق، وفعّل <strong>Cloud server</strong>، واضغط زر التشغيل، ووافق على صلاحية إرسال الرسائل.</li>
        <li>يظهر في التطبيق <strong>اسم مستخدم</strong> و<strong>كلمة مرور</strong>. انسخهما في الخانتين أدناه، واكتب رقم جوالك في «رقم التجربة»، ثم احفظ.</li>
        <li>اترك الجوال مشغّلاً ومتصلاً بالإنترنت، واستثنِ التطبيق من «توفير البطارية» في إعدادات الجوال.</li>
        <li>يُنصح أن تضبط في التطبيق مهلة بين الرسائل (Settings ← Messages)، حتى لا توقف شركة الاتصالات الإرسال الكثير دفعة واحدة.</li>
    </ol>
</div></div>

<form method="POST" action="{{ route('admin.sms.settings.update') }}" class="card">
    @csrf @method('PUT')
    <div class="card-body row g-3">
        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="sms_enabled" value="1" id="sms_enabled" @checked($s['sms_enabled'] === '1')><label class="form-check-label fw-semibold" for="sms_enabled">تفعيل إرسال الرسائل</label></div></div>
        <x-field name="sms_username" label="اسم المستخدم (من التطبيق)" :value="$s['sms_username']" col="col-md-4" dir="ltr" autocomplete="off" />
        <div class="col-md-4">
            <label class="form-label" for="sms_password">كلمة المرور (من التطبيق)</label>
            <input type="password" name="sms_password" id="sms_password" class="form-control" dir="ltr" autocomplete="new-password" placeholder="{{ $hasPassword ? 'محفوظة، اتركها فارغة لإبقائها' : '' }}">
        </div>
        <x-field name="sms_country_code" label="رمز الدولة لأرقام الأهالي" :value="$s['sms_country_code'] ?: '+970'" col="col-md-4" dir="ltr" help="مثل +970 فلسطين، +966 السعودية. يُضاف للأرقام التي تبدأ بصفر." />
        <x-field name="sms_signature" label="توقيع في آخر كل رسالة (اختياري)" :value="$s['sms_signature']" col="col-md-6" help="مثل: مركز غراس" />
        <x-field name="sms_url" label="عنوان البوابة (اتركه فارغاً)" :value="$s['sms_url']" col="col-md-6" dir="ltr" :help="'الافتراضي: '.\App\Services\SmsService::DEFAULT_URL" />
        <div class="col-12">
            <label class="form-label fw-semibold">رسائل تلقائية لولي الأمر</label>
            @foreach (\App\Services\SmsService::AUTO as $k => $label)
                <div class="form-check"><input class="form-check-input" type="checkbox" name="sms_auto_{{ $k }}" value="1" id="auto-{{ $k }}" @checked($s['sms_auto_'.$k] === '1')><label class="form-check-label" for="auto-{{ $k }}">{{ $label }}</label></div>
            @endforeach
            <div class="form-text">تصل أيضاً إشعارات الموقع كالمعتاد. كل رسالة تُحسب من رصيد الشريحة.</div>
        </div>
        <x-field name="test_phone" label="رقم التجربة (اختياري)" col="col-md-4" dir="ltr" help="تُرسل إليه رسالة تجربة بعد الحفظ." />
    </div>
    <div class="card-footer text-end"><button class="btn btn-primary"><i class="bi bi-check2"></i> حفظ</button></div>
</form>
@endsection
