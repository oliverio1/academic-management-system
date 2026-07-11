<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practices', function (Blueprint $table) {
            if (! Schema::hasColumn('practices', 'submission_fields')) {
                $table->json('submission_fields')->nullable()->after('questionnaire');
            }
        });

        if (Schema::hasColumn('practices', 'submission_fields')) {
            DB::table('practices')
                ->whereNull('submission_fields')
                ->update([
                    'submission_fields' => json_encode([
                        'objectives',
                        'hypothesis',
                        'theoretical_framework',
                        'results',
                        'discussion',
                        'conclusions',
                        'references',
                    ]),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('practices', function (Blueprint $table) {
            if (Schema::hasColumn('practices', 'submission_fields')) {
                $table->dropColumn('submission_fields');
            }
        });
    }
};
