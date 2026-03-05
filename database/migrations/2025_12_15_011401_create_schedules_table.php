<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_assignment_id')->constrained()->cascadeOnDelete();
            $table->string('day_of_week', 10);
            $table->time('start_time');
            $table->time('end_time');
            $table->string('type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(
                ['teaching_assignment_id', 'day_of_week', 'start_time', 'end_time'],
                'uniq_schedule_assignment_time'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
