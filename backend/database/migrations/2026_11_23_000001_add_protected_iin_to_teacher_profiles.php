<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->text('iin_ciphertext')->nullable();
            $table->char('iin_lookup', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        if (DB::table('teacher_profiles')->whereNotNull('iin_ciphertext')->orWhereNotNull('iin_lookup')->exists()) {
            throw new RuntimeException('Protected IIN data exists. Use the approved restore procedure.');
        }

        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->dropUnique(['iin_lookup']);
            $table->dropColumn(['iin_ciphertext', 'iin_lookup']);
        });
    }
};
