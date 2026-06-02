<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cycle_partials', function (Blueprint $table) {
            $table->foreignId('academic_period_id')
                ->nullable()
                ->after('school_cycle_id')
                ->constrained('academic_periods')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cycle_partials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_period_id');
        });
    }
};
