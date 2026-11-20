<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AccountRemoval;
use App\Models\User;
use App\UserRole;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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
        'role' => ['required', Rule::enum(UserRole::class)],
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
        'role' => ['required', Rule::enum(UserRole::class)],
    ]);
    $user->getConnection()->transaction(function () use ($request, $user, $validated) {
        $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
        if ($request->user()->is($lockedUser) || $lockedUser->isAdmin()) {
            $validated['role'] = $lockedUser->role->value;
        }

        $nextRole = UserRole::from($validated['role']);
        $oldScope = match ($lockedUser->role) {
            UserRole::SCHOOL_ADMIN => 'school_administrators',
            UserRole::METHODIST => 'district_methodists',
            default => null,
        };

        if ($nextRole !== $lockedUser->role && $oldScope !== null && Schema::hasTable($oldScope)) {
            $lockedUser->getConnection()->table($oldScope)->where('user_id', $lockedUser->id)->delete();
        }

        $lockedUser->update($validated);
    }, 3);

    return redirect()
        ->route('admin.users.index')
        ->with('success', 'Пайдаланушы мәліметтері сәтті жаңартылды.');
}
public function destroy(Request $request, User $user, AccountRemoval $accounts): RedirectResponse
{
    if ($request->user()->is($user)) {
        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Өз аккаунтыңызды өшіруге болмайды.');
    }

    $accounts->delete($user);

    return redirect()
        ->route('admin.users.index')
        ->with('success', 'Пайдаланушы сәтті өшірілді.');
}}
