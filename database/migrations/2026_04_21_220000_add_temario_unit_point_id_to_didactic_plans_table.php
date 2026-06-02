<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('didactic_plans', function (Blueprint $table) {
            $table->foreignId('temario_unit_point_id')
                ->nullable()
                ->after('academic_period_id')
                ->constrained('temario_points')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('didactic_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('temario_unit_point_id');
        });
    }
};

