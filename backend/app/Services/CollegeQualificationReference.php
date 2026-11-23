<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class CollegeQualificationReference
{
    public const SOURCE = 'https://old.adilet.zan.kz/kaz/docs/V1800017564';

    public const REVISION = '2026-05-13';

    private const SHA256 = '0c14498cc164ff5202e28c85fdd3a52d9a95f6c3f06bea5bef01f1f360da3e95';

    private const NAMES_SHA256 = '62977a234ce5a90eecdab4cd32a33783dda438f7a798f22c1fe976be134cd24e';

    private ?array $pairs = null;

    private ?array $names = null;

    /** @return array{specialty: list<string>, qualification: list<string>} */
    public function validate(string $specialty, string $qualification): array
    {
        $this->pairs ??= $this->loadPairs();
        $key = $specialty.','.$qualification;
        if (! isset($this->pairs[$key])) {
            throw ValidationException::withMessages([
                'qualification_code' => 'Мамандық пен біліктілік кодтарының жұбы №500 бұйрықтың сыныптауышында жоқ.',
            ]);
        }

        if ($this->names === null) {
            $path = resource_path('reference/college-qualification-names.json');
            $contents = is_readable($path) ? file_get_contents($path) : false;
            if ($contents === false || hash('sha256', $contents) !== self::NAMES_SHA256) {
                throw ValidationException::withMessages(['qualification_code' => 'Сыныптауыштағы ресми атаулар анықтамалығы қолжетімсіз.']);
            }
            $this->names = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        }

        return $this->names[$key];
    }

    private function loadPairs(): array
    {
        $path = resource_path('reference/college-qualifications.csv');
        $contents = is_readable($path) ? file_get_contents($path) : false;
        if ($contents === false || hash('sha256', $contents) !== self::SHA256) {
            throw ValidationException::withMessages([
                'qualification_code' => 'Мамандықтар мен біліктіліктердің расталған анықтамалығы қолжетімсіз.',
            ]);
        }

        return array_fill_keys(array_slice(explode("\n", rtrim($contents, "\n")), 1), true);
    }
}
