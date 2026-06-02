<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paper_exams', function (Blueprint $table) {
            $table->boolean('is_online_enabled')->default(false)->after('duration_minutes');
            $table->timestamp('online_available_from')->nullable()->after('is_online_enabled');
            $table->timestamp('online_available_until')->nullable()->after('online_available_from');
            $table->unsignedInteger('online_max_attempts')->default(1)->after('online_available_until');
            $table->boolean('online_show_result')->default(false)->after('online_max_attempts');
        });

        Schema::create('paper_exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('status', 20)->default('in_progress');
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('max_score', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['paper_exam_id', 'student_id', 'attempt_number'], 'uniq_exam_student_attempt');
            $table->index(['paper_exam_id', 'student_id']);
        });

        Schema::create('paper_exam_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->json('answer_payload')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('max_score', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['paper_exam_attempt_id', 'question_id'], 'uniq_attempt_question');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paper_exam_attempt_answers');
        Schema::dropIfExists('paper_exam_attempts');

        Schema::table('paper_exams', function (Blueprint $table) {
            $table->dropColumn([
                'is_online_enabled',
                'online_available_from',
                'online_available_until',
                'online_max_attempts',
                'online_show_result',
            ]);
        });
    }
};

