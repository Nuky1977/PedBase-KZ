<?php

declare(strict_types=1);

if ($argc !== 3 || ! is_file($argv[1])) {
    fwrite(STDERR, "Usage: php tools/prepare-college-names.php <official Kazakh HTML> <output JSON>\n");
    exit(1);
}

$html = file_get_contents($argv[1]);
$document = new DOMDocument;
$document->loadHTML('<?xml encoding="UTF-8">'.preg_replace('~<br\s*/?>~i', "\n", $html), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
$xpath = new DOMXPath($document);
$pairs = [];
$clean = static fn (string $value): string => trim(preg_replace('/[\s\p{Zs}]+/u', ' ', $value));
foreach ($xpath->query('//table') as $table) {
    $specialty = null;
    foreach ($xpath->query('./tr|./tbody/tr', $table) as $row) {
        foreach ($xpath->query('./td', $row) as $cell) {
            $value = $clean($cell->textContent);
            if (preg_match('/^(\d{8}) (.+)$/u', $value, $match)) {
                $specialty = [$match[1], $match[2]];
            }
            if ($specialty !== null && preg_match('/^((?:3W|4S|5AB)\d{8}) (.+)$/u', $value, $match)) {
                $key = $specialty[0].','.$match[1];
                $pairs[$key]['specialty'][$specialty[1]] = true;
                $pairs[$key]['qualification'][$match[2]] = true;
            }
        }
    }
}

$verified = array_slice(file(__DIR__.'/../backend/resources/reference/college-qualifications.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), 1);
if (array_diff($verified, array_keys($pairs)) || array_diff(array_keys($pairs), $verified)) {
    fwrite(STDERR, "Source rows differ from the verified code list. Review the classifier revision before generating names.\n");
    exit(2);
}
$output = [];
foreach ($verified as $key) {
    $output[$key] = array_map(array_keys(...), $pairs[$key]);
}
if (file_put_contents($argv[2], json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n") === false) {
    exit(3);
}
