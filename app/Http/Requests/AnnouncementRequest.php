<?php

namespace App\Http\Requests;

use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'audience' => ['required', Rule::in(array_keys(Announcement::AUDIENCES))],
            'publish_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:publish_at'],
            'notify' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['title' => 'العنوان', 'body' => 'نص الإعلان', 'audience' => 'الفئة المستهدفة', 'publish_at' => 'تاريخ النشر', 'expires_at' => 'تاريخ الانتهاء'];
    }
}
