<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practice_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('practice_submissions', 'score')) {
                $table->decimal('score', 5, 2)->nullable()->after('custom_field_answers');
            }

            if (! Schema::hasColumn('practice_submissions', 'teacher_corrections')) {
                $table->longText('teacher_corrections')->nullable()->after('score');
            }

            if (! Schema::hasColumn('practice_submissions', 'teacher_comments')) {
                $table->longText('teacher_comments')->nullable()->after('teacher_corrections');
            }

            if (! Schema::hasColumn('practice_submissions', 'teacher_suggestions')) {
                $table->longText('teacher_suggestions')->nullable()->after('teacher_comments');
            }

            if (! Schema::hasColumn('practice_submissions', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('teacher_suggestions')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('practice_submissions', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('practice_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('practice_submissions', 'reviewed_by')) {
                $table->dropConstrainedForeignId('reviewed_by');
            }

            foreach (['reviewed_at', 'teacher_suggestions', 'teacher_comments', 'teacher_corrections', 'score'] as $column) {
                if (Schema::hasColumn('practice_submissions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
