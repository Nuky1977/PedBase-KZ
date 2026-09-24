<?php

use App\Models\User;
use App\UserRole;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can access teacher page', function () {
    $admin = User::factory()->create([
        'role' => UserRole::ADMIN,
    ]);

    $response = $this
        ->actingAs($admin)
        ->get('/teacher');

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('teacher/Dashboard')
    );
});

test('teacher can access teacher page', function () {
    $teacher = User::factory()->create([
        'role' => UserRole::TEACHER,
    ]);

    $response = $this
        ->actingAs($teacher)
        ->get('/teacher');

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('teacher/Dashboard')
    );
});

test('methodist cannot access teacher page', function () {
    $methodist = User::factory()->create([
        'role' => UserRole::METHODIST,
    ]);

    $response = $this
        ->actingAs($methodist)
        ->get('/teacher');

    $response->assertForbidden();
});

test('school admin cannot access teacher page', function () {
    $schoolAdmin = User::factory()->create([
        'role' => UserRole::SCHOOL_ADMIN,
    ]);

    $response = $this
        ->actingAs($schoolAdmin)
        ->get('/teacher');

    $response->assertForbidden();
});