<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('teaching_assignment_student')) {
            return;
        }

        Schema::create('teaching_assignment_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_assignment_id')->constrained('teaching_assignments')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['teaching_assignment_id', 'student_id'], 'uniq_assignment_student');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('teaching_assignment_student')) {
            Schema::drop('teaching_assignment_student');
        }
    }
};
