<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_cycle_groups', function (Blueprint $table) {
            if (! Schema::hasColumn('school_cycle_groups', 'section_count')) {
                $table->unsignedTinyInteger('section_count')
                    ->default(1)
                    ->after('modality_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('school_cycle_groups', function (Blueprint $table) {
            if (Schema::hasColumn('school_cycle_groups', 'section_count')) {
                $table->dropColumn('section_count');
            }
        });
    }
};

