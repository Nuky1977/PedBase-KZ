<?php

use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

pest()->extend(TestCase::class);

beforeEach(function () {
    $database = DB::connection();
    if ($database->getDriverName() !== 'sqlite' || $database->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('Catalog tests require in-memory SQLite.');
    }
    config([
        'pedbase.education_catalog_enabled' => true,
        'session.driver' => 'array',
        'cache.default' => 'array',
        'inertia.ssr.enabled' => false,
    ]);

    DB::unprepared(<<<'SQL'
        CREATE TABLE education_organization_types (id INTEGER PRIMARY KEY, code TEXT, name TEXT);
        CREATE TABLE education_regions (id INTEGER PRIMARY KEY, code TEXT, name TEXT);
        CREATE TABLE education_areas (id INTEGER PRIMARY KEY, region_id INTEGER, code TEXT, name TEXT);
        CREATE TABLE education_organizations (id INTEGER PRIMARY KEY, type_id INTEGER, area_id INTEGER, name TEXT, short_name TEXT, bin TEXT, locality TEXT, is_active INTEGER, archived_at TEXT DEFAULT NULL);
        CREATE TABLE education_organization_admins (user_id INTEGER, organization_id INTEGER);
        CREATE TABLE education_methodist_grants (user_id INTEGER, area_id INTEGER, type_id INTEGER);
        CREATE TABLE education_employments (id INTEGER PRIMARY KEY, user_id INTEGER, organization_id INTEGER, position_title TEXT, status TEXT);
        CREATE TABLE education_primary_workplaces (user_id INTEGER, organization_id INTEGER);
        INSERT INTO education_organization_types VALUES (1, 'school', 'School'), (2, 'college', 'College');
        INSERT INTO education_regions VALUES (1, 'R1', 'Region'), (2, 'R2', 'Other region');
        INSERT INTO education_areas VALUES (1, 1, 'A1', 'Area A'), (2, 2, 'A2', 'Area B');
        INSERT INTO education_organizations (id, type_id, area_id, name, short_name, bin, locality, is_active) VALUES
            (1, 1, 1, 'School A', NULL, '000000000001', 'A', 1),
            (2, 2, 1, 'College A', NULL, '000000000002', 'A', 1),
            (3, 1, 2, 'School B', NULL, '000000000003', 'B', 1),
            (4, 2, 2, 'College B', NULL, '000000000004', 'B', 1);
        INSERT INTO education_methodist_grants VALUES (11, 1, 1), (11, 2, 2);
        INSERT INTO education_organization_admins VALUES (12, 2);
        INSERT INTO education_employments VALUES (1, 13, 1, 'Synthetic position', 'active'), (2, 13, 2, NULL, 'active'), (3, 14, 3, NULL, 'active');
        INSERT INTO education_primary_workplaces VALUES (13, 2);
        SQL);

    $this->catalogUser = static fn (int $id, UserRole $role) => (new User)->forceFill(['id' => $id, 'role' => $role]);
});

it('filters organizations by type for an administrator and shows the card', function () {
    $this->actingAs(($this->catalogUser)(10, UserRole::ADMIN));
    $this->get(route('education-organizations.index', ['type' => 'college']))->assertInertia(fn (Assert $page) => $page
        ->component('education-organizations/Index')->has('organizations.data', 2)->where('type', 'college'));
    $this->get(route('education-organizations.show', 2))->assertInertia(fn (Assert $page) => $page
        ->component('education-organizations/Show')->where('organization.name', 'College A')->where('organization.region_name', 'Region'));
});

it('requires an exact area and organization-type grant for a methodist', function () {
    $this->actingAs(($this->catalogUser)(11, UserRole::METHODIST));
    $this->get(route('education-organizations.index'))->assertInertia(fn (Assert $page) => $page->has('organizations.data', 2));
    $this->get(route('education-organizations.show', 1))->assertOk();
    $this->get(route('education-organizations.show', 4))->assertOk();
    $this->get(route('education-organizations.show', 2))->assertForbidden();
    $this->get(route('education-organizations.show', 3))->assertForbidden();
});

it('combines region area and status filters within the assigned organizations', function () {
    DB::table('education_organizations')->where('id', 2)->update(['is_active' => false, 'archived_at' => '2026-10-01']);
    $this->actingAs(($this->catalogUser)(12, UserRole::SCHOOL_ADMIN));
    $this->get(route('education-organizations.index', ['region' => 'R1', 'area' => 'A1', 'status' => 'archived']))
        ->assertInertia(fn (Assert $page) => $page->has('regions', 1)->has('areas', 1)->has('organizations.data', 1)
            ->where('organizations.data.0.id', 2));
    $this->get(route('education-organizations.index', ['region' => 'R1', 'status' => 'inactive']))
        ->assertInertia(fn (Assert $page) => $page->has('organizations.data', 0));
    $this->get(route('education-organizations.index', ['region' => 'R2', 'area' => 'A2']))
        ->assertInertia(fn (Assert $page) => $page->has('organizations.data', 0));
});

it('limits organization administrators to assigned organizations', function () {
    $this->actingAs(($this->catalogUser)(12, UserRole::SCHOOL_ADMIN));
    $this->get(route('education-organizations.show', 2))->assertOk();
    $this->get(route('education-organizations.show', 1))->assertForbidden();
    $this->actingAs(($this->catalogUser)(17, UserRole::SCHOOL_ADMIN));
    $this->get(route('education-organizations.index'))->assertInertia(fn (Assert $page) => $page->has('organizations.data', 0));
    $this->get(route('education-organizations.show', 2))->assertForbidden();
});

it('shows teachers only their own work and keeps the catalog disabled by default', function () {
    $this->actingAs(($this->catalogUser)(13, UserRole::TEACHER));
    $this->get(route('teacher.organization-employment'))->assertInertia(fn (Assert $page) => $page
        ->component('education-organizations/Employment')->has('employments.data', 2)
        ->where('employments.data.0.name', 'College A')->where('employments.data.0.is_primary', true));
    $this->get(route('education-organizations.index'))->assertForbidden();
    $this->actingAs(($this->catalogUser)(10, UserRole::ADMIN));
    $this->get(route('teacher.organization-employment'))->assertForbidden();
    config(['pedbase.education_catalog_enabled' => false]);
    $this->get(route('education-organizations.index'))->assertNotFound();
});

it('provides only administrators with type-specific draft workbooks', function () {
    $this->actingAs(($this->catalogUser)(10, UserRole::ADMIN));
    $response = $this->get(route('education-organizations.template', 'college'))
        ->assertDownload('pedbase-education-college-draft.xlsx');
    $path = $response->baseResponse->getFile()->getPathname();
    $zip = new ZipArchive;
    try {
        expect($zip->open($path))->toBeTrue();
        expect($zip->getFromName('xl/worksheets/sheet1.xml'))->toContain('Кәсіптік модуль коды');
        expect($zip->getFromName('xl/worksheets/sheet2.xml'))->toContain('Нақты жеке деректерді енгізбеңіз.');
        $zip->close();
    } finally {
        unlink($path);
    }
    $this->get(route('education-organizations.template', 'unknown'))->assertNotFound();
    $this->actingAs(($this->catalogUser)(11, UserRole::METHODIST));
    $this->get(route('education-organizations.template', 'college'))->assertForbidden();
});
