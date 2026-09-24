<?php

use App\Models\User;
use App\UserRole;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can access school admin page', function () {
    $admin = User::factory()->create([
        'role' => UserRole::ADMIN,
    ]);

    $response = $this
        ->actingAs($admin)
        ->get('/school-admin');

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('school-admin/Dashboard')
    );
});

test('school admin can access school admin page', function () {
    $schoolAdmin = User::factory()->create([
        'role' => UserRole::SCHOOL_ADMIN,
    ]);

    $response = $this
        ->actingAs($schoolAdmin)
        ->get('/school-admin');

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('school-admin/Dashboard')
    );
});

test('methodist cannot access school admin page', function () {
    $methodist = User::factory()->create([
        'role' => UserRole::METHODIST,
    ]);

    $response = $this
        ->actingAs($methodist)
        ->get('/school-admin');

    $response->assertForbidden();
});

test('teacher cannot access school admin page', function () {
    $teacher = User::factory()->create([
        'role' => UserRole::TEACHER,
    ]);

    $response = $this
        ->actingAs($teacher)
        ->get('/school-admin');

    $response->assertForbidden();
});