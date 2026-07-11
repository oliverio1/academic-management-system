<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_LABELS = [
        'objectives' => 'Objetivo',
        'hypothesis' => 'Hipótesis',
        'theoretical_framework' => 'Marco teórico',
        'results' => 'Resultados',
        'discussion' => 'Discusión',
        'conclusions' => 'Conclusiones',
        'references' => 'Referencias',
    ];

    public function up(): void
    {
        Schema::table('practices', function (Blueprint $table) {
            if (! Schema::hasColumn('practices', 'custom_submission_fields')) {
                $table->json('custom_submission_fields')->nullable()->after('submission_fields');
            }
        });

        Schema::table('practice_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('practice_submissions', 'custom_field_answers')) {
                $table->json('custom_field_answers')->nullable()->after('questionnaire_answers');
            }
        });

        if (Schema::hasColumn('practices', 'custom_submission_fields')) {
            DB::table('practices')
                ->whereNull('custom_submission_fields')
                ->orderBy('id')
                ->chunkById(100, function ($practices) {
                    foreach ($practices as $practice) {
                        $fields = json_decode($practice->submission_fields ?? '[]', true);

                        if (! is_array($fields) || empty($fields)) {
                            $fields = array_keys(self::LEGACY_LABELS);
                        }

                        $customFields = collect($fields)
                            ->filter(fn ($field) => array_key_exists($field, self::LEGACY_LABELS))
                            ->map(fn ($field) => [
                                'id' => $field,
                                'label' => self::LEGACY_LABELS[$field],
                                'required' => false,
                                'legacy_field' => $field,
                            ])
                            ->values()
                            ->all();

                        DB::table('practices')
                            ->where('id', $practice->id)
                            ->update(['custom_submission_fields' => json_encode($customFields, JSON_UNESCAPED_UNICODE)]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('practice_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('practice_submissions', 'custom_field_answers')) {
                $table->dropColumn('custom_field_answers');
            }
        });

        Schema::table('practices', function (Blueprint $table) {
            if (Schema::hasColumn('practices', 'custom_submission_fields')) {
                $table->dropColumn('custom_submission_fields');
            }
        });
    }
};
