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
        Schema::create('practices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained();
            $table->unsignedInteger('number'); // # de práctica
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->json('questionnaire')->nullable(); // preguntas dinámicas
            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->unique(['teaching_assignment_id', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('practices');
    }
};
