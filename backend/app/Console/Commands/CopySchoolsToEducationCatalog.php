<?php

namespace App\Console\Commands;

use App\UserRole;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

#[Signature('education:copy-schools {--database= : Laravel connection name} {--expect-schema= : Required database name for writes} {--area=* : Legacy district ID and new area code as ID:CODE} {--apply : Persist the verified copy}')]
#[Description('Check or copy legacy schools and teacher placements into the education catalog')]
class CopySchoolsToEducationCatalog extends Command
{
    public function handle(): int
    {
        $name = $this->option('database');
        $apply = $this->option('apply');
        $expected = $this->option('expect-schema');
        if ($apply && (! $name || ! $expected)) {
            $this->error('Writes require an explicit --database and --expect-schema.');

            return self::FAILURE;
        }

        try {
            $db = DB::connection($name);
            $actual = $db->getDriverName() === 'mysql'
                ? $db->selectOne('SELECT DATABASE() AS name')->name
                : $db->getDatabaseName();
            if ($expected !== null && $expected !== $actual) {
                throw new RuntimeException('The connected schema does not match --expect-schema.');
            }

            $counts = $db->transaction(fn () => $this->copy($db, $apply), 3);
        } catch (QueryException) {
            $this->error('The catalog copy could not be completed. Check the schema and unique constraints.');

            return self::FAILURE;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('%s: schools=%d, placements=%d, primary=%d', $apply ? 'Applied' : 'Dry run', ...$counts));

        return self::SUCCESS;
    }

    private function copy(Connection $db, bool $apply): array
    {
        $type = $db->table('education_organization_types')->where('code', 'school')->value('id');
        if ($type === null) {
            throw new RuntimeException('Configure the school organization type before copying.');
        }
        $areas = [];
        foreach ($this->option('area') as $mapping) {
            if (! preg_match('/^([1-9][0-9]*):([a-zA-Z0-9_-]{1,32})$/D', $mapping, $parts) || isset($areas[$parts[1]])) {
                throw new RuntimeException('Provide each district mapping once as --area=ID:CODE.');
            }
            $area = $db->table('education_areas')->where('code', $parts[2])->value('id');
            if ($area === null) {
                throw new RuntimeException("Unknown target area for district ID {$parts[1]}.");
            }
            $areas[$parts[1]] = $area;
        }

        $counts = [0, 0, 0];
        foreach ($db->table('schools')->lazyById() as $school) {
            $area = $areas[$school->district_id] ?? null;
            if ($area === null || ! preg_match('/^[0-9]{12}$/D', $school->bin ?? '') || trim($school->locality ?? '') === '') {
                throw new RuntimeException("School ID {$school->id} needs a valid BIN, locality and mapped district.");
            }
            $organization = $db->table('education_organizations')->where('legacy_school_id', $school->id)->first();
            if ($organization !== null && ($organization->bin !== $school->bin
                || (int) $organization->type_id !== (int) $type || (int) $organization->area_id !== (int) $area)) {
                throw new RuntimeException("Catalog identity differs for linked school ID {$school->id}.");
            }
            $id = $organization?->id;
            if ($id === null) {
                $sameBin = $db->table('education_organizations')->where('bin', $school->bin)->first();
                if ($sameBin !== null) {
                    $nameMatches = Str::lower(Str::squish($sameBin->name)) === Str::lower(Str::squish($school->name));
                    $detail = $nameMatches ? 'name matches' : 'name differs';
                    throw new RuntimeException("Review existing BIN for school ID {$school->id} ($detail).");
                }
                $counts[0]++;
                if ($apply) {
                    $id = $db->table('education_organizations')->insertGetId([
                        'legacy_school_id' => $school->id, 'type_id' => $type, 'area_id' => $area,
                        'bin' => $school->bin, 'name' => $school->name, 'short_name' => $school->short_name,
                        'locality' => $school->locality, 'is_active' => $school->is_active,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
            $this->copyPlacements($db, $school->id, $id, $apply, $counts);
        }

        return $counts;
    }

    private function copyPlacements(Connection $db, int $school, ?int $organization, bool $apply, array &$counts): void
    {
        $placements = $db->table('school_user as p')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->leftJoin('teacher_employments as e', 'e.school_user_id', '=', 'p.id')
            ->where('p.school_id', $school)->where('u.role', UserRole::TEACHER->value)
            ->select('p.id', 'p.user_id', 'p.is_primary', 'e.position_title', 'e.status')
            ->lazyById(1000, 'p.id', 'id');
        foreach ($placements as $placement) {
            $employment = $organization === null ? null : $db->table('education_employments')
                ->where('user_id', $placement->user_id)->where('organization_id', $organization)->first();
            if ($employment === null) {
                $counts[1]++;
                if ($apply) {
                    $db->table('education_employments')->insert([
                        'user_id' => $placement->user_id, 'organization_id' => $organization,
                        'position_title' => $placement->position_title, 'status' => $placement->status ?? 'active',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }

            $primary = $db->table('education_primary_workplaces')->where('user_id', $placement->user_id)->value('organization_id');
            if (($placement->is_primary && $primary !== null && (int) $primary !== $organization)
                || (! $placement->is_primary && $primary !== null && (int) $primary === $organization)) {
                throw new RuntimeException("Primary workplace conflict at placement ID {$placement->id}.");
            }
            if ($placement->is_primary && $primary === null) {
                $counts[2]++;
                if ($apply) {
                    $db->table('education_primary_workplaces')->insert(['user_id' => $placement->user_id, 'organization_id' => $organization]);
                }
            }
        }
    }
}
