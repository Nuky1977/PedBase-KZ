<?php

namespace App\Services;

use App\Http\Middleware\SetInterfaceLocale;

class SpreadsheetLabels
{
    public static function translate(array $labels, ?string $locale = null): array
    {
        if (($locale ?? app()->getLocale()) === SetInterfaceLocale::DEFAULT) {
            return $labels;
        }

        return array_map(fn (string $label): string => __($label, locale: $locale), $labels);
    }

    public static function matchHeader(array $columns, array $header, ?string $optional = null): ?array
    {
        foreach (SetInterfaceLocale::SUPPORTED as $locale) {
            $labels = self::translate($columns, $locale);
            if ($optional !== null && ! in_array($labels[$optional], $header, true)) {
                unset($labels[$optional]);
            }

            if (count($labels) === count($header) && count(array_unique($header)) === count($labels)
                && array_diff($header, $labels) === []) {
                return $labels;
            }
        }

        return null;
    }
}
