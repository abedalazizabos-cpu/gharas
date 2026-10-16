<?php

namespace App\Http\Requests;

use App\Models\AssessmentCriterion;
use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = Student::find($this->input('student_id'));

        return $student !== null && $this->user()->can('record', $student);
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'assessed_on' => ['required', 'date', 'before_or_equal:today'],
            'scores' => ['required', 'array'],
            'scores.*' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'shared_with_parent' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $criteria = AssessmentCriterion::active()->get()->keyBy('id');
            foreach ($criteria as $id => $criterion) {
                $score = $this->input("scores.$id");
                if ($score === null) {
                    $validator->errors()->add("scores.$id", "درجة «{$criterion->name}» مطلوبة.");
                } elseif ((int) $score > $criterion->max_score) {
                    $validator->errors()->add("scores.$id", "درجة «{$criterion->name}» يجب ألا تتجاوز {$criterion->max_score}.");
                }
            }
        }];
    }

    public function attributes(): array
    {
        return ['student_id' => 'الطالب', 'assessed_on' => 'تاريخ التقييم'];
    }
}
