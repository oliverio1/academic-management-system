<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prefect_incident_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->string('report_to', 30);
            $table->string('category', 30);
            $table->string('subject', 150);
            $table->text('description');
            $table->string('status', 20)->default('open');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'idx_prefect_reports_status_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prefect_incident_reports');
    }
};

