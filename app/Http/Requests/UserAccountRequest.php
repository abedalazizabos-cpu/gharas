<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Shared validation for admin, teacher and parent accounts. */
class UserAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => User::normalizePhone($this->input('phone')), 'email' => $this->input('email') ?: null]);
    }

    /** Parents sign in with their mobile number, so for them it is required and email is optional. */
    private function isParentForm(): bool
    {
        return str_starts_with((string) $this->route()?->getName(), 'admin.parents.');
    }

    public function rules(): array
    {
        $user = $this->route('teacher') ?? $this->route('parent') ?? $this->route('admin') ?? $this->route('visitor');
        $parent = $this->isParentForm();

        return [
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'email' => [$parent ? 'nullable' : 'required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user)],
            'phone' => [$parent ? 'required' : 'nullable', 'string', 'regex:/^0?5\d{8}$|^\d{9,15}$/', Rule::unique('users', 'phone')->ignore($user)],
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
            // teacher profile
            'qualification' => ['nullable', 'string', 'max:150'],
            'ijazah' => ['nullable', 'string', 'max:150'],
            'hire_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // parent profile
            'national_id' => ['nullable', 'string', 'max:30'],
            'alt_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s]+$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', Rule::exists('students', 'id')->whereNull('deleted_at')],
            'relationship' => ['nullable', Rule::in(['father', 'mother', 'guardian'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'الاسم',
            'email' => 'البريد الإلكتروني',
            'phone' => 'رقم الجوال',
            'password' => 'كلمة المرور',
            'status' => 'الحالة',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'رقم الجوال مسجّل لحساب آخر.',
            'phone.regex' => 'رقم الجوال غير صحيح. مثال: 0551234567',
            'phone.required' => 'رقم الجوال مطلوب لأنه اسم الدخول لولي الأمر.',
        ];
    }
}
