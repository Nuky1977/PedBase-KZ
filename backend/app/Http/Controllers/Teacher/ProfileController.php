<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->teacherProfile;

        return Inertia::render('teacher/Profile', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
            ],

            'profile' => $profile
                ? [
                    'iin' => $profile->iin,
                    'birth_date' => $profile->birth_date?->format('Y-m-d'),
                    'gender' => $profile->gender,
                    'phone' => $profile->phone,
                    'education_level' => $profile->education_level,
                    'educational_institution' => $profile->educational_institution,
                    'graduation_year' => $profile->graduation_year,
                    'diploma_number' => $profile->diploma_number,
                    'specialty' => $profile->specialty,
                    'total_experience_months' => $profile->total_experience_months,
                    'teaching_experience_months' => $profile->teaching_experience_months,
                ]
                : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $profileId = $user->teacherProfile()
            ->value('id');

        $validated = $request->validate([
            'iin' => [
                'nullable',
                'digits:12',
                Rule::unique('teacher_profiles', 'iin')
                    ->ignore($profileId),
            ],

            'birth_date' => [
                'nullable',
                'date',
                'before_or_equal:today',
            ],

            'gender' => [
                'nullable',
                'string',
                'max:20',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'education_level' => [
                'nullable',
                'string',
                'max:255',
            ],

            'educational_institution' => [
                'nullable',
                'string',
                'max:255',
            ],

            'graduation_year' => [
                'nullable',
                'integer',
                'min:1900',
                'max:' . now()->year,
            ],

            'diploma_number' => [
                'nullable',
                'string',
                'max:255',
            ],

            'specialty' => [
                'nullable',
                'string',
                'max:255',
            ],

            'total_experience_months' => [
                'nullable',
                'integer',
                'min:0',
                'max:1200',
            ],

            'teaching_experience_months' => [
                'nullable',
                'integer',
                'min:0',
                'max:1200',
            ],
        ]);

        $user->teacherProfile()->updateOrCreate(
            [],
            $validated
        );

        $user->unsetRelation('teacherProfile');

        return redirect()
            ->route('teacher.profile.edit')
            ->with(
                'success',
                'Профиль мәліметтері сәтті сақталды.'
            );
    }
}