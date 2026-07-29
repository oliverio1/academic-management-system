<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('didactic_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('didactic_plans', 'dgire_metadata')) {
                $table->json('dgire_metadata')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('didactic_plans', function (Blueprint $table) {
            if (Schema::hasColumn('didactic_plans', 'dgire_metadata')) {
                $table->dropColumn('dgire_metadata');
            }
        });
    }
};
