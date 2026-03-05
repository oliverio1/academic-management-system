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
        Schema::create('academic_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('teaching_assignment_id')->constrained();
            $table->foreignId('academic_period_id')->constrained();
            $table->enum('type', ['override','repeat_previous','defer_next']);
            $table->decimal('value', 5, 2)->nullable();
            $table->text('reason');
            $table->foreignId('resolved_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_resolutions');
    }
};
