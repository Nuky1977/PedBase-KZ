<?php

namespace App\Http\Requests;

use App\Models\TeacherEmployment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTeacherEmploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', [TeacherEmployment::class, $this->route('school')]);
    }

    public function rules(): array
    {
        return [
            'position_title' => ['required', 'string', 'max:150'],
            'status' => ['required', Rule::in(['active', 'on_leave'])],
        ];
    }

    public function messages(): array
    {
        return [
            'position_title.required' => 'Лауазымды енгізіңіз.',
            'position_title.max' => 'Лауазым 150 таңбадан аспауы керек.',
            'status.*' => 'Еңбек күйін таңдаңыз.',
        ];
    }
}
