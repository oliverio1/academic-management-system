<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendance_justifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->string('reason'); // enfermedad, trámite, etc.
            $table->string('document_path')->nullable(); // receta / comprobante
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at');
            $table->timestamps();
            $table->index(['student_id', 'from_date', 'to_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_justifications');
    }
};
