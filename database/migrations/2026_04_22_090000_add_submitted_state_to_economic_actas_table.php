<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('economic_actas', function (Blueprint $table) {
            $table->foreignId('submitted_by')->nullable()->after('drafted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
        });

        DB::statement("ALTER TABLE economic_actas MODIFY status ENUM('submitted','draft','closed','sent') NOT NULL DEFAULT 'submitted'");
    }

    public function down(): void
    {
        DB::statement("UPDATE economic_actas SET status = 'draft' WHERE status = 'submitted'");
        DB::statement("ALTER TABLE economic_actas MODIFY status ENUM('draft','closed','sent') NOT NULL DEFAULT 'draft'");

        Schema::table('economic_actas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropColumn('submitted_at');
        });
    }
};

