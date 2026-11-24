<?php

namespace App\Http\Requests;

use App\Models\TeacherEmployment;
use Illuminate\Foundation\Http\FormRequest;

class SaveTeachingAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', [TeacherEmployment::class, $this->route('school')]);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('weekly_hours'))) {
            $this->merge(['weekly_hours' => str_replace(',', '.', $this->input('weekly_hours'))]);
        }
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'academic_year' => ['required', 'integer', 'between:1900,9998'],
            'weekly_hours' => ['required', 'numeric', 'decimal:0,2', 'between:0.01,999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'subject_id.*' => __('Анықтамалықтан пәнді таңдаңыз.'),
            'academic_year.*' => __('Оқу жылының басталу жылын енгізіңіз (1900–9998).'),
            'weekly_hours.*' => __('0,01–999,99 аралығындағы санды екі ондық таңбаға дейін енгізіңіз.'),
        ];
    }
}
