<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $coordinatorRole = Role::firstOrCreate(['name' => 'coordinator', 'guard_name' => 'web']);

        $tableNames = config('permission.table_names');
        $pivotTable = $tableNames['model_has_roles'] ?? 'model_has_roles';
        $modelTypeColumn = config('permission.column_names.model_morph_key', 'model_id');

        $adminUser = \App\Models\User::where('email', 'admin@admin.com')->first();

        if ($adminUser) {
            $hasAdmin = DB::table($pivotTable)
                ->where('role_id', $adminRole->id)
                ->where('model_type', \App\Models\User::class)
                ->where($modelTypeColumn, $adminUser->id)
                ->exists();

            if (! $hasAdmin) {
                DB::table($pivotTable)->insert([
                    'role_id' => $adminRole->id,
                    'model_type' => \App\Models\User::class,
                    $modelTypeColumn => $adminUser->id,
                ]);
            }
        }

        $adminUserIds = DB::table($pivotTable)
            ->where('role_id', $adminRole->id)
            ->where('model_type', \App\Models\User::class)
            ->pluck($modelTypeColumn)
            ->all();

        if (! empty($adminUserIds)) {
            DB::table($pivotTable)
                ->where('role_id', $coordinatorRole->id)
                ->where('model_type', \App\Models\User::class)
                ->whereIn($modelTypeColumn, $adminUserIds)
                ->delete();
        }
    }

    public function down(): void
    {
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        $coordinatorRole = Role::where('name', 'coordinator')->where('guard_name', 'web')->first();

        if (! $adminRole || ! $coordinatorRole) {
            return;
        }

        $tableNames = config('permission.table_names');
        $pivotTable = $tableNames['model_has_roles'] ?? 'model_has_roles';
        $modelTypeColumn = config('permission.column_names.model_morph_key', 'model_id');

        $adminUserIds = DB::table($pivotTable)
            ->where('role_id', $adminRole->id)
            ->where('model_type', \App\Models\User::class)
            ->pluck($modelTypeColumn)
            ->all();

        foreach ($adminUserIds as $userId) {
            $exists = DB::table($pivotTable)
                ->where('role_id', $coordinatorRole->id)
                ->where('model_type', \App\Models\User::class)
                ->where($modelTypeColumn, $userId)
                ->exists();

            if (! $exists) {
                DB::table($pivotTable)->insert([
                    'role_id' => $coordinatorRole->id,
                    'model_type' => \App\Models\User::class,
                    $modelTypeColumn => $userId,
                ]);
            }
        }
    }
};
