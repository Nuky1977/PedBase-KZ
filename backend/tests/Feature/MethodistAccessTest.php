<?php

use App\Models\User;
use App\UserRole;
use Inertia\Testing\AssertableInertia as Assert;
test('admin can access methodist page', function () {
    $admin = User::factory()->create([
        'role' => UserRole::ADMIN,
    ]);

    $response = $this
        ->actingAs($admin)
        ->get('/methodist');

    $response->assertOk();

$response->assertInertia(fn (Assert $page) => $page
    ->component('methodist/Dashboard')
);
});

test('methodist can access methodist page', function () {
    $methodist = User::factory()->create([
        'role' => UserRole::METHODIST,
    ]);

    $response = $this
        ->actingAs($methodist)
        ->get('/methodist');

    $response->assertOk();

$response->assertInertia(fn (Assert $page) => $page
    ->component('methodist/Dashboard')
);
});

test('school admin cannot access methodist page', function () {
    $schoolAdmin = User::factory()->create([
        'role' => UserRole::SCHOOL_ADMIN,
    ]);

    $response = $this
        ->actingAs($schoolAdmin)
        ->get('/methodist');

    $response->assertForbidden();
});

test('teacher cannot access methodist page', function () {
    $teacher = User::factory()->create([
        'role' => UserRole::TEACHER,
    ]);

    $response = $this
        ->actingAs($teacher)
        ->get('/methodist');

    $response->assertForbidden();
});