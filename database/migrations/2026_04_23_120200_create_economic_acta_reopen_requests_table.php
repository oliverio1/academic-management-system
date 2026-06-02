<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('economic_acta_reopen_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('economic_acta_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('response_comment')->nullable();
            $table->timestamps();

            $table->index(['economic_acta_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('economic_acta_reopen_requests');
    }
};

