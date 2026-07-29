<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paper_exam_attempts', function (Blueprint $table) {
            if (! Schema::hasColumn('paper_exam_attempts', 'exam_payload')) {
                $table->json('exam_payload')->nullable()->after('options_sequence');
            }
        });
    }

    public function down(): void
    {
        Schema::table('paper_exam_attempts', function (Blueprint $table) {
            if (Schema::hasColumn('paper_exam_attempts', 'exam_payload')) {
                $table->dropColumn('exam_payload');
            }
        });
    }
};
