<?php

use App\Models\School;
use App\Models\Subject;
use App\Models\TeacherEmployment;
use App\Models\User;
use App\UserRole;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $this->teacher = User::factory()->create(['role' => UserRole::TEACHER]);
    $this->school = School::create(['name' => 'Бірінші мектеп']);
    $this->otherSchool = School::create(['name' => 'Екінші мектеп']);
    $this->teacher->schools()->attach($this->school, ['is_primary' => true]);
    $this->teacher->schools()->attach($this->otherSchool, ['is_primary' => false]);
    $this->scope = [$this->school, $this->teacher];
    $this->position = ['position_title' => 'Пән мұғалімі', 'status' => 'active'];
});

test('the admin can save employment and manage subject assignments', function () {
    $this->actingAs($this->admin);
    $this->put(route('admin.employment.save', $this->scope), $this->position)->assertRedirect();
    $this->put(route('admin.employment.save', $this->scope), ['position_title' => 'Аға мұғалім', 'status' => 'on_leave'])->assertRedirect();
    $this->post(route('admin.employment.subjects.store', $this->scope), ['code' => 'mat', 'name_kk' => 'Математика'])->assertRedirect();
    $subject = Subject::where('code', 'MAT')->firstOrFail();
    $data = ['subject_id' => $subject->id, 'academic_year' => 2026, 'weekly_hours' => '1,5'];
    $this->post(route('admin.employment.assignments.store', $this->scope), $data)->assertRedirect()->assertSessionHasNoErrors();
    $employment = TeacherEmployment::sole();
    expect($employment->position_title)->toBe('Аға мұғалім')
        ->and($employment->status)->toBe('on_leave');
    $assignment = $employment->assignments()->sole();
    expect($assignment->weekly_hours)->toBe('1.50');
    $route = [...$this->scope, $assignment];
    $this->put(route('admin.employment.assignments.update', $route), [...$data, 'weekly_hours' => '0.50'])->assertRedirect()->assertSessionHasNoErrors();
    expect($assignment->refresh()->weekly_hours)->toBe('0.50');
    $this->delete(route('admin.employment.assignments.destroy', $route))->assertRedirect();
    $this->assertModelMissing($assignment);
});

test('other roles cannot view or change a school employment record', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);
    if ($role === UserRole::SCHOOL_ADMIN) {
        $user->schools()->attach($this->otherSchool);
    }

    $this->actingAs($user);
    $this->get(route('admin.employment.school', $this->school))->assertForbidden();
    $this->get(route('admin.employment.show', $this->scope))->assertForbidden();
    $this->put(route('admin.employment.save', $this->scope), $this->position)->assertForbidden();
    $this->assertDatabaseCount('teacher_employments', 0);
})->with([UserRole::TEACHER, UserRole::SCHOOL_ADMIN, UserRole::METHODIST]);

test('workload is added exactly by year and each teacher only sees their own schools', function () {
    $this->actingAs($this->admin);
    $this->put(route('admin.employment.save', $this->scope), $this->position);
    $this->put(route('admin.employment.save', [$this->otherSchool, $this->teacher]), ['position_title' => 'Тәлімгер', 'status' => 'active']);
    $unrelated = User::factory()->create(['role' => UserRole::TEACHER]);
    $unrelated->schools()->attach($this->school);
    $this->put(route('admin.employment.save', [$this->school, $unrelated]), $this->position);
    $employment = TeacherEmployment::where('user_id', $this->teacher->id)->where('school_id', $this->school->id)->sole();
    $math = Subject::create(['code' => 'MAT', 'name_kk' => 'Математика']);
    $physics = Subject::create(['code' => 'PHY', 'name_kk' => 'Физика']);
    $employment->assignments()->createMany([
        ['subject_id' => $math->id, 'academic_year' => 2026, 'weekly_hours' => '1.50'],
        ['subject_id' => $physics->id, 'academic_year' => 2026, 'weekly_hours' => '0.55'],
        ['subject_id' => $math->id, 'academic_year' => 2025, 'weekly_hours' => '0.50'],
    ]);

    $this->get(route('admin.employment.school', $this->school))->assertInertia(fn (Assert $page) => $page
        ->component('employment/Index')->has('records', 2));

    $this->actingAs($this->teacher)->get(route('teacher.employment.index', ['user' => $unrelated->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('employment/Index')->has('records', 2)
            ->where('records.0.name', $this->school->name)
            ->where('records.0.is_primary', true)
            ->has('records.0.employment.assignments', 3)
            ->where('records.0.employment.totals', [
                ['academic_year' => 2026, 'weekly_hours' => '2.05'],
                ['academic_year' => 2025, 'weekly_hours' => '0.50'],
            ])
            ->where('records.1.is_primary', false)
            ->where('records.1.employment.position_title', 'Тәлімгер'));
});

test('invalid and duplicate workloads are rejected and assignment IDs are scoped', function () {
    $this->actingAs($this->admin);
    $this->put(route('admin.employment.save', $this->scope), $this->position);
    $otherScope = [$this->otherSchool, $this->teacher];
    $this->put(route('admin.employment.save', $otherScope), $this->position);
    $subject = Subject::create(['code' => 'MAT', 'name_kk' => 'Математика']);
    $data = ['subject_id' => $subject->id, 'academic_year' => 2026, 'weekly_hours' => '0.50'];
    $url = route('admin.employment.assignments.store', $this->scope);

    $this->postJson($url, [...$data, 'weekly_hours' => '-0.50'])->assertUnprocessable()->assertJsonValidationErrors('weekly_hours');
    $this->postJson($url, [...$data, 'weekly_hours' => '1.125'])->assertUnprocessable()->assertJsonValidationErrors('weekly_hours');
    $this->post($url, $data)->assertRedirect()->assertSessionHasNoErrors();
    $this->postJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors('subject_id');
    $assignment = TeacherEmployment::where('school_id', $this->school->id)->sole()->assignments()->sole();
    $this->putJson(route('admin.employment.assignments.update', [...$otherScope, $assignment]), $data)->assertNotFound();
    $this->delete(route('admin.employment.assignments.destroy', [...$otherScope, $assignment]))->assertNotFound();
    $this->assertModelExists($assignment);
});

test('changing primary school preserves employment and detaching retains the old data', function () {
    $this->actingAs($this->admin);
    $this->put(route('admin.employment.save', $this->scope), $this->position);
    $employment = TeacherEmployment::sole();
    $subject = Subject::create(['code' => 'MAT', 'name_kk' => 'Математика']);
    $assignment = $employment->assignments()->create(['subject_id' => $subject->id, 'academic_year' => 2026, 'weekly_hours' => '0.50']);
    $this->delete(route('admin.schools.teachers.detach', $this->scope))->assertRedirect();
    $this->assertDatabaseHas('school_user', ['id' => $employment->school_user_id, 'is_primary' => true]);
    $this->patch(route('admin.schools.teachers.primary', [$this->otherSchool, $this->teacher]))->assertRedirect();
    $this->assertDatabaseHas('school_user', ['id' => $employment->school_user_id, 'is_primary' => false]);
    $this->delete(route('admin.schools.teachers.detach', $this->scope))->assertRedirect();

    expect($employment->refresh()->school_user_id)->toBeNull();
    $this->assertModelExists($assignment);
    $this->get(route('admin.employment.show', $this->scope))->assertNotFound();
    $this->post(route('admin.schools.teachers.attach', $this->school), ['teacher_id' => $this->teacher->id, 'is_primary' => false])->assertRedirect();
    $this->get(route('admin.employment.show', $this->scope))->assertInertia(fn (Assert $page) => $page
        ->component('employment/Edit')->where('employment', null)->where('isPrimary', false));
});

test('the school and teacher employment views support empty data', function () {
    $emptySchool = School::create(['name' => 'Бос мектеп']);
    $this->actingAs($this->admin)->get(route('admin.employment.school', $emptySchool))
        ->assertInertia(fn (Assert $page) => $page->component('employment/Index')->has('records', 0));
    $this->get(route('admin.employment.show', $this->scope))
        ->assertInertia(fn (Assert $page) => $page->component('employment/Edit')->where('employment', null)->has('subjects', 0));
    $this->actingAs(User::factory()->create(['role' => UserRole::TEACHER]))->get(route('teacher.employment.index'))
        ->assertInertia(fn (Assert $page) => $page->component('employment/Index')->has('records', 0)->where('school', null));
});

test('archiving details preserves the placement and permits a new employment record', function () {
    $this->actingAs($this->admin);
    $this->put(route('admin.employment.save', $this->scope), $this->position)->assertSessionHasNoErrors();
    $employment = TeacherEmployment::sole();
    $placementId = $employment->school_user_id;
    $this->delete(route('admin.employment.archive', $this->scope))->assertRedirect();
    expect($employment->refresh()->school_user_id)->toBeNull();
    $this->assertDatabaseHas('school_user', ['id' => $placementId, 'is_primary' => true]);
    $this->put(route('admin.employment.save', $this->scope), $this->position)->assertSessionHasNoErrors();
    $this->assertDatabaseCount('teacher_employments', 2);
    $this->assertDatabaseHas('teacher_employments', ['school_user_id' => $placementId]);
});
