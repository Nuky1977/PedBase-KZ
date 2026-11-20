<?php

namespace App\Services;

use RuntimeException;
use XMLWriter;
use ZipArchive;

class SpreadsheetExport
{
    public function xlsx(iterable $rows, ?iterable $instructions = null): string
    {
        $sheet = tempnam(sys_get_temp_dir(), 'pedbase-sheet-');
        $guide = $instructions === null ? null : tempnam(sys_get_temp_dir(), 'pedbase-guide-');
        $path = tempnam(sys_get_temp_dir(), 'pedbase-export-');

        try {
            if ($sheet === false || $path === false || $guide === false) {
                throw new RuntimeException('Cannot allocate export files.');
            }

            $this->writeSheet($sheet, $rows);
            if ($guide !== null) {
                $this->writeSheet($guide, $instructions);
            }

            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Cannot open export archive.');
            }
            $types = $guide === null ? '' : '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $extraSheet = $guide === null ? '' : '<sheet name="Нұсқаулық" sheetId="2" r:id="rId2"/>';
            $dataSheet = $guide === null ? 'PedBase' : 'Деректер';
            $extraRelationship = $guide === null ? '' : '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>';
            $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'.$types.'</Types>');
            $zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.$dataSheet.'" sheetId="1" r:id="rId1"/>'.$extraSheet.'</sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'.$extraRelationship.'</Relationships>');
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

    private function writeSheet(string $path, iterable $rows): void
    {
        $xml = new XMLWriter;
        $xml->openUri($path);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElementNs(null, 'worksheet', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $xml->startElement('sheetData');
        foreach ($rows as $row) {
            $xml->startElement('row');
            foreach ($row as $cell) {
                $xml->startElement('c');
                if (is_int($cell) || is_float($cell)) {
                    $xml->writeElement('v', (string) $cell);
                } else {
                    $xml->writeAttribute('t', 'inlineStr');
                    $xml->startElement('is');
                    $xml->writeElement('t', preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string) $cell));
                    $xml->endElement();
                }
                $xml->endElement();
            }
            $xml->endElement();
        }
        $xml->endDocument();
        $xml->flush();
    }
}
