<?php

use App\Models\User; 
use App\UserRole;
use Illuminate\Support\Facades\Hash;

test('admin can create a teacher', function () {
    $admin = User::factory()->create([
        'role' => UserRole::ADMIN,
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
    expect($user->role)->toBe(UserRole::TEACHER);
    expect(Hash::check('Teacher123!', $user->password))->toBeTrue();
});
test('teacher cannot create users', function () {
    $teacher = User::factory()->create([
        'role' => UserRole::TEACHER,
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
test('admin can update a teacher', function () {
    $admin = User::factory()->create([
        'role' => UserRole::ADMIN,
    ]);

    $teacher = User::factory()->create([
        'role' => UserRole::TEACHER,
    ]);

    $response = $this
        ->actingAs($admin)
        ->put(route('admin.users.update', $teacher), [
            'name' => 'Updated Teacher',
            'username' => $teacher->username,
            'email' => $teacher->email,
           'role' => 'teacher',
        ]);
    $response->assertSessionHasNoErrors();
    expect($response->getStatusCode())->toBe(302);

    

    $this->assertDatabaseHas('users', [
        'id' => $teacher->id,
        'name' => 'Updated Teacher',
       'role' => UserRole::TEACHER->value,
    ]);
});
test('admin cannot change own role to teacher', function () {
    $admin = User::factory()->create([
        'role' => UserRole::ADMIN,
    ]);

    $response = $this
        ->actingAs($admin)
        ->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'username' => $admin->username,
            'email' => $admin->email,
            'role' => UserRole::TEACHER->value,
        ]);
    
    expect($response->getStatusCode())->toBe(302);

    

    $admin->refresh();

    expect($admin->role)->toBe(UserRole::ADMIN);
});
test('admin can delete a teacher', function () {
    $admin = User::factory()->create([
        'role' => UserRole::ADMIN,
    ]);

    $teacher = User::factory()->create([
        'role' => UserRole::TEACHER,
    ]);

    $response = $this
        ->actingAs($admin)
        ->delete(route('admin.users.destroy', $teacher));

    $response->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseMissing('users', [
        'id' => $teacher->id,
    ]);
});

test('admin cannot delete own account', function () {
    $admin = User::factory()->create([
        'role' => UserRole::ADMIN->value,
    ]);

    $response = $this
        ->actingAs($admin)
        ->delete(route('admin.users.destroy', $admin));

    $response->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseHas('users', [
        'id' => $admin->id,
        'role' => UserRole::ADMIN->value,

    ]);
});