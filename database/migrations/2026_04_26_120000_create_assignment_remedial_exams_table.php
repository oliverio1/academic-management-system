<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_remedial_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained('academic_periods')->cascadeOnDelete();
            $table->decimal('ordinario_a_score', 5, 2)->nullable();
            $table->decimal('ordinario_b_score', 5, 2)->nullable();
            $table->decimal('extraordinario_score', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(
                ['teaching_assignment_id', 'student_id', 'academic_period_id'],
                'uniq_assignment_student_period_remedial'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_remedial_exams');
    }
};

