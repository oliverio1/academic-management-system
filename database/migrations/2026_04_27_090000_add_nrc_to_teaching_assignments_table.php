<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('teaching_assignments', 'nrc')) {
            Schema::table('teaching_assignments', function (Blueprint $table) {
                $table->string('nrc', 30)->nullable()->after('subject_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('teaching_assignments', 'nrc')) {
            Schema::table('teaching_assignments', function (Blueprint $table) {
                $table->dropColumn('nrc');
            });
        }
    }
};

