<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['education_regions', 'education_areas'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('source_url', 2048)->nullable();
                $blueprint->date('source_updated_on')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['education_regions', 'education_areas'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['source_url', 'source_updated_on']);
            });
        }
    }
};
