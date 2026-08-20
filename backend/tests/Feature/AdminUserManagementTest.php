<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('admin can create a teacher', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $response = $this
        ->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Жаңа Мұғалім',
            'username' => 'new_teacher',
            'email' => 'new.teacher@pedbase.kz',
            'role' => 'teacher',
            'password' => 'Teacher123!',
        ]);

    $response->assertRedirect(route('admin.users.index'));

    $user = User::where('username', 'new_teacher')->first();

    expect($user)->not->toBeNull();
    expect($user->role)->toBe('teacher');
    expect(Hash::check('Teacher123!', $user->password))->toBeTrue();
});
test('teacher cannot create users', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
    ]);

    $response = $this
        ->actingAs($teacher)
        ->post(route('admin.users.store'), [
            'name' => 'Заңсыз пайдаланушы',
            'username' => 'unauthorized_user',
            'email' => 'unauthorized@pedbase.kz',
            'role' => 'teacher',
            'password' => 'Teacher123!',
        ]);

    $response->assertForbidden();

    $this->assertDatabaseMissing('users', [
        'username' => 'unauthorized_user',
    ]);
});