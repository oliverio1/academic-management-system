<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE `student_follow_ups`
            MODIFY `type` ENUM('academic', 'behavioral', 'mixed') NOT NULL DEFAULT 'mixed'
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE `student_follow_ups`
            MODIFY `type` ENUM('academic', 'behavioral', 'mixed') NOT NULL
        ");
    }
};
