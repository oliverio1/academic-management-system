<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('student_suspension_id')
                ->nullable()
                ->after('student_id')
                ->constrained('student_suspensions')
                ->nullOnDelete();
            $table->boolean('is_suspension_locked')
                ->default(false)
                ->after('status');

            $table->index(['student_suspension_id', 'is_suspension_locked'], 'idx_attendance_suspension_lock');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('idx_attendance_suspension_lock');
            $table->dropConstrainedForeignId('student_suspension_id');
            $table->dropColumn('is_suspension_locked');
        });
    }
};

