<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Campus;
use App\Models\EvaluationCriterion;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Level;
use App\Models\Modality;
use App\Models\Practice;
use App\Models\PracticeSubmission;
use App\Models\PracticeSubmissionAttachment;
use App\Models\Schedule;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PracticePublishedNotification;
use App\Notifications\PracticeReviewedNotification;
use App\Notifications\PracticeSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PracticeDeliveryFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId = 'florida';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-20 08:00:00'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['coordinator', 'teacher', 'student', 'prefect', 'guardian', 'tutor'] as $role) {
            Role::findOrCreate($role);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_teacher_creates_practice_and_linked_activity(): void
    {
        Notification::fake();
        $scenario = $this->practiceScenario();

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('practices.store', $scenario['assignment']), [
                'number' => 1,
                'kind' => 'task',
                'evaluation_criterion_id' => $scenario['criterion']->id,
                'title' => 'Reporte de laboratorio',
                'introduction' => 'Introduccion del entregable.',
                'instructions' => 'Completa el reporte con tus observaciones.',
                'procedure' => 'Sigue el procedimiento de clase.',
                'realization_date' => now()->toDateString(),
                'due_date' => now()->addWeek()->toDateString(),
                'questionnaire' => json_encode([
                    [
                        'id' => 'q_observacion',
                        'type' => 'text',
                        'question' => 'Describe tu principal observacion.',
                        'required' => false,
                    ],
                ]),
                'custom_submission_fields' => json_encode([
                    [
                        'id' => 'desarrollo',
                        'label' => 'Desarrollo',
                        'required' => true,
                    ],
                    [
                        'id' => 'conclusiones',
                        'label' => 'Conclusiones',
                        'required' => false,
                    ],
                ]),
            ])
            ->assertRedirect(route('practices.index', $scenario['assignment']));

        $practice = Practice::query()->where('title', 'Reporte de laboratorio')->firstOrFail();
        $activity = $practice->activity()->firstOrFail();

        $this->assertSame($scenario['assignment']->id, (int) $practice->teaching_assignment_id);
        $this->assertSame($activity->id, (int) $practice->activity_id);
        $this->assertSame('task', $practice->kind);
        $this->assertSame('Reporte de laboratorio', $practice->title);
        $this->assertSame(['desarrollo', 'conclusiones'], $practice->submission_fields);

        $this->assertSame($scenario['assignment']->id, (int) $activity->teaching_assignment_id);
        $this->assertSame($scenario['criterion']->id, (int) $activity->evaluation_criterion_id);
        $this->assertSame($scenario['period']->id, (int) $activity->academic_period_id);
        $this->assertSame('Tarea 1: Reporte de laboratorio', $activity->title);

        Notification::assertSentTo($scenario['studentUser'], PracticePublishedNotification::class);
    }

    public function test_teacher_cannot_manage_another_teacher_practice(): void
    {
        $scenario = $this->practiceScenario();
        $practice = $this->createPractice($scenario);
        $submission = $this->submitPractice($scenario, $practice);

        $this->actingAs($scenario['otherTeacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('practices.index', $scenario['assignment']))
            ->assertForbidden();

        $this->actingAs($scenario['otherTeacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('practices.submissions', $practice))
            ->assertForbidden();

        $this->actingAs($scenario['otherTeacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('practices.submissions.review', $submission))
            ->assertForbidden();

        $this->actingAs($scenario['otherTeacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->put(route('practices.submissions.review.store', $submission), [
                'score' => 10,
                'teacher_comments' => 'Intento de revision ajena.',
            ])
            ->assertForbidden();
    }

    public function test_student_can_save_draft_and_submit_practice(): void
    {
        Notification::fake();
        $scenario = $this->practiceScenario();
        $practice = $this->createPractice($scenario);

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('student.practices.index'))
            ->assertOk()
            ->assertSee('Reporte de laboratorio');

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('student.practices.store', $practice), [
                'action' => 'draft',
                'custom_field_answers' => [
                    'desarrollo' => 'Borrador del desarrollo.',
                    'conclusiones' => '',
                ],
                'questionnaire_answers' => [
                    'q_observacion_0' => 'Observe un cambio de color.',
                ],
            ])
            ->assertRedirect(route('student.practices.show', $practice));

        $submission = PracticeSubmission::query()->firstOrFail();

        $this->assertSame('draft', $submission->status);
        $this->assertNull($submission->submitted_at);
        $this->assertSame('Borrador del desarrollo.', $submission->custom_field_answers['desarrollo']);

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('student.practices.store', $practice), [
                'action' => 'submitted',
                'custom_field_answers' => [
                    'desarrollo' => 'Desarrollo final del reporte.',
                    'conclusiones' => 'La practica confirma la hipotesis.',
                ],
                'questionnaire_answers' => [
                    'q_observacion_0' => 'Observe un cambio de color.',
                ],
            ])
            ->assertRedirect(route('student.practices.report', $practice));

        $submission->refresh();

        $this->assertSame('submitted', $submission->status);
        $this->assertSame($scenario['studentUser']->id, (int) $submission->submitted_by);
        $this->assertNotNull($submission->submitted_at);
        $this->assertSame('Desarrollo final del reporte.', $submission->custom_field_answers['desarrollo']);
        $this->assertSame('La practica confirma la hipotesis.', $submission->custom_field_answers['conclusiones']);

        Notification::assertSentTo($scenario['teacherUser'], PracticeSubmittedNotification::class);
    }

    public function test_student_can_attach_evidence_to_practice_submission(): void
    {
        Storage::fake('local');
        $scenario = $this->practiceScenario();
        $practice = $this->createPractice($scenario);

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('student.practices.store', $practice), [
                'action' => 'submitted',
                'custom_field_answers' => [
                    'desarrollo' => 'Desarrollo con evidencia.',
                    'conclusiones' => 'Conclusion con evidencia.',
                ],
                'attachments' => [
                    UploadedFile::fake()->image('evidencia.jpg', 640, 480),
                ],
            ])
            ->assertRedirect(route('student.practices.report', $practice));

        $attachment = PracticeSubmissionAttachment::query()->firstOrFail();

        Storage::disk($attachment->disk)->assertExists($attachment->path);
        $this->assertSame('evidencia.jpg', $attachment->original_name);
        $this->assertSame($scenario['studentUser']->id, (int) $attachment->uploaded_by);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('practices.submissions.show', $attachment->submission))
            ->assertOk()
            ->assertSee('evidencia.jpg');
    }

    public function test_practice_attachment_download_requires_related_user(): void
    {
        Storage::fake('local');
        $scenario = $this->practiceScenario();
        $practice = $this->createPractice($scenario);

        $submission = $this->submitPractice($scenario, $practice);
        $path = 'practice-submissions/' . $submission->id . '/evidencia.pdf';
        Storage::disk('local')->put($path, 'contenido de prueba');
        $attachment = PracticeSubmissionAttachment::create([
            'practice_submission_id' => $submission->id,
            'uploaded_by' => $scenario['studentUser']->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'evidencia.pdf',
            'mime_type' => 'application/pdf',
            'size' => 18,
        ]);

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('practice-submission-attachments.download', $attachment))
            ->assertOk();

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('practice-submission-attachments.download', $attachment))
            ->assertOk();

        $this->actingAs($scenario['otherTeacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('practice-submission-attachments.download', $attachment))
            ->assertForbidden();
    }

    public function test_teacher_submissions_list_shows_pending_students(): void
    {
        $scenario = $this->practiceScenario();
        $practice = $this->createPractice($scenario);
        $pendingStudentUser = $this->studentUser($scenario['campus'], $scenario['group']);
        $scenario['assignment']->students()->syncWithoutDetaching([$pendingStudentUser->student->id]);

        $this->submitPractice($scenario, $practice);

        $response = $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('practices.submissions', $practice));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString($scenario['studentUser']->name, $content);
        $this->assertStringContainsString($pendingStudentUser->name, $content);
        $this->assertStringContainsString('Enviado', $content);
        $this->assertStringContainsString('Pendiente', $content);
    }

    public function test_student_must_complete_required_custom_fields(): void
    {
        $scenario = $this->practiceScenario();
        $practice = $this->createPractice($scenario);

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('student.practices.store', $practice), [
                'action' => 'submitted',
                'custom_field_answers' => [
                    'desarrollo' => '',
                ],
            ])
            ->assertSessionHasErrors('custom_field_answers.desarrollo');

        $this->assertDatabaseMissing('practice_submissions', [
            'practice_id' => $practice->id,
            'status' => 'submitted',
        ]);
    }

    public function test_student_cannot_access_practice_from_another_group(): void
    {
        $scenario = $this->practiceScenario();
        $practice = $this->createPractice($scenario);
        $otherGroup = Group::create([
            'level_id' => $scenario['level']->id,
            'name' => '5006',
            'capacity' => 30,
            'is_active' => true,
        ]);
        $outsider = $this->studentUser($scenario['campus'], $otherGroup);

        $this->actingAs($outsider)
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('student.practices.show', $practice))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('student.practices.store', $practice), [
                'action' => 'submitted',
                'custom_field_answers' => [
                    'desarrollo' => 'Intento ajeno.',
                ],
            ])
            ->assertForbidden();
    }

    public function test_student_cannot_submit_after_due_date(): void
    {
        $scenario = $this->practiceScenario();
        $practice = $this->createPractice($scenario);
        $practice->update([
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('student.practices.show', $practice))
            ->assertRedirect(route('student.practices.report', $practice));

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('student.practices.store', $practice), [
                'action' => 'submitted',
                'custom_field_answers' => [
                    'desarrollo' => 'Intento fuera de fecha.',
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('practice_submissions', [
            'practice_id' => $practice->id,
            'status' => 'submitted',
        ]);
    }

    public function test_teacher_cannot_review_draft_submission(): void
    {
        $scenario = $this->practiceScenario();
        $practice = $this->createPractice($scenario);

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('student.practices.store', $practice), [
                'action' => 'draft',
                'custom_field_answers' => [
                    'desarrollo' => 'Borrador en proceso.',
                ],
            ])
            ->assertRedirect(route('student.practices.show', $practice));

        $submission = PracticeSubmission::query()->firstOrFail();

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->put(route('practices.submissions.review.store', $submission), [
                'score' => 9,
                'teacher_comments' => 'No debe revisar borrador.',
            ])
            ->assertStatus(422);

        $submission->refresh();

        $this->assertSame('draft', $submission->status);
        $this->assertNull($submission->reviewed_at);
        $this->assertSame(0, Grade::query()->count());
    }

    public function test_teacher_reviews_submission_and_creates_grade(): void
    {
        Notification::fake();
        $scenario = $this->practiceScenario();
        $practice = $this->createPractice($scenario);
        $submission = $this->submitPractice($scenario, $practice);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('practices.submissions.review', $submission))
            ->assertOk();

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->put(route('practices.submissions.review.store', $submission), [
                'score' => 9.2,
                'teacher_corrections' => 'Corrige ortografia.',
                'teacher_comments' => 'Buen trabajo.',
                'teacher_suggestions' => 'Agrega evidencias en el siguiente reporte.',
            ])
            ->assertRedirect(route('practices.submissions', $practice));

        $submission->refresh();
        $activity = $practice->fresh()->activity;

        $this->assertSame('reviewed', $submission->status);
        $this->assertSame(9.2, (float) $submission->score);
        $this->assertSame($scenario['teacherUser']->id, (int) $submission->reviewed_by);
        $this->assertNotNull($submission->reviewed_at);
        $this->assertDatabaseHas('grades', [
            'activity_id' => $activity->id,
            'student_id' => $scenario['studentUser']->student->id,
            'score' => 9.2,
            'comments' => 'Buen trabajo.',
        ]);
        Notification::assertSentTo($scenario['studentUser'], PracticeReviewedNotification::class);
    }

    public function test_teacher_cannot_assign_score_above_activity_maximum(): void
    {
        $scenario = $this->practiceScenario();
        $practice = $this->createPractice($scenario);
        $submission = $this->submitPractice($scenario, $practice);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->put(route('practices.submissions.review.store', $submission), [
                'score' => 10.1,
                'teacher_comments' => 'Calificacion fuera de rango.',
            ])
            ->assertSessionHasErrors('score');

        $submission->refresh();

        $this->assertSame('submitted', $submission->status);
        $this->assertNull($submission->score);
        $this->assertSame(0, Grade::query()->count());
    }

    public function test_student_cannot_modify_submission_after_teacher_review(): void
    {
        $scenario = $this->practiceScenario();
        $practice = $this->createPractice($scenario);
        $submission = $this->submitPractice($scenario, $practice);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->put(route('practices.submissions.review.store', $submission), [
                'score' => 8.5,
                'teacher_comments' => 'Revisado.',
            ])
            ->assertRedirect(route('practices.submissions', $practice));

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('student.practices.store', $practice), [
                'action' => 'submitted',
                'custom_field_answers' => [
                    'desarrollo' => 'Intento de cambio posterior.',
                ],
            ])
            ->assertForbidden();

        $this->assertSame('Desarrollo final del reporte.', $submission->fresh()->custom_field_answers['desarrollo']);
    }

    public function test_teacher_can_request_resubmission_and_student_can_send_again(): void
    {
        Notification::fake();
        $scenario = $this->practiceScenario();
        $practice = $this->createPractice($scenario);
        $submission = $this->submitPractice($scenario, $practice);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->put(route('practices.submissions.review.store', $submission), [
                'score' => 7.5,
                'teacher_comments' => 'Falta evidencia.',
                'allow_resubmission' => 1,
                'resubmission_due_date' => now()->addDays(2)->toDateString(),
                'resubmission_note' => 'Agrega fotografias del procedimiento.',
            ])
            ->assertRedirect(route('practices.submissions', $practice));

        $submission->refresh();

        $this->assertSame('reviewed', $submission->status);
        $this->assertTrue($submission->is_resubmission_allowed);
        $this->assertSame(7.5, (float) $submission->score);
        $this->assertDatabaseHas('grades', [
            'activity_id' => $practice->activity_id,
            'student_id' => $scenario['studentUser']->student->id,
            'score' => 7.5,
        ]);

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('student.practices.show', $practice))
            ->assertOk()
            ->assertSee('Reentrega solicitada')
            ->assertSee('Agrega fotografias del procedimiento.');

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('student.practices.store', $practice), [
                'action' => 'submitted',
                'custom_field_answers' => [
                    'desarrollo' => 'Desarrollo corregido con evidencias.',
                    'conclusiones' => 'Conclusion corregida.',
                ],
            ])
            ->assertRedirect(route('student.practices.report', $practice));

        $submission->refresh();

        $this->assertSame('submitted', $submission->status);
        $this->assertFalse($submission->is_resubmission_allowed);
        $this->assertNull($submission->score);
        $this->assertNull($submission->reviewed_at);
        $this->assertSame(1, (int) $submission->resubmission_count);
        $this->assertSame('Desarrollo corregido con evidencias.', $submission->custom_field_answers['desarrollo']);
        $this->assertDatabaseMissing('grades', [
            'activity_id' => $practice->activity_id,
            'student_id' => $scenario['studentUser']->student->id,
        ]);
        Notification::assertSentTo($scenario['teacherUser'], PracticeSubmittedNotification::class);
    }

    public function test_student_can_view_reviewed_report_with_teacher_feedback(): void
    {
        $scenario = $this->practiceScenario();
        $practice = $this->createPractice($scenario);
        $submission = $this->submitPractice($scenario, $practice);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->put(route('practices.submissions.review.store', $submission), [
                'score' => 9,
                'teacher_corrections' => 'Corrige ortografia.',
                'teacher_comments' => 'Buen trabajo.',
                'teacher_suggestions' => 'Agrega evidencias.',
            ])
            ->assertRedirect(route('practices.submissions', $practice));

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('student.practices.report', $practice))
            ->assertOk()
            ->assertSee('Desarrollo final del reporte.')
            ->assertSee('Corrige ortografia.')
            ->assertSee('Buen trabajo.')
            ->assertSee('Agrega evidencias.');
    }

    private function createPractice(array $scenario): Practice
    {
        $practice = Practice::create([
            'teaching_assignment_id' => $scenario['assignment']->id,
            'activity_id' => $scenario['activity']->id,
            'number' => 1,
            'kind' => 'task',
            'title' => 'Reporte de laboratorio',
            'introduction' => 'Introduccion del entregable.',
            'instructions' => 'Completa el reporte con tus observaciones.',
            'procedure' => 'Sigue el procedimiento de clase.',
            'questionnaire' => [
                [
                    'id' => 'q_observacion',
                    'type' => 'text',
                    'question' => 'Describe tu principal observacion.',
                    'required' => false,
                ],
            ],
            'submission_fields' => ['desarrollo', 'conclusiones'],
            'custom_submission_fields' => [
                [
                    'id' => 'desarrollo',
                    'label' => 'Desarrollo',
                    'required' => true,
                ],
                [
                    'id' => 'conclusiones',
                    'label' => 'Conclusiones',
                    'required' => false,
                ],
            ],
            'realization_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
        ]);

        return $practice->refresh();
    }

    private function submitPractice(array $scenario, Practice $practice): PracticeSubmission
    {
        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('student.practices.store', $practice), [
                'action' => 'submitted',
                'custom_field_answers' => [
                    'desarrollo' => 'Desarrollo final del reporte.',
                    'conclusiones' => 'La practica confirma la hipotesis.',
                ],
                'questionnaire_answers' => [
                    'q_observacion_0' => 'Observe un cambio de color.',
                ],
            ])
            ->assertRedirect(route('student.practices.report', $practice));

        return PracticeSubmission::query()->firstOrFail();
    }

    private function practiceScenario(): array
    {
        $campus = Campus::create([
            'name' => 'Florida',
            'code' => 'FLORIDA'.uniqid(),
            'is_active' => true,
        ]);
        $this->createTenantForCurrentAppUrl();
        $modality = Modality::create([
            'name' => 'PREPARATORIA',
            'is_active' => true,
        ]);
        $level = Level::create([
            'modality_id' => $modality->id,
            'name' => 'Quinto',
            'is_active' => true,
        ]);
        $group = Group::create([
            'level_id' => $level->id,
            'name' => '5005',
            'capacity' => 30,
            'is_active' => true,
        ]);
        $cycle = SchoolCycle::create([
            'campus_id' => $campus->id,
            'modality_id' => $modality->id,
            'name' => 'Preparatoria 2026-2027',
            'code' => 'PREPA-'.uniqid(),
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonths(10)->toDateString(),
            'is_active' => true,
        ]);
        $period = AcademicPeriod::create([
            'modality_id' => $modality->id,
            'name' => 'Primer parcial',
            'code' => 'P1'.uniqid(),
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'is_active' => true,
        ]);
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
            'hours_per_week' => 3,
            'type' => Subject::TYPE_THEORETICAL,
            'is_active' => true,
        ]);
        $cycleGroup->subjects()->syncWithoutDetaching([$subject->id]);

        $teacherUser = $this->teacherUser($campus);
        $otherTeacherUser = $this->teacherUser($campus);
        $studentUser = $this->studentUser($campus, $group);

        $assignment = TeachingAssignment::create([
            'tenant_id' => $this->tenantId,
            'teacher_id' => $teacherUser->teacher->id,
            'group_id' => $group->id,
            'school_cycle_group_id' => $cycleGroup->id,
            'subject_id' => $subject->id,
            'section_number' => 1,
            'is_active' => true,
        ]);
        $assignment->students()->sync([$studentUser->student->id]);

        Schedule::create([
            'tenant_id' => $this->tenantId,
            'teaching_assignment_id' => $assignment->id,
            'school_cycle_id' => $cycle->id,
            'section_number' => 1,
            'day_of_week' => 'lunes',
            'start_time' => '07:00:00',
            'end_time' => '07:50:00',
            'type' => 'theory',
            'is_active' => true,
        ]);

        $criterion = EvaluationCriterion::create([
            'teaching_assignment_id' => $assignment->id,
            'name' => 'Tareas',
            'percentage' => 30,
        ]);

        $activity = Activity::create([
            'teaching_assignment_id' => $assignment->id,
            'evaluation_criterion_id' => $criterion->id,
            'academic_period_id' => $period->id,
            'title' => 'Tarea 1: Reporte de laboratorio',
            'description' => 'Completa el reporte con tus observaciones.',
            'max_score' => 10,
            'due_date' => now()->addWeek()->toDateString(),
            'is_active' => true,
        ]);

        return compact(
            'campus',
            'modality',
            'level',
            'group',
            'cycle',
            'period',
            'cycleGroup',
            'subject',
            'teacherUser',
            'otherTeacherUser',
            'studentUser',
            'assignment',
            'criterion',
            'activity'
        );
    }

    private function userWithRole(string $role, Campus $campus): User
    {
        $user = User::factory()->create(['default_campus_id' => $campus->id]);
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

    private function teacherUser(Campus $campus): User
    {
        $user = $this->userWithRole('teacher', $campus);
        Teacher::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        return $user->refresh()->load('teacher');
    }

    private function studentUser(Campus $campus, Group $group): User
    {
        $user = $this->userWithRole('student', $campus);
        Student::create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'enrollment_number' => 'U'.random_int(100000, 999999),
            'is_active' => true,
        ]);

        return $user->refresh()->load('student.user');
    }
}
