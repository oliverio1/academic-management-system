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

        $hasLegacyUnique = $this->indexExists('schedules', 'uniq_schedule_assignment_time');
        $hasCycleUnique = $this->indexExists('schedules', 'uniq_schedule_assignment_cycle_time');
        $hasAssignmentIdx = $this->indexExists('schedules', 'idx_schedules_assignment_id');

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
        $hasCycleUnique = $this->indexExists('schedules', 'uniq_schedule_assignment_cycle_time');
        $hasLegacyUnique = $this->indexExists('schedules', 'uniq_schedule_assignment_time');

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

        $hasAssignmentIdx = $this->indexExists('schedules', 'idx_schedules_assignment_id');
        if ($hasAssignmentIdx) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropIndex('idx_schedules_assignment_id');
            });
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        if (DB::getDriverName() === 'mysql') {
            return collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name]))->isNotEmpty();
        }

        $safeTable = str_replace("'", "''", $table);

        return collect(DB::select("PRAGMA index_list('{$safeTable}')"))
            ->contains(fn ($index) => ($index->name ?? null) === $name);
    }
};
