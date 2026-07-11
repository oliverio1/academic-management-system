<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_offering_subgroups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_offering_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('group_subgroup_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('weekly_periods')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['class_offering_id', 'group_subgroup_id'],
                'class_offering_subgroup_unique'
            );
            $table->index(['group_subgroup_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_offering_subgroups');
    }
};
