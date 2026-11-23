<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\TeacherProfile;
use App\Services\ProtectedIin;
use App\UserRole;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->role === UserRole::TEACHER, 403);
        $profile = $user->teacherProfile;

        Inertia::encryptHistory();
        $response = Inertia::render('teacher/Profile', [
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
        ])->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function update(Request $request, ProtectedIin $protection): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->role === UserRole::TEACHER, 403);

        $validated = $request->validate([
            'iin' => [
                'nullable',
                'digits:12',
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
                'max:'.now()->year,
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

        if (array_key_exists('iin', $validated)) {
            if (TeacherProfile::query()->whereNotNull('iin')->exists()) {
                throw ValidationException::withMessages(['iin' => 'ЖСН көшіру аяқталғанша бұл өрісті өзгерту қолжетімсіз.']);
            }
            $lookup = $validated['iin'] === null ? null : $protection->fingerprint($validated['iin']);
        }

        try {
            $user->teacherProfile()->updateOrCreate([], $validated);
        } catch (UniqueConstraintViolationException $exception) {
            if (isset($lookup) && TeacherProfile::query()->where('iin_lookup', $lookup)->where('user_id', '<>', $user->id)->exists()) {
                throw ValidationException::withMessages(['iin' => 'Бұл ЖСН бұрын тіркелген.']);
            }

            throw $exception;
        }

        $user->unsetRelation('teacherProfile');

        return redirect()
            ->route('teacher.profile.edit')
            ->with(
                'success',
                'Профиль мәліметтері сәтті сақталды.'
            );
    }
}
