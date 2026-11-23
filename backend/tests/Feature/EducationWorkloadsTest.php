<?php

use App\Models\School;
use App\Models\User;
use App\Services\EducationWorkloadImport;
use App\Services\SpreadsheetExport;
use App\UserRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->withoutVite();
    config(['pedbase.education_catalog_enabled' => true, 'inertia.ssr.enabled' => false]);
    $this->admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $this->teacher = User::factory()->create(['role' => UserRole::TEACHER]);
    $this->region = DB::table('education_regions')->insertGetId(['code' => 'FIXTURE-R', 'name' => 'Test region']);
    $this->area = DB::table('education_areas')->insertGetId(['code' => 'FIXTURE-A', 'name' => 'Test area', 'region_id' => $this->region]);
    $this->org = function (string $type, string $bin): int {
        $id = DB::table('education_organization_types')->where('code', $type)->value('id');
        $id ??= DB::table('education_organization_types')->insertGetId(['code' => $type, 'name' => $type]);

        return DB::table('education_organizations')->insertGetId([
            'type_id' => $id, 'area_id' => $this->area, 'bin' => $bin, 'name' => "Test $type", 'locality' => 'Test locality',
        ]);
    };
    $this->makeFile = function (string $type, array $rows): UploadedFile {
        $headers = array_values(app(EducationWorkloadImport::class)->columns($type));
        $path = app(SpreadsheetExport::class)->xlsx([$headers, ...$rows]);
        try {
            return UploadedFile::fake()->createWithContent('workload.xlsx', file_get_contents($path));
        } finally {
            unlink($path);
        }
    };
    $this->sample = fn (string $bin, string $placement = 'secondary', string $area = 'FIXTURE-A') => [$bin, $area, $this->teacher->username, 'Test position', $placement, '2026', '4.25', 'academic_hour', 'week'];
});

it('imports a type specific workbook after preview, protects confirmation and retains archived history', function (string $type, array $details) {
    $bin = '000000000450';
    $id = ($this->org)($type, $bin);
    $this->actingAs($this->admin);
    $this->get(route('education-organizations.template', ['type' => $type, 'workload' => 1]))
        ->assertDownload("pedbase-education-$type-workload.xlsx");
    $file = ($this->makeFile)($type, [[...($this->sample)($bin, 'primary'), ...$details]]);
    $input = ['file' => $file, 'workload_type' => $type];
    $token = $this->postJson(route('education-organizations.imports.preview'), $input)
        ->assertOk()->assertJsonCount(0, 'rows.0.errors')->json('token');
    expect($token)->toBeString();
    $this->assertDatabaseCount('education_workloads', 0);
    $this->postJson(route('education-organizations.imports.confirm'), array_replace($input, ['token' => $token, 'workload_type' => null]))
        ->assertUnprocessable();
    $this->postJson(route('education-organizations.imports.confirm'), $input + ['token' => $token])
        ->assertOk()->assertJson(['created' => 1]);
    $this->postJson(route('education-organizations.imports.confirm'), $input + ['token' => $token])->assertUnprocessable();
    $this->assertDatabaseHas('education_primary_workplaces', ['user_id' => $this->teacher->id, 'organization_id' => $id]);
    $this->assertDatabaseHas('audit_events', ['action' => 'spreadsheet.import.education_workloads']);
    $this->patch(route('education-organizations.status', $id), ['status' => 'archived'])->assertRedirect();
    $this->actingAs($this->teacher);
    $this->get(route('education-organizations.reports.index'))->assertInertia(fn (Assert $page) => $page
        ->component('reports/Catalog')->has('rows.data', 1)->where('rows.data.0.unit', 'academic_hour')
        ->where('rows.data.0.period', 'week')->where('rows.data.0.teacher', $this->teacher->name)
        ->where('rows.data.0.archived_at', fn ($value) => $value !== null));
    $export = $this->get(route('education-organizations.reports.export', 'csv'))->assertOk();
    expect($export->streamedContent())->toContain($this->teacher->name)->not->toContain($this->teacher->email);
})->with([
    'college' => ['college', ['TEST-SPEC', 'Test specialty', 'TEST-QUAL', 'Test qualification', 'Test program', 'Test module', '']],
    'preschool' => ['preschool', ['Test direction', 'Test age group', 'Test category']],
    'supplementary' => ['supplementary', ['Test club', '', 'Test program', 'Test direction']],
    'special' => ['special', ['Test program', 'Test direction']],
]);

it('rejects mixed area and primary workplace conflicts without partial writes', function () {
    $type = 'special';
    $bin1 = '000000000451';
    $bin2 = '000000000452';
    ($this->org)($type, $bin1);
    ($this->org)($type, $bin2);
    $this->actingAs($this->admin);
    $first = [...($this->sample)($bin1, 'primary'), 'Test program', 'Test direction'];
    $second = [...($this->sample)($bin2, 'primary'), 'Test program', 'Test direction'];
    $invalid = [...($this->sample)($bin2, 'secondary', 'UNKNOWN'), 'Test program', 'Test direction'];
    $this->postJson(route('education-organizations.imports.preview'), [
        'file' => ($this->makeFile)($type, [$first, $second, $invalid]), 'workload_type' => $type,
    ])->assertOk()->assertJsonCount(1, 'rows.1.errors')->assertJsonCount(1, 'rows.2.errors')->assertJsonPath('token', null);
    $this->assertDatabaseCount('education_workloads', 0);
    $this->assertDatabaseCount('education_employments', 0);
});

it('blocks conflicting primary workplaces across the legacy school and enabled catalog', function () {
    $bin = '000000000457';
    ($this->org)('special', $bin);
    $school = School::query()->create(['name' => 'Test legacy school', 'is_active' => true]);
    $this->teacher->setPrimarySchool($school);
    $this->actingAs($this->admin);
    $row = [...($this->sample)($bin, 'primary'), 'Test program', 'Test direction'];
    $input = ['file' => ($this->makeFile)('special', [$row]), 'workload_type' => 'special'];
    $this->postJson(route('education-organizations.imports.preview'), $input)
        ->assertOk()->assertJsonCount(1, 'rows.0.errors')->assertJsonPath('token', null);
    $this->teacher->schools()->detach($school);
    $token = $this->postJson(route('education-organizations.imports.preview'), $input)->assertOk()->json('token');
    $this->postJson(route('education-organizations.imports.confirm'), $input + ['token' => $token])->assertOk();
    expect(fn () => $this->teacher->setPrimarySchool($school))->toThrow(HttpException::class);
    $this->assertDatabaseCount('school_user', 0);
});

it('keeps amounts in separate unit and period groups and omits course document data from exports', function () {
    $bin = '000000000456';
    $id = ($this->org)('special', $bin);
    $this->actingAs($this->admin);
    $week = [...($this->sample)($bin), 'Test program', 'Test direction'];
    $year = $week;
    $year[8] = 'academic_year';
    $clock = $week;
    $clock[7] = 'clock_hour';
    $file = ($this->makeFile)('special', [$week, $year, $clock]);
    $input = ['file' => $file, 'workload_type' => 'special'];
    $token = $this->postJson(route('education-organizations.imports.preview'), $input)->assertOk()->json('token');
    $this->postJson(route('education-organizations.imports.confirm'), $input + ['token' => $token])->assertOk();
    $this->get(route('education-organizations.reports.index', ['report' => 'workload_totals', 'organization_id' => $id]))
        ->assertInertia(fn (Assert $page) => $page->has('rows.data', 3)
            ->where('rows.data.0.period', 'academic_year')->where('rows.data.2.unit', 'clock_hour'));

    $this->teacher->courses()->create([
        'title' => 'Test course', 'provider' => 'Test provider', 'hours' => 12,
        'started_on' => '2026-01-01', 'ended_on' => '2026-01-02', 'certificate_number' => 'PRIVATE-FIXTURE',
        'certificate_path' => 'private/fixture.pdf',
    ]);
    $export = $this->get(route('education-organizations.reports.export', ['format' => 'csv', 'report' => 'courses', 'provider' => 'Test provider']))
        ->assertOk()->streamedContent();
    expect($export)->toContain('Test provider')->not->toContain('PRIVATE-FIXTURE')->not->toContain('private/fixture.pdf');
    $xlsx = $this->get(route('education-organizations.reports.export', ['format' => 'xlsx', 'report' => 'courses']))
        ->assertDownload('pedbase-catalog-courses.xlsx');
    $path = $xlsx->baseResponse->getFile()->getPathname();
    $zip = new ZipArchive;
    try {
        expect($zip->open($path))->toBeTrue();
        expect($zip->getFromName('xl/worksheets/sheet1.xml'))->toContain('Test provider')->not->toContain('PRIVATE-FIXTURE');
        $zip->close();
    } finally {
        unlink($path);
    }
});

it('scopes administrators, methodists and teachers to permitted organizations and blocks non-admin imports', function () {
    $id = ($this->org)('college', '000000000453');
    $other = ($this->org)('preschool', '000000000454');
    $manager = User::factory()->create(['role' => UserRole::SCHOOL_ADMIN]);
    $methodist = User::factory()->create(['role' => UserRole::METHODIST]);
    DB::table('education_organization_admins')->insert(['user_id' => $manager->id, 'organization_id' => $id]);
    DB::table('education_methodist_grants')->insert([
        'user_id' => $methodist->id, 'area_id' => $this->area,
        'type_id' => DB::table('education_organization_types')->where('code', 'college')->value('id'),
    ]);
    $this->actingAs($manager);
    $this->put(route('education-organizations.update', $id), ['name' => 'Renamed', 'locality' => 'Test', 'bin' => '000000000455'])->assertRedirect();
    $this->assertDatabaseHas('education_organizations', ['id' => $id, 'name' => 'Renamed', 'bin' => '000000000453']);
    $this->get(route('education-organizations.edit', $other))->assertForbidden();
    foreach ([$manager, $methodist, $this->teacher] as $actor) {
        $this->actingAs($actor);
        $this->postJson(route('education-organizations.imports.preview'), [])->assertForbidden();
        $this->get(route('education-organizations.reports.export', ['format' => 'xlsx', 'organization_id' => $other]))->assertForbidden();
    }
    $this->actingAs($methodist)->get(route('education-organizations.reports.index', ['organization_id' => $id]))->assertOk();
    config(['pedbase.education_catalog_enabled' => false]);
    $this->actingAs($this->admin)->get(route('education-organizations.reports.index'))->assertNotFound();
});

it('filters multiple college assignments and attestation history in reports and exports', function () {
    $bin = '000000000457';
    ($this->org)('college', $bin);
    $this->actingAs($this->admin);
    $base = [...($this->sample)($bin), 'TEST-SPEC', 'Test specialty', 'TEST-QUAL', 'Test qualification', 'Test program'];
    $file = ($this->makeFile)('college', [
        [...$base, 'Test module A', ''],
        [...$base, 'Test module B', ''],
        [...$base, '', 'Test subject'],
    ]);
    $input = ['file' => $file, 'workload_type' => 'college'];
    $token = $this->postJson(route('education-organizations.imports.preview'), $input)->assertOk()->json('token');
    $this->postJson(route('education-organizations.imports.confirm'), $input + ['token' => $token])->assertOk();
    $this->assertDatabaseCount('education_workloads', 3);
    $this->actingAs($this->teacher)->get(route('dossiers.show', $this->teacher))
        ->assertInertia(fn (Assert $page) => $page->where('showCatalogEmployment', true));
    $this->actingAs($this->admin)->get(route('dossiers.show', $this->teacher))
        ->assertInertia(fn (Assert $page) => $page->where('showCatalogEmployment', false));

    $this->get(route('education-organizations.reports.index', ['module' => 'Test module A', 'unit' => 'academic_hour', 'period' => 'week']))
        ->assertInertia(fn (Assert $page) => $page->has('rows.data', 1));
    $this->get(route('education-organizations.reports.index', ['module' => 'Test module A', 'subject' => 'Test subject']))
        ->assertInertia(fn (Assert $page) => $page->has('rows.data', 0));
    $csv = $this->get(route('education-organizations.reports.export', ['format' => 'csv', 'subject' => 'Test subject']))
        ->assertOk()->streamedContent();
    expect($csv)->toContain('Test subject')->not->toContain('Test module A');

    $this->teacher->attestations()->create(['category' => 'Test category', 'awarded_on' => '2025-09-01', 'valid_until' => '2030-09-01']);
    $this->teacher->attestations()->create(['category' => 'Previous category', 'awarded_on' => '2021-09-01']);
    $this->get(route('education-organizations.reports.index', [
        'report' => 'categories', 'awarded_from' => '2025-01-01', 'awarded_until' => '2025-12-31',
    ]))->assertInertia(fn (Assert $page) => $page->has('rows.data', 1)
        ->where('rows.data.0.awarded_on', '2025-09-01')->where('rows.data.0.valid_until', '2030-09-01'));
    $this->getJson(route('education-organizations.reports.index', [
        'report' => 'categories', 'awarded_from' => '2025-01-01', 'awarded_until' => '2024-12-31',
    ]))->assertUnprocessable()->assertJsonValidationErrors('awarded_until');
});
