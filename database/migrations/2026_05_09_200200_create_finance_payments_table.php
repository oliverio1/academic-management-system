<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_payments', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->dateTime('payment_date');
            $table->decimal('amount', 10, 2);
            $table->enum('method', ['cash', 'transfer', 'card', 'other'])->default('cash');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['applied', 'partially_applied', 'unapplied', 'cancelled'])->default('unapplied')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_payments');
    }
};

