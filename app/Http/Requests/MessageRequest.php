<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isTeacher() || $this->user()->isParent();
    }

    public function rules(): array
    {
        return [
            'recipient_id' => ['required', 'integer'],
            'student_id' => ['nullable', 'integer'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return ['recipient_id' => 'المستلم', 'subject' => 'الموضوع', 'body' => 'نص الرسالة'];
    }
}
