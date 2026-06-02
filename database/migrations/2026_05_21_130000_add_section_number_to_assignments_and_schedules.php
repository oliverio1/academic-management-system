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

        $hasLegacyUnique = collect(DB::select("SHOW INDEX FROM teaching_assignments WHERE Key_name = 'uniq_teaching_assignments_cycle_group_subject'"))->isNotEmpty();
        $hasSectionUnique = collect(DB::select("SHOW INDEX FROM teaching_assignments WHERE Key_name = 'uniq_teaching_assignments_cycle_group_subject_section'"))->isNotEmpty();
        $hasCycleGroupIdx = collect(DB::select("SHOW INDEX FROM teaching_assignments WHERE Key_name = 'idx_teaching_assignments_cycle_group_id'"))->isNotEmpty();

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
        $hasSectionUnique = collect(DB::select("SHOW INDEX FROM teaching_assignments WHERE Key_name = 'uniq_teaching_assignments_cycle_group_subject_section'"))->isNotEmpty();
        $hasLegacyUnique = collect(DB::select("SHOW INDEX FROM teaching_assignments WHERE Key_name = 'uniq_teaching_assignments_cycle_group_subject'"))->isNotEmpty();

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

        $hasCycleGroupIdx = collect(DB::select("SHOW INDEX FROM teaching_assignments WHERE Key_name = 'idx_teaching_assignments_cycle_group_id'"))->isNotEmpty();
        if ($hasCycleGroupIdx) {
            Schema::table('teaching_assignments', function (Blueprint $table) {
                $table->dropIndex('idx_teaching_assignments_cycle_group_id');
            });
        }
    }
};
