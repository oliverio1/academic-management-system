<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quality_processes', function (Blueprint $table) {
            $table->string('process_type', 30)->default('support')->after('description');
            $table->text('iso_9001_clauses')->nullable()->after('process_type');
            $table->text('iso_21001_clauses')->nullable()->after('iso_9001_clauses');
        });

        Schema::table('quality_documents', function (Blueprint $table) {
            $table->string('document_type', 30)->default('procedure')->after('title');
            $table->text('iso_9001_clauses')->nullable()->after('owner');
            $table->text('iso_21001_clauses')->nullable()->after('iso_9001_clauses');
        });
    }

    public function down(): void
    {
        Schema::table('quality_documents', function (Blueprint $table) {
            $table->dropColumn(['document_type', 'iso_9001_clauses', 'iso_21001_clauses']);
        });

        Schema::table('quality_processes', function (Blueprint $table) {
            $table->dropColumn(['process_type', 'iso_9001_clauses', 'iso_21001_clauses']);
        });
    }
};
