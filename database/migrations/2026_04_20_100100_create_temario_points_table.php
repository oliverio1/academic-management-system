<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temario_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temario_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(1);
            $table->string('label', 20)->nullable();
            $table->enum('type', ['conceptual', 'procedimental', 'actitudinal', 'otro'])->default('otro');
            $table->text('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temario_points');
    }
};

