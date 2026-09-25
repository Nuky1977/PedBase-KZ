<?php

use App\Models\TeacherProfile;
use App\Models\User;
use App\UserRole;

test('teacher can have one teacher profile', function () {
    $teacher = User::factory()->create([
        'role' => UserRole::TEACHER,
    ]);

    $profile = $teacher->teacherProfile()->create([
        'iin' => '950101123456',
        'birth_date' => '1995-01-01',
        'gender' => 'Ер',
        'phone' => '+77001234567',
        'education_level' => 'Жоғары',
        'educational_institution' => 'Тест университеті',
        'graduation_year' => 2017,
        'diploma_number' => 'DIP-001',
        'specialty' => 'Информатика',
        'total_experience_months' => 96,
        'teaching_experience_months' => 84,
    ]);

    expect($profile)->toBeInstanceOf(TeacherProfile::class);
    expect($profile->user_id)->toBe($teacher->id);

    $teacher->refresh();

    expect($teacher->teacherProfile)->not->toBeNull();
    expect($teacher->teacherProfile->iin)->toBe('950101123456');
    expect($teacher->teacherProfile->specialty)->toBe('Информатика');
    expect($teacher->teacherProfile->birth_date->format('Y-m-d'))
        ->toBe('1995-01-01');

    $this->assertDatabaseHas('teacher_profiles', [
        'user_id' => $teacher->id,
        'iin' => '950101123456',
        'specialty' => 'Информатика',
        'total_experience_months' => 96,
        'teaching_experience_months' => 84,
    ]);
});test('teacher can create and update own profile', function () {
    $teacher = User::factory()->create([
        'role' => UserRole::TEACHER,
    ]);

    $response = $this
        ->actingAs($teacher)
        ->put(route('teacher.profile.update'), [
            'iin' => '900101123456',
            'birth_date' => '1990-01-01',
            'gender' => 'Ер',
            'phone' => '+77001234567',
            'education_level' => 'Жоғары',
            'educational_institution' => 'Тест университеті',
            'graduation_year' => 2012,
            'diploma_number' => 'DIP-TEST-001',
            'specialty' => 'Информатика',
            'total_experience_months' => 156,
            'teaching_experience_months' => 144,
        ]);

    expect($response->getStatusCode())->toBe(302);

    $this->assertDatabaseHas('teacher_profiles', [
        'user_id' => $teacher->id,
        'iin' => '900101123456',
        'specialty' => 'Информатика',
        'total_experience_months' => 156,
        'teaching_experience_months' => 144,
    ]);

    $response = $this
        ->actingAs($teacher)
        ->put(route('teacher.profile.update'), [
            'iin' => '900101123456',
            'birth_date' => '1990-01-01',
            'gender' => 'Ер',
            'phone' => '+77007654321',
            'education_level' => 'Жоғары',
            'educational_institution' => 'Тест университеті',
            'graduation_year' => 2012,
            'diploma_number' => 'DIP-TEST-001',
            'specialty' => 'Информатика пәнінің мұғалімі',
            'total_experience_months' => 157,
            'teaching_experience_months' => 145,
        ]);

    expect($response->getStatusCode())->toBe(302);

    $this->assertDatabaseHas('teacher_profiles', [
        'user_id' => $teacher->id,
        'iin' => '900101123456',
        'phone' => '+77007654321',
        'specialty' => 'Информатика пәнінің мұғалімі',
        'total_experience_months' => 157,
        'teaching_experience_months' => 145,
    ]);

    expect(
        \App\Models\TeacherProfile::where(
            'user_id',
            $teacher->id
        )->count()
    )->toBe(1);
});