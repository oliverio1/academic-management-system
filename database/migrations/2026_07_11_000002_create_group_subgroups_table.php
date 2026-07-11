<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_subgroups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_division_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 40);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['group_division_id', 'name']);
            $table->unique(['group_division_id', 'code']);
            $table->index(['group_division_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_subgroups');
    }
};
