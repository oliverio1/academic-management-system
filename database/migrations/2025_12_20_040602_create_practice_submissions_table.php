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
        Schema::create('practice_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->longText('theoretical_framework')->nullable();
            $table->longText('objectives')->nullable();
            $table->longText('hypothesis')->nullable();
            $table->longText('development')->nullable();
            $table->longText('results')->nullable();
            $table->longText('conclusions')->nullable();
            $table->longText('references')->nullable();
            $table->json('questionnaire_answers')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->enum('status', ['draft', 'submitted', 'reviewed'])->default('draft');
            $table->timestamps();
            $table->unique(['practice_id', 'team_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('practice_submissions');
    }
};
