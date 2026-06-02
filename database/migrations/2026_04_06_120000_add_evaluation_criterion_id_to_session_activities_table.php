<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('session_activities', function (Blueprint $table) {
            $table->foreignId('evaluation_criterion_id')
                ->nullable()
                ->after('description')
                ->constrained('evaluation_criteria')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('session_activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('evaluation_criterion_id');
        });
    }
};
