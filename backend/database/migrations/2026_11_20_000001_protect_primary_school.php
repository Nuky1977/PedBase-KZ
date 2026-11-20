<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('school_user')->where('is_primary', true)->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Resolve duplicate primary school assignments before applying this migration.');
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX school_user_one_primary ON school_user (user_id) WHERE is_primary = 1');

            return;
        }

        DB::statement('ALTER TABLE school_user
            ADD COLUMN primary_user_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN is_primary = 1 THEN user_id ELSE NULL END) VIRTUAL,
            ADD UNIQUE INDEX school_user_one_primary (primary_user_id)');
    }

    public function down(): void
    {
        Schema::table('school_user', function (Blueprint $table) {
            $table->dropUnique('school_user_one_primary');
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('school_user', function (Blueprint $table) {
                $table->dropColumn('primary_user_id');
            });
        }
    }
};
