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
        Schema::create('student_follow_up_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_follow_up_teacher_id')->constrained()->cascadeOnDelete();
            $table->json('questionnaire'); // respuestas estructuradas
            $table->text('comments')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_follow_up_responses');
    }
};
