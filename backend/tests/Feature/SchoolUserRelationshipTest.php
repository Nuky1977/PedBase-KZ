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
});