<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use XMLWriter;
use ZipArchive;

class SpreadsheetExport
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const CONTENT = 'application/vnd.openxmlformats-officedocument.spreadsheetml.';

    private const GENERAL = 3;

    private const TEXT = 4;

    private const DECIMAL = 5;

    private const INTEGER = 6;

    private const DATE = 7;

    private const DATETIME = 8;

    private const STRIPE_OFFSET = 6;

    public function template(array $columns, array $instructions, array $required = [], array $choices = []): string
    {
        $headings = SpreadsheetLabels::translate($columns);
        $guide = [
            [__('Толтыру нұсқаулығы')],
            [__('Асты сызылған тақырыптар — міндетті өрістер.')],
            [__('Мысалдарды импорттамаңыз. Ресми кодтарды бекітілген анықтамалықтан алыңыз.')],
            ...$instructions,
            [__('Баған'), __('Толтыру тәртібі'), __('Синтетикалық мысал')],
        ];
        foreach ($headings as $key => $label) {
            $example = $choices[$key][0] ?? match ($key) {
                'name' => __('Синтетикалық атау'),
                'bin', 'iin' => '000000000001',
                'teacher', 'username' => 'synthetic_teacher',
                'email' => 'teacher@example.invalid',
                'year', 'academic_year' => 2026,
                'weekly_hours', 'amount' => 1.5,
                default => null,
            };
            $guide[] = [$label, in_array($key, $required, true) ? __('Міндетті') : __('Қосымша немесе шартты'), $example];
        }

        return $this->xlsx([array_values($headings)], $guide, array_keys($columns), $required, $choices);
    }

    public function xlsx(iterable $rows, ?iterable $instructions = null, array $keys = [], array $required = [], array $choices = []): string
    {
        $sheet = tempnam(sys_get_temp_dir(), 'pedbase-sheet-');
        $guide = $instructions === null ? null : tempnam(sys_get_temp_dir(), 'pedbase-guide-');
        $path = tempnam(sys_get_temp_dir(), 'pedbase-export-');

        try {
            if ($sheet === false || $path === false || $guide === false) {
                throw new RuntimeException('Cannot allocate export files.');
            }

            [$headers, $range] = $this->writeSheet($sheet, $rows, $keys, $required, $choices, $guide !== null);
            if ($guide !== null) {
                $this->writeGuide($guide, $instructions);
            }

            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Cannot open export archive.');
            }
            $types = $guide === null ? '' : '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $extraSheet = $guide === null ? '' : '<sheet name="'.self::escape(__('Толтыру нұсқаулығы')).'" sheetId="2" r:id="rId2"/>';
            $dataSheet = self::escape(__('Деректер'));
            $extraRelationship = $guide === null ? '' : '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>';
            $types .= '<Override PartName="/xl/styles.xml" ContentType="'.self::CONTENT.'styles+xml"/>';
            if ($range !== null) {
                $types .= '<Override PartName="/xl/tables/table1.xml" ContentType="'.self::CONTENT.'table+xml"/>';
                $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', $this->relationships('<Relationship Id="rId1" Type="'.self::REL.'/table" Target="../tables/table1.xml"/>'));
                $zip->addFromString('xl/tables/table1.xml', $this->table($headers, $range));
            }
            $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'.$types.'</Types>');
            $zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<workbook xmlns="'.self::NS.'" xmlns:r="'.self::REL.'"><bookViews><workbookView/></bookViews><sheets><sheet name="'.$dataSheet.'" sheetId="1" r:id="rId1"/>'.$extraSheet.'</sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->relationships('<Relationship Id="rId1" Type="'.self::REL.'/worksheet" Target="worksheets/sheet1.xml"/>'.$extraRelationship.'<Relationship Id="rId3" Type="'.self::REL.'/styles" Target="styles.xml"/>'));
            $zip->addFromString('xl/styles.xml', $this->styles());
            $zip->addFile($sheet, 'xl/worksheets/sheet1.xml');
            if ($guide !== null) {
                $zip->addFile($guide, 'xl/worksheets/sheet2.xml');
            }
            if (! $zip->close()) {
                throw new RuntimeException('Cannot write export archive.');
            }

            return $path;
        } catch (\Throwable $exception) {
            unset($zip);
            if ($path !== false) {
                unlink($path);
            }
            throw $exception;
        } finally {
            if ($sheet !== false) {
                unlink($sheet);
            }
            if (is_string($guide)) {
                unlink($guide);
            }
        }
    }

    private function writeSheet(string $path, iterable $rows, array $keys, array $required, array $choices, bool $template): array
    {
        $xml = $this->worksheet($path);
        $headers = $widths = [];
        $number = 0;
        foreach ($rows as $row) {
            $row = array_values($row);
            if (++$number === 1) {
                $headers = array_map('strval', $row);
                $widths = $this->columns($xml, $headers, $keys);
                $xml->startElement('sheetData');
            }
            $this->row($xml, $row, $number, $keys, $widths, $required);
        }
        if ($number === 0) {
            $xml->startElement('sheetData');
        }
        if ($number === 1 && $headers !== []) {
            $this->row($xml, array_fill(0, count($headers), null), ++$number, $keys, $widths, []);
        }
        $xml->endElement();

        $range = $headers === [] ? null : 'A1:'.$this->columnName(count($headers) - 1).$number;
        $table = $range !== null && ! in_array('', $headers, true) && count(array_unique(array_map(mb_strtolower(...), $headers))) === count($headers);
        if (! $table && $range !== null) {
            $xml->writeRaw('<autoFilter ref="'.$range.'"/>');
        }
        if ($template) {
            $this->validation($xml, $keys, $choices);
        }
        if ($table) {
            $xml->writeRaw('<tableParts count="1"><tablePart xmlns:r="'.self::REL.'" r:id="rId1"/></tableParts>');
        }

        $xml->endDocument();
        $xml->flush();

        return [$headers, $table ? $range : null];
    }

    private function worksheet(string $path): XMLWriter
    {
        $xml = new XMLWriter;
        if (! $xml->openUri($path)) {
            throw new RuntimeException('Cannot open worksheet.');
        }
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElementNs(null, 'worksheet', self::NS);
        $xml->writeRaw('<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A2" sqref="A2"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="18"/>');

        return $xml;
    }

    private function columns(XMLWriter $xml, array $headers, array $keys): array
    {
        $widths = [];
        if ($headers === []) {
            return $widths;
        }
        $xml->startElement('cols');
        foreach ($headers as $index => $header) {
            $key = $keys[$index] ?? '';
            $minimum = match ($key) {
                'name', 'organization', 'teacher', 'position', 'position_title', 'provider', 'subject', 'details',
                'specialty_name', 'qualification_name', 'program', 'module', 'course_title', 'award_title', 'issuer' => 34,
                'archived_at' => 25,
                default => 19,
            };
            $width = min(56, max($minimum, (int) ceil(mb_strlen($header) * 0.85) + 6));
            $widths[] = $width;
            $style = $this->format($key);
            $column = $index + 1;
            $xml->writeRaw("<col min=\"$column\" max=\"$column\" width=\"$width\" customWidth=\"1\" style=\"$style\"/>");
        }
        $xml->endElement();

        return $widths;
    }

    private function row(XMLWriter $xml, array $row, int $number, array $keys, array $widths, array $required): void
    {
        $height = 18;
        foreach ($row as $index => $value) {
            $capacity = max(1, ($widths[$index] ?? 25) - 4);
            $lines = array_sum(array_map(fn ($line) => max(1, ceil(mb_strwidth($line) / $capacity)), explode("\n", (string) $value)));
            $height = max($height, min(409, (int) ($lines * 15 + 3)));
        }
        $height = $number === 1 ? 40 : $height;
        $xml->startElement('row');
        $xml->writeAttribute('r', (string) $number);
        $xml->writeAttribute('ht', (string) $height);
        $xml->writeAttribute('customHeight', '1');
        foreach ($row as $index => $value) {
            $key = $keys[$index] ?? '';
            $format = $this->format($key);
            $style = $number === 1 ? (in_array($key, $required, true) ? 2 : 1) : $format + ($number % 2 === 0 ? 0 : self::STRIPE_OFFSET);
            $this->cell($xml, $this->columnName($index).$number, $value, $style, $number === 1 ? self::TEXT : $format);
        }
        $xml->endElement();
    }

    private function cell(XMLWriter $xml, string $reference, mixed $value, int $style, int $format): void
    {
        $xml->startElement('c');
        $xml->writeAttribute('r', $reference);
        $xml->writeAttribute('s', (string) $style);
        if ($value !== null && $value !== '') {
            $date = in_array($format, [self::DATE, self::DATETIME], true) ? $this->excelDate((string) $value) : null;
            $numeric = is_int($value) || is_float($value) || (in_array($format, [self::DECIMAL, self::INTEGER], true) && is_numeric($value));
            if ($date !== null || ($format !== self::TEXT && $numeric)) {
                $xml->writeElement('v', (string) ($date ?? $value));
            } else {
                $xml->writeAttribute('t', 'inlineStr');
                $xml->startElement('is');
                $xml->startElement('t');
                $xml->writeAttribute('xml:space', 'preserve');
                $xml->text(self::clean((string) $value));
                $xml->endElement();
                $xml->endElement();
            }
        }
        $xml->endElement();
    }

    private function format(string $key): int
    {
        return match ($key) {
            'bin', 'iin', 'kato', 'area', 'region', 'region_code', 'area_code', 'type_code', 'specialty_code',
            'qualification_code', 'module_code', 'program_code', 'subject_code', 'subject' => self::TEXT,
            'hours', 'weekly_hours', 'amount' => self::DECIMAL,
            'year', 'academic_year' => self::INTEGER,
            'awarded_on', 'valid_until', 'ended_on', 'certificate_issued_on', 'achievement_on' => self::DATE,
            'archived_at' => self::DATETIME,
            default => self::GENERAL,
        };
    }

    private function excelDate(string $value): ?float
    {
        $pattern = strlen($value) === 10 ? 'Y-m-d' : 'Y-m-d H:i:s';
        $date = DateTimeImmutable::createFromFormat('!'.$pattern, $value, new DateTimeZone('UTC'));
        if ($date === false || $date->format($pattern) !== $value || $value < '1900-01-01') {
            return null;
        }

        return $date->getTimestamp() / 86400 + ($value < '1900-03-01' ? 25568 : 25569);
    }

    private function validation(XMLWriter $xml, array $keys, array $choices): void
    {
        $rules = [];
        foreach ($keys as $index => $key) {
            $column = $this->columnName($index);
            $cell = $column.'2';
            $rule = isset($choices[$key]) ? ['list', '"'.implode(',', $choices[$key]).'"', null] : match ($key) {
                'bin', 'iin' => ['custom', 'IFERROR(AND(LEN('.$cell.')=12,TEXT(VALUE('.$cell.'),"000000000000")='.$cell.'),FALSE)', null],
                'year', 'academic_year' => ['whole', '1900', '9998'],
                'weekly_hours' => ['decimal', '0.01', '999.99'],
                'amount' => ['decimal', '0.01', '999999.99'],
                default => null,
            };
            if ($rule === null) {
                continue;
            }
            [$type, $first, $second] = $rule;
            $lastRow = SpreadsheetImportReader::MAX_ROWS + 1;
            $limits = $second === null ? '' : '<formula2>'.$second.'</formula2>';
            $rules[] = '<dataValidation type="'.$type.'" allowBlank="1" showErrorMessage="1" errorStyle="stop" errorTitle="'.self::escape(__('Жарамсыз мән')).'" error="'.self::escape(__('Нұсқаулықтағы пішімді немесе тізімдегі мәнді пайдаланыңыз.')).'" sqref="'.$column.'2:'.$column.$lastRow.'"><formula1>'.self::escape($first).'</formula1>'.$limits.'</dataValidation>';
        }
        if ($rules !== []) {
            $xml->writeRaw('<dataValidations count="'.count($rules).'">'.implode('', $rules).'</dataValidations>');
        }
    }

    private function writeGuide(string $path, iterable $rows): void
    {
        $xml = $this->worksheet($path);
        $xml->writeRaw('<cols><col min="1" max="1" width="94" customWidth="1"/><col min="2" max="3" width="35" customWidth="1"/></cols>');
        $xml->startElement('sheetData');
        $number = 0;
        foreach ($rows as $row) {
            $this->row($xml, array_values($row), ++$number, [], [94, 35, 35], []);
        }
        $xml->endDocument();
        $xml->flush();
    }

    private function table(array $headers, string $range): string
    {
        $columns = '';
        foreach ($headers as $index => $header) {
            $columns .= '<tableColumn id="'.($index + 1).'" name="'.self::escape($header).'"/>';
        }

        return '<table xmlns="'.self::NS.'" id="1" name="PedBaseData" displayName="PedBaseData" ref="'.$range.'" totalsRowShown="0"><autoFilter ref="'.$range.'"/><tableColumns count="'.count($headers).'">'.$columns.'</tableColumns><tableStyleInfo name="TableStyleLight1" showFirstColumn="0" showLastColumn="0" showRowStripes="1" showColumnStripes="0"/></table>';
    }

    private function styles(): string
    {
        $formats = '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>';
        foreach ([1, 2] as $font) {
            $formats .= '<xf numFmtId="0" fontId="'.$font.'" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>';
        }
        foreach ([0, 3] as $fill) {
            foreach ([0, 49, 164, 1, 165, 166] as $format) {
                $formats .= '<xf numFmtId="'.$format.'" fontId="0" fillId="'.$fill.'" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>';
            }
        }

        return '<styleSheet xmlns="'.self::NS.'"><numFmts count="3"><numFmt numFmtId="164" formatCode="0.00"/><numFmt numFmtId="165" formatCode="yyyy-mm-dd"/><numFmt numFmtId="166" formatCode="yyyy-mm-dd hh:mm:ss"/></numFmts>'
            .'<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><u/><name val="Calibri"/></font></fonts>'
            .'<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF163C72"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF0F5FC"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD6E1EE"/></left><right style="thin"><color rgb="FFD6E1EE"/></right><top style="thin"><color rgb="FFD6E1EE"/></top><bottom style="thin"><color rgb="FFD6E1EE"/></bottom><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="15">'.$formats.'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private function columnName(int $index): string
    {
        $name = '';
        do {
            $name = chr(65 + $index % 26).$name;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return $name;
    }

    private function relationships(string $relationships): string
    {
        return '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationships.'</Relationships>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars(self::clean($value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function clean(string $value): string
    {
        return preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '';
    }
}
