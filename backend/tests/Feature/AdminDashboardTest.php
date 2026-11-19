<?php

use App\Models\School;
use App\Models\User;
use App\UserRole;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login from the admin dashboard', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

test('the admin dashboard rejects other roles', function (UserRole $role) {
    $this->actingAs(User::factory()->create(['role' => $role]))
        ->get(route('admin.dashboard'))
        ->assertForbidden();
})->with([UserRole::METHODIST, UserRole::SCHOOL_ADMIN, UserRole::TEACHER]);

test('administrators land on the admin dashboard', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]))
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.dashboard'));
});

test('the admin dashboard returns zero for empty school and teacher data', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]))
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Dashboard')
            ->where('stats', [
                'schools' => 0,
                'activeSchools' => 0,
                'teachers' => 0,
                'users' => 1,
                'assignedTeachers' => 0,
                'primaryAssignments' => 0,
                'secondaryAssignments' => 0,
            ]));
});

test('the admin dashboard counts teachers once and separates assignment types', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $assignedTeacher = User::factory()->create(['role' => UserRole::TEACHER]);
    User::factory()->create(['role' => UserRole::TEACHER]);
    $schoolAdmin = User::factory()->create(['role' => UserRole::SCHOOL_ADMIN]);

    $activeSchool = School::create(['name' => 'Active school', 'is_active' => true]);
    $inactiveSchool = School::create(['name' => 'Inactive school', 'is_active' => false]);
    $assignedTeacher->schools()->attach($activeSchool, ['is_primary' => true]);
    $assignedTeacher->schools()->attach($inactiveSchool, ['is_primary' => false]);
    $schoolAdmin->schools()->attach($activeSchool, ['is_primary' => true]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Dashboard')
            ->where('stats', [
                'schools' => 2,
                'activeSchools' => 1,
                'teachers' => 2,
                'users' => 4,
                'assignedTeachers' => 1,
                'primaryAssignments' => 1,
                'secondaryAssignments' => 1,
            ]));
});
