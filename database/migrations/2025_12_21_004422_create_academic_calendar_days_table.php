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
        Schema::create('academic_calendar_days', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->enum('type', ['holiday', 'vacation']);
            $table->string('name');
            $table->foreignId('modality_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('affects_teachers')->default(true);
            $table->boolean('affects_students')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_calendar_days');
    }
};
