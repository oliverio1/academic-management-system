<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_submission_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            $table->index(['practice_submission_id', 'created_at'], 'practice_submission_attachments_submission_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_submission_attachments');
    }
};
