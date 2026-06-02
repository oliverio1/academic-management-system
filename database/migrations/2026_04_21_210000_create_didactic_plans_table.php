<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('didactic_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_period_id')->nullable()->constrained('academic_periods')->nullOnDelete();
            $table->string('title');
            $table->text('field_training')->nullable();
            $table->text('objective')->nullable();
            $table->text('evaluation_instruments')->nullable();
            $table->text('general_resources')->nullable();
            $table->text('bibliography')->nullable();
            $table->text('complementary_bibliography')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('didactic_plans');
    }
};

