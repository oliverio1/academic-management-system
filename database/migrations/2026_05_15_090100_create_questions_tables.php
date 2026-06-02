<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_bank_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['open', 'multiple_choice', 'matching', 'fill_blank']);
            $table->longText('prompt');
            $table->decimal('points', 6, 2)->default(1);
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('option_text', 500);
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('question_matching_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('left_text', 500);
            $table->string('right_text', 500);
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('question_fill_blanks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('expected_answer', 255);
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_fill_blanks');
        Schema::dropIfExists('question_matching_pairs');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
    }
};

