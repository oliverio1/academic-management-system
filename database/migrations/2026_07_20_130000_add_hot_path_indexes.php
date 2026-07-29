<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->index('paper_exams', ['school_cycle_id', 'is_active', 'is_online_enabled'], 'pe_cycle_active_online_idx');
        $this->index('paper_exams', ['teaching_assignment_id', 'school_cycle_id', 'cycle_partial_id'], 'pe_assignment_cycle_partial_idx');
        $this->index('paper_exam_questions', ['paper_exam_id', 'sort_order'], 'peq_exam_sort_idx');
        $this->index('paper_exam_attempts', ['paper_exam_id', 'student_id', 'status'], 'pea_exam_student_status_idx');
        $this->index('paper_exam_attempts', ['status', 'started_at'], 'pea_status_started_idx');
        $this->index('paper_exam_attempt_answers', ['question_id'], 'peaa_question_idx');
        $this->index('questions', ['question_bank_id', 'is_active', 'sort_order'], 'questions_bank_active_sort_idx');
        $this->index('question_banks', ['teacher_id', 'subject_id', 'school_cycle_id', 'is_active'], 'qb_teacher_subject_cycle_active_idx');
        $this->index('attendances', ['student_id', 'status'], 'att_student_status_idx');
        $this->index('academic_sessions', ['teaching_assignment_id', 'session_date'], 'as_assignment_date_idx');
        $this->index('teaching_assignment_student', ['student_id', 'teaching_assignment_id'], 'tas_student_assignment_idx');
        $this->index('teaching_assignments', ['teacher_id', 'subject_id', 'is_active'], 'ta_teacher_subject_active_idx');
        $this->index('teaching_assignments', ['school_cycle_group_id', 'is_active'], 'ta_cycle_group_active_idx');
    }

    public function down(): void
    {
        foreach ([
            ['paper_exams', 'pe_cycle_active_online_idx'],
            ['paper_exams', 'pe_assignment_cycle_partial_idx'],
            ['paper_exam_questions', 'peq_exam_sort_idx'],
            ['paper_exam_attempts', 'pea_exam_student_status_idx'],
            ['paper_exam_attempts', 'pea_status_started_idx'],
            ['paper_exam_attempt_answers', 'peaa_question_idx'],
            ['questions', 'questions_bank_active_sort_idx'],
            ['question_banks', 'qb_teacher_subject_cycle_active_idx'],
            ['attendances', 'att_student_status_idx'],
            ['academic_sessions', 'as_assignment_date_idx'],
            ['teaching_assignment_student', 'tas_student_assignment_idx'],
            ['teaching_assignments', 'ta_teacher_subject_active_idx'],
            ['teaching_assignments', 'ta_cycle_group_active_idx'],
        ] as [$table, $name]) {
            if ($this->indexExists($table, $name)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
            }
        }
    }

    private function index(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
    }

    private function indexExists(string $table, string $name): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        if (DB::getDriverName() === 'mysql') {
            return collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name]))->isNotEmpty();
        }

        $safeTable = str_replace("'", "''", $table);

        return collect(DB::select("PRAGMA index_list('{$safeTable}')"))
            ->contains(fn ($index) => ($index->name ?? null) === $name);
    }
};
