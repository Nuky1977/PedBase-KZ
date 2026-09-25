<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_profiles', function (Blueprint $table) {
            $table->id();

            // Қай пайдаланушының педагог профилі екенін көрсетеді
            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            // Жеке мәліметтер
            $table->string('iin', 12)
                ->nullable()
                ->unique();

            $table->date('birth_date')
                ->nullable();

            $table->string('gender', 20)
                ->nullable();

            $table->string('phone', 30)
                ->nullable();

            // Білімі
            $table->string('education_level')
                ->nullable();

            $table->string('educational_institution')
                ->nullable();

            $table->unsignedSmallInteger('graduation_year')
                ->nullable();

            $table->string('diploma_number')
                ->nullable();

            $table->string('specialty')
                ->nullable();

            // Еңбек өтілі аймен сақталады
            $table->unsignedSmallInteger('total_experience_months')
                ->nullable();

            $table->unsignedSmallInteger('teaching_experience_months')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_profiles');
    }
};