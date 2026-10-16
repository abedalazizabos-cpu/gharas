@extends('layouts.app')
@section('title', 'الإعدادات')
@section('content')
<x-page-header title="إعدادات النظام" :crumbs="['الإعدادات']" />
<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" novalidate>
    @csrf @method('PUT')
    <div class="card mb-3">
        <div class="card-header">بيانات المركز</div>
        <div class="card-body"><div class="row g-3">
            <x-field name="center_name" label="اسم المركز" :value="$settings['center_name']" required col="col-md-6" />
            <x-field name="center_mission" label="رسالة المركز" :value="$settings['center_mission']" col="col-md-6" />
            <x-field name="center_about" label="نبذة عن المركز" type="textarea" :value="$settings['center_about']" col="col-md-6" />
            <x-field name="center_goals" label="أهداف المركز (هدف في كل سطر)" type="textarea" :value="$settings['center_goals']" col="col-md-6" />
        </div></div>
    </div>
    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-image"></i> الشعار والهوية</div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-lg-4 text-center">
                    <div class="small text-muted mb-2">معاينة</div>
                    <div class="p-3 rounded-3 d-inline-block" id="brand-preview" style="background:var(--g-green-900)">
                        @php($S = \App\Models\Setting::class)
                        <div class="d-flex align-items-center gap-2 text-white" style="text-align:right">
                            <img id="pv-logo" src="{{ asset($settings['center_logo'] ?: 'images/logo.png') }}" alt="" style="object-fit:contain;padding:3px">
                            <div><div id="pv-title" class="fw-bold"></div><div id="pv-sub" class="small" style="opacity:.8"></div></div>
                        </div>
                    </div>
                    <div class="mt-2"><small class="text-muted">{{ $settings['center_logo'] ? 'الشعار المرفوع' : 'الشعار الافتراضي' }}</small></div>
                </div>
                <div class="col-lg-8">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label for="logo" class="form-label">رفع شعار جديد</label>
                            <input type="file" name="logo" id="logo" accept="image/png,image/jpeg,image/webp" class="form-control @error('logo') is-invalid @enderror">
                            @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">PNG أو JPG أو WEBP، حتى 2 ميغابايت. يُفضّل مربعاً بخلفية شفافة.</div>
                            @if ($settings['center_logo'])
                                <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="rml"><label class="form-check-label" for="rml">حذف الشعار والعودة إلى الشعار الافتراضي</label></div>
                            @endif
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">اللون الرئيسي للنظام</label>
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                @foreach ($S::THEME_COLORS as $hex => $name)
                                    <input type="radio" class="btn-check" name="theme_color" value="{{ $hex }}" id="tc-{{ $loop->index }}" @checked(strtolower(old('theme_color', $settings['theme_color'])) === $hex)>
                                    <label for="tc-{{ $loop->index }}" title="{{ $name }}" class="rounded-circle border" style="width:30px;height:30px;background:{{ $hex }};cursor:pointer;outline-offset:2px"></label>
                                @endforeach
                            </div>
                            <div class="form-text">يتغير به لون القائمة والأزرار لكل المستخدمين.</div>
                        </div>
                        <x-field name="brand_title" label="الاسم بجانب الشعار" :value="$settings['brand_title']" required col="col-md-6" />
                        <x-field name="brand_subtitle" label="السطر الثاني (اختياري)" :value="$settings['brand_subtitle']" col="col-md-6" />
                        <div class="col-md-6">
                            <label class="form-label" for="logo_size">حجم الشعار</label>
                            <select id="logo_size" name="logo_size" class="form-select">@foreach ($S::LOGO_SIZES as $k => $v)<option value="{{ $k }}" data-px="{{ $v[1] }}" @selected(old('logo_size', $settings['logo_size']) === $k)>{{ $v[0] }}</option>@endforeach</select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="logo_shape">شكل إطار الشعار</label>
                            <select id="logo_shape" name="logo_shape" class="form-select">@foreach ($S::LOGO_SHAPES as $k => $v)<option value="{{ $k }}" @selected(old('logo_shape', $settings['logo_shape']) === $k)>{{ $v }}</option>@endforeach</select>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-4">
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="logo_background" value="1" id="lbg" @checked(old('logo_background', $settings['logo_background']) == '1')><label class="form-check-label" for="lbg">خلفية بيضاء خلف الشعار</label></div>
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="logo_on_pdf" value="1" id="lpdf" @checked(old('logo_on_pdf', $settings['logo_on_pdf']) == '1')><label class="form-check-label" for="lpdf">إظهار الشعار في ملفات PDF</label></div>
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="logo_favicon" value="1" id="lfav" @checked(old('logo_favicon', $settings['logo_favicon']) == '1')><label class="form-check-label" for="lfav">استخدام الشعار أيقونةً لتبويب المتصفح</label></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-header">بيانات التواصل</div>
        <div class="card-body"><div class="row g-3">
            <x-field name="contact_phone" label="الهاتف" :value="$settings['contact_phone']" col="col-md-4" dir="ltr" />
            <x-field name="contact_email" label="البريد الإلكتروني" type="email" :value="$settings['contact_email']" col="col-md-4" dir="ltr" />
            <x-field name="contact_address" label="العنوان" :value="$settings['contact_address']" col="col-md-4" />
        </div></div>
    </div>
    <div class="card mb-3">
        <div class="card-header">إعدادات الحلقات والتقييم</div>
        <div class="card-body"><div class="row g-3">
            <x-field name="pass_score" label="درجة اجتياز الحفظ الجديد" type="number" :value="$settings['pass_score']" required col="col-md-4" min="1" max="100" help="لا تُحتسب الآيات محفوظة إلا بهذه الدرجة فأكثر." />
            <x-field name="teacher_edit_days" label="مدة سماح تعديل المحفظ لسجلاته (أيام)" type="number" :value="$settings['teacher_edit_days']" required col="col-md-4" min="0" max="60" />
            <div class="col-md-4 d-flex align-items-center">
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="allow_capacity_override" value="1" id="aco" @checked(old('allow_capacity_override', $settings['allow_capacity_override']) == '1')><label class="form-check-label" for="aco">السماح للإدارة بتجاوز سعة الحلقة</label></div>
            </div>
            <div class="col-12"><a href="{{ route('admin.criteria.index') }}"><i class="bi bi-sliders"></i> إدارة معايير التقييم</a></div>
        </div></div>
    </div>
    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-chat-quote"></i> رسائل التذكير (أدعية وآيات)</div>
        <div class="card-body"><div class="row g-3">
            <div class="col-12">
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="reminders_enabled" value="1" id="rem-on" @checked(old('reminders_enabled', $settings['reminders_enabled']) == '1')><label class="form-check-label fw-semibold" for="rem-on">تفعيل التذكيرات داخل النظام</label></div>
            </div>
            <x-field name="reminders_interval" label="يظهر تذكير كل (دقائق)" type="number" :value="$settings['reminders_interval']" required col="col-6 col-md-3" min="1" max="120" />
            <x-field name="reminders_duration" label="يبقى على الشاشة (ثوانٍ)" type="number" :value="$settings['reminders_duration']" required col="col-6 col-md-3" min="5" max="120" />
            <x-field name="reminders_from" label="يبدأ الظهور من الساعة" type="time" :value="$settings['reminders_from']" required col="col-6 col-md-3" />
            <x-field name="reminders_to" label="ويتوقف عند الساعة" type="time" :value="$settings['reminders_to']" required col="col-6 col-md-3" />
            <div class="col-12">
                <label class="form-label">أيام الظهور</label>
                @php($remDays = collect(old('reminders_days', explode(',', (string) $settings['reminders_days'])))->map(fn ($d) => (int) $d))
                <div class="d-flex flex-wrap gap-2">
                    @foreach ([6, 0, 1, 2, 3, 4, 5] as $d)
                        <input type="checkbox" class="btn-check" name="reminders_days[]" value="{{ $d }}" id="rd-{{ $d }}" @checked($remDays->contains($d))>
                        <label class="btn btn-outline-primary btn-sm" for="rd-{{ $d }}">{{ \App\Models\Circle::DAYS[$d] }}</label>
                    @endforeach
                </div>
                @error('reminders_days')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">تظهر لـ</label>
                @php($remRoles = collect(old('reminders_roles', explode(',', (string) $settings['reminders_roles']))))
                <div class="d-flex flex-wrap gap-3">
                    @foreach (\App\Models\User::ROLES as $k => $v)
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="reminders_roles[]" value="{{ $k }}" id="rr-{{ $k }}" @checked($remRoles->contains($k))><label class="form-check-label" for="rr-{{ $k }}">{{ $v }}</label></div>
                    @endforeach
                </div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="reminders_order">ترتيب النصوص</label>
                <select id="reminders_order" name="reminders_order" class="form-select"><option value="sequential" @selected(old('reminders_order', $settings['reminders_order']) === 'sequential')>بالترتيب</option><option value="random" @selected(old('reminders_order', $settings['reminders_order']) === 'random')>عشوائي</option></select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="reminders_position">مكان الظهور</label>
                <select id="reminders_position" name="reminders_position" class="form-select"><option value="bottom" @selected(old('reminders_position', $settings['reminders_position']) === 'bottom')>أسفل الشاشة</option><option value="top" @selected(old('reminders_position', $settings['reminders_position']) === 'top')>أعلى الشاشة</option></select>
            </div>
            <div class="col-12">
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="reminders_on_login" value="1" id="rem-login" @checked(old('reminders_on_login', $settings['reminders_on_login']) == '1')><label class="form-check-label" for="rem-login">إظهار أول تذكير مباشرة بعد تسجيل الدخول</label></div>
            </div>
            <x-field name="reminders_text" label="النصوص (نص في كل سطر، والمصدر بعد العلامة |)" type="textarea" :value="$settings['reminders_text']" col="col-12" rows="8"
                help="مثال: اللهم ارحم شهداءنا | دعاء للشهداء. إذا أفرغت الحقل تعود القائمة الافتراضية." />
        </div></div>
    </div>
    <div class="d-flex justify-content-end mb-4"><button class="btn btn-primary px-4"><i class="bi bi-save ms-1"></i> حفظ الإعدادات</button></div>
</form>
<div class="card">
    <div class="card-header">النسخ الاحتياطي</div>
    <div class="card-body">
        <p class="small text-muted">يُنشئ ملف SQL كاملاً لقاعدة البيانات ويُحفظ نسخة منه في <code>storage/app/backups</code>. يمكن أيضاً جدولته عبر الأمر <code dir="ltr">php artisan gharas:backup</code>. يُنصح بالاحتفاظ بالنسخ خارج الخادم.</p>
        <a href="{{ route('admin.settings.backup') }}" class="btn btn-outline-primary"><i class="bi bi-download"></i> تنزيل نسخة احتياطية الآن</a>
    </div>
</div>
@push('scripts')
<script>
(function () {
    var logo = document.getElementById('pv-logo'), t = document.getElementById('pv-title'), sub = document.getElementById('pv-sub'), box = document.getElementById('brand-preview');
    function q(sel) { return document.querySelector(sel); }
    function sync() {
        var opt = q('#logo_size').selectedOptions[0], px = opt ? opt.dataset.px : 42;
        logo.style.width = logo.style.height = px + 'px';
        logo.style.borderRadius = { circle: '50%', square: '2px' }[q('#logo_shape').value] || '10px';
        logo.style.background = q('#lbg').checked ? '#fff' : 'transparent';
        t.textContent = q('#brand_title').value; sub.textContent = q('#brand_subtitle').value;
        var c = q('input[name="theme_color"]:checked'); if (c) box.style.background = 'color-mix(in srgb, ' + c.value + ' 60%, #000)';
        document.querySelectorAll('input[name="theme_color"]').forEach(function (r) { r.nextElementSibling.style.outline = r.checked ? '3px solid ' + r.value : 'none'; });
    }
    document.querySelectorAll('#logo_size, #logo_shape, #lbg, #brand_title, #brand_subtitle, input[name="theme_color"]').forEach(function (el) { el.addEventListener('input', sync); el.addEventListener('change', sync); });
    q('#logo').addEventListener('change', function () { if (this.files[0]) logo.src = URL.createObjectURL(this.files[0]); });
    sync();
})();
</script>
@endpush
@endsection
