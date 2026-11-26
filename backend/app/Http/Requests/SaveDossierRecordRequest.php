<?php

namespace App\Http\Requests;

use App\Policies\TeacherDossierPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class SaveDossierRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->route('kind') === 'achievements') {
            abort_unless(config('pedbase.education_catalog_enabled'), 404);
        }

        return app(TeacherDossierPolicy::class)->manage($this->user(), $this->route('teacher'));
    }

    public function rules(): array
    {
        return match ($this->route('kind')) {
            'diplomas' => [
                'education_level' => ['required', 'string', 'max:100'],
                'institution' => ['required', 'string', 'max:200'],
                'specialty' => ['required', 'string', 'max:150'],
                'diploma_number' => ['nullable', 'string', 'max:100'],
                'graduation_year' => ['required', 'integer', 'between:1900,'.now()->year],
            ],
            'courses' => [
                'title' => ['required', 'string', 'max:200'],
                'provider' => ['required', 'string', 'max:200'],
                'hours' => ['required', 'integer', 'between:1,65535'],
                'started_on' => ['required', 'date_format:Y-m-d'],
                'ended_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:started_on'],
                'certificate_number' => ['nullable', 'string', 'max:100'],
                'certificate_issued_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:ended_on'],
                'certificate' => ['nullable', File::types(['pdf'])->max('5mb')],
            ],
            'attestations' => [
                'category' => ['required', 'string', 'max:120'],
                'awarded_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                'valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:awarded_on'],
            ],
            'achievements' => [
                'title' => ['required', 'string', 'max:200'],
                'issuer' => ['required', 'string', 'max:200'],
                'awarded_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                'level' => ['required', 'string', 'max:100'],
                'description' => ['nullable', 'string', 'max:2000'],
                'certificate' => ['nullable', File::types(['pdf'])->max('5mb')],
            ],
        };
    }

    public function messages(): array
    {
        return [
            'required' => __('Бұл өрісті толтырыңыз.'),
            'string' => __('Мәтін енгізіңіз.'),
            'integer' => __('Бүтін сан енгізіңіз.'),
            'between' => __('Мән :min және :max арасында болуы керек.'),
            'max' => __('Мән берілген шектен аспауы керек.'),
            'date_format' => __('Дұрыс күнді таңдаңыз.'),
            'after_or_equal' => __('Күн алдыңғы көрсетілген күннен ерте болмауы керек.'),
            'before_or_equal' => __('Күн бүгіннен кеш болмауы керек.'),
            'certificate.*' => __('Көлемі 5 МБ-тан аспайтын PDF файлын таңдаңыз.'),
        ];
    }
}
