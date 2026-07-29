<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Group;
use App\Models\Level;
use App\Models\Modality;
use App\Models\PaperExam;
use App\Models\PaperExamAttempt;
use App\Models\PaperExamQuestion;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StudentExamFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-20 08:00:00'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['teacher', 'student'] as $role) {
            Role::findOrCreate($role);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_student_starts_exam_with_shuffled_sequences(): void
    {
        $scenario = $this->examScenario();

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('student.exams.start', $scenario['exam']))
            ->assertRedirect(route('student.exams.show', $scenario['exam']));

        $attempt = PaperExamAttempt::query()->firstOrFail();

        $this->assertSame('in_progress', $attempt->status);
        $this->assertCount(2, $attempt->question_sequence);
        $this->assertArrayHasKey((string) $scenario['multipleChoice']->id, $attempt->options_sequence);
        $this->assertCount(3, $attempt->options_sequence[(string) $scenario['multipleChoice']->id]);
        $this->assertCount(2, $attempt->exam_payload['questions'] ?? []);
        $this->assertSame($attempt->question_sequence, collect($attempt->exam_payload['questions'])->pluck('id')->all());
    }

    public function test_autosave_persists_answers_without_submitting_attempt(): void
    {
        $scenario = $this->examScenario();
        $attempt = PaperExamAttempt::create([
            'paper_exam_id' => $scenario['exam']->id,
            'student_id' => $scenario['student']->id,
            'attempt_number' => 1,
            'started_at' => now(),
            'status' => 'in_progress',
            'question_sequence' => [$scenario['multipleChoice']->id],
            'options_sequence' => [],
        ]);

        $option = $scenario['multipleChoice']->options()->where('is_correct', true)->firstOrFail();

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->postJson(route('student.exams.autosave', [$scenario['exam'], $attempt]), [
                'answers' => [
                    $scenario['multipleChoice']->id => ['option_id' => $option->id],
                ],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $attempt->refresh();
        $this->assertSame('in_progress', $attempt->status);
        $this->assertSame($option->id, $attempt->autosave_payload[(string) $scenario['multipleChoice']->id]['option_id']);
    }

    public function test_submit_scores_multiple_choice_answer(): void
    {
        $scenario = $this->examScenario();
        $attempt = PaperExamAttempt::create([
            'paper_exam_id' => $scenario['exam']->id,
            'student_id' => $scenario['student']->id,
            'attempt_number' => 1,
            'started_at' => now(),
            'status' => 'in_progress',
            'question_sequence' => [$scenario['multipleChoice']->id],
            'options_sequence' => [],
        ]);

        $option = $scenario['multipleChoice']->options()->where('is_correct', true)->firstOrFail();

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('student.exams.submit', [$scenario['exam'], $attempt]), [
                'answers' => [
                    $scenario['multipleChoice']->id => ['option_id' => $option->id],
                ],
            ])
            ->assertRedirect(route('student.exams.show', $scenario['exam']));

        $attempt->refresh();
        $this->assertSame('submitted', $attempt->status);
        $this->assertEquals(1.0, (float) $attempt->score);
        $this->assertDatabaseHas('paper_exam_attempt_answers', [
            'paper_exam_attempt_id' => $attempt->id,
            'question_id' => $scenario['multipleChoice']->id,
            'is_correct' => true,
        ]);
    }

    public function test_autosave_after_time_expired_saves_answers_and_locks_attempt(): void
    {
        $scenario = $this->examScenario();
        $scenario['exam']->update(['duration_minutes' => 1]);
        $attempt = PaperExamAttempt::create([
            'paper_exam_id' => $scenario['exam']->id,
            'student_id' => $scenario['student']->id,
            'attempt_number' => 1,
            'started_at' => now()->subMinutes(2),
            'status' => 'in_progress',
            'question_sequence' => [$scenario['multipleChoice']->id],
            'options_sequence' => [],
        ]);

        $option = $scenario['multipleChoice']->options()->where('is_correct', true)->firstOrFail();

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->postJson(route('student.exams.autosave', [$scenario['exam'], $attempt]), [
                'answers' => [
                    $scenario['multipleChoice']->id => ['option_id' => $option->id],
                ],
            ])
            ->assertStatus(409)
            ->assertJson([
                'ok' => false,
                'status' => 'locked',
                'reason' => 'time_expired',
            ]);

        $attempt->refresh();
        $this->assertSame('locked', $attempt->status);
        $this->assertSame('time_expired', $attempt->lock_reason);
        $this->assertEquals(1.0, (float) $attempt->score);
        $this->assertEquals(2.0, (float) $attempt->max_score);
        $this->assertDatabaseHas('paper_exam_attempt_answers', [
            'paper_exam_attempt_id' => $attempt->id,
            'question_id' => $scenario['multipleChoice']->id,
            'is_correct' => true,
        ]);
        $this->assertDatabaseHas('paper_exam_attempt_events', [
            'paper_exam_attempt_id' => $attempt->id,
            'event_type' => 'time_expired_autosave',
        ]);
    }

    public function test_lock_endpoint_saves_answers_and_records_reason(): void
    {
        $scenario = $this->examScenario();
        $attempt = PaperExamAttempt::create([
            'paper_exam_id' => $scenario['exam']->id,
            'student_id' => $scenario['student']->id,
            'attempt_number' => 1,
            'started_at' => now(),
            'status' => 'in_progress',
            'question_sequence' => [$scenario['multipleChoice']->id],
            'options_sequence' => [],
        ]);

        $option = $scenario['multipleChoice']->options()->where('is_correct', false)->firstOrFail();

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->postJson(route('student.exams.lock', [$scenario['exam'], $attempt]), [
                'reason' => 'app_switch',
                'answers' => [
                    $scenario['multipleChoice']->id => ['option_id' => $option->id],
                ],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $attempt->refresh();
        $this->assertSame('locked', $attempt->status);
        $this->assertSame('app_switch', $attempt->lock_reason);
        $this->assertEquals(0.0, (float) $attempt->score);
        $this->assertDatabaseHas('paper_exam_attempt_answers', [
            'paper_exam_attempt_id' => $attempt->id,
            'question_id' => $scenario['multipleChoice']->id,
            'is_correct' => false,
        ]);
        $this->assertDatabaseHas('paper_exam_attempt_events', [
            'paper_exam_attempt_id' => $attempt->id,
            'event_type' => 'lock',
        ]);
    }

    public function test_event_endpoint_records_batched_exam_security_events(): void
    {
        $scenario = $this->examScenario();
        $attempt = PaperExamAttempt::create([
            'paper_exam_id' => $scenario['exam']->id,
            'student_id' => $scenario['student']->id,
            'attempt_number' => 1,
            'started_at' => now(),
            'status' => 'in_progress',
            'question_sequence' => [$scenario['multipleChoice']->id],
            'options_sequence' => [],
        ]);

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->postJson(route('student.exams.event', [$scenario['exam'], $attempt]), [
                'events' => [
                    ['event_type' => 'visibility_hidden', 'metadata' => ['source' => 'visibilitychange']],
                    ['event_type' => 'window_blur', 'metadata' => ['source' => 'blur']],
                ],
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'count' => 2]);

        $this->assertDatabaseHas('paper_exam_attempt_events', [
            'paper_exam_attempt_id' => $attempt->id,
            'event_type' => 'visibility_hidden',
        ]);
        $this->assertDatabaseHas('paper_exam_attempt_events', [
            'paper_exam_attempt_id' => $attempt->id,
            'event_type' => 'window_blur',
        ]);
    }

    private function examScenario(): array
    {
        $campus = Campus::create(['name' => 'Florida', 'code' => 'FLORIDA', 'is_active' => true]);
        $modality = Modality::create(['name' => 'PREPARATORIA', 'is_active' => true]);
        $level = Level::create(['modality_id' => $modality->id, 'name' => 'Quinto', 'is_active' => true]);
        $group = Group::create(['level_id' => $level->id, 'name' => '5005', 'capacity' => 30, 'is_active' => true]);
        $cycle = SchoolCycle::create([
            'campus_id' => $campus->id,
            'modality_id' => $modality->id,
            'name' => 'Preparatoria 2026-2027',
            'code' => 'PREPA-TEST',
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonths(10)->toDateString(),
            'is_active' => true,
        ]);
        $cycleGroup = SchoolCycleGroup::create([
            'school_cycle_id' => $cycle->id,
            'group_id' => $group->id,
            'campus_id' => $campus->id,
            'modality_id' => $modality->id,
            'section_count' => 1,
            'is_active' => true,
        ]);
        $subject = Subject::create([
            'level_id' => $level->id,
            'name' => 'Quimica III',
            'hours_per_week' => 3,
            'type' => Subject::TYPE_THEORETICAL,
            'is_active' => true,
        ]);
        $teacherUser = User::factory()->create(['default_campus_id' => $campus->id]);
        $teacherUser->assignRole('teacher');
        $teacherUser->campuses()->sync([$campus->id]);
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'is_active' => true]);
        $studentUser = User::factory()->create(['default_campus_id' => $campus->id]);
        $studentUser->assignRole('student');
        $studentUser->campuses()->sync([$campus->id]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'group_id' => $group->id,
            'enrollment_number' => 'U123456',
            'is_active' => true,
        ]);
        $assignment = TeachingAssignment::create([
            'teacher_id' => $teacher->id,
            'group_id' => $group->id,
            'school_cycle_group_id' => $cycleGroup->id,
            'subject_id' => $subject->id,
            'section_number' => 1,
            'is_active' => true,
        ]);
        $assignment->students()->sync([$student->id]);
        $bank = QuestionBank::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'school_cycle_id' => $cycle->id,
            'name' => 'Banco Quimica',
            'is_active' => true,
        ]);
        $multipleChoice = Question::create([
            'question_bank_id' => $bank->id,
            'type' => 'multiple_choice',
            'prompt' => 'Pregunta de prueba',
            'points' => 1,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        QuestionOption::create(['question_id' => $multipleChoice->id, 'option_text' => 'Correcta', 'is_correct' => true, 'sort_order' => 1]);
        QuestionOption::create(['question_id' => $multipleChoice->id, 'option_text' => 'Incorrecta A', 'is_correct' => false, 'sort_order' => 2]);
        QuestionOption::create(['question_id' => $multipleChoice->id, 'option_text' => 'Incorrecta B', 'is_correct' => false, 'sort_order' => 3]);
        $openQuestion = Question::create([
            'question_bank_id' => $bank->id,
            'type' => 'open',
            'prompt' => 'Pregunta abierta',
            'points' => 1,
            'sort_order' => 2,
            'is_active' => true,
        ]);
        $exam = PaperExam::create([
            'created_by' => $teacherUser->id,
            'teaching_assignment_id' => $assignment->id,
            'school_cycle_id' => $cycle->id,
            'title' => 'Primer parcial',
            'duration_minutes' => 50,
            'is_active' => true,
            'is_online_enabled' => true,
            'online_available_from' => now()->subMinute(),
            'online_available_until' => now()->addHour(),
            'online_max_attempts' => 1,
        ]);
        PaperExamQuestion::create(['paper_exam_id' => $exam->id, 'question_id' => $multipleChoice->id, 'sort_order' => 1]);
        PaperExamQuestion::create(['paper_exam_id' => $exam->id, 'question_id' => $openQuestion->id, 'sort_order' => 2]);

        return compact('campus', 'studentUser', 'student', 'exam', 'multipleChoice', 'openQuestion');
    }
}
