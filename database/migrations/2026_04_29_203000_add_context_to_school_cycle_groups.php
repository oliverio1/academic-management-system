<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_cycle_groups', function (Blueprint $table) {
            if (! Schema::hasColumn('school_cycle_groups', 'campus_id')) {
                $table->foreignId('campus_id')->nullable()->after('group_id')->constrained('campuses')->cascadeOnDelete();
            }
            if (! Schema::hasColumn('school_cycle_groups', 'modality_id')) {
                $table->foreignId('modality_id')->nullable()->after('campus_id')->constrained('modalities')->cascadeOnDelete();
            }
        });

        DB::statement("
            UPDATE school_cycle_groups scg
            JOIN school_cycles sc ON sc.id = scg.school_cycle_id
            JOIN `groups` g ON g.id = scg.group_id
            JOIN levels l ON l.id = g.level_id
            SET scg.campus_id = COALESCE(scg.campus_id, sc.campus_id),
                scg.modality_id = COALESCE(scg.modality_id, l.modality_id)
            WHERE scg.campus_id IS NULL OR scg.modality_id IS NULL
        ");

        Schema::table('school_cycle_groups', function (Blueprint $table) {
            $table->dropUnique(['school_cycle_id', 'group_id']);
            $table->unique(['school_cycle_id', 'group_id', 'campus_id', 'modality_id'], 'school_cycle_groups_cycle_group_context_unique');
            $table->index(['school_cycle_id', 'campus_id', 'modality_id', 'is_active'], 'school_cycle_groups_ctx_active_idx');
        });
    }

    public function down(): void
    {
        Schema::table('school_cycle_groups', function (Blueprint $table) {
            $table->dropIndex('school_cycle_groups_ctx_active_idx');
            $table->dropUnique('school_cycle_groups_cycle_group_context_unique');
            $table->unique(['school_cycle_id', 'group_id']);
            $table->dropConstrainedForeignId('modality_id');
            $table->dropConstrainedForeignId('campus_id');
        });
    }
};

