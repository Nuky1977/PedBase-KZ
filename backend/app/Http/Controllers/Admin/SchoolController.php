<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SchoolController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/schools/Index', [
            'schools' => School::query()
                ->select(
                    'id',
                    'name',
                    'short_name',
                    'bin',
                    'type',
                    'locality',
                    'is_active'
                )
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/schools/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:255'],
            'bin' => [
                'nullable',
                'digits:12',
                Rule::unique('schools', 'bin'),
            ],
            'type' => ['nullable', 'string', 'max:50'],
            'locality' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        School::create($validated);

        return redirect()
            ->route('admin.schools.index')
            ->with('success', 'Мектеп сәтті қосылды.');
    }

    public function edit(School $school): Response
    {
        return Inertia::render('admin/schools/Edit', [
            'school' => $school->only([
                'id',
                'name',
                'short_name',
                'bin',
                'type',
                'locality',
                'is_active',
            ]),
        ]);
    }

    public function update(Request $request, School $school): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:255'],
            'bin' => [
                'nullable',
                'digits:12',
                Rule::unique('schools', 'bin')->ignore($school->id),
            ],
            'type' => ['nullable', 'string', 'max:50'],
            'locality' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $school->update($validated);

        return redirect()
            ->route('admin.schools.index')
            ->with('success', 'Мектеп мәліметтері сәтті жаңартылды.');
    }

    public function toggleStatus(School $school): RedirectResponse
    {
        $school->update([
            'is_active' => ! $school->is_active,
        ]);

        return redirect()
            ->route('admin.schools.index')
            ->with(
                'success',
                $school->is_active
                    ? 'Мектеп белсенді күйге ауыстырылды.'
                    : 'Мектеп белсенді емес күйге ауыстырылды.'
            );
    }

    public function teachers(School $school): Response
    {
        $attachedTeacherIds = $school->users()
            ->where('role', UserRole::TEACHER->value)
            ->pluck('users.id');

        return Inertia::render('admin/schools/Teachers', [
            'school' => $school->only([
                'id',
                'name',
                'short_name',
            ]),

            'teachers' => $school->users()
                ->where('role', UserRole::TEACHER->value)
                ->select(
                    'users.id',
                    'users.name',
                    'users.username',
                    'users.email'
                )
                ->orderBy('users.name')
                ->get()
                ->map(fn ($teacher) => [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                    'username' => $teacher->username,
                    'email' => $teacher->email,
                    'is_primary' => (bool) $teacher->pivot->is_primary,
                ]),

            'availableTeachers' => User::query()
                ->where('role', UserRole::TEACHER->value)
                ->whereNotIn('id', $attachedTeacherIds)
                ->select(
                    'id',
                    'name',
                    'username',
                    'email'
                )
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function attachTeacher(
        Request $request,
        School $school
    ): RedirectResponse {
        $validated = $request->validate([
            'teacher_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
            ],
            'is_primary' => [
                'required',
                'boolean',
            ],
        ]);

        $teacher = User::findOrFail($validated['teacher_id']);

        if ($teacher->role !== UserRole::TEACHER) {
            abort(422, 'Таңдалған пайдаланушы педагог емес.');
        }

        if ($school->users()->whereKey($teacher->id)->exists()) {
            return redirect()
                ->route('admin.schools.teachers', $school)
                ->with(
                    'success',
                    'Бұл педагог мектепке бұрыннан тіркелген.'
                );
        }

        $school->users()->attach($teacher->id, [
            'is_primary' => false,
        ]);

        if ($validated['is_primary']) {
            $teacher->setPrimarySchool($school);
        }

        return redirect()
            ->route('admin.schools.teachers', $school)
            ->with('success', 'Педагог мектепке сәтті тіркелді.');
    }public function detachTeacher(
    School $school,
    User $teacher
): RedirectResponse {
    $schoolTeacher = $school->users()
        ->whereKey($teacher->id)
        ->first();

    if (! $schoolTeacher) {
        return redirect()
            ->route('admin.schools.teachers', $school)
            ->with('success', 'Педагог бұл мектепке тіркелмеген.');
    }

    if ((bool) $schoolTeacher->pivot->is_primary) {
        return redirect()
            ->route('admin.schools.teachers', $school)
            ->with(
                'success',
                'Негізгі жұмыс орнын ажырату үшін алдымен басқа мектепті негізгі жұмыс орны ретінде белгілеңіз.'
            );
    }

    $school->users()->detach($teacher->id);

    return redirect()
        ->route('admin.schools.teachers', $school)
        ->with('success', 'Педагог мектептен сәтті ажыратылды.');
}public function makeTeacherPrimary(
    School $school,
    User $teacher
): RedirectResponse {
    if ($teacher->role !== UserRole::TEACHER) {
        abort(422, 'Таңдалған пайдаланушы педагог емес.');
    }

    if (! $school->users()->whereKey($teacher->id)->exists()) {
        abort(422, 'Педагог бұл мектепке тіркелмеген.');
    }

    $teacher->setPrimarySchool($school);

    return redirect()
        ->route('admin.schools.teachers', $school)
        ->with('success', 'Мектеп педагогтің негізгі жұмыс орны ретінде белгіленді.');
}
}