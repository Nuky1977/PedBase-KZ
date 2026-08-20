<?php

use App\Models\User;

test('guest cannot access admin page', function () {
   $response = $this->get('/admin/users');

    $response->assertRedirect(route('login'));
});

test('teacher cannot access admin page', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
    ]);

    $response = $this
        ->actingAs($teacher)
        ->get('/admin/users');

    $response->assertForbidden();
});

test('admin can access admin page', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $response = $this
        ->actingAs($admin)
        ->get('/admin/users');

    $response->assertOk();
    $response->assertSee('PedBase KZ');
});