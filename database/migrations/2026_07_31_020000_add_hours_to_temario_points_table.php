<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temario_points', function (Blueprint $table) {
            $table->decimal('hours', 6, 2)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('temario_points', function (Blueprint $table) {
            $table->dropColumn('hours');
        });
    }
};
