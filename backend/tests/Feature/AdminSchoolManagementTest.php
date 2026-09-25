<?php

use App\Models\School;
use App\Models\User;
use App\UserRole;

test('admin can create a school', function () {
    
    $admin = User::factory()->create([
        'role' => UserRole::ADMIN,
    ]);

    $response = $this
        ->actingAs($admin)
        ->post(route('admin.schools.store'), [
            'name' => 'Жаңа тест мектебі',
            'short_name' => 'Тест ОМ',
            'bin' => '987654321012',
            'type' => 'Жалпы білім беретін мектеп',
            'locality' => 'Сарқан қаласы',
            'is_active' => '1',
        ]);

    expect($response->getStatusCode())->toBe(302);

    $this->assertDatabaseHas('schools', [
        'name' => 'Жаңа тест мектебі',
        'short_name' => 'Тест ОМ',
        'bin' => '987654321012',
        'locality' => 'Сарқан қаласы',
        'is_active' => 1,
    ]);
});

test('teacher cannot create a school', function () {
    $teacher = User::factory()->create([
        'role' => UserRole::TEACHER,
    ]);

    $response = $this
        ->actingAs($teacher)
        ->post(route('admin.schools.store'), [
            'name' => 'Рұқсатсыз мектеп',
            'short_name' => 'Рұқсатсыз ОМ',
            'bin' => '876543210123',
            'type' => 'Жалпы білім беретін мектеп',
            'locality' => 'Сарқан қаласы',
            'is_active' => '1',
        ]);

    $response->assertForbidden();

    $this->assertDatabaseMissing('schools', [
        'bin' => '876543210123',
    ]);
});

test('admin can update a school', function () {

    $admin = User::factory()->create([
        'role' => UserRole::ADMIN,
    ]);

    $school = School::create([
        'name' => 'Ескі мектеп атауы',
        'short_name' => 'Ескі ОМ',
        'bin' => '765432109876',
        'type' => 'Жалпы білім беретін мектеп',
        'locality' => 'Сарқан қаласы',
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($admin)
        ->put(route('admin.schools.update', $school), [
            'name' => 'Жаңартылған мектеп атауы',
            'short_name' => 'Жаңа ОМ',
            'bin' => $school->bin,
            'type' => 'Мектеп-гимназия',
            'locality' => 'Сарқан қаласы',
            'is_active' => '1',
        ]);

    expect($response->getStatusCode())->toBe(302);

    $this->assertDatabaseHas('schools', [
        'id' => $school->id,
        'name' => 'Жаңартылған мектеп атауы',
        'short_name' => 'Жаңа ОМ',
        'type' => 'Мектеп-гимназия',
        'is_active' => 1,
    ]);
});

test('admin can toggle school active status', function () {
    
    $admin = User::factory()->create([
        'role' => UserRole::ADMIN,
    ]);

    $school = School::create([
        'name' => 'Мәртебе тест мектебі',
        'short_name' => 'Мәртебе ОМ',
        'bin' => '654321098765',
        'type' => 'Жалпы білім беретін мектеп',
        'locality' => 'Сарқан қаласы',
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($admin)
        ->patch(route('admin.schools.toggle-status', $school));

    expect($response->getStatusCode())->toBe(302);

    $this->assertDatabaseHas('schools', [
        'id' => $school->id,
        'is_active' => 0,
    ]);

    $response = $this
        ->actingAs($admin)
        ->patch(route('admin.schools.toggle-status', $school));

    expect($response->getStatusCode())->toBe(302);

    $this->assertDatabaseHas('schools', [
        'id' => $school->id,
        'is_active' => 1,
    ]);
});

test('admin can attach a teacher to a school as primary', function () {
    $admin = User::factory()->create([
        'role' => UserRole::ADMIN,
    ]);

    $teacher = User::factory()->create([
        'role' => UserRole::TEACHER,
    ]);

    $school = School::create([
        'name' => 'Педагог бекіту тест мектебі',
        'short_name' => 'Бекіту ОМ',
        'bin' => '543210987654',
        'type' => 'Жалпы білім беретін мектеп',
        'locality' => 'Сарқан қаласы',
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($admin)
        ->post(route('admin.schools.teachers.attach', $school), [
            'teacher_id' => $teacher->id,
            'is_primary' => '1',
        ]);

    expect($response->getStatusCode())->toBe(302);

    $this->assertDatabaseHas('school_user', [
        'school_id' => $school->id,
        'user_id' => $teacher->id,
        'is_primary' => 1,
    ]);

    expect(
        $teacher->schools()
            ->wherePivot('is_primary', true)
            ->count()
    )->toBe(1);
    test('admin can detach a teacher from a secondary school', function () {
    $admin = User::factory()->create([
        'role' => UserRole::ADMIN,
    ]);

    $teacher = User::factory()->create([
        'role' => UserRole::TEACHER,
    ]);

    $school = School::create([
        'name' => 'Қосымша жұмыс орны мектебі',
        'short_name' => 'Қосымша ОМ',
        'bin' => '432109876543',
        'type' => 'Жалпы білім беретін мектеп',
        'locality' => 'Сарқан қаласы',
        'is_active' => true,
    ]);

    $teacher->schools()->attach($school->id, [
        'is_primary' => false,
    ]);

    $response = $this
        ->actingAs($admin)
        ->delete(
            route('admin.schools.teachers.detach', [
                'school' => $school,
                'teacher' => $teacher,
            ])
        );

    expect($response->getStatusCode())->toBe(302);

    $this->assertDatabaseMissing('school_user', [
        'school_id' => $school->id,
        'user_id' => $teacher->id,
    ]);
});

test('admin cannot detach a teacher from primary school', function () {
    $admin = User::factory()->create([
        'role' => UserRole::ADMIN,
    ]);

    $teacher = User::factory()->create([
        'role' => UserRole::TEACHER,
    ]);

    $school = School::create([
        'name' => 'Негізгі жұмыс орны мектебі',
        'short_name' => 'Негізгі ОМ',
        'bin' => '321098765432',
        'type' => 'Жалпы білім беретін мектеп',
        'locality' => 'Сарқан қаласы',
        'is_active' => true,
    ]);

    $teacher->schools()->attach($school->id, [
        'is_primary' => true,
    ]);

    $response = $this
        ->actingAs($admin)
        ->delete(
            route('admin.schools.teachers.detach', [
                'school' => $school,
                'teacher' => $teacher,
            ])
        );

    expect($response->getStatusCode())->toBe(302);

    $this->assertDatabaseHas('school_user', [
        'school_id' => $school->id,
        'user_id' => $teacher->id,
        'is_primary' => 1,
    ]);
    
});
});test('admin can detach a teacher from a secondary school', function () {
    $admin = User::factory()->create([
        'role' => UserRole::ADMIN,
    ]);

    $teacher = User::factory()->create([
        'role' => UserRole::TEACHER,
    ]);

    $school = School::create([
        'name' => 'Қосымша жұмыс орны мектебі',
        'short_name' => 'Қосымша ОМ',
        'bin' => '432109876543',
        'type' => 'Жалпы білім беретін мектеп',
        'locality' => 'Сарқан қаласы',
        'is_active' => true,
    ]);

    $teacher->schools()->attach($school->id, [
        'is_primary' => false,
    ]);

    $response = $this
        ->actingAs($admin)
        ->delete(
            route('admin.schools.teachers.detach', [
                'school' => $school,
                'teacher' => $teacher,
            ])
        );

    expect($response->getStatusCode())->toBe(302);

    $this->assertDatabaseMissing('school_user', [
        'school_id' => $school->id,
        'user_id' => $teacher->id,
    ]);
});

test('admin cannot detach a teacher from primary school', function () {
    $admin = User::factory()->create([
        'role' => UserRole::ADMIN,
    ]);

    $teacher = User::factory()->create([
        'role' => UserRole::TEACHER,
    ]);

    $school = School::create([
        'name' => 'Негізгі жұмыс орны мектебі',
        'short_name' => 'Негізгі ОМ',
        'bin' => '321098765432',
        'type' => 'Жалпы білім беретін мектеп',
        'locality' => 'Сарқан қаласы',
        'is_active' => true,
    ]);

    $teacher->schools()->attach($school->id, [
        'is_primary' => true,
    ]);

    $response = $this
        ->actingAs($admin)
        ->delete(
            route('admin.schools.teachers.detach', [
                'school' => $school,
                'teacher' => $teacher,
            ])
        );

    expect($response->getStatusCode())->toBe(302);

    $this->assertDatabaseHas('school_user', [
        'school_id' => $school->id,
        'user_id' => $teacher->id,
        'is_primary' => 1,
    ]);
});test('admin can change teacher primary school', function () {
    $admin = User::factory()->create([
        'role' => UserRole::ADMIN,
    ]);

    $teacher = User::factory()->create([
        'role' => UserRole::TEACHER,
    ]);

    $firstSchool = School::create([
        'name' => 'Бірінші негізгі мектеп',
        'short_name' => 'Бірінші ОМ',
        'bin' => '210987654321',
        'type' => 'Жалпы білім беретін мектеп',
        'locality' => 'Сарқан қаласы',
        'is_active' => true,
    ]);

    $secondSchool = School::create([
        'name' => 'Екінші мектеп',
        'short_name' => 'Екінші ОМ',
        'bin' => '109876543210',
        'type' => 'Жалпы білім беретін мектеп',
        'locality' => 'Сарқан қаласы',
        'is_active' => true,
    ]);

    $teacher->schools()->attach($firstSchool->id, [
        'is_primary' => true,
    ]);

    $teacher->schools()->attach($secondSchool->id, [
        'is_primary' => false,
    ]);

    $response = $this
        ->actingAs($admin)
        ->patch(
            route('admin.schools.teachers.primary', [
                'school' => $secondSchool,
                'teacher' => $teacher,
            ])
        );

    expect($response->getStatusCode())->toBe(302);

    $this->assertDatabaseHas('school_user', [
        'school_id' => $firstSchool->id,
        'user_id' => $teacher->id,
        'is_primary' => 0,
    ]);

    $this->assertDatabaseHas('school_user', [
        'school_id' => $secondSchool->id,
        'user_id' => $teacher->id,
        'is_primary' => 1,
    ]);

    expect(
        $teacher->schools()
            ->wherePivot('is_primary', true)
            ->count()
    )->toBe(1);
});