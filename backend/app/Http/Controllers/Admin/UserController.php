<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Users', [
            'users' => User::query()
                ->select('id', 'name', 'username', 'role', 'email')
                ->orderBy('name')
                ->get(),
        ]);
    }
public function create(): Response
{
    return Inertia::render('admin/CreateUser');
}
   public function store(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'username' => [
            'required',
            'string',
            'max:100',
            'alpha_dash',
            Rule::unique('users', 'username'),
        ],
        'email' => [
            'required',
            'email',
            'max:255',
            Rule::unique('users', 'email'),
        ],
        'role' => ['required', Rule::in(['admin', 'teacher'])],
        'password' => ['required', 'string', 'min:8'],
    ]);

    $validated['password'] = Hash::make($validated['password']);

    User::create($validated);

    return redirect()
        ->route('admin.users.index')
        ->with('success', 'Пайдаланушы сәтті қосылды.');
}

public function edit(Request $request, User $user): Response
{
    return Inertia::render('admin/EditUser', [
        'user' => $user->only([
            'id',
            'name',
            'username',
            'email',
            'role',
        ]),
        'isSelf' => $request->user()->is($user),
    ]);
}
public function update(Request $request, User $user): RedirectResponse
{
    $validated = $request->validate([
        
    'name' => ['required', 'string', 'max:255'],
        'username' => [
            'required',
            'string',
            'max:100',
            'alpha_dash',
            Rule::unique('users', 'username')->ignore($user->id),
        ],
        'email' => [
            'required',
            'email',
            'max:255',
            Rule::unique('users', 'email')->ignore($user->id),
        ],
        'role' => ['required', Rule::in(['admin', 'teacher'])],
    ]);
if ($request->user()->is($user)) {
    $validated['role'] = $user->role;
}
    $user->update($validated);

    return redirect()
        ->route('admin.users.index')
        ->with('success', 'Пайдаланушы мәліметтері сәтті жаңартылды.');
}
}