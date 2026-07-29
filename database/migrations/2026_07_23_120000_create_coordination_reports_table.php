<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coordination_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->string('received_via', 30)->default('in_person');
            $table->string('reporter_name', 160)->nullable();
            $table->string('reporter_contact', 180)->nullable();
            $table->string('category', 50)->default('other');
            $table->string('subject', 150);
            $table->text('description');
            $table->unsignedTinyInteger('priority')->default(2);
            $table->string('status', 20)->default('open');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['campus_id', 'status', 'created_at'], 'coord_reports_campus_status_created_idx');
            $table->index(['reported_by', 'status'], 'coord_reports_reported_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coordination_reports');
    }
};
