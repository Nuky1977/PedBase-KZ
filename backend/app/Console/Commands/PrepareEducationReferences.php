<?php

namespace App\Console\Commands;

use App\Services\EducationOrganizationRegistration;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;

#[Signature('education:prepare-references {--file= : JSON source for regions and areas} {--database= : Laravel connection name} {--expect-schema= : Required schema name for writes} {--apply : Save the validated references}')]
#[Description('Validate and optionally install education organization types and sourced territory codes')]
class PrepareEducationReferences extends Command
{
    public function handle(): int
    {
        $apply = $this->option('apply');
        $name = $this->option('database');
        $expected = $this->option('expect-schema');
        if ($apply && (! $name || ! $expected)) {
            $this->error('Writing requires --database and --expect-schema.');

            return self::FAILURE;
        }

        try {
            $territories = $this->territories($this->option('file'));
            $db = DB::connection($name);
            $actual = $db->getDriverName() === 'mysql' ? $db->selectOne('SELECT DATABASE() AS name')->name : $db->getDatabaseName();
            if ($expected !== null && $expected !== $actual) {
                throw new RuntimeException('The connected schema does not match --expect-schema.');
            }
            if ($apply) {
                $db->transaction(fn () => $this->saveReferences($db, $territories), 3);
            } elseif ($territories !== []) {
                $this->checkAreaParents($db, $territories['areas']);
            }
        } catch (ValidationException $exception) {
            $this->error(implode(PHP_EOL, array_merge(...array_values($exception->errors()))));

            return self::FAILURE;
        } catch (QueryException) {
            $this->error('The reference schema or data could not be verified.');

            return self::FAILURE;
        } catch (RuntimeException|JsonException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('%s: types=%d, regions=%d, areas=%d', $apply ? 'Applied' : 'Dry run',
            count(EducationOrganizationRegistration::TYPES), count($territories['regions'] ?? []), count($territories['areas'] ?? [])));

        return self::SUCCESS;
    }

    private function territories(?string $file): array
    {
        if ($file === null) {
            return [];
        }
        if (! is_file($file) || ! is_readable($file) || filesize($file) > 2 * 1024 * 1024) {
            throw new RuntimeException('Supply a readable local JSON file of at most 2 MB.');
        }
        $data = json_decode(file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new RuntimeException('The territory source must be a JSON object.');
        }

        return Validator::make($data, [
            'source_url' => ['required', 'url:http,https', 'max:2048'],
            'source_updated_on' => ['required', 'date_format:Y-m-d'],
            'regions' => ['required', 'array', 'list'],
            'regions.*' => ['required', 'array:code,name'],
            'regions.*.code' => ['required', 'string', 'alpha_dash:ascii', 'max:32', 'distinct:ignore_case'],
            'regions.*.name' => ['required', 'string', 'max:150'],
            'areas' => ['present', 'array', 'list'],
            'areas.*' => ['required', 'array:code,name,region_code'],
            'areas.*.code' => ['required', 'string', 'alpha_dash:ascii', 'max:32', 'distinct:ignore_case'],
            'areas.*.name' => ['required', 'string', 'max:150'],
            'areas.*.region_code' => ['required', 'string', Rule::in(array_filter((array) data_get($data, 'regions.*.code', []), 'is_string'))],
        ])->validate();
    }

    private function saveReferences(Connection $db, array $territories): void
    {
        foreach (EducationOrganizationRegistration::TYPES as $code => $name) {
            $db->table('education_organization_types')->insertOrIgnore(['code' => $code, 'name' => $name]);
        }
        if ($territories === []) {
            return;
        }
        $source = array_intersect_key($territories, array_flip(['source_url', 'source_updated_on']));
        $regions = [];
        foreach ($territories['regions'] as $region) {
            $db->table('education_regions')->upsert([$region + $source], ['code'], ['name', ...array_keys($source)]);
            $regions[$region['code']] = $db->table('education_regions')->where('code', $region['code'])->value('id');
        }
        foreach ($territories['areas'] as $area) {
            $region = $regions[$area['region_code']];
            unset($area['region_code']);
            $db->table('education_areas')->upsert([$area + $source + ['region_id' => $region]], ['code'], ['name', ...array_keys($source)]);
            if ((int) $db->table('education_areas')->where('code', $area['code'])->value('region_id') !== (int) $region) {
                throw new RuntimeException('An existing area code belongs to another region. Review the source manually.');
            }
        }
    }

    private function checkAreaParents(Connection $db, array $areas): void
    {
        foreach ($areas as $area) {
            $parent = $db->table('education_areas as areas')
                ->join('education_regions as regions', 'regions.id', '=', 'areas.region_id')
                ->where('areas.code', $area['code'])->value('regions.code');
            if ($parent !== null && strcasecmp($parent, $area['region_code']) !== 0) {
                throw new RuntimeException('An existing area code belongs to another region. Review the source manually.');
            }
        }
    }
}
