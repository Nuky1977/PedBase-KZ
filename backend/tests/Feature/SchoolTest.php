<?php

use App\Models\School;

test('school can be created', function () {
    $school = School::create([
        'name' => 'Тест жалпы білім беретін мектебі',
        'short_name' => 'Тест мектебі',
        'bin' => '123456789012',
        'type' => 'Жалпы білім беретін мектеп',
        'locality' => 'Сарқан қаласы',
        'is_active' => true,
    ]);

    expect($school->exists)->toBeTrue();
    expect($school->name)->toBe('Тест жалпы білім беретін мектебі');
    expect($school->is_active)->toBeTrue();

    $this->assertDatabaseHas('schools', [
        'id' => $school->id,
        'name' => 'Тест жалпы білім беретін мектебі',
        'bin' => '123456789012',
        'is_active' => 1,
    ]);
});