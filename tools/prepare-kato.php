<?php

declare(strict_types=1);

// Converts the Bureau's KATO XLSX distribution without booting Laravel.
function sheetRows(string $path): Generator
{
    $zip = new ZipArchive;
    if ($zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
        throw new RuntimeException('Cannot open the source XLSX.');
    }

    try {
        $read = static function (string $entry) use ($zip): SimpleXMLElement {
            $stat = $zip->statName($entry);
            if ($stat === false || $stat['size'] > 24 * 1024 * 1024) {
                throw new RuntimeException("Missing or unsupported XLSX entry: $entry");
            }
            $data = $zip->getFromName($entry);
            if ($data === false || stripos($data, '<!DOCTYPE') !== false) {
                throw new RuntimeException("Missing or unsupported XLSX entry: $entry");
            }

            return new SimpleXMLElement($data, LIBXML_NONET);
        };
        $strings = [];
        foreach ($read('xl/sharedStrings.xml')->si as $item) {
            $value = (string) $item->t;
            foreach ($item->r as $run) {
                $value .= (string) $run->t;
            }
            $strings[] = $value;
        }
        foreach ($read('xl/worksheets/sheet1.xml')->sheetData->row as $row) {
            $values = [];
            foreach ($row->c as $cell) {
                if (isset($cell->f)) {
                    throw new RuntimeException('Unexpected formula in the KATO worksheet.');
                }
                $value = (string) $cell->v;
                if ((string) $cell['t'] === 's') {
                    $value = $strings[(int) $value] ?? throw new RuntimeException('Invalid XLSX string index.');
                }
                $values[rtrim((string) $cell['r'], '0123456789')] = $value;
            }
            if (array_filter($values, static fn (string $value): bool => $value !== '') !== []) {
                yield (int) $row['r'] => $values;
            }
        }
    } finally {
        $zip->close();
    }
}

function rootCode(string $code): string
{
    return substr($code, 0, 2).'0000000';
}

function administrativeKind(string $code, array $row): ?string
{
    if ($code === rootCode($code) && $row['k'] === '0' && str_ends_with($row['name'], ' облысы')) {
        return 'oblast';
    }
    if (preg_match('/[ .]ауданы$/u', $row['name']) === 1) {
        return 'district';
    }
    if ($row['k'] === '1' && preg_match('/^г\s*\.(?!\s*а\s*\.)/iu', $row['russian'])) {
        return match (true) {
            $code === rootCode($code) => 'republican_city',
            substr($code, 4) === '10000' => 'oblast_city',
            default => 'district_city',
        };
    }

    return null;
}

function sourceParent(string $code, array $rows): ?string
{
    foreach ([6, 4, 2] as $length) {
        $candidate = str_pad(substr($code, 0, $length), 9, '0');
        if ($candidate !== $code && isset($rows[$candidate])) {
            return $candidate;
        }
    }

    return null;
}

function writeJson(string $directory, string $name, array $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (file_put_contents($directory.'/'.$name, $json.PHP_EOL) === false) {
        throw new RuntimeException("Cannot write $name.");
    }
}

try {
    if ($argc !== 5) {
        throw new RuntimeException('Usage: php tools/prepare-kato.php <official.xlsx> <stat.gov.kz XLSX URL> <YYYY-MM-DD> <output-directory>');
    }
    [, $in, $url, $date, $out] = $argv;
    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https'
        || parse_url($url, PHP_URL_HOST) !== 'stat.gov.kz' || ! str_ends_with((string) parse_url($url, PHP_URL_PATH), '.xlsx')
        || $parsedDate === false || $parsedDate->format('Y-m-d') !== $date || ! is_dir($out)) {
        throw new RuntimeException('Supply the official HTTPS XLSX URL, publication date and an existing output directory.');
    }
    $source = ['source_url' => $url, 'source_updated_on' => $date];
    $errors = $review = $rows = [];
    $header = ['A' => 'te', 'B' => 'ab', 'C' => 'cd', 'D' => 'ef', 'E' => 'hij', 'F' => 'k', 'G' => 'kaz_name', 'H' => 'rus_name'];
    $seenHeader = false;
    foreach (sheetRows($in) as $line => $cells) {
        if (! $seenHeader) {
            if ($line !== 1 || array_intersect_key($cells, $header) !== $header) {
                throw new RuntimeException('Unexpected worksheet header.');
            }
            $seenHeader = true;

            continue;
        }
        $code = $cells['A'] ?? '';
        $parts = str_pad($cells['B'] ?? '', 2, '0', STR_PAD_LEFT)
            .str_pad($cells['C'] ?? '', 2, '0', STR_PAD_LEFT)
            .str_pad($cells['D'] ?? '', 2, '0', STR_PAD_LEFT)
            .str_pad($cells['E'] ?? '', 3, '0', STR_PAD_LEFT);
        $name = trim($cells['G'] ?? '');
        if (! preg_match('/^[0-9]{9}$/D', $code) || $code !== $parts || $name === '' || isset($rows[$code])) {
            $errors[] = ['row' => $line, 'code' => $code, 'reason' => isset($rows[$code]) ? 'duplicate_code' : 'invalid_code_or_name'];

            continue;
        }
        $rows[$code] = ['name' => $name, 'russian' => trim($cells['H'] ?? ''), 'k' => $cells['F'] ?? '', 'row' => $line];
    }
    $kinds = [];
    foreach ($rows as $key => $row) {
        $code = (string) $key;
        $kind = administrativeKind($code, $row);
        if ($kind !== null) {
            $kinds[$code] = $kind;
        } elseif ($code === rootCode($code) || preg_match('/ [қќ]\.$/iu', $row['name']) === 1) {
            $errors[] = ['row' => $row['row'], 'code' => $code, 'name' => $row['name'], 'reason' => 'unclassified_region_or_city'];
        }
    }
    ksort($kinds, SORT_STRING);
    $index = $regions = $areas = $counts = [];
    foreach ($kinds as $key => $kind) {
        $code = (string) $key;
        $row = $rows[$code];
        $root = rootCode($code);
        $top = in_array($kind, ['oblast', 'republican_city'], true);
        $districtPrefix = substr($code, 0, 4).'00000';
        $parent = match (true) {
            $top => null,
            $kind === 'district_city' => $districtPrefix,
            $kind === 'district' && $code !== $districtPrefix && ($kinds[$root] ?? null) === 'oblast'
                => substr($code, 0, 4).'10000',
            default => $root,
        };
        $parentKinds = match ($kind) {
            'district_city' => ['district'],
            'district' => ['oblast', 'republican_city', 'oblast_city'],
            default => ['oblast'],
        };
        $sourceParent = sourceParent($code, $rows);
        if (! $top && ($sourceParent === null
            || ! in_array($kinds[$root] ?? null, ['oblast', 'republican_city'], true)
            || ! in_array($kinds[$parent] ?? null, $parentKinds, true)
            || rootCode($parent) !== $root)) {
            $errors[] = ['row' => $row['row'], 'code' => $code, 'name' => $row['name'], 'parent_code' => $parent, 'reason' => 'invalid_hierarchy'];

            continue;
        }
        if (str_contains($row['name'], 'ќ')) {
            $review[] = ['code' => $code, 'name' => $row['name'], 'reason' => 'source_name_contains_macedonian_character'];
        }
        if ($code === '431910000') {
            $review[] = ['code' => $code, 'name' => $row['name'], 'reason' => 'special_status_city_check_before_rollout'];
        }
        $counts[$kind] = ($counts[$kind] ?? 0) + 1;
        $level = match (true) {
            $top => 1,
            $kind === 'district_city' || ($kinds[$parent] ?? null) === 'oblast_city' => 3,
            default => 2,
        };
        $fields = ['code' => $code, 'name' => $row['name']];
        $index[] = $fields + [
            'display_name' => $code === '475220100' ? str_replace('ќ', 'қ', $row['name']) : $row['name'],
            'kind' => $kind,
            'administrative_level' => $level,
            'parent_code' => $parent,
            'source_parent_code' => $sourceParent,
            'region_code' => $root,
            'source_territory_type' => $row['k'],
            'source_row' => $row['row'],
        ] + $source;
        if ($top) {
            $regions[] = $fields;
        } else {
            $areas[] = $fields + ['region_code' => $root];
        }
    }
    $indexCodes = array_fill_keys(array_column($index, 'code'), true);
    foreach ($index as $unit) {
        if ($unit['parent_code'] !== null && ! isset($indexCodes[$unit['parent_code']])) {
            $errors[] = ['code' => $unit['code'], 'reason' => 'parent_excluded'];
        }
    }
    if ($regions === [] || $areas === []) {
        $errors[] = ['reason' => 'empty_hierarchy'];
    }
    $report = $source + [
        'source_sha256' => hash_file('sha256', $in),
        'source_rows' => count($rows),
        'selected_counts' => $counts,
        'out_of_scope_rows' => count($rows) - count($kinds),
        'import_regions' => count($regions),
        'import_areas' => count($areas),
        'errors' => $errors,
        'manual_review' => $review,
    ];
    writeJson($out, 'kato-validation.json', $report);
    if ($errors !== []) {
        throw new RuntimeException('KATO validation failed. See kato-validation.json.');
    }
    writeJson($out, 'kato-hierarchy.json', $source + ['units' => $index]);
    writeJson($out, 'kato-import.json', $source + ['regions' => $regions, 'areas' => $areas]);
    echo count($regions).' regions and '.count($areas).' areas'.PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
}
