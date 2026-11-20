<?php

namespace App\Http\Requests;

use App\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EducationReportRequest extends FormRequest
{
    public const REPORTS = ['schools', 'subjects', 'categories', 'experience', 'education', 'courses', 'attestations', 'placements', 'workload', 'years'];

    public function authorize(): bool
    {
        return in_array($this->user()->role, [UserRole::ADMIN, UserRole::SCHOOL_ADMIN, UserRole::METHODIST], true);
    }

    public function rules(): array
    {
        return [
            'report' => ['sometimes', Rule::in(self::REPORTS)],
            'school_id' => ['nullable', 'integer', 'min:1'],
            'academic_year' => ['nullable', 'integer', 'between:1900,9998'],
        ];
    }
}
