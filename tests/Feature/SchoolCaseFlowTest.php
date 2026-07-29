<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\CoordinationReport;
use App\Models\Group;
use App\Models\Level;
use App\Models\Modality;
use App\Models\SchoolCase;
use App\Models\SchoolCaseAction;
use App\Models\Student;
use App\Models\StudentIncidentReport;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SchoolCaseAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SchoolCaseFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId = 'florida';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['admin', 'coordinator', 'teacher', 'student', 'guardian', 'tutor', 'prefect'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_coordination_can_create_general_school_case_without_student(): void
    {
        $scenario = $this->scenario();

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('coordination.school-cases.create'))
            ->assertOk()
            ->assertSee('Nuevo caso escolar');

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('coordination.school-cases.store'), [
                'source_type' => 'guardian',
                'source_user_id' => $scenario['guardian']->id,
                'target_type' => 'equipment',
                'category' => 'technology',
                'priority' => 'high',
                'status' => 'new',
                'subject' => 'Proyector no enciende',
                'description' => 'El tutor reporta que el proyector del salon 5005 no funciona.',
                'location' => 'Salon 5005',
                'assigned_to' => $scenario['coordinator']->id,
                'public_response' => 'Recibimos el reporte y lo canalizamos a mantenimiento.',
                'action_title' => 'Revisar cableado y fuente del proyector',
                'action_assigned_to' => $scenario['coordinator']->id,
            ])
            ->assertRedirect();

        $case = SchoolCase::query()->firstOrFail();

        $this->assertSame($scenario['campus']->id, (int) $case->campus_id);
        $this->assertSame('equipment', $case->target_type);
        $this->assertNull($case->student_id);
        $this->assertSame('Proyector no enciende', $case->subject);
        $this->assertSame('Salon 5005', $case->location);
        $this->assertDatabaseHas('school_case_actions', [
            'school_case_id' => $case->id,
            'title' => 'Revisar cableado y fuente del proyector',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('school_case_entries', [
            'school_case_id' => $case->id,
            'entry_type' => 'public_response',
        ]);
    }

    public function test_coordination_can_follow_up_and_close_case_actions(): void
    {
        $scenario = $this->scenario();
        $case = SchoolCase::create([
            'campus_id' => $scenario['campus']->id,
            'case_number' => 'CASE-2026-0001',
            'source_type' => 'teacher',
            'source_user_id' => $scenario['teacherUser']->id,
            'target_type' => 'student',
            'student_id' => $scenario['student']->id,
            'category' => 'attendance',
            'priority' => 'medium',
            'status' => 'new',
            'subject' => 'Alumno no ingresa a clase',
            'description' => 'El alumno fue registrado por prefectura pero no entro a la sesion.',
        ]);

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('coordination.school-cases.entries.store', $case), [
                'entry_type' => 'public_response',
                'visibility' => 'reporter',
                'body' => 'Se informo a coordinacion y se revisara con prefectura.',
            ])
            ->assertRedirect();

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('coordination.school-cases.actions.store', $case), [
                'title' => 'Llamar al tutor para seguimiento',
                'assigned_to' => $scenario['coordinator']->id,
            ])
            ->assertRedirect();

        $action = SchoolCaseAction::query()->firstOrFail();

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->patch(route('coordination.school-cases.actions.complete', $action), [
                'notes' => 'Tutor enterado.',
            ])
            ->assertRedirect();

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->patch(route('coordination.school-cases.status', $case), [
                'status' => 'resolved',
                'status_note' => 'Caso resuelto con tutor.',
            ])
            ->assertRedirect();

        $case->refresh();
        $action->refresh();

        $this->assertSame('resolved', $case->status);
        $this->assertNotNull($case->closed_at);
        $this->assertSame('completed', $action->status);
        $this->assertSame('Tutor enterado.', $action->notes);
        $this->assertSame('Se informo a coordinacion y se revisara con prefectura.', $case->public_response);
    }

    public function test_case_closes_when_last_pending_action_is_completed(): void
    {
        $scenario = $this->scenario();
        $case = SchoolCase::create([
            'campus_id' => $scenario['campus']->id,
            'case_number' => 'CASE-2026-0002',
            'source_type' => 'student',
            'source_user_id' => $scenario['studentUser']->id,
            'target_type' => 'student',
            'student_id' => $scenario['student']->id,
            'group_id' => $scenario['group']->id,
            'category' => 'academic',
            'priority' => 'medium',
            'status' => 'in_progress',
            'subject' => 'Seguimiento activo',
            'description' => 'Caso con una accion pendiente.',
        ]);
        $action = SchoolCaseAction::create([
            'school_case_id' => $case->id,
            'title' => 'Cerrar seguimiento',
            'status' => 'pending',
        ]);

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->patch(route('coordination.school-cases.actions.complete', $action), [
                'notes' => 'Seguimiento completado.',
            ])
            ->assertRedirect();

        $case->refresh();

        $this->assertSame('closed', $case->status);
        $this->assertNotNull($case->closed_at);
        $this->assertDatabaseHas('school_case_entries', [
            'school_case_id' => $case->id,
            'entry_type' => 'status_change',
            'body' => 'Caso cerrado automaticamente al completar todas las acciones.',
        ]);
    }

    public function test_coordination_can_open_school_case_from_report_inbox(): void
    {
        $scenario = $this->scenario();
        $report = StudentIncidentReport::create([
            'student_id' => $scenario['student']->id,
            'report_to' => 'coordination',
            'category' => 'academic',
            'subject' => 'Tutor solicita respuesta',
            'description' => 'La familia solicita seguimiento puntual del alumno.',
            'status' => 'open',
        ]);

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('coordination.school-cases.create', [
                'source_report_type' => 'student',
                'source_report_id' => $report->id,
            ]))
            ->assertOk()
            ->assertSee('Reporte de origen')
            ->assertSee('Tutor solicita respuesta')
            ->assertSee('La familia solicita seguimiento puntual del alumno.')
            ->assertDontSee('Usuario que reporta');

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('coordination.school-cases.store'), [
                'source_report_type' => 'student',
                'source_report_id' => $report->id,
                'source_type' => 'student',
                'source_user_id' => $scenario['studentUser']->id,
                'target_type' => 'student',
                'student_id' => $scenario['student']->id,
                'group_id' => $scenario['group']->id,
                'guardian_user_id' => $scenario['guardian']->id,
                'category' => 'academic',
                'priority' => 'high',
                'status' => 'assigned',
                'subject' => 'Tutor solicita respuesta',
                'description' => 'Se abre seguimiento formal con tutor y coordinacion.',
                'assigned_to' => $scenario['coordinator']->id,
                'initial_note' => 'Coordinar llamada con tutor.',
                'action_title' => 'Llamar al tutor',
                'action_assigned_to' => $scenario['coordinator']->id,
            ])
            ->assertRedirect();

        $case = SchoolCase::query()->firstOrFail();
        $report->refresh();

        $this->assertSame('assigned', $case->status);
        $this->assertSame('reviewed', $report->status);
        $this->assertDatabaseHas('school_case_entries', [
            'school_case_id' => $case->id,
            'entry_type' => 'linked_report',
        ]);
        $this->assertDatabaseHas('school_case_actions', [
            'school_case_id' => $case->id,
            'title' => 'Llamar al tutor',
        ]);
    }

    public function test_coordination_can_open_school_case_from_coordination_report(): void
    {
        $scenario = $this->scenario();
        $report = CoordinationReport::create([
            'campus_id' => $scenario['campus']->id,
            'reported_by' => $scenario['coordinator']->id,
            'received_via' => 'phone',
            'reporter_name' => 'Tutor que llama',
            'reporter_contact' => '5555555555',
            'category' => 'communication',
            'priority' => 3,
            'subject' => 'Tutor solicita respuesta',
            'description' => 'La tutora reporta que no ha recibido respuesta de la escuela.',
            'status' => 'open',
        ]);

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('coordination.school-cases.create', [
                'source_report_type' => 'coordination',
                'source_report_id' => $report->id,
            ]))
            ->assertOk()
            ->assertSee('Reporte de origen')
            ->assertSee('Tutor solicita respuesta')
            ->assertSee('Tutor que llama')
            ->assertSee('5555555555');

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('coordination.school-cases.store'), [
                'source_report_type' => 'coordination',
                'source_report_id' => $report->id,
                'source_type' => 'coordination',
                'source_user_id' => $scenario['coordinator']->id,
                'target_type' => 'general',
                'category' => 'communication',
                'priority' => 'high',
                'status' => 'assigned',
                'subject' => 'Tutor solicita respuesta',
                'description' => 'Se abre seguimiento formal por llamada de tutor.',
                'assigned_to' => $scenario['coordinator']->id,
                'public_response' => 'Se dara seguimiento desde coordinacion.',
            ])
            ->assertRedirect();

        $case = SchoolCase::query()->firstOrFail();
        $report->refresh();

        $this->assertSame('reviewed', $report->status);
        $this->assertDatabaseHas('school_case_entries', [
            'school_case_id' => $case->id,
            'entry_type' => 'linked_report',
        ]);
        $this->assertTrue(
            $case->entries()
                ->where('entry_type', 'linked_report')
                ->get()
                ->contains(fn ($entry) => ($entry->meta['source_report_type'] ?? null) === 'coordination'
                    && (int) ($entry->meta['source_report_id'] ?? 0) === $report->id)
        );
    }

    public function test_school_case_cannot_be_assigned_to_teacher_student_or_guardian(): void
    {
        $scenario = $this->scenario();

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('coordination.school-cases.store'), [
                'source_type' => 'coordination',
                'target_type' => 'general',
                'category' => 'communication',
                'priority' => 'medium',
                'status' => 'new',
                'subject' => 'Asignacion no valida',
                'description' => 'Intento de asignar el caso a un docente.',
                'assigned_to' => $scenario['teacherUser']->id,
            ])
            ->assertSessionHasErrors('assigned_to');

        $this->assertDatabaseMissing('school_cases', [
            'subject' => 'Asignacion no valida',
        ]);
    }

    public function test_school_case_action_cannot_be_assigned_to_teacher_student_or_guardian(): void
    {
        $scenario = $this->scenario();
        $case = SchoolCase::create([
            'campus_id' => $scenario['campus']->id,
            'case_number' => 'CASE-2026-0003',
            'source_type' => 'coordination',
            'target_type' => 'general',
            'category' => 'communication',
            'priority' => 'medium',
            'status' => 'new',
            'subject' => 'Caso con accion',
            'description' => 'Caso para validar responsables de acciones.',
        ]);

        foreach ([$scenario['teacherUser'], $scenario['studentUser'], $scenario['guardian']] as $blockedUser) {
            $this->actingAs($scenario['coordinator'])
                ->withSession(['active_campus_id' => $scenario['campus']->id])
                ->post(route('coordination.school-cases.actions.store', $case), [
                    'title' => 'Accion no valida '.$blockedUser->id,
                    'assigned_to' => $blockedUser->id,
                ])
                ->assertSessionHasErrors('assigned_to');
        }

        $this->assertDatabaseMissing('school_case_actions', [
            'school_case_id' => $case->id,
        ]);
    }

    public function test_assigned_user_is_notified_when_school_case_is_assigned(): void
    {
        Notification::fake();
        $scenario = $this->scenario();

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('coordination.school-cases.store'), [
                'source_type' => 'coordination',
                'target_type' => 'general',
                'category' => 'communication',
                'priority' => 'medium',
                'status' => 'assigned',
                'subject' => 'Tutor solicita llamada',
                'description' => 'Coordinar respuesta a tutor.',
                'assigned_to' => $scenario['coordinator']->id,
            ])
            ->assertRedirect();

        Notification::assertSentTo(
            $scenario['coordinator'],
            SchoolCaseAssignedNotification::class,
            function (SchoolCaseAssignedNotification $notification) use ($scenario) {
                $payload = $notification->toDatabase($scenario['coordinator']);

                return $payload['type'] === 'school_case_assigned'
                    && $payload['title'] === 'Caso escolar asignado'
                    && $payload['message'] === 'Tutor solicita llamada'
                    && str_contains($payload['url'], '/coordination/school-cases/');
            }
        );
    }

    public function test_assigned_user_is_notified_when_school_case_action_is_assigned(): void
    {
        Notification::fake();
        $scenario = $this->scenario();
        $case = SchoolCase::create([
            'campus_id' => $scenario['campus']->id,
            'case_number' => 'CASE-2026-0004',
            'source_type' => 'coordination',
            'target_type' => 'general',
            'category' => 'communication',
            'priority' => 'medium',
            'status' => 'new',
            'subject' => 'Seguimiento de tutor',
            'description' => 'Caso para validar notificacion de acciones.',
        ]);

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('coordination.school-cases.actions.store', $case), [
                'title' => 'Llamar al tutor',
                'assigned_to' => $scenario['coordinator']->id,
            ])
            ->assertRedirect();

        Notification::assertSentTo(
            $scenario['coordinator'],
            SchoolCaseAssignedNotification::class,
            function (SchoolCaseAssignedNotification $notification) use ($scenario) {
                $payload = $notification->toDatabase($scenario['coordinator']);

                return $payload['type'] === 'school_case_action_assigned'
                    && $payload['title'] === 'Seguimiento asignado'
                    && str_contains($payload['message'], 'Llamar al tutor')
                    && str_contains($payload['message'], 'Seguimiento de tutor')
                    && str_contains($payload['url'], '/coordination/school-cases/');
            }
        );
    }

    private function scenario(): array
    {
        $this->createTenantForCurrentAppUrl();

        $campus = Campus::create([
            'name' => 'Florida',
            'code' => 'FLORIDA'.uniqid(),
            'is_active' => true,
        ]);
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

        $coordinator = $this->userWithRole('coordinator', $campus, 'Coordinacion Demo');
        $guardian = $this->userWithRole('guardian', $campus, 'Tutor Demo');
        $teacherUser = $this->userWithRole('teacher', $campus, 'Docente Demo');
        $teacher = Teacher::create([
            'user_id' => $teacherUser->id,
            'is_active' => true,
        ]);
        $studentUser = $this->userWithRole('student', $campus, 'Alumno Demo');
        $student = Student::create([
            'user_id' => $studentUser->id,
            'guardian_user_id' => $guardian->id,
            'group_id' => $group->id,
            'enrollment_number' => 'U'.random_int(100000, 999999),
            'is_active' => true,
        ]);

        return compact('campus', 'coordinator', 'guardian', 'teacherUser', 'teacher', 'studentUser', 'student', 'group');
    }

    private function userWithRole(string $role, Campus $campus, string $name): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'default_campus_id' => $campus->id,
        ]);
        $user->assignRole($role);
        $user->campuses()->syncWithoutDetaching([$campus->id]);

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
}
