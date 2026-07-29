<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paper_exam_attempt_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_exam_attempt_id')
                ->constrained('paper_exam_attempts')
                ->cascadeOnDelete();
            $table->string('event_type', 80);
            $table->timestamp('occurred_at')->useCurrent();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['paper_exam_attempt_id', 'event_type'], 'pea_events_attempt_type_idx');
            $table->index('occurred_at', 'pea_events_occurred_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paper_exam_attempt_events');
    }
};
