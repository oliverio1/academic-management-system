<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_cycle_group_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_cycle_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['school_cycle_group_id', 'subject_id'], 'cycle_group_subject_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_cycle_group_subject');
    }
};

