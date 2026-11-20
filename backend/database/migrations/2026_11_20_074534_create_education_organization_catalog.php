<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('education_organization_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
        });
        Schema::create('education_regions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 150);
        });
        Schema::create('education_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('education_regions')->restrictOnDelete();
            $table->string('code', 32)->unique();
            $table->string('name', 150);
        });
        Schema::create('education_organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_id')->constrained('education_organization_types')->restrictOnDelete();
            $table->foreignId('area_id')->constrained('education_areas')->restrictOnDelete();
            $table->foreignId('legacy_school_id')->nullable()->unique()->constrained('schools')->restrictOnDelete();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->char('bin', 12)->unique();
            $table->string('locality');
            $table->boolean('is_active')->default(true);
            $table->index(['type_id', 'area_id']);
            $table->timestamps();
        });
        Schema::create('education_organization_admins', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('education_organizations')->cascadeOnDelete();
            $table->primary(['user_id', 'organization_id'], 'education_org_admin_pk');
        });
        Schema::create('education_methodist_grants', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->constrained('education_areas')->cascadeOnDelete();
            $table->foreignId('type_id')->constrained('education_organization_types')->cascadeOnDelete();
            $table->primary(['user_id', 'area_id', 'type_id'], 'education_methodist_grant_pk');
        });
        Schema::create('education_employments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->constrained('education_organizations')->restrictOnDelete();
            $table->string('position_title', 150)->nullable();
            $table->string('status', 20);
            $table->unique(['user_id', 'organization_id'], 'education_employment_user_org_unique');
            $table->timestamps();
        });
        Schema::create('education_primary_workplaces', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->foreign(['user_id', 'organization_id'], 'education_primary_employment_fk')
                ->references(['user_id', 'organization_id'])->on('education_employments')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['education_primary_workplaces', 'education_employments', 'education_methodist_grants',
            'education_organization_admins', 'education_organizations', 'education_areas',
            'education_regions', 'education_organization_types'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
