<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('schedules', 'school_cycle_id')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->foreignId('school_cycle_id')
                    ->nullable()
                    ->after('teaching_assignment_id')
                    ->constrained('school_cycles')
                    ->nullOnDelete();
            });
        }

        $legacyCycleId = DB::table('school_cycles')
            ->where('code', '25-3')
            ->orWhere('name', '2025-3')
            ->value('id');

        if (! $legacyCycleId) {
            $legacyCycleId = DB::table('school_cycles')
                ->orderBy('start_date')
                ->value('id');
        }

        if ($legacyCycleId) {
            DB::table('schedules')
                ->whereNull('school_cycle_id')
                ->update(['school_cycle_id' => $legacyCycleId]);
        }

        $hasLegacyUnique = collect(DB::select("SHOW INDEX FROM schedules WHERE Key_name = 'uniq_schedule_assignment_time'"))->isNotEmpty();
        $hasCycleUnique = collect(DB::select("SHOW INDEX FROM schedules WHERE Key_name = 'uniq_schedule_assignment_cycle_time'"))->isNotEmpty();
        $hasAssignmentIdx = collect(DB::select("SHOW INDEX FROM schedules WHERE Key_name = 'idx_schedules_assignment_id'"))->isNotEmpty();

        if ($hasLegacyUnique && ! $hasAssignmentIdx) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->index('teaching_assignment_id', 'idx_schedules_assignment_id');
            });
        }

        if ($hasLegacyUnique) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropUnique('uniq_schedule_assignment_time');
            });
        }

        if (! $hasCycleUnique) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->unique(
                    ['teaching_assignment_id', 'school_cycle_id', 'day_of_week', 'start_time', 'end_time'],
                    'uniq_schedule_assignment_cycle_time'
                );
            });
        }
    }

    public function down(): void
    {
        $hasCycleUnique = collect(DB::select("SHOW INDEX FROM schedules WHERE Key_name = 'uniq_schedule_assignment_cycle_time'"))->isNotEmpty();
        $hasLegacyUnique = collect(DB::select("SHOW INDEX FROM schedules WHERE Key_name = 'uniq_schedule_assignment_time'"))->isNotEmpty();

        if ($hasCycleUnique) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropUnique('uniq_schedule_assignment_cycle_time');
            });
        }

        if (! $hasLegacyUnique) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->unique(
                    ['teaching_assignment_id', 'day_of_week', 'start_time', 'end_time'],
                    'uniq_schedule_assignment_time'
                );
            });
        }

        if (Schema::hasColumn('schedules', 'school_cycle_id')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropConstrainedForeignId('school_cycle_id');
            });
        }

        $hasAssignmentIdx = collect(DB::select("SHOW INDEX FROM schedules WHERE Key_name = 'idx_schedules_assignment_id'"))->isNotEmpty();
        if ($hasAssignmentIdx) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropIndex('idx_schedules_assignment_id');
            });
        }
    }
};
