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
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluation_criterion_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->decimal('max_score', 5, 2)->default(10);
            $table->date('due_date')->nullable();
            $table->text('description')->nullable();
            $table->enum('evaluation_mode', ['individual', 'team'])->default('individual');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
