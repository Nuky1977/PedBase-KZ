<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
