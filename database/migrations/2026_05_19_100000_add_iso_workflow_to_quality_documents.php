<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quality_documents', function (Blueprint $table) {
            $table->enum('approval_status', ['draft', 'pending_approval', 'approved', 'rejected'])->default('draft')->after('status');
            $table->foreignId('current_version_id')->nullable()->after('content');
        });

        Schema::create('quality_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quality_document_id')->constrained('quality_documents')->cascadeOnDelete();
            $table->string('version', 30);
            $table->longText('content')->nullable();
            $table->text('change_summary')->nullable();
            $table->enum('approval_status', ['draft', 'pending_approval', 'approved', 'rejected'])->default('draft');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_comment')->nullable();
            $table->timestamps();

            $table->index(['quality_document_id', 'version']);
        });

        Schema::create('quality_document_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quality_document_id')->constrained('quality_documents')->cascadeOnDelete();
            $table->foreignId('quality_document_version_id')->nullable()->constrained('quality_document_versions')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 60);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['quality_document_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_document_events');
        Schema::dropIfExists('quality_document_versions');

        Schema::table('quality_documents', function (Blueprint $table) {
            $table->dropColumn(['approval_status', 'current_version_id']);
        });
    }
};

