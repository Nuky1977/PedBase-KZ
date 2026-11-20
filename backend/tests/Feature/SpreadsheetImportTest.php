<?php

use App\Models\School;
use App\Models\Subject;
use App\Models\User;
use App\Services\SpreadsheetExport;
use App\Services\SpreadsheetImport;
use App\Services\SpreadsheetImportReader;
use App\UserRole;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->files = [];
    $this->workbook = function (string $kind, array $rows) {
        $path = app(SpreadsheetExport::class)->xlsx([array_values(SpreadsheetImport::COLUMNS[$kind]), ...$rows]);
        $this->files[] = $path;

        return new UploadedFile($path, 'import.xlsx', null, UPLOAD_ERR_OK, true);
    };
    $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]));
});

afterEach(function () {
    foreach ($this->files as $path) {
        unlink($path);
    }
});

it('opens the administrator import page with both column maps', function () {
    $this->get(route('admin.imports.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('admin/Imports')->has('columns.schools', 6)->has('columns.teachers', 8));
});

it('provides separate two-sheet templates with empty data', function (string $kind) {
    $download = $this->get(route('admin.imports.template', $kind))->assertOk()->assertDownload("pedbase-{$kind}-template.xlsx");
    $path = $download->baseResponse->getFile()->getPathname();
    $this->files[] = $path;
    expect(app(SpreadsheetImportReader::class)->read($path))->toHaveCount(1);
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    expect($zip->getFromName('xl/worksheets/sheet2.xml'))->toContain('000000000001');
    $zip->close();
})->with(['schools', 'teachers']);

it('requires confirmation of the exact file and rejects replay after creation', function () {
    $file = ($this->workbook)('schools', [['Example school', 'ES', '000000000001', 'School', 'Example', '1']]);
    $preview = route('admin.imports.preview', 'schools');
    $confirm = route('admin.imports.confirm', 'schools');
    $token = $this->postJson($preview, ['file' => $file])->assertOk()->assertJsonPath('new_records', 1)->json('token');
    $this->assertDatabaseCount('schools', 0);
    $changed = ($this->workbook)('schools', [['Another school', '', '000000000002', '', '', '1']]);
    $this->postJson($confirm, ['file' => $changed, 'token' => $token])->assertUnprocessable();
    $admin = auth()->user();
    $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]))
        ->postJson($confirm, ['file' => $file, 'token' => $token])->assertUnprocessable();
    $this->actingAs($admin)->postJson($confirm, ['file' => $file, 'token' => $token])->assertOk()->assertJsonPath('created', 1);
    $this->assertDatabaseHas('schools', ['bin' => '000000000001']);
    $this->assertDatabaseHas('audit_events', ['action' => 'spreadsheet.import.schools']);
    $this->postJson($confirm, ['file' => $file, 'token' => $token])->assertUnprocessable();
    $this->assertDatabaseCount('schools', 1);
});

it('creates one teacher with one primary school and fractional assignments', function () {
    $first = School::create(['name' => 'First', 'bin' => '000000000001', 'is_active' => true]);
    School::create(['name' => 'Second', 'bin' => '000000000002', 'is_active' => true]);
    Subject::create(['code' => 'MAT', 'name_kk' => 'Math']);
    Subject::create(['code' => 'KAZ', 'name_kk' => 'Kazakh']);
    $file = ($this->workbook)('teachers', [
        ['Example Teacher', 'EXAMPLE_TEACHER', 'teacher@example.com', '000000000001', 'mat', 'Teacher', '1,50', 2026],
        ['Example Teacher', 'example_teacher', 'teacher@example.com', '000000000001', 'KAZ', 'Teacher', '0.50', 2026],
        ['Example Teacher', 'example_teacher', 'teacher@example.com', '000000000002', 'MAT', 'Tutor', '2', 2026],
    ]);
    $token = $this->postJson(route('admin.imports.preview', 'teachers'), ['file' => $file])
        ->assertOk()->assertJsonPath('new_records', 1)->assertJsonPath('rows.0.primary', true)
        ->assertJsonPath('rows.2.primary', false)->assertJsonPath('placements', 2)
        ->assertJsonPath('hours_by_year', [['year' => 2026, 'hours' => '4,00']])
        ->assertDontSee('teacher@example.com')->json('token');
    $this->assertDatabaseCount('teacher_employments', 0);
    $this->postJson(route('admin.imports.confirm', 'teachers'), ['file' => $file, 'token' => $token])->assertOk()->assertJsonPath('created', 1);
    $teacher = User::where('username', 'example_teacher')->sole();
    expect($teacher->role)->toBe(UserRole::TEACHER);
    expect(password_get_info($teacher->password)['algoName'])->not->toBe('unknown');
    $this->assertDatabaseCount('school_user', 2);
    $this->assertDatabaseHas('school_user', ['user_id' => $teacher->id, 'school_id' => $first->id, 'is_primary' => true]);
    $this->assertDatabaseCount('teacher_employments', 2);
    $this->assertDatabaseCount('teaching_assignments', 3);
    $this->assertDatabaseHas('teaching_assignments', ['weekly_hours' => 1.5, 'academic_year' => 2026]);
});

it('reports invalid references and duplicate school rows without issuing a token', function () {
    $row = ['Example', '', '000000000001', '', '', '1'];
    $this->postJson(route('admin.imports.preview', 'schools'), ['file' => ($this->workbook)('schools', [$row, $row])])
        ->assertOk()->assertJsonPath('token', null)->assertJsonPath('valid', 0);
    $rows = [['Example Teacher', 'example_teacher', 'teacher@example.com', '000000000001', 'MAT', 'Teacher', '1', 2026]];
    $this->postJson(route('admin.imports.preview', 'teachers'), ['file' => ($this->workbook)('teachers', $rows)])
        ->assertOk()->assertJsonPath('token', null)->assertJsonCount(2, 'rows.0.errors');
    $this->assertDatabaseCount('schools', 0);
});

it('rejects formulas and limits import endpoints to administrators', function () {
    $file = ($this->workbook)('schools', [['Example', '', '000000000001', '', '', '1']]);
    $zip = new ZipArchive;
    expect($zip->open($file->getPathname()))->toBeTrue();
    $sheet = 'xl/worksheets/sheet1.xml';
    $zip->addFromString($sheet, str_replace('</sheetData>', '<row><c><f>1+1</f><v>2</v></c></row></sheetData>', $zip->getFromName($sheet)));
    $zip->close();
    $this->postJson(route('admin.imports.preview', 'schools'), ['file' => $file])->assertUnprocessable();
    $this->actingAs(User::factory()->create(['role' => UserRole::TEACHER]));
    $this->get(route('admin.imports.index'))->assertForbidden();
    $this->get(route('admin.imports.template', 'schools'))->assertForbidden();
    $this->postJson(route('admin.imports.preview', 'schools'), ['file' => $file])->assertForbidden();
    $this->postJson(route('admin.imports.confirm', 'schools'), ['file' => $file])->assertForbidden();
});
