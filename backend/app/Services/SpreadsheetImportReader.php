<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class SpreadsheetImportReader
{
    public const MAX_ROWS = 100;

    /** @return array<int, array<int, string>> */
    public function read(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            $this->invalid('Файл жарамды XLSX жұмыс кітабы болуы керек.');
        }

        try {
            $this->verifyArchive($zip);
            [$firstSheet, $sharedStrings] = $this->workbook($zip);
            $sheet = $this->xml($zip, $firstSheet);
            $rows = [];
            $lastRow = 0;
            foreach ($sheet->query('/*[local-name()="worksheet"]/*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
                $number = $row->hasAttribute('r') ? (int) $row->getAttribute('r') : $lastRow + 1;
                if ($number <= $lastRow || $number > 1048576) {
                    $this->invalid('Жол нөмірлері жарамсыз.');
                }
                $lastRow = $number;
                $values = [];
                $lastColumn = -1;
                foreach ($sheet->query('./*[local-name()="c"]', $row) as $cell) {
                    $column = $this->column($cell, $number, $lastColumn);
                    $lastColumn = $column;
                    $value = $this->cell($sheet, $cell, $sharedStrings);
                    if ($value !== '') {
                        if ($column >= 8 || mb_strlen($value) > 1000) {
                            $this->invalid('Ұяшық немесе баған саны импорт шегінен асты.');
                        }
                        $values[$column] = $value;
                    }
                }
                if ($values !== []) {
                    $rows[$number] = $values;
                    if (count($rows) > self::MAX_ROWS + 1) {
                        $this->invalid('Бір файлға ең көбі 100 дерек жолын енгізіңіз.');
                    }
                }
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function verifyArchive(ZipArchive $zip): void
    {
        $total = 0;
        $seen = [];
        if ($zip->numFiles > 128) {
            $this->invalid('XLSX файлының ішкі бөліктері тым көп.');
        }
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->statIndex($index);
            if ($entry === false) {
                $this->invalid('XLSX архивінің құрылымы жарамсыз.');
            }
            $name = strtolower($entry['name']);
            $total += $entry['size'];
            if (isset($seen[$name]) || str_contains($name, '\\') || str_starts_with($name, '/')
                || preg_match('~(?:^|/)\.\.(?:/|$)|vba|^xl/(?:externallinks|embeddings|activex|connections)~i', $name)
                || ($entry['encryption_method'] ?? 0) !== 0 || $entry['size'] > 4 * 1024 * 1024 || $total > 16 * 1024 * 1024) {
                $this->invalid('Қорғалған, сыртқы байланысы бар немесе көлемі тым үлкен файл қабылданбайды.');
            }
            $seen[$name] = true;
            if (str_ends_with($name, '.rels') && $this->xml($zip, $entry['name'])->query('//*[@TargetMode="External"]')->length > 0) {
                $this->invalid('XLSX файлында сыртқы байланыстар болмауы керек.');
            }
            if (preg_match('~^xl/worksheets/[^/]+\.xml$~', $name)
                && $this->xml($zip, $entry['name'])->query('//*[local-name()="f"]')->length > 0) {
                $this->invalid('Формулаларға рұқсат жоқ. Ұяшықтарға тек мәндерді қойыңыз.');
            }
        }
        $types = $this->xml($zip, '[Content_Types].xml');
        if ($types->query('//*[@PartName="/xl/workbook.xml" and @ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"]')->length !== 1) {
            $this->invalid('Тек макроссыз XLSX файлы қабылданады.');
        }
    }

    /** @return array{string, list<string>} */
    private function workbook(ZipArchive $zip): array
    {
        $book = $this->xml($zip, 'xl/workbook.xml');
        $sheet = $book->query('/*[local-name()="workbook"]/*[local-name()="sheets"]/*[local-name()="sheet"][1]')->item(0);
        $id = $sheet?->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
        $rels = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
        $target = null;
        foreach ($rels->query('/*/*[local-name()="Relationship"]') as $rel) {
            if ($id && $rel->getAttribute('Id') === $id && str_ends_with($rel->getAttribute('Type'), '/worksheet')) {
                $path = $rel->getAttribute('Target');
                $target = str_starts_with($path, '/') ? substr($path, 1) : 'xl/'.$path;
            }
        }
        if ($target === null || ! preg_match('~^xl/worksheets/[A-Za-z0-9_-]+\.xml$~D', $target)) {
            $this->invalid('Жұмыс кітабының бірінші парағы табылмады.');
        }
        $strings = [];
        if ($zip->locateName('xl/sharedStrings.xml') !== false) {
            $xml = $this->xml($zip, 'xl/sharedStrings.xml');
            foreach ($xml->query('/*[local-name()="sst"]/*[local-name()="si"]') as $value) {
                $strings[] = $this->text($xml, $value);
            }
        }

        return [$target, $strings];
    }

    private function column(DOMElement $cell, int $row, int $previous): int
    {
        if (! $cell->hasAttribute('r')) {
            return $previous + 1;
        }
        if (! preg_match('/^([A-Z]{1,3})([1-9][0-9]*)$/D', $cell->getAttribute('r'), $position) || (int) $position[2] !== $row) {
            $this->invalid('Ұяшық мекенжайы жарамсыз.');
        }
        $column = 0;
        foreach (str_split($position[1]) as $letter) {
            $column = $column * 26 + ord($letter) - ord('A') + 1;
        }
        if ($column - 1 <= $previous) {
            $this->invalid('Бір жолда қайталанған ұяшық бар.');
        }

        return $column - 1;
    }

    private function cell(DOMXPath $xml, DOMElement $cell, array $strings): string
    {
        if ($xml->query('./*[local-name()="f"]', $cell)->length > 0) {
            $this->invalid('Формулаларға рұқсат жоқ.');
        }
        $raw = $xml->evaluate('string(./*[local-name()="v"])', $cell);
        $value = match ($cell->getAttribute('t')) {
            'inlineStr' => $this->text($xml, $cell),
            's' => ctype_digit($raw) ? ($strings[(int) $raw] ?? null) : null,
            '', 'n', 'str', 'b' => $raw,
            default => null,
        };
        if ($value === null || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', $value) || preg_match('/^\s*[-=+@]/u', $value)) {
            $this->invalid('Файлда жарамсыз ұяшық немесе формулаға ұқсас мән бар.');
        }

        return trim($value);
    }

    private function text(DOMXPath $xml, DOMElement $element): string
    {
        $text = '';
        foreach ($xml->query('.//*[local-name()="t" and not(ancestor::*[local-name()="rPh"])]', $element) as $node) {
            $text .= $node->textContent;
        }

        return $text;
    }

    private function xml(ZipArchive $zip, string $path): DOMXPath
    {
        $raw = $zip->getFromName($path);
        $xml = new DOMDocument;
        if (! is_string($raw) || $raw === '' || preg_match('/<!DOCTYPE|<!ENTITY/i', $raw)
            || ! @$xml->loadXML($raw, LIBXML_NONET) || $xml->doctype !== null) {
            $this->invalid('XLSX файлының XML құрылымы жарамсыз.');
        }

        return new DOMXPath($xml);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
