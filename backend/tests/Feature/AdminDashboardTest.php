<?php

use App\Models\School;
use App\Models\Subject;
use App\Models\TeacherEmployment;
use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\DB;
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

test('the admin dashboard exposes aggregate workload and safe audit fields', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $teacher = User::factory()->create(['role' => UserRole::TEACHER]);
    $school = School::create(['name' => 'Synthetic school']);
    $teacher->schools()->attach($school, ['is_primary' => true]);
    $employment = TeacherEmployment::create([
        'school_user_id' => DB::table('school_user')->where('user_id', $teacher->id)->value('id'),
        'user_id' => $teacher->id,
        'school_id' => $school->id,
        'position_title' => 'Teacher',
        'status' => 'active',
    ]);
    $subject = Subject::create(['code' => 'TEST', 'name_kk' => 'Synthetic subject']);
    $employment->assignments()->create(['subject_id' => $subject->id, 'academic_year' => 2026, 'weekly_hours' => 2.5]);
    DB::table('audit_events')->insert([
        ['actor_id' => $admin->id, 'action' => 'assignment.create', 'subject_type' => 'teaching_assignment', 'subject_id' => 1, 'created_at' => '2026-11-24 12:00:00'],
        ['actor_id' => $admin->id, 'action' => 'certificate.download', 'subject_type' => 'courses', 'subject_id' => 2, 'created_at' => '2026-11-24 12:05:00'],
    ]);

    config(['pedbase.education_catalog_enabled' => true]);
    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('analytics.catalogOrganizations', 0)
            ->where('analytics.workloadByYear', [['year' => 2026, 'hours' => 2.5]])
            ->has('analytics.recentActivities', 1)
            ->where('analytics.recentActivities.0.action', 'assignment.create')
            ->missing('analytics.recentActivities.0.actor_id')
            ->missing('analytics.recentActivities.0.subject_id'));
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
