<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('didactic_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('didactic_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(1);
            $table->foreignId('temario_point_id')->nullable()->constrained('temario_points')->nullOnDelete();
            $table->json('temario_subtopic_ids')->nullable();
            $table->text('opening')->nullable();
            $table->text('development')->nullable();
            $table->text('closing')->nullable();
            $table->text('resources')->nullable();
            $table->text('evaluation')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('didactic_plan_items');
    }
};

