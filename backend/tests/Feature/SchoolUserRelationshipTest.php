<?php

use App\Models\School;
use App\Models\User;
use App\UserRole;

test('teacher can belong to multiple schools with one primary school', function () {
    $teacher = User::factory()->create([
        'role' => UserRole::TEACHER,
    ]);

    $primarySchool = School::create([
        'name' => 'Негізгі мектеп',
        'short_name' => '№1 мектеп',
        'bin' => '111111111111',
        'type' => 'Жалпы білім беретін мектеп',
        'locality' => 'Сарқан қаласы',
        'is_active' => true,
    ]);

    $secondarySchool = School::create([
        'name' => 'Қосымша мектеп',
        'short_name' => '№2 мектеп',
        'bin' => '222222222222',
        'type' => 'Жалпы білім беретін мектеп',
        'locality' => 'Сарқан қаласы',
        'is_active' => true,
    ]);

    $teacher->schools()->attach($primarySchool->id, [
        'is_primary' => true,
    ]);

    $teacher->schools()->attach($secondarySchool->id, [
        'is_primary' => false,
    ]);

    $teacher->load('schools');

    expect($teacher->schools)->toHaveCount(2);

    expect(
        $teacher->schools
            ->firstWhere('id', $primarySchool->id)
            ->pivot
            ->is_primary
    )->toBe(1);

    expect(
        $teacher->schools
            ->firstWhere('id', $secondarySchool->id)
            ->pivot
            ->is_primary
    )->toBe(0);

    $this->assertDatabaseHas('school_user', [
        'school_id' => $primarySchool->id,
        'user_id' => $teacher->id,
        'is_primary' => 1,
    ]);

    $this->assertDatabaseHas('school_user', [
        'school_id' => $secondarySchool->id,
        'user_id' => $teacher->id,
        'is_primary' => 0,
    ]);
});test('teacher can have only one primary school', function () {
    $teacher = User::factory()->create([
        'role' => UserRole::TEACHER,
    ]);

    $firstSchool = School::create([
        'name' => 'Бірінші мектеп',
        'short_name' => '№1 мектеп',
        'bin' => '333333333333',
        'type' => 'Жалпы білім беретін мектеп',
        'locality' => 'Сарқан қаласы',
        'is_active' => true,
    ]);

    $secondSchool = School::create([
        'name' => 'Екінші мектеп',
        'short_name' => '№2 мектеп',
        'bin' => '444444444444',
        'type' => 'Жалпы білім беретін мектеп',
        'locality' => 'Сарқан қаласы',
        'is_active' => true,
    ]);

    $teacher->schools()->attach($firstSchool->id, [
        'is_primary' => false,
    ]);

    $teacher->schools()->attach($secondSchool->id, [
        'is_primary' => false,
    ]);

    // Алдымен бірінші мектепті негізгі етеміз.
    $teacher->setPrimarySchool($firstSchool);

    $this->assertDatabaseHas('school_user', [
        'school_id' => $firstSchool->id,
        'user_id' => $teacher->id,
        'is_primary' => 1,
    ]);

    $this->assertDatabaseHas('school_user', [
        'school_id' => $secondSchool->id,
        'user_id' => $teacher->id,
        'is_primary' => 0,
    ]);

    // Кейін екінші мектепті негізгі етеміз.
    $teacher->setPrimarySchool($secondSchool);

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
});test('setting an unattached school as primary attaches it automatically', function () {
    $teacher = User::factory()->create([
        'role' => UserRole::TEACHER,
    ]);

    $school = School::create([
        'name' => 'Жаңа жұмыс орны',
        'short_name' => '№3 мектеп',
        'bin' => '555555555555',
        'type' => 'Жалпы білім беретін мектеп',
        'locality' => 'Сарқан қаласы',
        'is_active' => true,
    ]);

    expect($teacher->schools()->count())->toBe(0);

    $teacher->setPrimarySchool($school);

    expect($teacher->schools()->count())->toBe(1);

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
});