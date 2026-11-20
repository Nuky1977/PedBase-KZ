<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_diplomas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('education_level', 100);
            $table->string('institution', 200);
            $table->string('specialty', 150);
            $table->string('diploma_number', 100)->nullable();
            $table->unsignedSmallInteger('graduation_year');
            $table->timestamps();
        });

        Schema::create('teacher_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('provider', 200);
            $table->unsignedSmallInteger('hours');
            $table->date('started_on');
            $table->date('ended_on');
            $table->string('certificate_number', 100)->nullable();
            $table->date('certificate_issued_on')->nullable();
            $table->string('certificate_path')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'ended_on']);
        });

        Schema::create('teacher_attestations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 120);
            $table->date('awarded_on');
            $table->date('valid_until')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'awarded_on']);
            $table->index('valid_until');
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject_type', 60);
            $table->unsignedBigInteger('subject_id');
            $table->timestamp('created_at');
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('teacher_attestations');
        Schema::dropIfExists('teacher_courses');
        Schema::dropIfExists('teacher_diplomas');
    }
};
