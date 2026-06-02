<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_campus_attendances', function (Blueprint $table) {
            $table->decimal('check_in_latitude', 10, 7)->nullable()->after('check_in_time');
            $table->decimal('check_in_longitude', 10, 7)->nullable()->after('check_in_latitude');
            $table->decimal('check_out_latitude', 10, 7)->nullable()->after('check_out_time');
            $table->decimal('check_out_longitude', 10, 7)->nullable()->after('check_out_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_campus_attendances', function (Blueprint $table) {
            $table->dropColumn([
                'check_in_latitude',
                'check_in_longitude',
                'check_out_latitude',
                'check_out_longitude',
            ]);
        });
    }
};

