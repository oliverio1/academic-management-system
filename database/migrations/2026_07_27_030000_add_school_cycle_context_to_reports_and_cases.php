<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coordination_reports', function (Blueprint $table) {
            $table->foreignId('school_cycle_id')
                ->nullable()
                ->after('campus_id')
                ->constrained('school_cycles')
                ->nullOnDelete();
            $table->index(['school_cycle_id', 'status', 'created_at'], 'coord_reports_cycle_status_created_idx');
        });

        Schema::table('school_cases', function (Blueprint $table) {
            $table->foreignId('school_cycle_id')
                ->nullable()
                ->after('campus_id')
                ->constrained('school_cycles')
                ->nullOnDelete();
            $table->index(['school_cycle_id', 'status', 'priority'], 'school_cases_cycle_status_priority_idx');
        });
    }

    public function down(): void
    {
        Schema::table('school_cases', function (Blueprint $table) {
            $table->dropIndex('school_cases_cycle_status_priority_idx');
            $table->dropConstrainedForeignId('school_cycle_id');
        });

        Schema::table('coordination_reports', function (Blueprint $table) {
            $table->dropIndex('coord_reports_cycle_status_created_idx');
            $table->dropConstrainedForeignId('school_cycle_id');
        });
    }
};
