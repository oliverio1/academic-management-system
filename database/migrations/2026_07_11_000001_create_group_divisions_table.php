<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_divisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('division_type', 30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['group_id', 'name']);
            $table->index(['group_id', 'division_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_divisions');
    }
};
