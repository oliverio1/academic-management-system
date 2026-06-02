<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycle_partials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_cycle_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 40)->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_cycle_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_partials');
    }
};
