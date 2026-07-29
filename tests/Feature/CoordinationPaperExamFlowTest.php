<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\CyclePartial;
use App\Models\Group;
use App\Models\Level;
use App\Models\Modality;
use App\Models\PaperExam;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Schedule;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CoordinationPaperExamFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId = 'florida';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-20 08:00:00'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['coordinator', 'teacher', 'student'] as $role) {
            Role::findOrCreate($role);
        }

        $this->createTenantForCurrentAppUrl();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_coordination_sees_only_active_campus_cycle_exams(): void
    {
        $scenario = $this->coordinationScenario();
        $otherCampusExam = $this->examForOtherCampus();

        $response = $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('coordination.paper-exams.index'))
            ->assertOk();

        $response->assertViewHas('exams', function ($exams) use ($scenario, $otherCampusExam) {
            return $exams->pluck('id')->contains($scenario['existingExam']->id)
                && ! $exams->pluck('id')->contains($otherCampusExam->id);
        });
    }

    public function test_coordination_creates_exam_with_questions_from_matching_cycle_and_subject(): void
    {
        $scenario = $this->coordinationScenario();

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('coordination.paper-exams.store'), [
                'title' => 'Examen creado por coordinacion',
                'instructions' => 'Lee con cuidado.',
                'duration_minutes' => 45,
                'is_online_enabled' => '1',
                'online_available_from' => '2026-09-01 07:00:00',
                'online_available_until' => '2026-09-01 07:45:00',
                'online_max_attempts' => 1,
                'online_show_result' => '1',
                'teaching_assignment_id' => $scenario['assignment']->id,
                'cycle_partial_id' => $scenario['partial']->id,
                'question_ids' => [$scenario['questionA']->id, $scenario['questionB']->id],
            ])
            ->assertRedirect();

        $exam = PaperExam::query()
            ->where('title', 'Examen creado por coordinacion')
            ->with('examQuestions')
            ->firstOrFail();

        $this->assertSame($scenario['assignment']->id, $exam->teaching_assignment_id);
        $this->assertSame($scenario['cycle']->id, $exam->school_cycle_id);
        $this->assertSame($scenario['partial']->id, $exam->cycle_partial_id);
        $this->assertTrue($exam->is_online_enabled);
        $this->assertTrue($exam->online_show_result);
        $this->assertSame(
            [$scenario['questionA']->id, $scenario['questionB']->id],
            $exam->examQuestions->pluck('question_id')->map(fn ($id) => (int) $id)->all()
        );
    }

    public function test_coordination_cannot_create_exam_with_question_from_other_subject_or_cycle(): void
    {
        $scenario = $this->coordinationScenario();

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->from(route('coordination.paper-exams.create'))
            ->post(route('coordination.paper-exams.store'), [
                'title' => 'Examen invalido',
                'duration_minutes' => 45,
                'teaching_assignment_id' => $scenario['assignment']->id,
                'cycle_partial_id' => $scenario['partial']->id,
                'question_ids' => [$scenario['questionA']->id, $scenario['otherSubjectQuestion']->id],
            ])
            ->assertRedirect(route('coordination.paper-exams.create'))
            ->assertSessionHasErrors('question_ids');

        $this->assertDatabaseMissing('paper_exams', ['title' => 'Examen invalido']);
    }

    public function test_coordination_schedules_exam_on_valid_class_day_and_reuses_subject_exam(): void
    {
        $scenario = $this->coordinationScenario();

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->put(route('coordination.paper-exams.schedule.update'), [
                'schedule_id' => $scenario['mondaySchedule']->id,
                'exam_date' => '2026-09-07',
            ])
            ->assertRedirect(route('coordination.paper-exams.schedule', ['cycle_group_id' => $scenario['cycleGroup']->id]));

        $exam = PaperExam::query()
            ->where('school_cycle_id', $scenario['cycle']->id)
            ->where('cycle_partial_id', $scenario['partial']->id)
            ->whereHas('assignment', fn ($query) => $query->where('subject_id', $scenario['subject']->id))
            ->orderByDesc('id')
            ->firstOrFail();

        $this->assertSame($scenario['assignment']->id, $exam->teaching_assignment_id);
        $this->assertSame('2026-09-07 07:00:00', $exam->online_available_from->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-07 07:50:00', $exam->online_available_until->format('Y-m-d H:i:s'));
        $this->assertSame(50, (int) $exam->duration_minutes);
        $this->assertStringContainsString('Primer parcial - Quimica III - Grupo 5005', $exam->title);

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->put(route('coordination.paper-exams.schedule.update'), [
                'schedule_id' => $scenario['mondaySchedule']->id,
                'exam_date' => '2026-09-14',
            ])
            ->assertRedirect(route('coordination.paper-exams.schedule', ['cycle_group_id' => $scenario['cycleGroup']->id]));

        $this->assertSame(1, PaperExam::query()
            ->where('school_cycle_id', $scenario['cycle']->id)
            ->where('cycle_partial_id', $scenario['partial']->id)
            ->whereHas('assignment', fn ($query) => $query->where('subject_id', $scenario['subject']->id))
            ->count());
        $this->assertSame('2026-09-14 07:00:00', $exam->refresh()->online_available_from->format('Y-m-d H:i:s'));
    }

    public function test_coordination_cannot_schedule_exam_on_day_without_that_subject(): void
    {
        $scenario = $this->coordinationScenario();

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->from(route('coordination.paper-exams.schedule', ['cycle_group_id' => $scenario['cycleGroup']->id]))
            ->put(route('coordination.paper-exams.schedule.update'), [
                'schedule_id' => $scenario['mondaySchedule']->id,
                'exam_date' => '2026-09-09',
            ])
            ->assertRedirect(route('coordination.paper-exams.schedule', ['cycle_group_id' => $scenario['cycleGroup']->id]))
            ->assertSessionHasErrors('exam_date');

        $this->assertDatabaseMissing('paper_exams', [
            'title' => 'Primer parcial - Quimica III - Grupo 5005 - 09/09/2026',
        ]);
    }

    public function test_coordination_cannot_schedule_exam_for_another_active_campus(): void
    {
        $scenario = $this->coordinationScenario();
        $otherCampusExam = $this->examForOtherCampus();
        $otherSchedule = $otherCampusExam->assignment->schedules()->firstOrFail();

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->put(route('coordination.paper-exams.schedule.update'), [
                'schedule_id' => $otherSchedule->id,
                'exam_date' => '2026-09-07',
            ])
            ->assertForbidden();
    }

    public function test_coordination_updates_online_settings_with_valid_window(): void
    {
        $scenario = $this->coordinationScenario();

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->put(route('coordination.paper-exams.online.update', $scenario['existingExam']), [
                'is_online_enabled' => '1',
                'online_available_from' => '2026-09-01 08:00:00',
                'online_available_until' => '2026-09-01 08:50:00',
                'online_max_attempts' => 2,
                'online_show_result' => '1',
            ])
            ->assertRedirect(route('coordination.paper-exams.show', $scenario['existingExam']));

        $scenario['existingExam']->refresh();
        $this->assertTrue($scenario['existingExam']->is_online_enabled);
        $this->assertTrue($scenario['existingExam']->online_show_result);
        $this->assertSame(2, (int) $scenario['existingExam']->online_max_attempts);
        $this->assertSame('2026-09-01 08:00:00', $scenario['existingExam']->online_available_from->format('Y-m-d H:i:s'));
    }

    public function test_coordination_pdf_download_renders_exam_when_binary_exists(): void
    {
        $this->skipIfWkhtmltopdfIsMissing();
        $scenario = $this->coordinationScenario();

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('coordination.paper-exams.pdf', $scenario['existingExam']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function coordinationScenario(): array
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
            'tenant_id' => $this->tenantId,
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
        $otherSubject = Subject::create([
            'level_id' => $level->id,
            'name' => 'Geografia',
            'hours_per_week' => 3,
            'type' => Subject::TYPE_THEORETICAL,
            'is_active' => true,
        ]);
        $cycleGroup->subjects()->sync([$subject->id, $otherSubject->id]);

        $coordinator = $this->userWithRole('coordinator', $campus, 'Coordinacion Examenes');
        $teacherUser = $this->userWithRole('teacher', $campus, 'Profesor Quimica');
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'is_active' => true]);

        $assignment = TeachingAssignment::create([
            'tenant_id' => $this->tenantId,
            'teacher_id' => $teacher->id,
            'group_id' => $group->id,
            'school_cycle_group_id' => $cycleGroup->id,
            'subject_id' => $subject->id,
            'section_number' => 1,
            'is_active' => true,
        ]);
        $mondaySchedule = $this->schedule($assignment, $cycle, 'monday', '07:00:00', '07:50:00');
        $this->schedule($assignment, $cycle, 'thursday', '08:00:00', '08:50:00');

        $otherAssignment = TeachingAssignment::create([
            'tenant_id' => $this->tenantId,
            'teacher_id' => $teacher->id,
            'group_id' => $group->id,
            'school_cycle_group_id' => $cycleGroup->id,
            'subject_id' => $otherSubject->id,
            'section_number' => 1,
            'is_active' => true,
        ]);
        $this->schedule($otherAssignment, $cycle, 'wednesday', '09:00:00', '09:50:00');

        $partial = CyclePartial::create([
            'school_cycle_id' => $cycle->id,
            'name' => 'Primer parcial',
            'code' => 'P1',
            'sort_order' => 1,
            'start_date' => '2026-08-10',
            'end_date' => '2026-10-10',
            'is_active' => true,
        ]);

        $bank = QuestionBank::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'school_cycle_id' => $cycle->id,
            'cycle_partial_id' => $partial->id,
            'name' => 'Banco Quimica',
            'is_active' => true,
        ]);
        $questionA = $this->multipleChoice($bank, 'Pregunta A', 1);
        $questionB = $this->multipleChoice($bank, 'Pregunta B', 2);

        $otherBank = QuestionBank::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $otherSubject->id,
            'school_cycle_id' => $cycle->id,
            'cycle_partial_id' => $partial->id,
            'name' => 'Banco Geografia',
            'is_active' => true,
        ]);
        $otherSubjectQuestion = $this->multipleChoice($otherBank, 'Pregunta de otra materia', 1);

        $existingExam = PaperExam::create([
            'created_by' => $coordinator->id,
            'teaching_assignment_id' => $assignment->id,
            'school_cycle_id' => $cycle->id,
            'cycle_partial_id' => $partial->id,
            'title' => 'Examen existente',
            'instructions' => 'Contesta todo.',
            'duration_minutes' => 50,
            'is_online_enabled' => false,
            'online_max_attempts' => 1,
            'is_active' => true,
        ]);
        $existingExam->examQuestions()->create(['question_id' => $questionA->id, 'sort_order' => 1]);

        return compact(
            'campus',
            'coordinator',
            'cycle',
            'cycleGroup',
            'subject',
            'partial',
            'assignment',
            'mondaySchedule',
            'questionA',
            'questionB',
            'otherSubjectQuestion',
            'existingExam'
        );
    }

    private function examForOtherCampus(): PaperExam
    {
        $campus = Campus::create(['name' => 'Campus Ajeno', 'code' => 'AJENO', 'is_active' => true]);
        $modality = Modality::query()->firstOrCreate(['name' => 'BACHILLERATO'], ['is_active' => true]);
        $level = Level::create(['modality_id' => $modality->id, 'name' => 'Sexto Ajeno', 'is_active' => true]);
        $group = Group::create(['level_id' => $level->id, 'name' => '6001', 'capacity' => 30, 'is_active' => true]);
        $cycle = SchoolCycle::create([
            'campus_id' => $campus->id,
            'modality_id' => $modality->id,
            'name' => 'Bachillerato Ajeno',
            'code' => 'BACH-AJENO',
            'start_date' => '2026-08-10',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);
        $cycle->campuses()->sync([$campus->id]);
        $cycle->modalities()->sync([$modality->id]);
        $cycleGroup = SchoolCycleGroup::create([
            'tenant_id' => $this->tenantId,
            'school_cycle_id' => $cycle->id,
            'group_id' => $group->id,
            'campus_id' => $campus->id,
            'modality_id' => $modality->id,
            'section_count' => 1,
            'is_active' => true,
        ]);
        $subject = Subject::create([
            'level_id' => $level->id,
            'name' => 'Materia Ajena',
            'hours_per_week' => 3,
            'type' => Subject::TYPE_THEORETICAL,
            'is_active' => true,
        ]);
        $teacherUser = $this->userWithRole('teacher', $campus, 'Profesor Ajeno');
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'is_active' => true]);
        $assignment = TeachingAssignment::create([
            'tenant_id' => $this->tenantId,
            'teacher_id' => $teacher->id,
            'group_id' => $group->id,
            'school_cycle_group_id' => $cycleGroup->id,
            'subject_id' => $subject->id,
            'section_number' => 1,
            'is_active' => true,
        ]);
        $this->schedule($assignment, $cycle, 'monday', '07:00:00', '07:50:00');

        return PaperExam::create([
            'created_by' => $teacherUser->id,
            'teaching_assignment_id' => $assignment->id,
            'school_cycle_id' => $cycle->id,
            'title' => 'Examen otro campus',
            'duration_minutes' => 50,
            'is_online_enabled' => true,
            'online_available_from' => now()->addDay(),
            'online_available_until' => now()->addDay()->addMinutes(50),
            'online_max_attempts' => 1,
            'is_active' => true,
        ]);
    }

    private function schedule(TeachingAssignment $assignment, SchoolCycle $cycle, string $day, string $start, string $end): Schedule
    {
        return Schedule::create([
            'tenant_id' => $this->tenantId,
            'teaching_assignment_id' => $assignment->id,
            'school_cycle_id' => $cycle->id,
            'section_number' => $assignment->section_number,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
            'type' => 'theory',
            'is_active' => true,
        ]);
    }

    private function multipleChoice(QuestionBank $bank, string $prompt, int $sortOrder): Question
    {
        $question = Question::create([
            'question_bank_id' => $bank->id,
            'type' => 'multiple_choice',
            'prompt' => $prompt,
            'points' => 1,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
        QuestionOption::create(['question_id' => $question->id, 'option_text' => 'Correcta', 'is_correct' => true, 'sort_order' => 1]);
        QuestionOption::create(['question_id' => $question->id, 'option_text' => 'Incorrecta', 'is_correct' => false, 'sort_order' => 2]);

        return $question;
    }

    private function userWithRole(string $role, Campus $campus, string $name): User
    {
        $user = User::factory()->create([
            'default_campus_id' => $campus->id,
            'name' => $name,
        ]);
        $user->assignRole($role);
        $user->campuses()->sync([$campus->id]);

        return $user;
    }

    private function createTenantForCurrentAppUrl(): void
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'gestion-escolar.text';
        $tenant = Tenant::query()->firstOrCreate(['id' => $this->tenantId]);

        if (! $tenant->domains()->where('domain', $host)->exists()) {
            $tenant->domains()->create(['domain' => $host]);
        }
    }

    private function skipIfWkhtmltopdfIsMissing(): void
    {
        $binary = trim((string) config('snappy.pdf.binary'), '"');
        if ($binary === '' || ! is_file($binary)) {
            $this->markTestSkipped('wkhtmltopdf no esta disponible en este entorno.');
        }
    }
}
