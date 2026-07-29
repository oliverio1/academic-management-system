<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Group;
use App\Models\Level;
use App\Models\Modality;
use App\Models\PaperExam;
use App\Models\PaperExamAttempt;
use App\Models\PaperExamAttemptAnswer;
use App\Models\PaperExamAttemptEvent;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Schedule;
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

class TeacherPaperExamGradingFlowTest extends TestCase
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

    public function test_teacher_can_review_submitted_attempt_with_answers_and_security_events(): void
    {
        $scenario = $this->gradingScenario();

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.paper-exams.attempts.review', [$scenario['exam'], $scenario['attempt']]))
            ->assertOk()
            ->assertSee('Respuesta abierta del alumno', false)
            ->assertSee('window_blur', false)
            ->assertSee($scenario['studentUser']->name, false);
    }

    public function test_teacher_grades_open_answer_and_recalculates_attempt_total(): void
    {
        $scenario = $this->gradingScenario();

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->put(route('teacher.paper-exams.attempts.grade', [$scenario['exam'], $scenario['attempt']]), [
                'manual_scores' => [
                    $scenario['openAnswer']->id => '3.50',
                    $scenario['multipleChoiceAnswer']->id => '99',
                    999999 => '10',
                ],
            ])
            ->assertRedirect(route('teacher.paper-exams.show', $scenario['exam']));

        $scenario['attempt']->refresh();
        $scenario['openAnswer']->refresh();
        $scenario['multipleChoiceAnswer']->refresh();

        $this->assertSame('graded', $scenario['attempt']->status);
        $this->assertEquals(3.5, (float) $scenario['openAnswer']->score);
        $this->assertEquals(1.0, (float) $scenario['multipleChoiceAnswer']->score);
        $this->assertEquals(4.5, (float) $scenario['attempt']->score);
        $this->assertEquals(5.0, (float) $scenario['attempt']->max_score);
    }

    public function test_teacher_cannot_grade_open_answer_above_its_maximum(): void
    {
        $scenario = $this->gradingScenario();

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->from(route('teacher.paper-exams.attempts.review', [$scenario['exam'], $scenario['attempt']]))
            ->put(route('teacher.paper-exams.attempts.grade', [$scenario['exam'], $scenario['attempt']]), [
                'manual_scores' => [
                    $scenario['openAnswer']->id => '4.01',
                ],
            ])
            ->assertRedirect(route('teacher.paper-exams.attempts.review', [$scenario['exam'], $scenario['attempt']]))
            ->assertSessionHasErrors("manual_scores.{$scenario['openAnswer']->id}");

        $this->assertNull($scenario['openAnswer']->refresh()->score);
        $this->assertSame('submitted', $scenario['attempt']->refresh()->status);
    }

    public function test_negative_manual_score_is_saved_as_zero(): void
    {
        $scenario = $this->gradingScenario();

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->put(route('teacher.paper-exams.attempts.grade', [$scenario['exam'], $scenario['attempt']]), [
                'manual_scores' => [
                    $scenario['openAnswer']->id => '-2',
                ],
            ])
            ->assertRedirect(route('teacher.paper-exams.show', $scenario['exam']));

        $this->assertEquals(0.0, (float) $scenario['openAnswer']->refresh()->score);
        $this->assertEquals(1.0, (float) $scenario['attempt']->refresh()->score);
    }

    public function test_teacher_cannot_grade_attempt_that_is_still_in_progress(): void
    {
        $scenario = $this->gradingScenario(['attempt_status' => 'in_progress']);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->put(route('teacher.paper-exams.attempts.grade', [$scenario['exam'], $scenario['attempt']]), [
                'manual_scores' => [
                    $scenario['openAnswer']->id => '3',
                ],
            ])
            ->assertStatus(422);

        $this->assertSame('in_progress', $scenario['attempt']->refresh()->status);
    }

    public function test_another_teacher_cannot_review_or_grade_attempt(): void
    {
        $scenario = $this->gradingScenario();
        $otherTeacherUser = User::factory()->create([
            'default_campus_id' => $scenario['campus']->id,
            'name' => 'Profesor ajeno',
        ]);
        $otherTeacherUser->assignRole('teacher');
        $otherTeacherUser->campuses()->sync([$scenario['campus']->id]);
        Teacher::create(['user_id' => $otherTeacherUser->id, 'is_active' => true]);

        $this->actingAs($otherTeacherUser)
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.paper-exams.attempts.review', [$scenario['exam'], $scenario['attempt']]))
            ->assertForbidden();

        $this->actingAs($otherTeacherUser)
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->put(route('teacher.paper-exams.attempts.grade', [$scenario['exam'], $scenario['attempt']]), [
                'manual_scores' => [
                    $scenario['openAnswer']->id => '3',
                ],
            ])
            ->assertForbidden();
    }

    private function gradingScenario(array $overrides = []): array
    {
        $campus = Campus::create(['name' => 'Florida', 'code' => 'FLORIDA', 'is_active' => true]);
        $modality = Modality::create(['name' => 'PREPARATORIA', 'is_active' => true]);
        $level = Level::create(['modality_id' => $modality->id, 'name' => 'Quinto', 'is_active' => true]);
        $group = Group::create(['level_id' => $level->id, 'name' => '5005', 'capacity' => 30, 'is_active' => true]);
        $cycle = SchoolCycle::create([
            'campus_id' => $campus->id,
            'modality_id' => $modality->id,
            'name' => 'Preparatoria 2026-2027',
            'code' => 'PREPA-26-27',
            'start_date' => '2026-08-10',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);
        $cycle->campuses()->sync([$campus->id]);
        $cycle->modalities()->sync([$modality->id]);

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
            'hours_per_week' => 4,
            'type' => Subject::TYPE_THEORETICAL_PRACTICAL,
            'is_active' => true,
        ]);
        $cycleGroup->subjects()->sync([$subject->id]);

        $teacherUser = User::factory()->create([
            'default_campus_id' => $campus->id,
            'name' => 'Profesor Calificador',
        ]);
        $teacherUser->assignRole('teacher');
        $teacherUser->campuses()->sync([$campus->id]);
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'is_active' => true]);

        $studentUser = User::factory()->create([
            'default_campus_id' => $campus->id,
            'name' => 'Alumno Examen',
        ]);
        $studentUser->assignRole('student');
        $studentUser->campuses()->sync([$campus->id]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'group_id' => $group->id,
            'enrollment_number' => 'U555001',
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
        Schedule::create([
            'teaching_assignment_id' => $assignment->id,
            'school_cycle_id' => $cycle->id,
            'section_number' => 1,
            'day_of_week' => 'lunes',
            'start_time' => '07:00:00',
            'end_time' => '07:50:00',
            'type' => 'theory',
            'is_active' => true,
        ]);

        $bank = QuestionBank::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'school_cycle_id' => $cycle->id,
            'name' => 'Banco para calificar',
            'is_active' => true,
        ]);
        $multipleChoice = Question::create([
            'question_bank_id' => $bank->id,
            'type' => 'multiple_choice',
            'prompt' => 'Pregunta automatica',
            'points' => 1,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $correctOption = QuestionOption::create([
            'question_id' => $multipleChoice->id,
            'option_text' => 'Correcta',
            'is_correct' => true,
            'sort_order' => 1,
        ]);
        QuestionOption::create([
            'question_id' => $multipleChoice->id,
            'option_text' => 'Incorrecta',
            'is_correct' => false,
            'sort_order' => 2,
        ]);

        $openQuestion = Question::create([
            'question_bank_id' => $bank->id,
            'type' => 'open',
            'prompt' => 'Explica el fenomeno observado.',
            'points' => 4,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $exam = PaperExam::create([
            'created_by' => $teacherUser->id,
            'teaching_assignment_id' => $assignment->id,
            'school_cycle_id' => $cycle->id,
            'title' => 'Primer parcial - Quimica III - Grupo 5005',
            'duration_minutes' => 50,
            'is_online_enabled' => true,
            'online_available_from' => now()->subMinute(),
            'online_available_until' => now()->addHour(),
            'online_max_attempts' => 1,
            'is_active' => true,
        ]);
        $exam->examQuestions()->create(['question_id' => $multipleChoice->id, 'sort_order' => 1]);
        $exam->examQuestions()->create(['question_id' => $openQuestion->id, 'sort_order' => 2]);

        $attempt = PaperExamAttempt::create([
            'paper_exam_id' => $exam->id,
            'student_id' => $student->id,
            'attempt_number' => 1,
            'started_at' => now()->subMinutes(20),
            'submitted_at' => now()->subMinutes(5),
            'status' => $overrides['attempt_status'] ?? 'submitted',
            'score' => 1,
            'max_score' => 5,
            'question_sequence' => [$multipleChoice->id, $openQuestion->id],
            'options_sequence' => [],
        ]);
        $multipleChoiceAnswer = PaperExamAttemptAnswer::create([
            'paper_exam_attempt_id' => $attempt->id,
            'question_id' => $multipleChoice->id,
            'answer_payload' => ['option_id' => $correctOption->id],
            'is_correct' => true,
            'score' => 1,
            'max_score' => 1,
        ]);
        $openAnswer = PaperExamAttemptAnswer::create([
            'paper_exam_attempt_id' => $attempt->id,
            'question_id' => $openQuestion->id,
            'answer_payload' => ['text' => 'Respuesta abierta del alumno'],
            'is_correct' => null,
            'score' => null,
            'max_score' => 4,
        ]);
        PaperExamAttemptEvent::create([
            'paper_exam_attempt_id' => $attempt->id,
            'event_type' => 'window_blur',
            'occurred_at' => now()->subMinutes(10),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'metadata' => ['source' => 'blur'],
        ]);

        return compact(
            'campus',
            'teacherUser',
            'studentUser',
            'student',
            'exam',
            'attempt',
            'multipleChoiceAnswer',
            'openAnswer'
        );
    }
}
