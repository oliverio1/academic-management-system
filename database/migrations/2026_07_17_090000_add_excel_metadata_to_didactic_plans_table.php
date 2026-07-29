<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('didactic_plans', function (Blueprint $table) {
            $table->string('unam_incorporation_key')->nullable()->after('title');
            $table->string('teacher_dgire_file')->nullable()->after('unam_incorporation_key');
            $table->date('technical_review_date')->nullable()->after('teacher_dgire_file');
            $table->string('subject_character')->nullable()->after('technical_review_date');
            $table->string('subject_key')->nullable()->after('subject_character');
            $table->unsignedSmallInteger('total_annual_hours')->nullable()->after('subject_key');
        });
    }

    public function down(): void
    {
        Schema::table('didactic_plans', function (Blueprint $table) {
            $table->dropColumn([
                'unam_incorporation_key',
                'teacher_dgire_file',
                'technical_review_date',
                'subject_character',
                'subject_key',
                'total_annual_hours',
            ]);
        });
    }
};
