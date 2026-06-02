<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('economic_actas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cycle_partial_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['draft', 'closed', 'sent'])->default('draft');

            $table->foreignId('drafted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('drafted_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();

            $table->string('sent_reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['teaching_assignment_id', 'cycle_partial_id'], 'uniq_economic_acta_assignment_partial');
            $table->index(['cycle_partial_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('economic_actas');
    }
};

