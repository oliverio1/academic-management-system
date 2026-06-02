<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_charges', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('concept_id')->constrained('finance_concepts')->restrictOnDelete();
            $table->string('reference')->nullable()->index();
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->date('due_date')->index();
            $table->dateTime('issued_at')->nullable();
            $table->enum('status', ['pending', 'partial', 'paid', 'cancelled'])->default('pending')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_charges');
    }
};

