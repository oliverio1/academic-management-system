<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('teaching_assignments', 'section_number')) {
            Schema::table('teaching_assignments', function (Blueprint $table) {
                $table->unsignedTinyInteger('section_number')->default(1)->after('subject_id');
            });
        }

        DB::table('teaching_assignments')
            ->whereNull('section_number')
            ->update(['section_number' => 1]);

        $hasLegacyUnique = $this->indexExists('teaching_assignments', 'uniq_teaching_assignments_cycle_group_subject');
        $hasSectionUnique = $this->indexExists('teaching_assignments', 'uniq_teaching_assignments_cycle_group_subject_section');
        $hasCycleGroupIdx = $this->indexExists('teaching_assignments', 'idx_teaching_assignments_cycle_group_id');

        if (! $hasCycleGroupIdx) {
            Schema::table('teaching_assignments', function (Blueprint $table) {
                $table->index('school_cycle_group_id', 'idx_teaching_assignments_cycle_group_id');
            });
        }

        if ($hasLegacyUnique) {
            Schema::table('teaching_assignments', function (Blueprint $table) {
                $table->dropUnique('uniq_teaching_assignments_cycle_group_subject');
            });
        }

        if (! $hasSectionUnique) {
            Schema::table('teaching_assignments', function (Blueprint $table) {
                $table->unique(
                    ['school_cycle_group_id', 'subject_id', 'section_number'],
                    'uniq_teaching_assignments_cycle_group_subject_section'
                );
            });
        }

        if (! Schema::hasColumn('schedules', 'section_number')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->unsignedTinyInteger('section_number')->default(1)->after('school_cycle_id');
            });
        }

        DB::table('schedules')
            ->whereNull('section_number')
            ->update(['section_number' => 1]);
    }

    public function down(): void
    {
        $hasSectionUnique = $this->indexExists('teaching_assignments', 'uniq_teaching_assignments_cycle_group_subject_section');
        $hasLegacyUnique = $this->indexExists('teaching_assignments', 'uniq_teaching_assignments_cycle_group_subject');

        if ($hasSectionUnique) {
            Schema::table('teaching_assignments', function (Blueprint $table) {
                $table->dropUnique('uniq_teaching_assignments_cycle_group_subject_section');
            });
        }

        if (! $hasLegacyUnique) {
            Schema::table('teaching_assignments', function (Blueprint $table) {
                $table->unique(
                    ['school_cycle_group_id', 'subject_id'],
                    'uniq_teaching_assignments_cycle_group_subject'
                );
            });
        }

        if (Schema::hasColumn('schedules', 'section_number')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropColumn('section_number');
            });
        }

        if (Schema::hasColumn('teaching_assignments', 'section_number')) {
            Schema::table('teaching_assignments', function (Blueprint $table) {
                $table->dropColumn('section_number');
            });
        }

        $hasCycleGroupIdx = $this->indexExists('teaching_assignments', 'idx_teaching_assignments_cycle_group_id');
        if ($hasCycleGroupIdx) {
            Schema::table('teaching_assignments', function (Blueprint $table) {
                $table->dropIndex('idx_teaching_assignments_cycle_group_id');
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
