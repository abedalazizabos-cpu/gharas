{{-- Shared account fields: $user --}}
<div class="row g-3 mb-4">
    <x-field name="name" label="الاسم الكامل" :value="$user->name" required col="col-md-6" />
    @if ($loginByPhone ?? false)
        <x-field name="phone" label="رقم الجوال (اسم الدخول)" :value="$user->phone" required col="col-md-6" dir="ltr" inputmode="tel" placeholder="05xxxxxxxx" help="يدخل ولي الأمر إلى النظام برقم جواله وكلمة المرور." />
        <x-field name="email" label="البريد الإلكتروني (اختياري)" type="email" :value="$user->email" col="col-md-4" dir="ltr" />
    @else
        <x-field name="email" label="البريد الإلكتروني (اسم الدخول)" type="email" :value="$user->email" required col="col-md-6" dir="ltr" />
        <x-field name="phone" label="رقم الجوال" :value="$user->phone" col="col-md-4" dir="ltr" inputmode="tel" />
    @endif
    <div class="col-md-4">
        <label class="form-label" for="status">حالة الحساب <span class="text-danger">*</span></label>
        <select id="status" name="status" class="form-select">
            @foreach (\App\Models\User::STATUSES as $k => $v)<option value="{{ $k }}" @selected(old('status', $user->status) === $k)>{{ $v }}</option>@endforeach
        </select>
    </div>
    <div class="col-md-4"></div>
    <x-field name="password" label="كلمة المرور" type="password" :required="! $user->exists" col="col-md-6" dir="ltr" autocomplete="new-password" :help="$user->exists ? 'اتركها فارغة للإبقاء على كلمة المرور الحالية.' : '8 أحرف على الأقل، تحتوي على حروف وأرقام.'" />
    <x-field name="password_confirmation" label="تأكيد كلمة المرور" type="password" :required="! $user->exists" col="col-md-6" dir="ltr" autocomplete="new-password" />
</div>
