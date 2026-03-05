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
        Schema::create('student_follow_up_teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_follow_up_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'answered'])->default('pending');
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['student_follow_up_id', 'teacher_id'],
                'sfut_followup_teacher_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_follow_up_teachers');
    }
};
