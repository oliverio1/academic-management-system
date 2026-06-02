<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temarios', function (Blueprint $table) {
            if (! Schema::hasColumn('temarios', 'subject_id')) {
                $table->foreignId('subject_id')
                    ->nullable()
                    ->after('id')
                    ->constrained()
                    ->cascadeOnDelete();
            }
        });

        DB::statement('
            UPDATE temarios t
            INNER JOIN teaching_assignments ta ON ta.id = t.teaching_assignment_id
            SET t.subject_id = ta.subject_id
            WHERE t.subject_id IS NULL
        ');

        Schema::table('temarios', function (Blueprint $table) {
            if (Schema::hasColumn('temarios', 'teaching_assignment_id')) {
                $table->dropConstrainedForeignId('teaching_assignment_id');
            }
        });

        DB::statement('ALTER TABLE temarios MODIFY subject_id BIGINT UNSIGNED NOT NULL');
    }

    public function down(): void
    {
        Schema::table('temarios', function (Blueprint $table) {
            if (! Schema::hasColumn('temarios', 'teaching_assignment_id')) {
                $table->foreignId('teaching_assignment_id')
                    ->nullable()
                    ->after('subject_id')
                    ->constrained()
                    ->cascadeOnDelete();
            }
        });

        DB::statement('
            UPDATE temarios t
            INNER JOIN teaching_assignments ta ON ta.subject_id = t.subject_id
            SET t.teaching_assignment_id = ta.id
            WHERE t.teaching_assignment_id IS NULL
        ');

        DB::statement('ALTER TABLE temarios MODIFY teaching_assignment_id BIGINT UNSIGNED NOT NULL');

        Schema::table('temarios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subject_id');
        });
    }
};
