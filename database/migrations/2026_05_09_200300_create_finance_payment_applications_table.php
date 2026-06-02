<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_payment_applications', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('payment_id')->constrained('finance_payments')->cascadeOnDelete();
            $table->foreignId('charge_id')->constrained('finance_charges')->cascadeOnDelete();
            $table->decimal('applied_amount', 10, 2);
            $table->dateTime('applied_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_payment_applications');
    }
};

