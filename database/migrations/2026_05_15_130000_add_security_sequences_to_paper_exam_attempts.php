<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paper_exam_attempts', function (Blueprint $table) {
            $table->json('question_sequence')->nullable()->after('status');
            $table->json('options_sequence')->nullable()->after('question_sequence');
            $table->timestamp('locked_at')->nullable()->after('submitted_at');
            $table->string('lock_reason', 120)->nullable()->after('locked_at');
        });
    }

    public function down(): void
    {
        Schema::table('paper_exam_attempts', function (Blueprint $table) {
            $table->dropColumn(['question_sequence', 'options_sequence', 'locked_at', 'lock_reason']);
        });
    }
};

