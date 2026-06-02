<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prefect_daily_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('status', 20);
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'attendance_date'], 'uniq_prefect_attendance_student_date');
            $table->index(['group_id', 'attendance_date'], 'idx_prefect_attendance_group_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prefect_daily_attendances');
    }
};

