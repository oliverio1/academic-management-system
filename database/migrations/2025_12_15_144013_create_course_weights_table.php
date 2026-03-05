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
        Schema::create('course_weights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_assignment_id')->constrained()->cascadeOnDelete();
            $table->string('activity_type', 50); // exam, homework, project
            $table->decimal('weight', 5, 2); // porcentaje (ej. 40.00)
            $table->timestamps();
            $table->unique(
                ['teaching_assignment_id', 'activity_type'],
                'uniq_assignment_type'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_weights');
    }
};
