<?php

use App\Models\User;
use App\Services\EducationOrganizationRegistration;
use App\Services\SpreadsheetExport;
use App\Services\SpreadsheetImportReader;
use App\UserRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

pest()->extend(TestCase::class);

beforeEach(function () {
    if (! app()->environment('testing')) {
        throw new RuntimeException('Catalog tests require the testing environment.');
    }
    if (getenv('PEDBASE_CATALOG_MYSQL_TEST') === '1') {
        config([
            'database.default' => 'catalog_test',
            'database.connections.catalog_test' => [
                'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 33408, 'database' => 'pedbase_catalog_test',
                'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'strict' => true,
            ],
        ]);
        $target = DB::selectOne('SELECT DATABASE() AS schema_name, @@port AS server_port');
        if ($target->schema_name !== 'pedbase_catalog_test' || (int) $target->server_port !== 33408) {
            throw new RuntimeException('Unexpected MySQL test target.');
        }
    } else {
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new RuntimeException('Catalog tests require isolated SQLite.');
        }
        DB::unprepared(<<<'SQL'
            CREATE TABLE users (id INTEGER PRIMARY KEY);
            CREATE TABLE education_organization_types (id INTEGER PRIMARY KEY, code TEXT NOT NULL UNIQUE, name TEXT NOT NULL);
            CREATE TABLE education_regions (id INTEGER PRIMARY KEY, code TEXT NOT NULL UNIQUE, name TEXT NOT NULL, source_url TEXT, source_updated_on TEXT);
            CREATE TABLE education_areas (id INTEGER PRIMARY KEY, code TEXT NOT NULL UNIQUE, name TEXT NOT NULL, region_id INTEGER NOT NULL REFERENCES education_regions(id), source_url TEXT, source_updated_on TEXT);
            CREATE TABLE education_organizations (id INTEGER PRIMARY KEY, type_id INTEGER NOT NULL REFERENCES education_organization_types(id), area_id INTEGER NOT NULL REFERENCES education_areas(id), name TEXT NOT NULL, short_name TEXT, bin TEXT NOT NULL UNIQUE, locality TEXT NOT NULL, is_active INTEGER NOT NULL, archived_at TEXT, created_at TEXT, updated_at TEXT);
            CREATE TABLE audit_events (id INTEGER PRIMARY KEY, actor_id INTEGER REFERENCES users(id), action TEXT NOT NULL, subject_type TEXT NOT NULL, subject_id INTEGER NOT NULL, created_at TEXT NOT NULL);
            SQL);
    }
    DB::beginTransaction();
    config(['pedbase.education_catalog_enabled' => true, 'inertia.ssr.enabled' => false]);
    $this->withoutVite();
    if (DB::connection()->getDriverName() === 'mysql') {
        $this->adminId = User::factory()->create(['role' => UserRole::ADMIN])->id;
    } else {
        $this->adminId = 41;
        DB::table('users')->insert(['id' => $this->adminId]);
    }
    $this->actingAs((new User)->forceFill(['id' => $this->adminId, 'role' => UserRole::ADMIN]));
    $this->referencePath = tempnam(sys_get_temp_dir(), 'pedbase-areas-');
    $this->references = [
        'source_url' => 'https://example.invalid/catalog-test',
        'source_updated_on' => '2026-01-01',
        'regions' => [['code' => 'TEST-R1', 'name' => 'Synthetic region 1'], ['code' => 'TEST-R2', 'name' => 'Synthetic region 2']],
        'areas' => [
            ['code' => 'TEST-A1', 'name' => 'Same name', 'region_code' => 'TEST-R1'],
            ['code' => 'TEST-A2', 'name' => 'Same name', 'region_code' => 'TEST-R2'],
        ],
    ];
    file_put_contents($this->referencePath, json_encode($this->references));
    $this->referenceOptions = [
        '--database' => DB::getDefaultConnection(), '--expect-schema' => DB::connection()->getDatabaseName(), '--file' => $this->referencePath,
    ];
    $this->artisan('education:prepare-references', $this->referenceOptions + ['--apply' => true])->assertSuccessful();

    $template = $this->get(route('education-organizations.template', ['type' => 'school', 'registration' => 1]));
    $template->assertDownload('pedbase-education-school-registration.xlsx');
    $path = $template->baseResponse->getFile()->getPathname();
    try {
        $this->headers = app(SpreadsheetImportReader::class)->read($path)[1];
    } finally {
        unlink($path);
    }
    $this->workbook = function (array $rows, ?array $headers = null): UploadedFile {
        $path = app(SpreadsheetExport::class)->xlsx([$headers ?? $this->headers, ...$rows]);
        try {
            return UploadedFile::fake()->createWithContent('organizations.xlsx', file_get_contents($path));
        } finally {
            unlink($path);
        }
    };
});

afterEach(function () {
    if (isset($this->referencePath)) {
        unlink($this->referencePath);
    }
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('installs sourced references idempotently and keeps the parent of an existing area', function () {
    $this->artisan('education:prepare-references', $this->referenceOptions)->assertSuccessful();
    $this->artisan('education:prepare-references', $this->referenceOptions + ['--apply' => true])->assertSuccessful();
    expect(DB::table('education_organization_types')->count())->toBe(5)
        ->and(DB::table('education_areas')->where('name', 'Same name')->count())->toBe(2)
        ->and(DB::table('education_areas')->where('code', 'TEST-A1')->value('source_url'))->toBe($this->references['source_url'])
        ->and(DB::table('education_regions')->where('code', 'TEST-R2')->value('source_updated_on'))->toBe('2026-01-01');
    $changed = $this->references;
    $changed['areas'][0]['region_code'] = 'TEST-R2';
    $changed['regions'][0]['name'] = 'Must roll back';
    file_put_contents($this->referencePath, json_encode($changed));
    $this->artisan('education:prepare-references', $this->referenceOptions)->assertFailed();
    $this->artisan('education:prepare-references', $this->referenceOptions + ['--apply' => true])->assertFailed();
    expect(DB::table('education_regions')->where('code', 'TEST-R1')->value('name'))->toBe('Synthetic region 1');
    $changed['areas'][1]['code'] = 'test-a1';
    file_put_contents($this->referencePath, json_encode($changed));
    $this->artisan('education:prepare-references', $this->referenceOptions)->assertFailed();
});

it('lets administrators register and edit an organization using the selected region and area', function () {
    $this->get(route('education-organizations.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('education-organizations/Form')->has('types', 5)->has('regions', 2)->has('areas', 2));
    $this->get(route('education-organizations.imports.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('education-organizations/Import')->has('types', 5)->has('regions', 2));
    $input = [
        'name' => 'Synthetic organization', 'short_name' => '0', 'bin' => '000000000401',
        'type_code' => 'college', 'region_code' => 'TEST-R1', 'area_code' => 'TEST-A2',
        'locality' => 'Synthetic locality', 'status' => 'active',
    ];
    $this->post(route('education-organizations.store'), $input)->assertSessionHasErrors('area_code');
    $input['region_code'] = 'TEST-R2';
    $this->post(route('education-organizations.store'), $input)->assertRedirect();
    $id = DB::table('education_organizations')->where('bin', $input['bin'])->value('id');
    $this->get(route('education-organizations.edit', $id))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('education-organizations/Form')->where('organization.short_name', '0')->where('organization.region_code', 'TEST-R2'));
    $this->post(route('education-organizations.store'), $input)->assertSessionHasErrors('bin');
    $input['status'] = 'archived';
    $this->put(route('education-organizations.update', $id), $input)->assertRedirect(route('education-organizations.show', $id));
    expect(DB::table('education_organizations')->where('id', $id)->value('archived_at'))->not->toBeNull();

    foreach ([UserRole::SCHOOL_ADMIN, UserRole::METHODIST, UserRole::TEACHER] as $role) {
        $this->actingAs((new User)->forceFill(['id' => 81, 'role' => $role]));
        $this->get(route('education-organizations.create'))->assertForbidden();
        $this->get(route('education-organizations.edit', $id))->assertForbidden();
        $this->put(route('education-organizations.update', $id), $input)->assertForbidden();
        $this->postJson(route('education-organizations.imports.preview'), [])->assertForbidden();
        $this->postJson(route('education-organizations.imports.confirm'), [])->assertForbidden();
    }
    $this->actingAs((new User)->forceFill(['id' => $this->adminId, 'role' => UserRole::ADMIN]));
    config(['pedbase.education_catalog_enabled' => false]);
    $this->get(route('education-organizations.create'))->assertNotFound();
    $this->postJson(route('education-organizations.imports.preview'), [])->assertNotFound();
});

it('downloads five registration workbooks and imports all five types exactly once', function () {
    $rows = [];
    foreach (array_keys(EducationOrganizationRegistration::TYPES) as $code) {
        $response = $this->get(route('education-organizations.template', ['type' => $code, 'registration' => 1]));
        $response->assertDownload("pedbase-education-{$code}-registration.xlsx");
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            expect(app(SpreadsheetImportReader::class)->read($path))->toBe([1 => $this->headers]);
        } finally {
            unlink($path);
        }
        $rows[] = ['Synthetic '.$code, '', sprintf('%012d', count($rows) + 700), $code, 'TEST-R1', 'TEST-A1', 'Synthetic locality', 'active'];
    }
    $preview = route('education-organizations.imports.preview');
    $confirm = route('education-organizations.imports.confirm');
    $file = ($this->workbook)($rows);
    $token = $this->postJson($preview, ['file' => $file])->assertOk()->assertJsonCount(5, 'rows')->json('token');
    expect($token)->toBeString();
    expect(DB::table('education_organizations')->count())->toBe(0);
    $changed = $rows;
    $changed[0][0] = 'Different workbook';
    $this->postJson($confirm, ['file' => ($this->workbook)($changed), 'token' => $token])->assertUnprocessable();
    $this->postJson($confirm, ['file' => $file, 'token' => $token])->assertOk()->assertJson(['created' => 5]);
    $this->postJson($confirm, ['file' => $file, 'token' => $token])->assertUnprocessable();
    expect(DB::table('education_organizations')->distinct()->count('type_id'))->toBe(5)
        ->and(DB::table('audit_events')->where('action', 'spreadsheet.import.education_organizations')->count())->toBe(1);
});

it('returns exact row errors and rolls back every row when the audit insert fails', function () {
    $row = ['Synthetic organization', '', '000000000801', 'school', 'TEST-R1', 'TEST-A1', 'Synthetic locality', 'inactive'];
    $invalid = $row;
    $invalid[5] = 'TEST-A2';
    $invalid[3] = 'unknown';
    $preview = route('education-organizations.imports.preview');
    $confirm = route('education-organizations.imports.confirm');
    $this->postJson($preview, ['file' => ($this->workbook)([$row], array_reverse(array_slice($this->headers, 1)))])->assertUnprocessable();
    $this->postJson($preview, ['file' => ($this->workbook)([$row, $invalid])])->assertOk()
        ->assertJsonPath('rows.0.number', 2)->assertJsonPath('rows.1.number', 3)
        ->assertJsonCount(1, 'rows.0.errors')->assertJsonCount(3, 'rows.1.errors')->assertJsonPath('token', null);
    $second = $row;
    $second[2] = '000000000802';
    $file = ($this->workbook)([$row, $second]);
    $this->actingAs((new User)->forceFill(['id' => 9999999, 'role' => UserRole::ADMIN]));
    $token = $this->postJson($preview, ['file' => $file])->assertOk()->json('token');
    $this->postJson($confirm, ['file' => $file, 'token' => $token])->assertUnprocessable();
    expect(DB::table('education_organizations')->count())->toBe(0)->and(DB::table('audit_events')->count())->toBe(0);
});
