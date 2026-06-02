<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_cycle_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_cycle_id', 'group_id']);
            $table->index(['school_cycle_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_cycle_groups');
    }
};

