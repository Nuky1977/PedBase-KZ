<?php

use App\Models\User;
use App\Services\EducationOrganizationRegistration;
use App\Services\SpreadsheetExport;
use App\Services\SpreadsheetImportReader;
use App\UserRole;
use Illuminate\Validation\ValidationException;

test('blank organization templates retain the original headers and include a styled filterable table', function () {
    $path = app(EducationOrganizationRegistration::class)->template('college', app(SpreadsheetExport::class));
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();

    try {
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $styles = $zip->getFromName('xl/styles.xml');
        $table = $zip->getFromName('xl/tables/table1.xml');
        expect($sheet)->toContain('ySplit="1"', 'topLeftCell="A2"', 'state="frozen"', 'r="1" ht="40"',
            'customWidth="1"', '<c r="A1" s="2"', '<c r="B1" s="1"', '<c r="C2" s="4"',
            'sqref="C2:C101"', 'sqref="D2:D101"', 'sqref="H2:H101"', 'errorStyle="stop"',
            'active,inactive,archived', 'college,school,preschool,supplementary,special', '<tableParts count="1">');
        expect($styles)->toContain('FF163C72', 'FFFFFFFF', 'FFF0F5FC', 'wrapText="1"', 'horizontal="center"', 'vertical="center"', 'style="thin"');
        expect($table)->toContain('ref="A1:H2"', '<autoFilter ref="A1:H2"/>', 'showRowStripes="1"');
        expect(app(SpreadsheetImportReader::class)->read($path))->toBe([
            1 => ['Атауы', 'Қысқаша атауы', 'БСН', 'Ұйым түрінің коды', 'Облыс коды', 'Аудан немесе қала коды', 'Елді мекен', 'Мәртебе'],
        ]);

        $zip->addFromString('xl/tables/table1.xml', str_replace('name="Атауы"/>', 'name="Атауы"><calculatedColumnFormula>1+1</calculatedColumnFormula></tableColumn>', $table));
    } finally {
        $zip->close();
    }

    try {
        expect(fn () => app(SpreadsheetImportReader::class)->read($path))->toThrow(ValidationException::class);
    } finally {
        unlink($path);
    }
});

test('the template routes use the common layout across five organization types', function () {
    config(['pedbase.education_catalog_enabled' => true]);
    $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]));
    $routes = [route('admin.imports.template', 'teachers'), route('education-organizations.template', 'teacher-accounts')];
    foreach (['school', 'college', 'preschool', 'supplementary', 'special'] as $type) {
        $routes[] = route('education-organizations.template', ['type' => $type]);
        $routes[] = route('education-organizations.template', ['type' => $type, 'registration' => 1]);
        if ($type !== 'school') {
            $routes[] = route('education-organizations.template', ['type' => $type, 'workload' => 1]);
        }
    }
    foreach ($routes as $route) {
        $path = $this->get($route)->assertDownload()->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        expect($zip->open($path, ZipArchive::RDONLY))->toBeTrue();
        try {
            expect($zip->getFromName('xl/worksheets/sheet1.xml'))->toContain('ht="40"', 'state="frozen"');
            expect($zip->getFromName('xl/tables/table1.xml'))->toContain('<autoFilter');
            expect($zip->getFromName('xl/worksheets/sheet2.xml'))->toContain('Синтетикалық мысал');
        } finally {
            $zip->close();
            unlink($path);
        }
    }
});

test('reports keep numbers dates and codes typed and write formula-like text literally', function () {
    $path = app(SpreadsheetExport::class)->xlsx([
        ['Мәтін', 'БСН', 'Апталық сағат', 'Берілген күні'],
        ['=1+1', '000000000001', '1.50', '2026-11-25'],
        ['Synthetic title', '000000000002', 2, '2026-11-26'],
    ], keys: ['name', 'bin', 'hours', 'awarded_on']);
    $zip = new ZipArchive;
    expect($zip->open($path, ZipArchive::RDONLY))->toBeTrue();
    try {
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        expect($sheet)->toContain(
            'r="A2" s="3" t="inlineStr"', '>=1+1</t>',
            'r="B2" s="4" t="inlineStr"', '>000000000001</t>',
            'r="C2" s="5"><v>1.50</v>', 'r="D2" s="7"><v>46351</v>',
            'r="A3" s="9"',
        )->not->toContain('<f>');
        expect($zip->getFromName('xl/tables/table1.xml'))->toContain('<autoFilter ref="A1:D3"/>');
    } finally {
        $zip->close();
        unlink($path);
    }
});
