<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSuperAdmin();
    }

    protected function prepareForValidation(): void
    {
        foreach (['reminders_from', 'reminders_to'] as $f) {
            if ($this->filled($f)) {
                $this->merge([$f => substr($this->input($f), 0, 5)]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'center_name' => ['required', 'string', 'max:150'],
            'center_about' => ['nullable', 'string', 'max:2000'],
            'center_mission' => ['nullable', 'string', 'max:1000'],
            'center_goals' => ['nullable', 'string', 'max:2000'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'contact_address' => ['nullable', 'string', 'max:255'],
            'pass_score' => ['required', 'integer', 'between:1,100'],
            'teacher_edit_days' => ['required', 'integer', 'between:0,60'],
            'allow_capacity_override' => ['nullable', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'reminders_enabled' => ['nullable', 'boolean'],
            'reminders_interval' => ['required', 'integer', 'between:1,120'],
            'reminders_text' => ['nullable', 'string', 'max:10000'],
            'brand_title' => ['required', 'string', 'max:60'],
            'brand_subtitle' => ['nullable', 'string', 'max:80'],
            'logo_size' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Models\Setting::LOGO_SIZES))],
            'logo_shape' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Models\Setting::LOGO_SHAPES))],
            'logo_background' => ['nullable', 'boolean'],
            'logo_on_pdf' => ['nullable', 'boolean'],
            'logo_favicon' => ['nullable', 'boolean'],
            'theme_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'reminders_duration' => ['required', 'integer', 'between:5,120'],
            'reminders_from' => ['required', 'date_format:H:i'],
            'reminders_to' => ['required', 'date_format:H:i'],
            'reminders_days' => ['required', 'array', 'min:1'],
            'reminders_days.*' => ['integer', 'between:0,6'],
            'reminders_roles' => ['nullable', 'array'],
            'reminders_roles.*' => [\Illuminate\Validation\Rule::in(array_keys(\App\Models\User::ROLES))],
            'reminders_order' => ['required', 'in:sequential,random'],
            'reminders_position' => ['required', 'in:bottom,top'],
            'reminders_on_login' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['center_name' => 'اسم المركز', 'pass_score' => 'درجة اجتياز الحفظ', 'teacher_edit_days' => 'مدة تعديل المحفظ', 'logo' => 'شعار المركز', 'reminders_interval' => 'مدة ظهور التذكير', 'reminders_text' => 'نصوص التذكير', 'brand_title' => 'الاسم بجانب الشعار', 'theme_color' => 'اللون الرئيسي', 'reminders_duration' => 'مدة بقاء التذكير', 'reminders_from' => 'بداية وقت الظهور', 'reminders_to' => 'نهاية وقت الظهور', 'reminders_days' => 'أيام الظهور'];
    }

    public function messages(): array
    {
        return ['reminders_days.required' => 'اختر يوماً واحداً على الأقل لظهور التذكيرات.', 'theme_color.regex' => 'اختر لوناً صحيحاً.'];
    }
}
