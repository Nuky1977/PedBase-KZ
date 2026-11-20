<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreEmploymentSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => Str::upper($this->input('code'))]);
        }
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'alpha_dash:ascii', 'max:50', 'unique:subjects,code'],
            'name_kk' => ['required', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Бұл пән коды бұрыннан бар.',
            'code.*' => 'Пән кодына 50 таңбаға дейін латын әріптерін, сандарды, дефис немесе астыңғы сызықты енгізіңіз.',
            'name_kk.*' => 'Пәннің қазақша атауын енгізіңіз (150 таңбаға дейін).',
        ];
    }
}
