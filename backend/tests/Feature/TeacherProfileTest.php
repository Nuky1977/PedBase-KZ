<?php

use App\Models\TeacherProfile;
use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'identity.encryption_key' => base64_encode(random_bytes(32)),
        'identity.lookup_key' => base64_encode(random_bytes(32)),
        'identity.previous_encryption_keys' => [],
    ]);
});

test('Vault supplies separate IIN keys and unavailable Vault prevents writes and clearing', function () {
    $tokenFile = tempnam(sys_get_temp_dir(), 'pedbase-vault-');
    file_put_contents($tokenFile, 'synthetic-token');
    try {
        config([
            'identity.key_store' => 'vault',
            'identity.vault.address' => 'https://vault.example.test',
            'identity.vault.token_file' => $tokenFile,
        ]);
        Http::preventStrayRequests();
        $aesUrl = 'https://vault.example.test/v1/secret/data/pedbase/iin-aes';
        $hmacUrl = 'https://vault.example.test/v1/secret/data/pedbase/iin-hmac';
        Http::fake([
            $aesUrl => Http::sequence()->push(['data' => ['data' => ['current' => base64_encode(random_bytes(32))]]], 200)
                ->pushStatus(503)->pushStatus(503),
            $hmacUrl => Http::response(['data' => ['data' => ['current' => base64_encode(random_bytes(32))]]]),
        ]);
        $teacher = User::factory()->create(['role' => UserRole::TEACHER]);
        $url = route('teacher.profile.update');
        $this->actingAs($teacher)->put($url, ['iin' => '000000000009'])->assertRedirect(route('teacher.profile.edit'))->assertSessionHasNoErrors();
        $before = DB::table('teacher_profiles')->first();
        expect($before->iin)->toBeNull()->and($before->iin_ciphertext)->not->toBeNull()->and($before->iin_lookup)->toHaveLength(64);

        $this->putJson($url, ['iin' => '000000000008'])->assertServiceUnavailable();
        $this->putJson($url, ['iin' => null])->assertServiceUnavailable();
        expect((array) DB::table('teacher_profiles')->first())->toEqual((array) $before);
        Http::assertSent(fn ($request) => $request->url() === $hmacUrl && $request->hasHeader('X-Vault-Token', 'synthetic-token'));
    } finally {
        unlink($tokenFile);
    }
});

test('teacher can have one teacher profile', function () {
    $teacher = User::factory()->create([
        'role' => UserRole::TEACHER,
    ]);

    $profile = $teacher->teacherProfile()->create([
        'iin' => '000000000001',
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
    expect($teacher->teacherProfile->iin)->toBe('000000000001');
    expect($teacher->teacherProfile->specialty)->toBe('Информатика');
    expect($teacher->teacherProfile->birth_date->format('Y-m-d'))
        ->toBe('1995-01-01');

    $this->assertDatabaseHas('teacher_profiles', [
        'user_id' => $teacher->id,
        'iin' => null,
        'specialty' => 'Информатика',
        'total_experience_months' => 96,
        'teaching_experience_months' => 84,
    ]);
});
test('teacher can create and update own profile', function () {
    $teacher = User::factory()->create([
        'role' => UserRole::TEACHER,
    ]);

    $response = $this
        ->actingAs($teacher)
        ->put(route('teacher.profile.update'), [
            'iin' => '000000000002',
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
        'iin' => null,
        'specialty' => 'Информатика',
        'total_experience_months' => 156,
        'teaching_experience_months' => 144,
    ]);

    $response = $this
        ->actingAs($teacher)
        ->put(route('teacher.profile.update'), [
            'iin' => '000000000002',
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
        'iin' => null,
        'phone' => '+77007654321',
        'specialty' => 'Информатика пәнінің мұғалімі',
        'total_experience_months' => 157,
        'teaching_experience_months' => 145,
    ]);

    expect(
        TeacherProfile::where(
            'user_id',
            $teacher->id
        )->count()
    )->toBe(1);
});

test('protected IIN is unique, never serialized on the model or flashed on validation failure', function () {
    $owner = User::factory()->create(['role' => UserRole::TEACHER]);
    $other = User::factory()->create(['role' => UserRole::TEACHER]);
    $iin = '000000000003';
    $profile = $owner->teacherProfile()->create(['iin' => $iin]);
    $row = DB::table('teacher_profiles')->find($profile->id);
    expect($row->iin)->toBeNull()->and($row->iin_ciphertext)->not->toBe($iin)
        ->and($row->iin_lookup)->toHaveLength(64)->and($profile->toArray())->not->toHaveKeys(['iin', 'iin_ciphertext', 'iin_lookup']);
    $this->actingAs($other)->from(route('teacher.profile.edit'))
        ->put(route('teacher.profile.update'), ['iin' => $iin])->assertSessionHasErrors('iin')
        ->assertSessionMissing('_old_input.iin');
    $this->assertDatabaseCount('teacher_profiles', 1);
    $this->actingAs($owner)->get(route('teacher.profile.edit'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('profile.iin', $iin)->missing('auth.user.iin'));
    $this->actingAs($other)->get(route('teacher.profile.edit'))->assertInertia(fn ($page) => $page->where('profile', null));
});

test('previous encryption keys read existing IINs and missing keys fail closed', function () {
    $owner = User::factory()->create(['role' => UserRole::TEACHER]);
    $profile = $owner->teacherProfile()->create(['iin' => '000000000004']);
    $old = config('identity.encryption_key');
    config(['identity.encryption_key' => base64_encode(random_bytes(32)), 'identity.previous_encryption_keys' => [$old]]);
    expect($profile->fresh()->iin)->toBe('000000000004');
    config(['identity.lookup_key' => null]);
    expect($profile->fresh()->iin)->toBe('000000000004');
    config(['identity.previous_encryption_keys' => []]);
    $this->actingAs($owner)->get(route('teacher.profile.edit'))->assertServiceUnavailable();
});

test('legacy identifiers block new identifier writes while remaining available only to the profile owner', function () {
    $owner = User::factory()->create(['role' => UserRole::TEACHER]);
    $other = User::factory()->create(['role' => UserRole::TEACHER]);
    $iin = '000000000005';
    DB::table('teacher_profiles')->insert(['user_id' => $owner->id, 'iin' => $iin]);

    $this->actingAs($other)->put(route('teacher.profile.update'), ['iin' => $iin])
        ->assertSessionHasErrors('iin')->assertSessionMissing('_old_input.iin');
    $this->actingAs($owner)->get(route('teacher.profile.edit'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('profile.iin', $iin));
    $this->assertDatabaseCount('teacher_profiles', 1);
});
