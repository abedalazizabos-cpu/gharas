<?php

namespace App\Http\Requests;

use App\Models\MemorizationRecord;
use App\Models\Student;
use App\Models\Surah;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MemorizationRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('record');
        if ($record) {
            return $this->user()->can('update', $record);
        }
        $student = Student::find($this->input('student_id'));

        return $student !== null && $this->user()->can('record', $student);
    }

    public function rules(): array
    {
        return [
            'student_id' => [$this->route('record') ? 'prohibited' : 'required', 'integer', 'exists:students,id'],
            'recorded_on' => ['required', 'date', 'before_or_equal:today'],
            'type' => ['required', Rule::in(array_keys(MemorizationRecord::TYPES))],
            'surah_id' => ['required', 'integer', 'exists:surahs,id'],
            'from_ayah' => ['required', 'integer', 'min:1'],
            'to_ayah' => ['required', 'integer', 'gte:from_ayah'],
            'pages' => ['nullable', 'numeric', 'min:0', 'max:604'],
            'score' => ['required', 'integer', 'between:0,100'],
            'mistakes_count' => ['nullable', 'integer', 'min:0', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'recommendations' => ['nullable', 'string', 'max:2000'],
            'shared_with_parent' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $surah = Surah::find($this->surah_id);
            if ($surah && (int) $this->to_ayah > $surah->ayah_count) {
                $validator->errors()->add('to_ayah', "عدد آيات سورة {$surah->name} هو {$surah->ayah_count} آية فقط.");
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'student_id' => 'الطالب', 'recorded_on' => 'تاريخ التسميع', 'type' => 'نوع النشاط',
            'surah_id' => 'السورة', 'from_ayah' => 'من آية', 'to_ayah' => 'إلى آية', 'score' => 'الدرجة',
        ];
    }
}
