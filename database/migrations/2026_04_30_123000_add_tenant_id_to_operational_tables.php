<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Database\Models\Tenant;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_cycle_groups', function (Blueprint $table) {
            if (! Schema::hasColumn('school_cycle_groups', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->after('id');
            }
        });

        Schema::table('teaching_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('teaching_assignments', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->after('id');
            }
        });

        Schema::table('schedules', function (Blueprint $table) {
            if (! Schema::hasColumn('schedules', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->after('id');
            }
        });

        $this->backfillSchoolCycleGroupsTenantId();
        $this->backfillTeachingAssignmentsTenantId();
        $this->backfillSchedulesTenantId();

        Schema::table('school_cycle_groups', function (Blueprint $table) {
            $table->index(['tenant_id', 'school_cycle_id'], 'scg_tenant_cycle_idx');
            $table->index(['tenant_id', 'group_id'], 'scg_tenant_group_idx');
        });

        Schema::table('teaching_assignments', function (Blueprint $table) {
            $table->index(['tenant_id', 'school_cycle_group_id'], 'ta_tenant_cycle_group_idx');
            $table->index(['tenant_id', 'group_id', 'subject_id'], 'ta_tenant_group_subject_idx');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->index(['tenant_id', 'teaching_assignment_id'], 's_tenant_assignment_idx');
            $table->index(['tenant_id', 'school_cycle_id'], 's_tenant_cycle_idx');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex('s_tenant_assignment_idx');
            $table->dropIndex('s_tenant_cycle_idx');
        });

        Schema::table('teaching_assignments', function (Blueprint $table) {
            $table->dropIndex('ta_tenant_cycle_group_idx');
            $table->dropIndex('ta_tenant_group_subject_idx');
        });

        Schema::table('school_cycle_groups', function (Blueprint $table) {
            $table->dropIndex('scg_tenant_cycle_idx');
            $table->dropIndex('scg_tenant_group_idx');
        });

        Schema::table('schedules', function (Blueprint $table) {
            if (Schema::hasColumn('schedules', 'tenant_id')) {
                $table->dropColumn('tenant_id');
            }
        });

        Schema::table('teaching_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('teaching_assignments', 'tenant_id')) {
                $table->dropColumn('tenant_id');
            }
        });

        Schema::table('school_cycle_groups', function (Blueprint $table) {
            if (Schema::hasColumn('school_cycle_groups', 'tenant_id')) {
                $table->dropColumn('tenant_id');
            }
        });
    }

    private function backfillSchoolCycleGroupsTenantId(): void
    {
        $tenants = Tenant::query()->get()->keyBy(fn ($tenant) => mb_strtolower((string) $tenant->id));
        $campuses = DB::table('campuses')->select('id', 'name')->get();

        $campusTenantMap = [];
        foreach ($campuses as $campus) {
            $name = mb_strtolower((string) $campus->name);
            if (str_contains($name, 'valle') && $tenants->has('valle')) {
                $campusTenantMap[(int) $campus->id] = 'valle';
            } elseif (str_contains($name, 'florida') && $tenants->has('florida')) {
                $campusTenantMap[(int) $campus->id] = 'florida';
            }
        }

        if (empty($campusTenantMap)) {
            return;
        }

        foreach ($campusTenantMap as $campusId => $tenantId) {
            DB::table('school_cycle_groups')
                ->where('campus_id', $campusId)
                ->whereNull('tenant_id')
                ->update(['tenant_id' => $tenantId]);
        }
    }

    private function backfillTeachingAssignmentsTenantId(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            DB::table('teaching_assignments')
                ->whereNull('tenant_id')
                ->update([
                    'tenant_id' => DB::raw('(SELECT tenant_id FROM school_cycle_groups WHERE school_cycle_groups.id = teaching_assignments.school_cycle_group_id)'),
                ]);

            return;
        }

        DB::statement(
            'UPDATE teaching_assignments ta
             INNER JOIN school_cycle_groups scg ON scg.id = ta.school_cycle_group_id
             SET ta.tenant_id = scg.tenant_id
             WHERE ta.tenant_id IS NULL'
        );
    }

    private function backfillSchedulesTenantId(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            DB::table('schedules')
                ->whereNull('tenant_id')
                ->update([
                    'tenant_id' => DB::raw('(SELECT tenant_id FROM teaching_assignments WHERE teaching_assignments.id = schedules.teaching_assignment_id)'),
                ]);

            return;
        }

        DB::statement(
            'UPDATE schedules s
             INNER JOIN teaching_assignments ta ON ta.id = s.teaching_assignment_id
             SET s.tenant_id = ta.tenant_id
             WHERE s.tenant_id IS NULL'
        );
    }
};
