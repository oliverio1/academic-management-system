<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_campus_attendances', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 64)->nullable()->index();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->date('attendance_date');
            $table->time('first_class_start_time')->nullable();
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->enum('status', ['pending', 'on_time', 'late', 'absent'])->default('pending');
            $table->unsignedInteger('minutes_late')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'teacher_id', 'campus_id', 'attendance_date'],
                'uniq_teacher_campus_date'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_campus_attendances');
    }
};

