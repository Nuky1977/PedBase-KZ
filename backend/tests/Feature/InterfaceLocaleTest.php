<?php

use App\Models\User;
use App\Services\SpreadsheetExport;
use App\Services\SpreadsheetImport;
use App\Services\SpreadsheetImportReader;
use App\Services\SpreadsheetLabels;
use App\UserRole;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('the guest selects a language and returns to the same filtered URL', function () {
    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('locale', 'kk'));
    $target = '/login?notice=pilot';
    $response = $this->post(route('interface-locale.update'), ['locale' => 'ru', 'returnTo' => $target], ['X-Inertia' => 'true']);
    $response->assertStatus(409)->assertHeader('X-Inertia-Location', $target)->assertCookie('pedbase_locale', 'ru');
    $this->withCookie('pedbase_locale', 'ru')->get($target)
        ->assertSee('<html lang="ru"', false)
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'ru'));
    $this->withCookie('pedbase_locale', 'unsupported')->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'kk'));
});

test('the language endpoint rejects unsupported locales and unsafe return addresses', function () {
    $this->postJson(route('interface-locale.update'), ['locale' => 'fr', 'returnTo' => '/login'])
        ->assertUnprocessable()->assertJsonValidationErrors('locale');

    foreach (['https://example.invalid', '//example.invalid', '/\\example.invalid'] as $target) {
        $this->postJson(route('interface-locale.update'), ['locale' => 'en', 'returnTo' => $target])
            ->assertUnprocessable()->assertJsonValidationErrors('returnTo');
    }
});

test('validation templates and spreadsheet downloads follow the selected language', function (string $locale, string $reportHeading, string $templateHeading, string $requiredMessage) {
    $this->withCredentials()->withCookie('pedbase_locale', $locale);
    $this->postJson(route('login.store'), [])->assertUnprocessable()
        ->assertJsonPath('errors.username.0', $requiredMessage);
    $teacher = User::factory()->create(['role' => UserRole::TEACHER]);
    $this->actingAs($teacher)->get(route('admin.dashboard'))->assertForbidden();
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $this->actingAs($admin);
    $this->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page
        ->component('admin/Dashboard')->where('locale', $locale));
    $this->get(route('organization.teachers'))->assertInertia(fn (Assert $page) => $page
        ->component('organization/Teachers')->where('locale', $locale));
    $sheet = $this->get(route('admin.imports.template', 'schools'))->assertDownload()->baseResponse->getFile()->getPathname();
    try {
        $header = app(SpreadsheetImportReader::class)->read($sheet)[1];
        expect($header[0])->toBe($templateHeading);
        expect(SpreadsheetLabels::matchHeader(SpreadsheetImport::COLUMNS['schools'], $header))->not->toBeNull();
    } finally {
        unlink($sheet);
    }

    $input = app(SpreadsheetExport::class)->xlsx([
        $header, ['Synthetic school', 'SS', '000000000991', 'school', 'Synthetic locality', 'Иә'],
    ]);
    try {
        $preview = app(SpreadsheetImport::class)->inspect('schools', $input);
        expect($preview['errors'])->toBe([])->and($preview['data'][2]['name'])->toBe('Synthetic school');
    } finally {
        unlink($input);
    }

    $csv = $this->get(route('reports.export', 'csv'))->assertOk()->streamedContent();
    expect($csv)->toContain($reportHeading);
    $workbook = $this->get(route('reports.export', 'xlsx'))->assertDownload()->baseResponse->getFile()->getPathname();
    try {
        expect(app(SpreadsheetImportReader::class)->read($workbook)[1][0])->toBe($reportHeading);
    } finally {
        unlink($workbook);
    }
})->with([
    'kk' => ['kk', 'Мектеп', 'Атауы', '«логин» өрісін толтырыңыз.'],
    'ru' => ['ru', 'Школа', 'Название', 'Заполните поле «логин».'],
    'en' => ['en', 'School', 'Name', 'The username field is required.'],
]);

test('all three interface dictionaries have identical unique keys', function () {
    $keys = [];
    foreach (['kk', 'ru', 'en'] as $locale) {
        $json = file_get_contents(lang_path("$locale.json"));
        $dictionary = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        preg_match_all('/^\s{4}"(?:[^"\\\\]|\\\\.)+"\s*:/m', $json, $entries);
        expect(count($entries[0]))->toBe(count($dictionary));
        expect(array_filter($dictionary, fn ($message) => ! is_string($message) || trim($message) === ''))->toBe([]);
        $keys[] = array_keys($dictionary);
    }
    expect($keys[1])->toBe($keys[0])->and($keys[2])->toBe($keys[0]);
});
