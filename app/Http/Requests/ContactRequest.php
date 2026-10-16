<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s]+$/'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'الاسم', 'email' => 'البريد الإلكتروني', 'phone' => 'رقم الجوال', 'message' => 'الرسالة'];
    }
}
