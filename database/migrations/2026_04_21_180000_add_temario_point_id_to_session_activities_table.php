<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_activities', function (Blueprint $table) {
            $table->foreignId('temario_point_id')
                ->nullable()
                ->after('evaluation_criterion_id')
                ->constrained('temario_points')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('session_activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('temario_point_id');
        });
    }
};

