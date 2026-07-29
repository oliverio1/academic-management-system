<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practice_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('practice_submissions', 'is_resubmission_allowed')) {
                $table->boolean('is_resubmission_allowed')->default(false)->after('status');
            }

            if (! Schema::hasColumn('practice_submissions', 'resubmission_due_date')) {
                $table->date('resubmission_due_date')->nullable()->after('is_resubmission_allowed');
            }

            if (! Schema::hasColumn('practice_submissions', 'resubmission_note')) {
                $table->text('resubmission_note')->nullable()->after('resubmission_due_date');
            }

            if (! Schema::hasColumn('practice_submissions', 'resubmission_requested_at')) {
                $table->timestamp('resubmission_requested_at')->nullable()->after('resubmission_note');
            }

            if (! Schema::hasColumn('practice_submissions', 'resubmission_requested_by')) {
                $table->foreignId('resubmission_requested_by')->nullable()->after('resubmission_requested_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('practice_submissions', 'resubmission_count')) {
                $table->unsignedInteger('resubmission_count')->default(0)->after('resubmission_requested_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('practice_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('practice_submissions', 'resubmission_requested_by')) {
                $table->dropConstrainedForeignId('resubmission_requested_by');
            }

            foreach ([
                'resubmission_count',
                'resubmission_requested_at',
                'resubmission_note',
                'resubmission_due_date',
                'is_resubmission_allowed',
            ] as $column) {
                if (Schema::hasColumn('practice_submissions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
