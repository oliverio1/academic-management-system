<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_activities', function (Blueprint $table) {
            $table->json('temario_subtopic_ids')
                ->nullable()
                ->after('temario_point_id');
        });
    }

    public function down(): void
    {
        Schema::table('session_activities', function (Blueprint $table) {
            $table->dropColumn('temario_subtopic_ids');
        });
    }
};

