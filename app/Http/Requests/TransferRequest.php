<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'circle_id' => ['nullable', 'integer', Rule::exists('circles', 'id')->whereNull('deleted_at')],
            'reason' => ['nullable', 'string', 'max:255'],
            'override_capacity' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['circle_id' => 'الحلقة', 'reason' => 'سبب النقل'];
    }
}
