<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('campuses')) {
            Schema::create('campuses', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code', 30)->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $existingCampusId = DB::table('campuses')
            ->where('code', 'PRINCIPAL')
            ->value('id');

        $defaultCampusId = $existingCampusId ?: DB::table('campuses')->insertGetId([
            'name' => 'Campus Principal',
            'code' => 'PRINCIPAL',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! Schema::hasColumn('groups', 'campus_id')) {
            Schema::table('groups', function (Blueprint $table) {
                $table->foreignId('campus_id')->nullable()->after('level_id')->constrained('campuses')->cascadeOnDelete();
            });
        }
        DB::table('groups')->whereNull('campus_id')->update(['campus_id' => $defaultCampusId]);
        if (! $this->indexExists('groups', 'groups_level_id_index')) {
            Schema::table('groups', function (Blueprint $table) {
                $table->index('level_id', 'groups_level_id_index');
            });
        }
        if ($this->indexExists('groups', 'groups_level_id_name_unique')) {
            Schema::table('groups', function (Blueprint $table) {
                $table->dropUnique('groups_level_id_name_unique');
            });
        }
        if (! $this->indexExists('groups', 'groups_campus_level_name_unique')) {
            Schema::table('groups', function (Blueprint $table) {
                $table->unique(['campus_id', 'level_id', 'name'], 'groups_campus_level_name_unique');
            });
        }

        if (! Schema::hasColumn('school_cycles', 'campus_id')) {
            Schema::table('school_cycles', function (Blueprint $table) {
                $table->foreignId('campus_id')->nullable()->after('id')->constrained('campuses')->cascadeOnDelete();
            });
        }
        DB::table('school_cycles')->whereNull('campus_id')->update(['campus_id' => $defaultCampusId]);
        if (! $this->indexExists('school_cycles', 'school_cycles_campus_start_idx')) {
            Schema::table('school_cycles', function (Blueprint $table) {
                $table->index(['campus_id', 'start_date'], 'school_cycles_campus_start_idx');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('school_cycles', 'school_cycles_campus_start_idx')) {
            Schema::table('school_cycles', function (Blueprint $table) {
                $table->dropIndex('school_cycles_campus_start_idx');
            });
        }
        if (Schema::hasColumn('school_cycles', 'campus_id')) {
            Schema::table('school_cycles', function (Blueprint $table) {
                $table->dropConstrainedForeignId('campus_id');
            });
        }

        if ($this->indexExists('groups', 'groups_campus_level_name_unique')) {
            Schema::table('groups', function (Blueprint $table) {
                $table->dropUnique('groups_campus_level_name_unique');
            });
        }
        if (! $this->indexExists('groups', 'groups_level_id_name_unique')) {
            Schema::table('groups', function (Blueprint $table) {
                $table->unique(['level_id', 'name']);
            });
        }
        if (Schema::hasColumn('groups', 'campus_id')) {
            Schema::table('groups', function (Blueprint $table) {
                $table->dropConstrainedForeignId('campus_id');
            });
        }

        Schema::dropIfExists('campuses');
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
};
