<?php

namespace App\Console\Commands;

use App\Models\TeacherProfile;
use App\Services\ProtectedIin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProtectTeacherIin extends Command
{
    protected $signature = 'teachers:protect-iin {--database= : Explicit Laravel connection} {--expect-schema= : Expected MySQL schema} {--apply : Rewrite legacy rows while the application is in maintenance mode}';

    protected $description = 'Check or encrypt legacy teacher identifiers without printing their values';

    public function handle(ProtectedIin $protection): int
    {
        $connection = $this->option('database');
        $expected = $this->option('expect-schema');
        $apply = $this->option('apply');

        if (! $connection || ! $expected || ($apply && ! app()->isDownForMaintenance())) {
            $this->error('Specify the connection and expected schema. Apply also requires maintenance mode.');

            return self::FAILURE;
        }

        try {
            $db = DB::connection($connection);
            if ($db->getDriverName() !== 'mysql' || $db->selectOne('SELECT DATABASE() AS name')->name !== $expected) {
                $this->error('The connection does not match the expected MySQL schema.');

                return self::FAILURE;
            }
            $query = TeacherProfile::on($connection)->whereNotNull('iin');
            $checked = 0;
            $query->chunkById(200, function ($batch) use ($db, $connection, $protection, $apply, &$checked): void {
                $db->transaction(function () use ($batch, $db, $connection, $protection, $apply, &$checked): void {
                    foreach ($batch as $candidate) {
                        $query = TeacherProfile::on($connection)->whereKey($candidate->id);
                        $profile = ($apply ? $query->lockForUpdate() : $query)->firstOrFail();
                        $legacy = $profile->getRawOriginal('iin');
                        if ($legacy === null) {
                            continue;
                        }
                        if ($profile->iin_ciphertext !== null || $profile->iin_lookup !== null) {
                            throw new \RuntimeException('Mixed identifier state.');
                        }
                        $protected = $protection->encrypt($legacy);
                        $fingerprint = $protection->fingerprint($legacy);
                        if ($protection->decrypt($protected) !== $legacy || TeacherProfile::on($connection)->where('iin_lookup', $fingerprint)->exists()) {
                            throw new \RuntimeException('Identifier validation conflict.');
                        }
                        if ($apply) {
                            $db->table('teacher_profiles')->where('id', $profile->id)->update([
                                'iin' => null, 'iin_ciphertext' => $protected, 'iin_lookup' => $fingerprint,
                            ]);
                        }
                        $checked++;
                    }
                });
            });
        } catch (Throwable) {
            $this->error('Identifier protection stopped. Review configuration and row state before resuming. Earlier committed batches are retained.');

            return self::FAILURE;
        }

        $this->info(($apply ? 'Protected: ' : 'Verified: ').$checked);

        return self::SUCCESS;
    }
}
