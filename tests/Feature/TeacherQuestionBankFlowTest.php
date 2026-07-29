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
use App\Models\TeacherDocumentRequestItem;
use App\Models\TeachingAssignment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TeacherQuestionBankFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-20 08:00:00'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('teacher');
        Role::findOrCreate('coordinator');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_teacher_creates_question_with_support_text_and_optimized_image(): void
    {
        Storage::fake('public');
        $scenario = $this->questionBankScenario();

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('teacher.question-banks.questions.store', $scenario['bank']), [
                'type' => 'multiple_choice',
                'prompt' => 'La imagen representa el teorema de Torricelli?',
                'points' => 1,
                'support_title' => 'Material de apoyo',
                'support_text' => str_repeat('Texto largo de apoyo para interpretar la imagen. ', 30),
                'support_image' => UploadedFile::fake()->image('torricelli.png', 900, 600),
                'options' => [
                    ['text' => 'Si', 'is_correct' => '1'],
                    ['text' => 'No', 'is_correct' => '0'],
                    ['text' => 'No corresponde', 'is_correct' => '0'],
                ],
            ])
            ->assertRedirect(route('teacher.question-banks.show', $scenario['bank']));

        $question = Question::query()
            ->where('question_bank_id', $scenario['bank']->id)
            ->where('prompt', 'La imagen representa el teorema de Torricelli?')
            ->with('options')
            ->firstOrFail();

        $this->assertSame('Material de apoyo', $question->meta['support_title']);
        $this->assertStringContainsString('Texto largo de apoyo', $question->meta['support_text']);
        $this->assertNotEmpty($question->meta['support_image_path']);
        Storage::disk('public')->assertExists($question->meta['support_image_path']);
        $this->assertCount(3, $question->options);
        $this->assertTrue((bool) $question->options->firstWhere('option_text', 'Si')->is_correct);
    }

    public function test_teacher_configures_bank_questions_for_all_current_groups_without_section_duplicates(): void
    {
        $scenario = $this->questionBankScenario();
        $questionA = $this->multipleChoice($scenario['bank'], 'Pregunta A', 1);
        $questionB = $this->multipleChoice($scenario['bank'], 'Pregunta B', 2);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->put(route('teacher.question-banks.exam.update', $scenario['bank']), [
                'exam_target' => 'new:' . $scenario['sectionAAssignment']->id,
                'apply_scope' => 'all_groups',
                'question_ids' => [$questionA->id, $questionB->id],
            ])
            ->assertRedirect(route('teacher.question-banks.exam.configure', $scenario['bank']));

        $exams = PaperExam::query()
            ->where('school_cycle_id', $scenario['cycle']->id)
            ->where('cycle_partial_id', $scenario['partial']->id)
            ->with(['assignment.group', 'examQuestions'])
            ->get();

        $this->assertCount(2, $exams);
        $this->assertEqualsCanonicalizing(['5003', '5005'], $exams->pluck('assignment.group.name')->all());

        foreach ($exams as $exam) {
            $this->assertSame(
                [$questionA->id, $questionB->id],
                $exam->examQuestions->pluck('question_id')->map(fn ($id) => (int) $id)->all()
            );
        }
    }

    public function test_configured_teacher_exam_marks_partial_document_as_delivered_without_coordination_date(): void
    {
        $scenario = $this->questionBankScenario();
        $questionA = $this->multipleChoice($scenario['bank'], 'Pregunta A', 1);
        $questionB = $this->multipleChoice($scenario['bank'], 'Pregunta B', 2);

        $documentTypes = TeacherDocumentRequestItem::query()
            ->where('teaching_assignment_id', (int) $scenario['sectionAAssignment']->id)
            ->where('document_type', 'like', 'examen_parcial_%')
            ->pluck('document_type')
            ->all();

        $this->assertEqualsCanonicalizing([
            'examen_parcial_1',
            'examen_parcial_2',
            'examen_parcial_3',
            'examen_parcial_4',
        ], $documentTypes);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->put(route('teacher.question-banks.exam.update', $scenario['bank']), [
                'exam_target' => 'new:' . $scenario['sectionAAssignment']->id,
                'question_ids' => [$questionA->id, $questionB->id],
            ])
            ->assertRedirect(route('teacher.question-banks.exam.configure', $scenario['bank']));

        $exam = PaperExam::query()
            ->where('teaching_assignment_id', (int) $scenario['sectionAAssignment']->id)
            ->where('cycle_partial_id', (int) $scenario['partial']->id)
            ->firstOrFail();

        $this->assertNull($exam->online_available_from);
        $this->assertNull($exam->online_available_until);
        $this->assertSame(2, $exam->examQuestions()->count());

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.document-requests.index'))
            ->assertOk()
            ->assertSee('Examen primer parcial')
            ->assertSee('Examen configurado (2 pregunta(s))')
            ->assertSee('Entregado');

        $coordinator = User::factory()->create(['default_campus_id' => $scenario['campus']->id]);
        $coordinator->assignRole('coordinator');
        $coordinator->campuses()->sync([$scenario['campus']->id]);

        $this->actingAs($coordinator)
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('coordination.teacher-documents.teachers.show', $scenario['teacher']))
            ->assertOk()
            ->assertSee('Ver PDF')
            ->assertDontSee('Examen configurado (2 pregunta(s))');
    }

    public function test_teacher_dashboard_counts_real_pending_document_items(): void
    {
        $scenario = $this->questionBankScenario();
        $questionA = $this->multipleChoice($scenario['bank'], 'Pregunta A', 1);
        $questionB = $this->multipleChoice($scenario['bank'], 'Pregunta B', 2);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->put(route('teacher.question-banks.exam.update', $scenario['bank']), [
                'exam_target' => 'new:' . $scenario['sectionAAssignment']->id,
                'question_ids' => [$questionA->id, $questionB->id],
            ])
            ->assertRedirect(route('teacher.question-banks.exam.configure', $scenario['bank']));

        $rawPendingItems = TeacherDocumentRequestItem::query()
            ->whereHas('assignment', fn ($query) => $query->where('teacher_id', (int) $scenario['teacher']->id))
            ->whereHas('request', fn ($query) => $query->where('status', 'open'))
            ->whereDoesntHave('submissions')
            ->count();

        $response = $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tienes 19 documento(s) pendiente(s) por entregar.');

        $this->assertGreaterThan($response->viewData('pendingDocumentItems'), $rawPendingItems);
        $this->assertSame(19, $response->viewData('pendingDocumentItems'));
    }

    public function test_question_bank_index_filters_by_selected_partial(): void
    {
        $scenario = $this->questionBankScenario();
        $this->multipleChoice($scenario['bank'], 'Pregunta primer parcial', 1);

        $secondPartialBank = QuestionBank::create([
            'teacher_id' => $scenario['teacher']->id,
            'subject_id' => $scenario['subject']->id,
            'school_cycle_id' => $scenario['cycle']->id,
            'cycle_partial_id' => $scenario['secondPartial']->id,
            'name' => 'Banco segundo parcial',
            'is_active' => true,
        ]);
        $this->multipleChoice($secondPartialBank, 'Pregunta segundo parcial', 1);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.question-banks.index', ['cycle_partial_id' => $scenario['partial']->id]))
            ->assertOk()
            ->assertSee('Banco Quimica III')
            ->assertSee('Primer parcial')
            ->assertDontSee('Banco segundo parcial')
            ->assertDontSee('Pregunta segundo parcial');
    }

    public function test_teacher_updates_question_bank_partial_before_exam_is_configured(): void
    {
        $scenario = $this->questionBankScenario();

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->put(route('teacher.question-banks.update', $scenario['bank']), [
                'name' => 'Banco Quimica Segundo Parcial',
                'description' => 'Preguntas del segundo periodo.',
                'subject_id' => $scenario['subject']->id,
                'school_cycle_id' => $scenario['cycle']->id,
                'cycle_partial_id' => $scenario['secondPartial']->id,
            ])
            ->assertRedirect(route('teacher.question-banks.show', $scenario['bank']));

        $scenario['bank']->refresh();
        $this->assertSame('Banco Quimica Segundo Parcial', $scenario['bank']->name);
        $this->assertSame((int) $scenario['secondPartial']->id, (int) $scenario['bank']->cycle_partial_id);
    }

    public function test_teacher_downloads_contextual_import_template_for_bank_partial(): void
    {
        $scenario = $this->questionBankScenario();

        $response = $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.question-banks.template.download-for-bank', $scenario['bank']));

        $response->assertOk();

        $path = storage_path('framework/testing/question-bank-template.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        copy($response->baseResponse->getFile()->getPathname(), $path);

        $spreadsheet = IOFactory::load($path);
        $context = $spreadsheet->getSheetByName('Contexto');

        $this->assertNotNull($context);
        $this->assertSame('Quimica III', $context->getCell('B2')->getValue());
        $this->assertSame('Primer parcial', $context->getCell('B4')->getValue());
    }

    public function test_teacher_cannot_bulk_import_questions_until_bank_has_partial(): void
    {
        $scenario = $this->questionBankScenario();
        $scenario['bank']->update(['cycle_partial_id' => null]);

        $file = UploadedFile::fake()->create('preguntas.xlsx', 12, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('teacher.question-banks.import', $scenario['bank']), [
                'file' => $file,
            ])
            ->assertRedirect(route('teacher.question-banks.edit', $scenario['bank']))
            ->assertSessionHasErrors('cycle_partial_id');
    }

    public function test_teacher_exam_question_editor_reuses_previous_cycle_bank_for_same_subject_and_modality(): void
    {
        $scenario = $this->questionBankScenario();
        $currentQuestion = $this->multipleChoice($scenario['bank'], 'Pregunta ciclo actual', 1);
        $previousBank = QuestionBank::create([
            'teacher_id' => $scenario['teacher']->id,
            'subject_id' => $scenario['subject']->id,
            'school_cycle_id' => $scenario['previousCycle']->id,
            'cycle_partial_id' => null,
            'name' => 'Banco reutilizable ciclo anterior',
            'is_active' => true,
        ]);
        $previousQuestion = $this->multipleChoice($previousBank, 'Pregunta reutilizable', 1);

        $otherModalityBank = QuestionBank::create([
            'teacher_id' => $scenario['teacher']->id,
            'subject_id' => $scenario['otherModalitySubject']->id,
            'school_cycle_id' => $scenario['otherModalityCycle']->id,
            'cycle_partial_id' => null,
            'name' => 'Banco de otra modalidad',
            'is_active' => true,
        ]);
        $this->multipleChoice($otherModalityBank, 'Pregunta que no debe aparecer', 1);

        $exam = PaperExam::create([
            'created_by' => $scenario['teacherUser']->id,
            'teaching_assignment_id' => $scenario['sectionAAssignment']->id,
            'school_cycle_id' => $scenario['cycle']->id,
            'cycle_partial_id' => $scenario['partial']->id,
            'title' => 'Primer parcial - Quimica III - Grupo 5005',
            'duration_minutes' => 50,
            'is_online_enabled' => false,
            'online_max_attempts' => 1,
            'is_active' => true,
        ]);
        $exam->examQuestions()->create(['question_id' => $currentQuestion->id, 'sort_order' => 1]);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.paper-exams.questions.edit', $exam))
            ->assertOk()
            ->assertViewHas('banks', function ($banks) use ($scenario, $previousBank, $otherModalityBank) {
                return $banks->pluck('id')->contains($scenario['bank']->id)
                    && $banks->pluck('id')->contains($previousBank->id)
                    && ! $banks->pluck('id')->contains($otherModalityBank->id);
            });

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->put(route('teacher.paper-exams.questions.update', $exam), [
                'question_ids' => [$currentQuestion->id, $previousQuestion->id],
            ])
            ->assertRedirect(route('teacher.paper-exams.show', $exam));

        $this->assertSame(
            [$currentQuestion->id, $previousQuestion->id],
            $exam->refresh()->examQuestions->pluck('question_id')->map(fn ($id) => (int) $id)->all()
        );
    }

    public function test_teacher_cannot_configure_exam_with_question_from_another_bank(): void
    {
        $scenario = $this->questionBankScenario();
        $ownQuestion = $this->multipleChoice($scenario['bank'], 'Pregunta propia', 1);
        $otherBank = QuestionBank::create([
            'teacher_id' => $scenario['teacher']->id,
            'subject_id' => $scenario['subject']->id,
            'school_cycle_id' => $scenario['cycle']->id,
            'cycle_partial_id' => $scenario['partial']->id,
            'name' => 'Otro banco',
            'is_active' => true,
        ]);
        $foreignQuestion = $this->multipleChoice($otherBank, 'Pregunta de otro banco', 1);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->from(route('teacher.question-banks.exam.configure', $scenario['bank']))
            ->put(route('teacher.question-banks.exam.update', $scenario['bank']), [
                'exam_target' => 'new:' . $scenario['sectionAAssignment']->id,
                'question_ids' => [$ownQuestion->id, $foreignQuestion->id],
            ])
            ->assertRedirect(route('teacher.question-banks.exam.configure', $scenario['bank']))
            ->assertSessionHasErrors('question_ids');

        $this->assertDatabaseCount('paper_exams', 0);
    }

    private function questionBankScenario(): array
    {
        $this->createTenantForCurrentAppUrl();

        $campus = Campus::create(['name' => 'Florida', 'code' => 'FLORIDA', 'is_active' => true]);
        $modality = Modality::create(['name' => 'PREPARATORIA', 'is_active' => true]);
        $otherModality = Modality::create(['name' => 'BACHILLERATO', 'is_active' => true]);
        $level = Level::create(['modality_id' => $modality->id, 'name' => 'Quinto', 'is_active' => true]);
        $otherLevel = Level::create(['modality_id' => $otherModality->id, 'name' => 'Quinto Bach', 'is_active' => true]);

        $group5005 = Group::create(['level_id' => $level->id, 'name' => '5005', 'capacity' => 30, 'is_active' => true]);
        $group5003 = Group::create(['level_id' => $level->id, 'name' => '5003', 'capacity' => 30, 'is_active' => true]);

        $cycle = $this->cycle($campus, $modality, 'Preparatoria 2026-2027', 'PREPA-26-27', true);
        $previousCycle = $this->cycle($campus, $modality, 'Preparatoria 2025-2026', 'PREPA-25-26', false);
        $otherModalityCycle = $this->cycle($campus, $otherModality, 'Bachillerato 2026-3', 'BACH-26-3', false);

        $cycleGroup5005 = $this->cycleGroup($campus, $modality, $cycle, $group5005, 2);
        $cycleGroup5003 = $this->cycleGroup($campus, $modality, $cycle, $group5003, 1);

        $subject = Subject::create([
            'level_id' => $level->id,
            'name' => 'Quimica III',
            'hours_per_week' => 4,
            'type' => Subject::TYPE_THEORETICAL_PRACTICAL,
            'is_active' => true,
        ]);
        $otherModalitySubject = Subject::create([
            'level_id' => $otherLevel->id,
            'name' => 'Quimica III',
            'hours_per_week' => 4,
            'type' => Subject::TYPE_THEORETICAL_PRACTICAL,
            'is_active' => true,
        ]);

        $cycleGroup5005->subjects()->sync([$subject->id]);
        $cycleGroup5003->subjects()->sync([$subject->id]);

        $teacherUser = User::factory()->create([
            'default_campus_id' => $campus->id,
            'name' => 'Profesor Examenes',
        ]);
        $teacherUser->assignRole('teacher');
        $teacherUser->campuses()->sync([$campus->id]);
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'is_active' => true]);

        $sectionAAssignment = $this->assignment($teacher, $group5005, $cycleGroup5005, $subject, 1, 'lab_taller', 'A');
        $sectionBAssignment = $this->assignment($teacher, $group5005, $cycleGroup5005, $subject, 2, 'lab_taller', 'B');
        $group5003Assignment = $this->assignment($teacher, $group5003, $cycleGroup5003, $subject, 1, null, null);

        $this->schedule($sectionAAssignment, $cycle, 'lunes');
        $this->schedule($sectionBAssignment, $cycle, 'martes');
        $this->schedule($group5003Assignment, $cycle, 'miercoles');

        $partial = CyclePartial::create([
            'school_cycle_id' => $cycle->id,
            'name' => 'Primer parcial',
            'code' => 'P1',
            'sort_order' => 1,
            'start_date' => '2026-08-10',
            'end_date' => '2026-10-10',
            'is_active' => true,
        ]);
        $secondPartial = CyclePartial::create([
            'school_cycle_id' => $cycle->id,
            'name' => 'Segundo parcial',
            'code' => 'P2',
            'sort_order' => 2,
            'start_date' => '2026-10-11',
            'end_date' => '2026-12-10',
            'is_active' => true,
        ]);

        $bank = QuestionBank::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'school_cycle_id' => $cycle->id,
            'cycle_partial_id' => $partial->id,
            'name' => 'Banco Quimica III',
            'is_active' => true,
        ]);

        return compact(
            'campus',
            'teacher',
            'teacherUser',
            'cycle',
            'previousCycle',
            'otherModalityCycle',
            'partial',
            'secondPartial',
            'subject',
            'otherModalitySubject',
            'bank',
            'sectionAAssignment',
            'sectionBAssignment',
            'group5003Assignment'
        );
    }

    private function cycle(Campus $campus, Modality $modality, string $name, string $code, bool $active): SchoolCycle
    {
        $cycle = SchoolCycle::create([
            'campus_id' => $campus->id,
            'modality_id' => $modality->id,
            'name' => $name,
            'code' => $code,
            'start_date' => $active ? '2026-08-10' : '2025-08-10',
            'end_date' => $active ? '2027-06-30' : '2026-06-30',
            'is_active' => $active,
        ]);
        $cycle->campuses()->sync([$campus->id]);
        $cycle->modalities()->sync([$modality->id]);

        return $cycle;
    }

    private function cycleGroup(Campus $campus, Modality $modality, SchoolCycle $cycle, Group $group, int $sectionCount): SchoolCycleGroup
    {
        return SchoolCycleGroup::create([
            'tenant_id' => 'florida',
            'school_cycle_id' => $cycle->id,
            'group_id' => $group->id,
            'campus_id' => $campus->id,
            'modality_id' => $modality->id,
            'section_count' => $sectionCount,
            'is_active' => true,
        ]);
    }

    private function assignment(
        Teacher $teacher,
        Group $group,
        SchoolCycleGroup $cycleGroup,
        Subject $subject,
        int $sectionNumber,
        ?string $sectionType,
        ?string $sectionLabel
    ): TeachingAssignment {
        return TeachingAssignment::create([
            'tenant_id' => 'florida',
            'teacher_id' => $teacher->id,
            'group_id' => $group->id,
            'school_cycle_group_id' => $cycleGroup->id,
            'subject_id' => $subject->id,
            'section_number' => $sectionNumber,
            'section_type' => $sectionType,
            'section_label' => $sectionLabel,
            'is_active' => true,
        ]);
    }

    private function schedule(TeachingAssignment $assignment, SchoolCycle $cycle, string $day): void
    {
        Schedule::create([
            'teaching_assignment_id' => $assignment->id,
            'school_cycle_id' => $cycle->id,
            'section_number' => $assignment->section_number,
            'section_type' => $assignment->section_type,
            'section_label' => $assignment->section_label,
            'day_of_week' => $day,
            'start_time' => '07:00:00',
            'end_time' => '07:50:00',
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

    private function createTenantForCurrentAppUrl(): void
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'gestion-escolar.text';
        $tenant = Tenant::query()->firstOrCreate(['id' => 'florida']);

        if (! $tenant->domains()->where('domain', $host)->exists()) {
            $tenant->domains()->create(['domain' => $host]);
        }
    }
}
