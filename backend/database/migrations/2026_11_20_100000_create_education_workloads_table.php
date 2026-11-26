<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('education_workloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employment_id')->constrained('education_employments')->restrictOnDelete();
            $table->unsignedSmallInteger('academic_year');
            $table->decimal('amount', 8, 2);
            $table->string('unit', 24);
            $table->string('period', 24);
            $table->string('position_title', 150);
            $table->boolean('was_primary');
            $table->json('details');
            $table->char('identity', 64)->unique();
            $table->timestamps();
            $table->index(['employment_id', 'academic_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('education_workloads');
    }
};
