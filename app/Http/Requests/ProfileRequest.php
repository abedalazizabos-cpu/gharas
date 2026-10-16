<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'email' => [$this->user()->isParent() ? 'nullable' : 'required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($this->user())],
            'phone' => [$this->user()->isParent() ? 'required' : 'nullable', 'string', 'regex:/^0?5\d{8}$|^\d{9,15}$/', Rule::unique('users', 'phone')->ignore($this->user())],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => \App\Models\User::normalizePhone($this->input('phone')), 'email' => $this->input('email') ?: null]);
    }

    public function attributes(): array
    {
        return ['name' => 'الاسم', 'email' => 'البريد الإلكتروني', 'phone' => 'رقم الجوال'];
    }
}
