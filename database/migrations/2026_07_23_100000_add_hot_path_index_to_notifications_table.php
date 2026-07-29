<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications') || $this->indexExists('notifications', 'notifications_unread_nav_idx')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(
                ['notifiable_type', 'notifiable_id', 'read_at', 'created_at'],
                'notifications_unread_nav_idx'
            );
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('notifications') && $this->indexExists('notifications', 'notifications_unread_nav_idx')) {
            Schema::table('notifications', fn (Blueprint $table) => $table->dropIndex('notifications_unread_nav_idx'));
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
