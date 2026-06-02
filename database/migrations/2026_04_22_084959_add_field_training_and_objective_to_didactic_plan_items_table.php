<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('didactic_plan_items', function (Blueprint $table) {
            $table->foreignId('field_training_point_id')
                ->nullable()
                ->after('position')
                ->constrained('temario_points')
                ->nullOnDelete();

            $table->text('objective')->nullable()->after('field_training_point_id');
        });
    }

    public function down(): void
    {
        Schema::table('didactic_plan_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('field_training_point_id');
            $table->dropColumn('objective');
        });
    }
};
