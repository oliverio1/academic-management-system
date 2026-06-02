<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('teaching_assignments', 'school_cycle_group_id')) {
            Schema::table('teaching_assignments', function (Blueprint $table) {
                $table->foreignId('school_cycle_group_id')
                    ->nullable()
                    ->after('group_id')
                    ->constrained('school_cycle_groups')
                    ->nullOnDelete();
            });
        }

        $activeCycleId = DB::table('school_cycles')
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->value('id');

        if ($activeCycleId) {
            $assignments = DB::table('teaching_assignments')
                ->whereNull('school_cycle_group_id')
                ->get(['id', 'group_id']);

            foreach ($assignments as $assignment) {
                $cycleGroupId = DB::table('school_cycle_groups')
                    ->where('school_cycle_id', $activeCycleId)
                    ->where('group_id', $assignment->group_id)
                    ->value('id');

                if (! $cycleGroupId) {
                    $cycleGroupId = DB::table('school_cycle_groups')->insertGetId([
                        'school_cycle_id' => $activeCycleId,
                        'group_id' => $assignment->group_id,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('teaching_assignments')
                    ->where('id', $assignment->id)
                    ->update(['school_cycle_group_id' => $cycleGroupId]);
            }
        }

        $hasLegacyUnique = collect(DB::select("SHOW INDEX FROM teaching_assignments WHERE Key_name = 'teaching_assignments_teacher_id_group_id_subject_id_unique'"))->isNotEmpty();
        $hasCycleUnique = collect(DB::select("SHOW INDEX FROM teaching_assignments WHERE Key_name = 'uniq_teaching_assignments_cycle_group_subject'"))->isNotEmpty();
        $hasTeacherIdx = collect(DB::select("SHOW INDEX FROM teaching_assignments WHERE Key_name = 'idx_teaching_assignments_teacher_id'"))->isNotEmpty();

        if (! $hasTeacherIdx) {
            Schema::table('teaching_assignments', function (Blueprint $table) {
                $table->index('teacher_id', 'idx_teaching_assignments_teacher_id');
            });
        }

        if ($hasLegacyUnique) {
            Schema::table('teaching_assignments', function (Blueprint $table) {
                $table->dropUnique('teaching_assignments_teacher_id_group_id_subject_id_unique');
            });
        }

        if (! $hasCycleUnique) {
            Schema::table('teaching_assignments', function (Blueprint $table) {
                $table->unique(
                    ['school_cycle_group_id', 'subject_id'],
                    'uniq_teaching_assignments_cycle_group_subject'
                );
            });
        }
    }

    public function down(): void
    {
        $hasCycleUnique = collect(DB::select("SHOW INDEX FROM teaching_assignments WHERE Key_name = 'uniq_teaching_assignments_cycle_group_subject'"))->isNotEmpty();
        $hasLegacyUnique = collect(DB::select("SHOW INDEX FROM teaching_assignments WHERE Key_name = 'teaching_assignments_teacher_id_group_id_subject_id_unique'"))->isNotEmpty();
        $hasTeacherIdx = collect(DB::select("SHOW INDEX FROM teaching_assignments WHERE Key_name = 'idx_teaching_assignments_teacher_id'"))->isNotEmpty();

        if ($hasCycleUnique) {
            Schema::table('teaching_assignments', function (Blueprint $table) {
                $table->dropUnique('uniq_teaching_assignments_cycle_group_subject');
            });
        }

        if (! $hasLegacyUnique) {
            Schema::table('teaching_assignments', function (Blueprint $table) {
                $table->unique(
                    ['teacher_id', 'group_id', 'subject_id'],
                    'teaching_assignments_teacher_id_group_id_subject_id_unique'
                );
            });
        }

        if ($hasTeacherIdx) {
            Schema::table('teaching_assignments', function (Blueprint $table) {
                $table->dropIndex('idx_teaching_assignments_teacher_id');
            });
        }

        if (Schema::hasColumn('teaching_assignments', 'school_cycle_group_id')) {
            Schema::table('teaching_assignments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('school_cycle_group_id');
            });
        }
    }
};
