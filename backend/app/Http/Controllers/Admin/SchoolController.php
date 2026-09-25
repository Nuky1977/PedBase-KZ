<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
    }public function create(): Response
{
    return Inertia::render('admin/schools/Create');
}public function store(Request $request): RedirectResponse
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
}public function edit(School $school): Response
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
}public function update(Request $request, School $school): RedirectResponse
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
}public function toggleStatus(School $school): RedirectResponse
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
}