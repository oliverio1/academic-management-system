<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('quality_processes')->nullOnDelete();
            $table->string('code', 50)->nullable();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('parent_id');
            $table->index('name');
        });

        Schema::create('quality_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quality_process_id')->constrained('quality_processes')->cascadeOnDelete();
            $table->string('code', 80)->nullable();
            $table->string('title', 255);
            $table->string('version', 30)->nullable();
            $table->enum('status', ['draft', 'active', 'obsolete'])->default('draft');
            $table->date('effective_date')->nullable();
            $table->date('review_date')->nullable();
            $table->string('owner', 255)->nullable();
            $table->longText('content')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('quality_process_id');
            $table->index('title');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_documents');
        Schema::dropIfExists('quality_processes');
    }
};

