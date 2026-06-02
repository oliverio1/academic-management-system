<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modality_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 40)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_cycles');
    }
};
