<?php

use App\Models\EducationOrganization;
use App\Models\School;
use App\Models\User;
use App\Services\EducationReports;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $type = DB::table('education_organization_types')->insertGetId(['code' => 'school', 'name' => 'School']);
    $region = DB::table('education_regions')->insertGetId(['code' => 'R1', 'name' => 'Region']);
    $area = DB::table('education_areas')->insertGetId(['region_id' => $region, 'code' => 'AREA', 'name' => 'Area']);
    $district = DB::table('districts')->insertGetId(['name' => 'Legacy district']);
    $this->school = School::create(['name' => 'Example school', 'bin' => '000000000001', 'locality' => 'Locality', 'is_active' => true]);
    $this->school->forceFill(['district_id' => $district])->save();
    $this->teacher = User::factory()->create(['role' => UserRole::TEACHER]);
    $this->teacher->schools()->attach($this->school->id, ['is_primary' => true]);
    $this->mapping = ["$district:AREA"];
    $this->switches = ['--database' => 'sqlite', '--expect-schema' => ':memory:', '--area' => $this->mapping];
    $this->organizationValues = ['type_id' => $type, 'area_id' => $area, 'name' => 'Example school', 'bin' => '000000000001', 'locality' => 'Locality'];
});

it('previews without writes and copies the school and primary placement only once', function () {
    $this->artisan('education:copy-schools', $this->switches)->assertSuccessful();
    $this->assertDatabaseCount('education_organizations', 0);
    $this->artisan('education:copy-schools', ['--apply' => true])->assertFailed();
    $this->artisan('education:copy-schools', $this->switches + ['--apply' => true])->assertSuccessful();
    $this->artisan('education:copy-schools', $this->switches + ['--apply' => true])
        ->expectsOutput('Applied: schools=0, placements=0, primary=0')->assertSuccessful();
    $entry = EducationOrganization::query()->sole();
    expect($entry->legacySchool->id)->toBe($this->school->id);
    $this->assertDatabaseHas('education_primary_workplaces', ['user_id' => $this->teacher->id, 'organization_id' => $entry->id]);
    $this->assertDatabaseCount('education_employments', 1);
    $this->assertDatabaseCount('school_user', 1);
});

it('refuses to claim an existing BIN even when the names match', function () {
    $entry = EducationOrganization::create($this->organizationValues);
    $this->artisan('education:copy-schools', $this->switches + ['--apply' => true])->assertFailed();
    expect($entry->fresh()->legacy_school_id)->toBeNull();
    $this->assertDatabaseCount('education_employments', 0);
});

it('rolls back the whole batch when a later school has no valid mapping', function () {
    School::create(['name' => 'Unmapped school', 'locality' => 'Locality', 'bin' => '000000000002']);
    $this->artisan('education:copy-schools', $this->switches + ['--apply' => true])->assertFailed();
    $this->assertDatabaseCount('education_organizations', 0);
    $this->assertDatabaseCount('education_employments', 0);
});

it('archives without removing employment, legacy placements or report visibility', function () {
    $this->artisan('education:copy-schools', $this->switches + ['--apply' => true])->assertSuccessful();
    $entry = EducationOrganization::query()->sole();
    $url = route('education-organizations.status', $entry->id);
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    config(['pedbase.education_catalog_enabled' => true]);
    $this->actingAs($this->teacher)->patchJson($url, ['status' => 'archived'])->assertForbidden();
    $this->actingAs($admin)->patchJson($url, ['status' => 'deleted'])->assertUnprocessable();
    expect(Gate::forUser($admin)->denies('delete', $entry))->toBeTrue();
    $this->patch($url, ['status' => 'archived'])->assertRedirect(route('education-organizations.show', $entry->id));
    $this->patch($url, ['status' => 'archived'])->assertRedirect();
    expect($entry->fresh()->status())->toBe('archived');
    expect(DB::table('audit_events')->where('action', 'organization.status.archived')->count())->toBe(1);
    $this->assertDatabaseCount('education_employments', 1);
    $this->assertDatabaseCount('education_primary_workplaces', 1);
    $this->assertDatabaseCount('school_user', 1);
    expect(app(EducationReports::class)->query($admin, 'schools', $this->school->id, null)->first()->teachers)->toEqual(1);
    $this->actingAs($this->teacher)->get(route('teacher.organization-employment'))->assertInertia(fn (Assert $page) => $page
        ->where('employments.data.0.organization_archived', true)->where('employments.data.0.is_primary', true));
    $this->actingAs($admin)->patch($url, ['status' => 'inactive'])->assertRedirect();
    expect($entry->fresh()->status())->toBe('inactive');
});
