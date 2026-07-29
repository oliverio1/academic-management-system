<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->string('case_number', 30)->unique();
            $table->string('source_type', 30);
            $table->foreignId('source_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('target_type', 30);
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('guardian_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('location')->nullable();
            $table->string('category', 50);
            $table->string('priority', 20)->default('medium');
            $table->string('status', 30)->default('new');
            $table->string('subject', 180);
            $table->text('description');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->text('public_response')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'priority', 'created_at'], 'school_cases_status_priority_idx');
            $table->index(['campus_id', 'status'], 'school_cases_campus_status_idx');
            $table->index(['assigned_to', 'status'], 'school_cases_assigned_status_idx');
            $table->index(['student_id', 'status'], 'school_cases_student_status_idx');
            $table->index(['group_id', 'status'], 'school_cases_group_status_idx');
        });

        Schema::create('school_case_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_case_id')->constrained('school_cases')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entry_type', 30);
            $table->string('visibility', 20)->default('internal');
            $table->text('body');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['school_case_id', 'created_at'], 'case_entries_case_created_idx');
        });

        Schema::create('school_case_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_case_id')->constrained('school_cases')->cascadeOnDelete();
            $table->string('title', 180);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['school_case_id', 'status'], 'case_actions_case_status_idx');
            $table->index(['assigned_to', 'status'], 'case_actions_assigned_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_case_actions');
        Schema::dropIfExists('school_case_entries');
        Schema::dropIfExists('school_cases');
    }
};
