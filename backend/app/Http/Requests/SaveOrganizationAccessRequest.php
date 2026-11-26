<?php

namespace App\Http\Requests;

use App\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveOrganizationAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return match ($this->route('operation')) {
            'district' => ['name' => ['required', 'string', 'max:150', 'unique:districts,name']],
            'school' => ['school_id' => ['required', 'integer', 'exists:schools,id'], 'district_id' => ['nullable', 'integer', 'exists:districts,id']],
            'school-admin' => [
                'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', UserRole::SCHOOL_ADMIN->value)],
                'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            ],
            'methodist' => [
                'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', UserRole::METHODIST->value)],
                'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            ],
        };
    }

    public function messages(): array
    {
        return ['required' => __('Мән енгізіңіз.'), 'exists' => __('Жазба немесе пайдаланушы рөлі жарамсыз.'), 'unique' => __('Бұл атау тіркелген.'), 'max' => __('Мән берілген шектен ұзын.')];
    }
}
