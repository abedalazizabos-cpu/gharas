<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Models\Circle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CircleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        $circle = $this->route('circle');

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('circles', 'code')->ignore($circle)],
            'teacher_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', User::ROLE_TEACHER)->whereNull('deleted_at')],
            'level' => ['nullable', 'string', 'max:50'],
            'age_group' => ['nullable', 'string', 'max:50'],
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['integer', \Illuminate\Validation\Rule::in(\App\Models\Circle::STUDY_DAYS)],
            'time_type' => ['required', Rule::in(array_keys(Circle::TIME_TYPES))],
            'start_time' => ['required_if:time_type,clock', 'nullable', 'date_format:H:i'],
            'end_time' => ['required_if:time_type,clock', 'nullable', 'date_format:H:i', 'after:start_time'],
            'prayer' => ['required_if:time_type,prayer', 'nullable', Rule::in(array_keys(Circle::PRAYERS))],
            'time_note' => ['required_if:time_type,custom', 'nullable', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:200'],
            'location' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_public' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'اسم الحلقة', 'code' => 'رمز الحلقة', 'teacher_id' => 'المحفظ', 'days' => 'أيام الدراسة',
            'start_time' => 'وقت البداية', 'end_time' => 'وقت النهاية', 'capacity' => 'السعة',
            'time_type' => 'طريقة تحديد الموعد', 'prayer' => 'الصلاة', 'time_note' => 'وصف الموعد',
        ];
    }

    public function messages(): array
    {
        return [
            'start_time.required_if' => 'حدد وقت البداية عند اختيار ساعة محددة.',
            'end_time.required_if' => 'حدد وقت النهاية عند اختيار ساعة محددة.',
            'prayer.required_if' => 'اختر الصلاة التي تبدأ بعدها الحلقة.',
            'time_note.required_if' => 'اكتب وصف الموعد، مثل: الضحى أو صباح الجمعة.',
        ];
    }

    /** Fields of the timing kinds not chosen are cleared so stale values never show. */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        if ($key !== null) {
            return $data;
        }
        if ($data['time_type'] !== 'clock') {
            $data['start_time'] = $data['end_time'] = null;
        } else {
            $data['time_note'] = null;
        }
        if ($data['time_type'] !== 'prayer') {
            $data['prayer'] = null;
        }

        return $data;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['time_type' => $this->input('time_type', 'clock')]);
        foreach (['start_time', 'end_time'] as $field) {
            if ($this->filled($field)) {
                $this->merge([$field => substr($this->input($field), 0, 5)]);
            }
        }
    }
}
