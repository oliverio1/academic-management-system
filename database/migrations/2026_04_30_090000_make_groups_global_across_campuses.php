<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureIndex('groups', 'groups_campus_id_index', ['campus_id']);

        Schema::table('groups', function (Blueprint $table) {
            if (! $this->indexExists('groups', 'groups_level_id_name_unique')) {
                $table->unique(['level_id', 'name'], 'groups_level_id_name_unique');
            }
        });

        if ($this->indexExists('groups', 'groups_campus_level_name_unique')) {
            Schema::table('groups', function (Blueprint $table) {
                $table->dropUnique('groups_campus_level_name_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if ($this->indexExists('groups', 'groups_level_id_name_unique')) {
                $table->dropUnique('groups_level_id_name_unique');
            }
            if (! $this->indexExists('groups', 'groups_campus_level_name_unique')) {
                $table->unique(['campus_id', 'level_id', 'name'], 'groups_campus_level_name_unique');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            $safeTable = str_replace("'", "''", $table);

            return collect(DB::select("PRAGMA index_list('{$safeTable}')"))
                ->contains(fn ($item) => ($item->name ?? null) === $indexName);
        }

        $database = DB::getDatabaseName();

        $result = DB::selectOne(
            'SELECT COUNT(*) AS total
             FROM information_schema.statistics
             WHERE table_schema = ?
               AND table_name = ?
               AND index_name = ?',
            [$database, $table, $indexName]
        );

        return (int) ($result->total ?? 0) > 0;
    }

    private function ensureIndex(string $table, string $indexName, array $columns): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($columns, $indexName) {
            $table->index($columns, $indexName);
        });
    }
};
