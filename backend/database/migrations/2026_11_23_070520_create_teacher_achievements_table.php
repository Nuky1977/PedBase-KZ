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
        Schema::create('teacher_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('title', 200);
            $table->string('issuer', 200);
            $table->date('awarded_on');
            $table->string('level', 100);
            $table->text('description')->nullable();
            $table->string('document_path')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'awarded_on', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_achievements');
    }
};
