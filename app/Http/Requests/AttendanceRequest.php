<?php

namespace App\Http\Requests;

use App\Models\AttendanceRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('circle'));
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date', 'before_or_equal:today'],
            'session_notes' => ['nullable', 'string', 'max:1000'],
            'attendance' => ['required', 'array', 'min:1'],
            'attendance.*.status' => ['required', Rule::in(array_keys(AttendanceRecord::STATUSES))],
            'attendance.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['date' => 'تاريخ الجلسة', 'attendance' => 'الحضور', 'attendance.*.status' => 'حالة الحضور'];
    }
}
