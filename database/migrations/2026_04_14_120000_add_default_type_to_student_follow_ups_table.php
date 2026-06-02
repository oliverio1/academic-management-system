<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE `student_follow_ups`
            MODIFY `type` ENUM('academic', 'behavioral', 'mixed') NOT NULL DEFAULT 'mixed'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE `student_follow_ups`
            MODIFY `type` ENUM('academic', 'behavioral', 'mixed') NOT NULL
        ");
    }
};

