<?php

namespace App\Http\Requests;

use App\Services\EducationWorkloadImport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SpreadsheetImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->routeIs('education-organizations.imports.*')) {
            abort_unless(config('pedbase.education_catalog_enabled'), 404);
        }

        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:xlsx', 'mimes:xlsx', 'max:2048'],
            'token' => [$this->routeIs('admin.imports.confirm', 'education-organizations.imports.confirm') ? 'required' : 'nullable', 'string', 'max:2048'],
            ...($this->routeIs('education-organizations.imports.*') ? [
                'workload_type' => ['nullable', 'string', Rule::in(EducationWorkloadImport::TYPES)],
            ] : []),
        ];
    }

    public function messages(): array
    {
        return [
            'file.*' => 'Көлемі 2 МБ-тан аспайтын XLSX файлын таңдаңыз.',
            'token.*' => 'Файлды алдын ала қайта тексеріңіз.',
        ];
    }
}
