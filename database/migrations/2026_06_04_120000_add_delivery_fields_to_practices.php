<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practices', function (Blueprint $table) {
            if (! Schema::hasColumn('practices', 'kind')) {
                $table->string('kind', 30)->default('practice')->after('number');
            }

            if (! Schema::hasColumn('practices', 'introduction')) {
                $table->longText('introduction')->nullable()->after('title');
            }

            if (! Schema::hasColumn('practices', 'procedure')) {
                $table->longText('procedure')->nullable()->after('instructions');
            }

            if (! Schema::hasColumn('practices', 'realization_date')) {
                $table->date('realization_date')->nullable()->after('questionnaire');
            }
        });

        Schema::table('practice_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('practice_submissions', 'discussion')) {
                $table->longText('discussion')->nullable()->after('results');
            }
        });
    }

    public function down(): void
    {
        Schema::table('practice_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('practice_submissions', 'discussion')) {
                $table->dropColumn('discussion');
            }
        });

        Schema::table('practices', function (Blueprint $table) {
            foreach (['kind', 'introduction', 'procedure', 'realization_date'] as $column) {
                if (Schema::hasColumn('practices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
