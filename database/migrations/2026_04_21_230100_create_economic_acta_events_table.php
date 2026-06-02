<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('economic_acta_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('economic_acta_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['economic_acta_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('economic_acta_events');
    }
};

