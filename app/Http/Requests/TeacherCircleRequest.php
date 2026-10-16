<?php

namespace App\Http\Requests;

/** A teacher may rename their own circle and change its time; everything else stays with the administration. */
class TeacherCircleRequest extends CircleRequest
{
    public function authorize(): bool
    {
        $circle = $this->route('circle');

        return $this->user()->isTeacher() && $circle && $circle->teacher_id === $this->user()->id;
    }

    public function rules(): array
    {
        return array_intersect_key(parent::rules(), array_flip(['name', 'time_type', 'start_time', 'end_time', 'prayer', 'time_note']));
    }
}
