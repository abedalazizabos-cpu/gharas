<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');

        return $student ? $this->user()->can('manage', $student) : $this->user()->isAdmin();
    }

    public function rules(): array
    {
        $student = $this->route('student');

        return [
            'full_name' => ['required', 'string', 'min:3', 'max:150'],
            'student_number' => ['nullable', 'string', 'max:20', Rule::unique('students', 'student_number')->ignore($student)],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s]+$/'],
            'enrollment_date' => ['required', 'date'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
            'circle_id' => [$student ? 'prohibited' : 'nullable', 'integer', Rule::exists('circles', 'id')->whereNull('deleted_at')],
            'override_capacity' => ['nullable', 'boolean'],
            'parent_ids' => ['nullable', 'array'],
            'parent_ids.*' => ['integer', Rule::exists('users', 'id')->where('role', User::ROLE_PARENT)->whereNull('deleted_at')],
            // Guardian typed in by name + mobile: linked if the number exists, otherwise a parent account is created.
            'guardian_phone' => [$student ? 'nullable' : 'required_without:parent_ids', 'nullable', 'string', 'max:30'],
            'guardian_name' => ['nullable', 'string', 'min:3', 'max:150'],
            'guardian_relationship' => ['nullable', Rule::in(['father', 'mother', 'guardian'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('guardian_phone')) {
            $this->merge(['guardian_phone' => User::normalizePhone($this->input('guardian_phone'))]);
        }
    }

    public function after(): array
    {
        return [function ($validator) {
            $phone = $this->input('guardian_phone');
            if (! $phone || $validator->errors()->has('guardian_phone')) {
                return;
            }
            if (! preg_match('/^05\d{8}$/', $phone)) {
                $validator->errors()->add('guardian_phone', 'رقم جوال ولي الأمر يجب أن يبدأ بـ 05 ويتكون من 10 أرقام.');

                return;
            }
            $existing = User::withTrashed()->where('phone', $phone)->first();
            if ($existing && ($existing->role !== User::ROLE_PARENT || $existing->trashed())) {
                $validator->errors()->add('guardian_phone', 'هذا الرقم مسجل لحساب آخر ليس ولي أمر نشطاً.');
            } elseif (! $existing && mb_strlen(trim((string) $this->input('guardian_name'))) < 3) {
                $validator->errors()->add('guardian_name', 'الرقم غير مسجل، فاكتب اسم ولي الأمر لإنشاء حسابه.');
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'full_name' => 'اسم الطالب',
            'student_number' => 'رقم الطالب',
            'enrollment_date' => 'تاريخ التسجيل',
            'circle_id' => 'الحلقة',
            'parent_ids' => 'أولياء الأمور',
            'guardian_phone' => 'جوال ولي الأمر',
            'guardian_name' => 'اسم ولي الأمر',
        ];
    }

    public function messages(): array
    {
        return ['guardian_phone.required_without' => 'اكتب رقم جوال ولي الأمر.'];
    }
}
