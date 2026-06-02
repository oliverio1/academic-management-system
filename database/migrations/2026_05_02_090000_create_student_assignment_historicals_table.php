<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_assignment_historicals', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teaching_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_cycle_id')->constrained()->cascadeOnDelete();
            $table->decimal('partial_1_final', 5, 2)->nullable();
            $table->decimal('partial_2_final', 5, 2)->nullable();
            $table->decimal('final_grade', 5, 2)->nullable();
            $table->decimal('partial_1_attendance', 5, 2)->nullable();
            $table->decimal('partial_2_attendance', 5, 2)->nullable();
            $table->decimal('attendance_percentage', 5, 2)->nullable();
            $table->string('source_file')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'student_id', 'teaching_assignment_id', 'school_cycle_id'],
                'hist_unique_student_assignment_cycle'
            );
            $table->index(['tenant_id', 'school_cycle_id'], 'hist_tenant_cycle_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_assignment_historicals');
    }
};
