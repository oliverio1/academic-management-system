<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paper_exam_attempts', function (Blueprint $table) {
            if (! Schema::hasColumn('paper_exam_attempts', 'autosave_payload')) {
                $table->json('autosave_payload')->nullable()->after('options_sequence');
            }

            if (! Schema::hasColumn('paper_exam_attempts', 'autosaved_at')) {
                $table->timestamp('autosaved_at')->nullable()->after('autosave_payload');
            }
        });
    }

    public function down(): void
    {
        Schema::table('paper_exam_attempts', function (Blueprint $table) {
            if (Schema::hasColumn('paper_exam_attempts', 'autosave_payload')) {
                $table->dropColumn('autosave_payload');
            }

            if (Schema::hasColumn('paper_exam_attempts', 'autosaved_at')) {
                $table->dropColumn('autosaved_at');
            }
        });
    }
};
