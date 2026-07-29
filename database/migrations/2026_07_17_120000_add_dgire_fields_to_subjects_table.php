<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            if (! Schema::hasColumn('subjects', 'subject_character')) {
                $table->string('subject_character')->nullable()->after('type');
            }

            if (! Schema::hasColumn('subjects', 'subject_key')) {
                $table->string('subject_key')->nullable()->after('subject_character');
            }

            if (! Schema::hasColumn('subjects', 'annual_hours')) {
                $table->unsignedSmallInteger('annual_hours')->nullable()->after('hours_per_week');
            }

            if (! Schema::hasColumn('subjects', 'annual_theory_hours')) {
                $table->unsignedSmallInteger('annual_theory_hours')->nullable()->after('annual_hours');
            }

            if (! Schema::hasColumn('subjects', 'annual_practice_hours')) {
                $table->unsignedSmallInteger('annual_practice_hours')->nullable()->after('annual_theory_hours');
            }

            if (! Schema::hasColumn('subjects', 'weekly_theory_hours')) {
                $table->unsignedTinyInteger('weekly_theory_hours')->nullable()->after('hours_per_week');
            }

            if (! Schema::hasColumn('subjects', 'weekly_practice_hours')) {
                $table->unsignedTinyInteger('weekly_practice_hours')->nullable()->after('weekly_theory_hours');
            }
        });

        DB::table('subjects')
            ->where(function ($query) {
                $query->where('type', 'Laboratorio')
                    ->orWhere('type', 'Taller')
                    ->orWhere('type', 'PRÁCTICA')
                    ->orWhere('type', 'PRACTICA');
            })
            ->update(['type' => 'Teorico-practica']);

        DB::table('subjects')
            ->where(function ($query) {
                $query->where('type', 'Teórica')
                    ->orWhere('type', 'TeÃ³rica')
                    ->orWhere('type', 'Teorica')
                    ->orWhereNull('type')
                    ->orWhere('type', '');
            })
            ->update(['type' => 'Teorica']);
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $columns = [
                'subject_character',
                'subject_key',
                'annual_hours',
                'annual_theory_hours',
                'annual_practice_hours',
                'weekly_theory_hours',
                'weekly_practice_hours',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('subjects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
