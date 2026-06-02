<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paper_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('teaching_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cycle_partial_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 180);
            $table->text('instructions')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('paper_exam_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(1);
            $table->decimal('points_override', 6, 2)->nullable();
            $table->timestamps();

            $table->unique(['paper_exam_id', 'question_id'], 'uniq_paper_exam_question');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paper_exam_questions');
        Schema::dropIfExists('paper_exams');
    }
};

