<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $prefectorRole = DB::table('roles')->where('name', 'prefector')->first();
        $prefectRole = DB::table('roles')->where('name', 'prefect')->first();

        if ($prefectorRole && ! $prefectRole) {
            DB::table('roles')
                ->where('id', $prefectorRole->id)
                ->update(['name' => 'prefect']);

            return;
        }

        if ($prefectorRole && $prefectRole) {
            DB::table('model_has_roles')
                ->where('role_id', $prefectorRole->id)
                ->update(['role_id' => $prefectRole->id]);

            DB::table('role_has_permissions')
                ->where('role_id', $prefectorRole->id)
                ->update(['role_id' => $prefectRole->id]);

            DB::table('roles')
                ->where('id', $prefectorRole->id)
                ->delete();
        }
    }

    public function down(): void
    {
        $prefectRole = DB::table('roles')->where('name', 'prefect')->first();
        $prefectorRole = DB::table('roles')->where('name', 'prefector')->first();

        if ($prefectRole && ! $prefectorRole) {
            DB::table('roles')
                ->where('id', $prefectRole->id)
                ->update(['name' => 'prefector']);
        }
    }
};

