<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_calendar_days', function (Blueprint $table) {
            $table->dropUnique('academic_calendar_days_date_unique');
            $table->unique(['date', 'modality_id'], 'academic_calendar_days_date_modality_unique');
        });
    }

    public function down(): void
    {
        Schema::table('academic_calendar_days', function (Blueprint $table) {
            $table->dropUnique('academic_calendar_days_date_modality_unique');
            $table->unique('date');
        });
    }
};
