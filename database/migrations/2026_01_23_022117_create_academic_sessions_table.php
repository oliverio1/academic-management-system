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
        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->date('session_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->timestamp('attendance_closed_at')->nullable();
            $table->string('attendance_closed_by')->nullable();
            $table->boolean('is_cancelled')->default(false);
            $table->timestamps();
            $table->unique(
                ['schedule_id', 'session_date'],
                'unique_schedule_academic_session'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_sessions');
    }
};
