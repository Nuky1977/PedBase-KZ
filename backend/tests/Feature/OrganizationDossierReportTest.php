<?php

use App\Http\Requests\EducationReportRequest;
use App\Models\District;
use App\Models\School;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $this->schoolAdmin = User::factory()->create(['role' => UserRole::SCHOOL_ADMIN]);
    $this->methodist = User::factory()->create(['role' => UserRole::METHODIST]);
    $this->teacher = User::factory()->create(['role' => UserRole::TEACHER]);
    $this->outsider = User::factory()->create(['role' => UserRole::TEACHER]);
    $this->district = District::create(['name' => 'Аудан A']);
    $this->school = School::create(['name' => 'Мектеп A']);
    $this->outsideSchool = School::create(['name' => 'Мектеп B']);
    $this->teacher->schools()->attach($this->school, ['is_primary' => true]);
    $this->outsider->schools()->attach($this->outsideSchool, ['is_primary' => true]);
});

test('only administrators can grant persistent school and district access', function () {
    $this->actingAs($this->schoolAdmin)
        ->put(route('admin.employment.save', [$this->school, $this->teacher]), ['position_title' => 'Мұғалім', 'status' => 'active'])
        ->assertForbidden();
    $this->post(route('organization.access.save', 'school-admin'), ['school_id' => $this->school->id, 'user_id' => $this->schoolAdmin->id])
        ->assertForbidden();

    $this->actingAs($this->admin);
    $this->post(route('organization.access.save', 'school'), ['school_id' => $this->school->id, 'district_id' => $this->district->id])->assertSessionHasNoErrors();
    $this->post(route('organization.access.save', 'school-admin'), ['school_id' => $this->school->id, 'user_id' => $this->schoolAdmin->id])->assertSessionHasNoErrors();
    $this->post(route('organization.access.save', 'methodist'), ['district_id' => $this->district->id, 'user_id' => $this->methodist->id])->assertSessionHasNoErrors();

    $this->actingAs($this->schoolAdmin);
    $this->get(route('organization.school', $this->school))->assertOk();
    $this->get(route('organization.school', $this->outsideSchool))->assertForbidden();
    $this->put(route('admin.employment.save', [$this->school, $this->teacher]), ['position_title' => 'Мұғалім', 'status' => 'active'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->put(route('admin.employment.save', [$this->outsideSchool, $this->outsider]), ['position_title' => 'Мұғалім', 'status' => 'active'])
        ->assertForbidden();
    $this->actingAs($this->methodist)->get(route('dossiers.show', $this->teacher))->assertOk();
    $this->get(route('dossiers.show', $this->outsider))->assertForbidden();
    $this->put(route('admin.employment.save', [$this->school, $this->teacher]), ['position_title' => 'Мұғалім', 'status' => 'active'])->assertForbidden();

    $this->actingAs($this->admin)->put(route('admin.users.update', $this->schoolAdmin), [
        ...$this->schoolAdmin->only('name', 'username', 'email'),
        'role' => UserRole::TEACHER->value,
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseMissing('school_administrators', ['user_id' => $this->schoolAdmin->id]);
});

test('dossiers support multiple diplomas, attestation history, private certificates and scoped reads', function () {
    Storage::fake('teacher-documents');
    DB::table('school_administrators')->insert(['user_id' => $this->schoolAdmin->id, 'school_id' => $this->school->id]);
    $this->teacher->teacherProfile()->create(['phone' => 'synthetic-private-marker', 'education_level' => 'Жоғары']);
    $this->actingAs($this->teacher);
    $this->get(route('dossiers.show', $this->outsider))->assertForbidden();
    $diploma = ['education_level' => 'Жоғары', 'institution' => 'Сынақ ұйымы', 'specialty' => 'Физика', 'graduation_year' => 2018];
    $this->post(route('dossiers.store', [$this->teacher, 'diplomas']), $diploma)->assertSessionHasNoErrors();
    $this->post(route('dossiers.store', [$this->teacher, 'diplomas']), [...$diploma, 'graduation_year' => 2020])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('teacher_diplomas', 2);

    $course = ['title' => 'Сынақ курсы', 'provider' => 'Сынақ ұйымы', 'hours' => 30, 'started_on' => '2025-01-10',
        'ended_on' => '2025-01-20', 'certificate_number' => 'SYNTHETIC', 'certificate_issued_on' => '2025-01-21'];
    $courseUrl = route('dossiers.store', [$this->teacher, 'courses']);
    $this->postJson($courseUrl, [...$course, 'certificate' => UploadedFile::fake()->create('test.txt', 1, 'text/plain')])
        ->assertUnprocessable()->assertJsonValidationErrors('certificate');
    $this->post($courseUrl, [...$course, 'certificate' => UploadedFile::fake()->create('test.pdf', 1, 'application/pdf')])
        ->assertSessionHasNoErrors();
    $saved = $this->teacher->courses()->sole();
    Storage::disk('teacher-documents')->assertExists($saved->certificate_path);
    $this->get(route('dossiers.certificate', [$this->teacher, $saved]))->assertDownload('certificate.pdf');
    $this->post(route('dossiers.store', [$this->teacher, 'attestations']), ['category' => 'Сынақ санаты', 'awarded_on' => '2025-02-10', 'valid_until' => '2030-02-10'])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->schoolAdmin)->get(route('dossiers.show', $this->teacher))
        ->assertInertia(fn (Assert $page) => $page->component('dossier/Show')->where('diplomas', null)
            ->missing('profile.phone')->missing('profile.iin')->missing('courses.data.0.certificate_number')->missing('courses.data.0.certificate_path')
            ->has('attestations.data', 1));
    $this->get(route('dossiers.certificate', [$this->teacher, $saved]))->assertForbidden();
    $this->actingAs($this->teacher)->delete(route('dossiers.destroy', [$this->teacher, 'courses', $saved]))->assertRedirect();
    Storage::disk('teacher-documents')->assertMissing($saved->certificate_path);
    $this->assertDatabaseHas('audit_events', ['action' => 'dossier.remove', 'subject_id' => $saved->id]);
});

test('all reports run with correct scope and CSV Excel exports are downloadable', function () {
    DB::table('schools')->where('id', $this->school->id)->update(['district_id' => $this->district->id]);
    DB::table('district_methodists')->insert(['user_id' => $this->methodist->id, 'district_id' => $this->district->id]);
    $this->actingAs($this->methodist);
    $this->get(route('reports.index'))->assertInertia(fn (Assert $page) => $page->component('reports/Index')
        ->has('rows.data', 1)->where('rows.data.0.name', $this->school->name)->where('rows.data.0.teachers', 1));
    foreach (EducationReportRequest::REPORTS as $report) {
        $this->get(route('reports.index', ['report' => $report]))->assertOk();
    }
    $this->get(route('reports.index', ['school_id' => $this->outsideSchool->id]))->assertForbidden();
    $this->get(route('reports.export', ['format' => 'csv', 'school_id' => $this->outsideSchool->id]))->assertForbidden();
    $this->school->update(['name' => '=unsafe-test-value']);
    $csv = $this->get(route('reports.export', 'csv'))->assertDownload('pedbase-schools.csv')->streamedContent();
    expect($csv)->toContain("'=unsafe-test-value")->not->toContain($this->outsideSchool->name);
    $xlsx = $this->get(route('reports.export', 'xlsx'))->assertDownload('pedbase-schools.xlsx');
    $file = $xlsx->baseResponse->getFile()->getPathname();
    try {
        $zip = new ZipArchive;
        expect($zip->open($file))->toBeTrue();
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        expect($sheet)->toContain('inlineStr', '=unsafe-test-value')->not->toContain('<f>');
        $zip->close();
    } finally {
        unlink($file);
    }
});

test('primary school uniqueness is enforced and administrator accounts cannot be deleted', function () {
    expect(fn () => $this->teacher->schools()->attach($this->outsideSchool, ['is_primary' => true]))
        ->toThrow(UniqueConstraintViolationException::class);
    $this->teacher->setPrimarySchool($this->outsideSchool);
    expect($this->teacher->schools()->wherePivot('is_primary', true)->sole()->id)->toBe($this->outsideSchool->id);

    $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]))
        ->delete(route('admin.users.destroy', $this->admin))->assertForbidden();
    $this->actingAs($this->admin)->delete(route('profile.destroy'), ['password' => 'password'])->assertForbidden();
    $this->assertModelExists($this->admin);
});

test('attestation warnings use configured expiry dates and latest records only', function () {
    $this->travelTo(now()->setDate(2026, 11, 20));
    config(['pedbase.attestation_warning_days' => 30]);
    $this->teacher->attestations()->createMany([
        ['category' => 'Old', 'awarded_on' => '2020-01-01', 'valid_until' => '2026-11-28'],
        ['category' => 'Current', 'awarded_on' => '2025-01-01', 'valid_until' => '2026-12-05'],
    ]);
    $this->outsider->attestations()->create(['category' => 'Outside window', 'awarded_on' => '2025-01-01', 'valid_until' => '2027-01-15']);
    $this->actingAs($this->admin)->get(route('reports.index', ['report' => 'attestations']))
        ->assertInertia(fn (Assert $page) => $page->has('rows.data', 1)
            ->where('rows.data.0.category', 'Current')->where('warningDays', 30));
});
