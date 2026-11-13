@extends('layouts.app')
@section('title', 'إعدادات رسائل SMS')
@section('content')
<x-page-header title="إعدادات رسائل SMS" :crumbs="['رسائل SMS' => route('admin.sms.index'), 'إعدادات الربط']" />

<div class="alert alert-light border small"><i class="bi bi-info-circle text-success"></i>
    طريقتان للإرسال، فعّل إحداهما أو كلتيهما:
    <strong>جوال المركز</strong> (مجاني عدا رصيد الشريحة، وتصل الرسالة من رقم المركز)،
    و<strong>مزوّد رسائل مدفوع</strong> (تصل الرسالة باسم المركز، وأسرع للإرسال الكثير، ولا يحتاج جوالاً مشغّلاً).
    عند تفعيل الطريقتين تختار عند كل رسالة يدوية، وتختار هنا طريقة الرسائل التلقائية.
</div>

<form method="POST" action="{{ route('admin.sms.settings.update') }}">
    @csrf @method('PUT')

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-phone"></i> الطريقة الأولى: جوال المركز</span>
            @if (isset($channels['phone']))<span class="badge text-bg-success">مفعّل</span>@endif
        </div>
        <div class="card-body">
            <ol class="small mb-3">
                <li>على جوال أندرويد فيه شريحة رقم المركز ورصيد رسائل، ثبّت تطبيق <strong>SMS Gateway for Android</strong> من Google Play (مطوّره capcom6، وموقعه sms-gate.app).</li>
                <li>افتح التطبيق، وفعّل <strong>Cloud server</strong>، واضغط زر التشغيل، ووافق على صلاحية إرسال الرسائل.</li>
                <li>يظهر في التطبيق <strong>اسم مستخدم</strong> و<strong>كلمة مرور</strong>، فانسخهما في الخانتين أدناه.</li>
                <li>اترك الجوال مشغّلاً ومتصلاً بالإنترنت، واستثنِ التطبيق من «توفير البطارية». ويُنصح بضبط مهلة بين الرسائل في التطبيق (Settings ← Messages).</li>
            </ol>
            <div class="row g-3">
                <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="sms_enabled" value="1" id="sms_enabled" @checked($s['sms_enabled'] === '1')><label class="form-check-label fw-semibold" for="sms_enabled">تفعيل الإرسال من جوال المركز</label></div></div>
                <x-field name="sms_username" label="اسم المستخدم (من التطبيق)" :value="$s['sms_username']" col="col-md-4" dir="ltr" autocomplete="off" />
                <div class="col-md-4">
                    <label class="form-label" for="sms_password">كلمة المرور (من التطبيق)</label>
                    <input type="password" name="sms_password" id="sms_password" class="form-control" dir="ltr" autocomplete="new-password" placeholder="{{ $hasPassword ? 'محفوظة، اتركها فارغة لإبقائها' : '' }}">
                </div>
                <x-field name="sms_url" label="عنوان البوابة (اتركه فارغاً)" :value="$s['sms_url']" col="col-md-4" dir="ltr" :help="'الافتراضي: '.\App\Services\SmsService::DEFAULT_URL" />
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-broadcast"></i> الطريقة الثانية: مزوّد رسائل مدفوع</span>
            @if (isset($channels['provider']))<span class="badge text-bg-success">مفعّل</span>@endif
        </div>
        <div class="card-body">
            <p class="small mb-3">تشترك عند شركة رسائل (محلية أو دولية) وتسجّل عندها <strong>اسم المرسل</strong> (مثل GHARAS)، فتعطيك <strong>رابط الإرسال (API)</strong> و<strong>مفتاحاً</strong> أو اسم مستخدم وكلمة مرور، وأسماء الخانات. انسخ ما أعطوك في الخانات أدناه كما هو، ثم أرسل رسالة تجربة. إن احترت فأرسل لنا صفحة «API» من موقع الشركة.</p>
            <div class="row g-3">
                <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="sms_provider_enabled" value="1" id="sms_provider_enabled" @checked($s['sms_provider_enabled'] === '1')><label class="form-check-label fw-semibold" for="sms_provider_enabled">تفعيل الإرسال عبر مزوّد الرسائل</label></div></div>
                <x-field name="sms_provider_url" label="رابط الإرسال (API URL)" :value="$s['sms_provider_url']" col="col-md-8" dir="ltr" placeholder="https://provider.example/api/send" />
                <div class="col-md-4">
                    <label class="form-label" for="sms_provider_method">طريقة الطلب</label>
                    <select name="sms_provider_method" id="sms_provider_method" class="form-select" dir="ltr"><option value="POST" @selected(($s['sms_provider_method'] ?: 'POST') === 'POST')>POST</option><option value="GET" @selected($s['sms_provider_method'] === 'GET')>GET</option></select>
                </div>
                <x-field name="sms_provider_key_param" label="اسم خانة المفتاح" :value="$s['sms_provider_key_param'] ?? 'api_key'" col="col-md-4" dir="ltr" help="مثل api_key أو token أو password" />
                <div class="col-md-8">
                    <label class="form-label" for="sms_provider_key">المفتاح (API key) أو كلمة المرور</label>
                    <input type="password" name="sms_provider_key" id="sms_provider_key" class="form-control" dir="ltr" autocomplete="new-password" placeholder="{{ $hasProviderKey ? 'محفوظ، اتركه فارغاً لإبقائه' : '' }}">
                    <div class="form-text">يُحفظ مشفّراً.</div>
                </div>
                <x-field name="sms_provider_sender_param" label="اسم خانة المرسل" :value="$s['sms_provider_sender_param'] ?? 'sender'" col="col-md-4" dir="ltr" help="مثل sender أو from" />
                <x-field name="sms_provider_sender" label="اسم المرسل المسجّل" :value="$s['sms_provider_sender']" col="col-md-4" dir="ltr" placeholder="GHARAS" />
                <div class="col-md-4">
                    <label class="form-label" for="sms_provider_number_format">شكل الرقم الذي يطلبه المزوّد</label>
                    <select name="sms_provider_number_format" id="sms_provider_number_format" class="form-select">@foreach (\App\Services\SmsService::NUMBER_FORMATS as $k => $v)<option value="{{ $k }}" @selected(($s['sms_provider_number_format'] ?: 'intl') === $k)>{{ $v }}</option>@endforeach</select>
                </div>
                <x-field name="sms_provider_to_param" label="اسم خانة الرقم" :value="$s['sms_provider_to_param'] ?? 'to'" col="col-md-4" dir="ltr" help="مثل to أو numbers أو mobile" />
                <x-field name="sms_provider_text_param" label="اسم خانة النص" :value="$s['sms_provider_text_param'] ?? 'message'" col="col-md-4" dir="ltr" help="مثل message أو text أو msg" />
                <x-field name="sms_provider_success" label="علامة النجاح في الرد (اختياري)" :value="$s['sms_provider_success']" col="col-md-4" dir="ltr" help="نص يظهر في رد المزوّد عند النجاح، مثل success أو 1" />
                <x-field name="sms_provider_extra" label="خانات ثابتة إضافية (اختياري)" type="textarea" :value="$s['sms_provider_extra']" col="col-md-8" rows="2" dir="ltr" help="خانة في كل سطر بالشكل name=value، مثل username=gharas أو unicode=1" />
                <div class="col-md-4 d-flex align-items-center"><div class="form-check"><input class="form-check-input" type="checkbox" name="sms_provider_bulk" value="1" id="sms_provider_bulk" @checked($s['sms_provider_bulk'] === '1')><label class="form-check-label" for="sms_provider_bulk">يقبل عدة أرقام في طلب واحد مفصولة بفاصلة</label></div></div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-semibold"><i class="bi bi-sliders"></i> إعدادات مشتركة</div>
        <div class="card-body row g-3">
            <x-field name="sms_country_code" label="رمز الدولة لأرقام الأهالي" :value="$s['sms_country_code'] ?: '+970'" col="col-md-4" dir="ltr" help="مثل +970 فلسطين. يُضاف للأرقام التي تبدأ بصفر." />
            <x-field name="sms_signature" label="توقيع في آخر كل رسالة (اختياري)" :value="$s['sms_signature']" col="col-md-4" help="مثل: مركز غراس" />
            <div class="col-md-4">
                <label class="form-label" for="sms_default_channel">طريقة الرسائل التلقائية</label>
                <select name="sms_default_channel" id="sms_default_channel" class="form-select">@foreach (\App\Services\SmsService::CHANNELS as $k => $v)<option value="{{ $k }}" @selected(($s['sms_default_channel'] ?: 'phone') === $k)>{{ $v }}</option>@endforeach</select>
                <div class="form-text">وهي الاختيار الأول عند كتابة رسالة يدوية. إن لم تكن مفعّلة تُستعمل الأخرى.</div>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">رسائل تلقائية لولي الأمر</label>
                @foreach (\App\Services\SmsService::AUTO as $k => $label)
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="sms_auto_{{ $k }}" value="1" id="auto-{{ $k }}" @checked($s['sms_auto_'.$k] === '1')><label class="form-check-label" for="auto-{{ $k }}">{{ $label }}</label></div>
                @endforeach
                <div class="form-text">تصل أيضاً إشعارات الموقع كالمعتاد. كل رسالة تُحسب من رصيد الشريحة أو المزوّد.</div>
            </div>
            <x-field name="test_phone" label="رقم التجربة (اختياري)" col="col-md-4" dir="ltr" help="تُرسل إليه رسالة تجربة بعد الحفظ." />
            <div class="col-md-4">
                <label class="form-label" for="test_channel">ترسل التجربة عبر</label>
                <select name="test_channel" id="test_channel" class="form-select">@foreach (\App\Services\SmsService::CHANNELS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
            </div>
        </div>
        <div class="card-footer text-end"><button class="btn btn-primary"><i class="bi bi-check2"></i> حفظ</button></div>
    </div>
</form>
@endsection
