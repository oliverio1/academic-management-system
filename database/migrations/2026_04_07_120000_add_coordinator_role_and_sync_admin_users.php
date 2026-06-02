<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $coordinatorRole = Role::firstOrCreate(['name' => 'coordinator', 'guard_name' => 'web']);
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();

        if (! $adminRole) {
            return;
        }

        $tableNames = config('permission.table_names');
        $pivotTable = $tableNames['model_has_roles'] ?? 'model_has_roles';
        $modelTypeColumn = config('permission.column_names.model_morph_key', 'model_id');

        $adminAssignments = DB::table($pivotTable)
            ->where('role_id', $adminRole->id)
            ->where('model_type', \App\Models\User::class)
            ->get();

        foreach ($adminAssignments as $assignment) {
            $exists = DB::table($pivotTable)
                ->where('role_id', $coordinatorRole->id)
                ->where('model_type', $assignment->model_type)
                ->where($modelTypeColumn, $assignment->{$modelTypeColumn})
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table($pivotTable)->insert([
                'role_id' => $coordinatorRole->id,
                'model_type' => $assignment->model_type,
                $modelTypeColumn => $assignment->{$modelTypeColumn},
            ]);
        }
    }

    public function down(): void
    {
        $coordinatorRole = Role::where('name', 'coordinator')->where('guard_name', 'web')->first();

        if (! $coordinatorRole) {
            return;
        }

        $tableNames = config('permission.table_names');
        $pivotTable = $tableNames['model_has_roles'] ?? 'model_has_roles';
        $modelTypeColumn = config('permission.column_names.model_morph_key', 'model_id');

        DB::table($pivotTable)
            ->where('role_id', $coordinatorRole->id)
            ->where('model_type', \App\Models\User::class)
            ->delete();
    }
};
