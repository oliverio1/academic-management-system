<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_criteria', function (Blueprint $table) {
            if (! Schema::hasColumn('evaluation_criteria', 'cycle_partial_id')) {
                $table->foreignId('cycle_partial_id')
                    ->nullable()
                    ->after('teaching_assignment_id')
                    ->constrained('cycle_partials')
                    ->nullOnDelete();
            }
        });

        if (! $this->indexExists('evaluation_criteria', 'evaluation_criteria_teaching_assignment_idx')) {
            Schema::table('evaluation_criteria', function (Blueprint $table) {
                $table->index('teaching_assignment_id', 'evaluation_criteria_teaching_assignment_idx');
            });
        }

        if ($this->indexExists('evaluation_criteria', 'evaluation_criteria_teaching_assignment_id_name_unique')) {
            Schema::table('evaluation_criteria', function (Blueprint $table) {
                $table->dropUnique('evaluation_criteria_teaching_assignment_id_name_unique');
            });
        }

        if (! $this->indexExists('evaluation_criteria', 'evaluation_criteria_assignment_partial_name_unique')) {
            Schema::table('evaluation_criteria', function (Blueprint $table) {
                $table->unique(
                    ['teaching_assignment_id', 'cycle_partial_id', 'name'],
                    'evaluation_criteria_assignment_partial_name_unique'
                );
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('evaluation_criteria', 'evaluation_criteria_assignment_partial_name_unique')) {
            Schema::table('evaluation_criteria', function (Blueprint $table) {
                $table->dropUnique('evaluation_criteria_assignment_partial_name_unique');
            });
        }

        if (! $this->indexExists('evaluation_criteria', 'evaluation_criteria_teaching_assignment_id_name_unique')) {
            Schema::table('evaluation_criteria', function (Blueprint $table) {
                $table->unique(['teaching_assignment_id', 'name']);
            });
        }

        if (Schema::hasColumn('evaluation_criteria', 'cycle_partial_id')) {
            if ($this->foreignKeyExists('evaluation_criteria', 'evaluation_criteria_cycle_partial_id_foreign')) {
                Schema::table('evaluation_criteria', function (Blueprint $table) {
                    $table->dropForeign('evaluation_criteria_cycle_partial_id_foreign');
                });
            }

            Schema::table('evaluation_criteria', function (Blueprint $table) {
                $table->dropColumn('cycle_partial_id');
            });
        }

        $this->dropIndexIfExists('evaluation_criteria', 'evaluation_criteria_teaching_assignment_idx');
    }

    private function indexExists(string $table, string $index): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            $safeTable = str_replace("'", "''", $table);

            return collect(DB::select("PRAGMA index_list('{$safeTable}')"))
                ->contains(fn ($item) => ($item->name ?? null) === $index);
        }

        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        $database = DB::getDatabaseName();

        return DB::table('information_schema.table_constraints')
            ->where('constraint_schema', $database)
            ->where('table_name', $table)
            ->where('constraint_name', $foreignKey)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if ($this->indexExists($table, $index)) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement(sprintf('ALTER TABLE `%s` DROP INDEX `%s`', $table, $index));
                return;
            }

            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($index));
        }
    }
};
