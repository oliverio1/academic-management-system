<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_document_request_items', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('request_id')->constrained('teacher_document_requests')->cascadeOnDelete();
            $table->foreignId('teaching_assignment_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->text('notes')->nullable();
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['request_id', 'teaching_assignment_id', 'document_type'], 'teacher_doc_req_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_document_request_items');
    }
};

