<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cycle_partials', function (Blueprint $table) {
            $table->dateTime('teacher_capture_deadline_at')->nullable()->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('cycle_partials', function (Blueprint $table) {
            $table->dropColumn('teacher_capture_deadline_at');
        });
    }
};

