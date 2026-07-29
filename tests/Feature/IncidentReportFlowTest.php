<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\CoordinationReport;
use App\Models\Group;
use App\Models\Level;
use App\Models\Modality;
use App\Models\PrefectIncidentReport;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Student;
use App\Models\StudentIncidentReport;
use App\Models\Teacher;
use App\Models\TeacherStudentReport;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\CoordinatorReviewNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class IncidentReportFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId = 'florida';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['coordinator', 'teacher', 'student', 'prefect', 'guardian', 'tutor'] as $role) {
            Role::findOrCreate($role);
        }

        Notification::fake();
    }

    public function test_student_creates_incident_report_and_notifies_coordination(): void
    {
        $scenario = $this->incidentScenario();

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('student.incident-reports.create'))
            ->assertOk()
            ->assertSee('Reportar situacion');

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('student.incident-reports.store'), [
                'report_to' => 'coordination',
                'category' => 'classmates',
                'subject' => 'Conflicto en clase',
                'description' => 'Un companero me molesto durante la actividad.',
            ])
            ->assertRedirect(route('student.incident-reports.index'));

        $report = StudentIncidentReport::query()->firstOrFail();

        $this->assertSame($scenario['studentUser']->student->id, (int) $report->student_id);
        $this->assertSame('coordination', $report->report_to);
        $this->assertSame('classmates', $report->category);
        $this->assertSame('Conflicto en clase', $report->subject);
        $this->assertSame('open', $report->status);

        Notification::assertSentTo($scenario['coordinatorUser'], CoordinatorReviewNotification::class);
    }

    public function test_student_index_shows_only_own_incident_reports(): void
    {
        $scenario = $this->incidentScenario();
        $otherStudentUser = $this->studentUser($scenario['campus'], $scenario['group']);

        StudentIncidentReport::create([
            'student_id' => $scenario['studentUser']->student->id,
            'report_to' => 'coordination',
            'category' => 'academic',
            'subject' => 'Reporte visible',
            'description' => 'Este reporte pertenece al alumno autenticado.',
            'status' => 'open',
        ]);
        StudentIncidentReport::create([
            'student_id' => $otherStudentUser->student->id,
            'report_to' => 'coordination',
            'category' => 'academic',
            'subject' => 'Reporte ajeno',
            'description' => 'Este reporte pertenece a otro alumno.',
            'status' => 'open',
        ]);

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('student.incident-reports.index'))
            ->assertOk()
            ->assertSee('Reporte visible')
            ->assertDontSee('Reporte ajeno');
    }

    public function test_coordination_can_list_and_update_student_incident_report_status(): void
    {
        $scenario = $this->incidentScenario();
        $report = StudentIncidentReport::create([
            'student_id' => $scenario['studentUser']->student->id,
            'report_to' => 'coordination',
            'category' => 'behavioral',
            'subject' => 'Situacion de convivencia',
            'description' => 'Descripcion del reporte del alumno.',
            'status' => 'open',
        ]);

        $this->actingAs($scenario['coordinatorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('coordination.student-incident-reports.index'))
            ->assertOk()
            ->assertSee($scenario['studentUser']->name)
            ->assertSee('Situacion de convivencia');

        $this->actingAs($scenario['coordinatorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->patch(route('coordination.student-incident-reports.update-status', $report), [
                'status' => 'resolved',
            ])
            ->assertRedirect();

        $report->refresh();

        $this->assertSame('resolved', $report->status);
        $this->assertSame($scenario['coordinatorUser']->id, (int) $report->reviewed_by);
        $this->assertNotNull($report->reviewed_at);
    }

    public function test_prefect_creates_incident_report_and_notifies_coordination(): void
    {
        $scenario = $this->incidentScenario();

        $this->actingAs($scenario['prefectUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('prefect.reports.create'))
            ->assertOk()
            ->assertSee('Reportar situacion');

        $this->actingAs($scenario['prefectUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('prefect.reports.store'), [
                'report_to' => 'coordination',
                'category' => 'facilities',
                'subject' => 'Puerta danada',
                'description' => 'La puerta del salon no cierra correctamente.',
            ])
            ->assertRedirect(route('prefect.reports.index'));

        $report = PrefectIncidentReport::query()->firstOrFail();

        $this->assertSame($scenario['prefectUser']->id, (int) $report->reported_by);
        $this->assertSame('coordination', $report->report_to);
        $this->assertSame('facilities', $report->category);
        $this->assertSame('Puerta danada', $report->subject);
        $this->assertSame('open', $report->status);

        Notification::assertSentTo($scenario['coordinatorUser'], CoordinatorReviewNotification::class);
    }

    public function test_prefect_index_shows_only_own_incident_reports(): void
    {
        $scenario = $this->incidentScenario();
        $otherPrefect = $this->userWithRole('prefect', $scenario['campus']);

        PrefectIncidentReport::create([
            'reported_by' => $scenario['prefectUser']->id,
            'report_to' => 'coordination',
            'category' => 'facilities',
            'subject' => 'Reporte visible prefectura',
            'description' => 'Este reporte pertenece al prefecto autenticado.',
            'status' => 'open',
        ]);
        PrefectIncidentReport::create([
            'reported_by' => $otherPrefect->id,
            'report_to' => 'coordination',
            'category' => 'facilities',
            'subject' => 'Reporte ajeno prefectura',
            'description' => 'Este reporte pertenece a otro prefecto.',
            'status' => 'open',
        ]);

        $this->actingAs($scenario['prefectUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('prefect.reports.index'))
            ->assertOk()
            ->assertSee('Reporte visible prefectura')
            ->assertDontSee('Reporte ajeno prefectura');
    }

    public function test_coordination_can_list_and_update_prefect_report_status(): void
    {
        $scenario = $this->incidentScenario();
        $report = PrefectIncidentReport::create([
            'reported_by' => $scenario['prefectUser']->id,
            'report_to' => 'coordination',
            'category' => 'facilities',
            'subject' => 'Reporte de prefectura',
            'description' => 'Descripcion del reporte de prefectura.',
            'status' => 'open',
        ]);

        $this->actingAs($scenario['coordinatorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('coordination.prefect-reports.index'))
            ->assertOk()
            ->assertSee($scenario['prefectUser']->name)
            ->assertSee('Reporte de prefectura');

        $this->actingAs($scenario['coordinatorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->patch(route('coordination.prefect-reports.update-status', $report), [
                'status' => 'reviewed',
            ])
            ->assertRedirect();

        $report->refresh();

        $this->assertSame('reviewed', $report->status);
        $this->assertSame($scenario['coordinatorUser']->id, (int) $report->reviewed_by);
        $this->assertNotNull($report->reviewed_at);
    }

    public function test_coordination_reports_index_combines_all_report_sources(): void
    {
        $scenario = $this->incidentScenario();
        $cycle = SchoolCycle::create([
            'campus_id' => $scenario['campus']->id,
            'modality_id' => $scenario['group']->level->modality_id,
            'name' => 'Preparatoria Reportes '.uniqid(),
            'code' => 'REP-'.uniqid(),
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'is_active' => true,
        ]);
        SchoolCycleGroup::create([
            'tenant_id' => $this->tenantId,
            'school_cycle_id' => $cycle->id,
            'group_id' => $scenario['group']->id,
            'campus_id' => $scenario['campus']->id,
            'modality_id' => $scenario['group']->level->modality_id,
            'section_count' => 1,
            'is_active' => true,
        ]);
        $teacher = Teacher::create([
            'user_id' => $scenario['teacherUser']->id,
            'is_active' => true,
        ]);

        $studentReport = StudentIncidentReport::create([
            'student_id' => $scenario['studentUser']->student->id,
            'report_to' => 'coordination',
            'category' => 'academic',
            'subject' => 'Alumno central',
            'description' => 'Reporte del alumno en bandeja central.',
            'status' => 'open',
        ]);
        $studentReport->forceFill([
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ])->save();

        $teacherReport = TeacherStudentReport::create([
            'teacher_id' => $teacher->id,
            'group_id' => $scenario['group']->id,
            'student_id' => $scenario['studentUser']->student->id,
            'report_type' => 'mixed',
            'reason' => 'Motivo docente central',
            'severity' => 3,
            'status' => 'open',
        ]);
        $teacherReport->forceFill([
            'created_at' => now()->subMinutes(20),
            'updated_at' => now()->subMinutes(20),
        ])->save();

        $prefectReport = PrefectIncidentReport::create([
            'reported_by' => $scenario['prefectUser']->id,
            'report_to' => 'coordination',
            'category' => 'facilities',
            'subject' => 'Prefectura central',
            'description' => 'Reporte de prefectura en bandeja central.',
            'status' => 'reviewed',
        ]);
        $prefectReport->forceFill([
            'created_at' => now(),
            'updated_at' => now(),
        ])->save();

        $response = $this->actingAs($scenario['coordinatorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('coordination.reports.index'));

        $response
            ->assertOk()
            ->assertSee('Bandeja central')
            ->assertSee('Docente')
            ->assertSee('Alumno')
            ->assertSee('Prefectura')
            ->assertSee('Alumno central')
            ->assertSee('Motivo docente central')
            ->assertSee('Prefectura central')
            ->assertViewHas('reports', function ($reports) {
                $ordered = $reports->map(fn ($report) => $report['subject'].' '.$report['description'])->values()->all();

                $studentIndex = collect($ordered)->search(fn ($text) => str_contains($text, 'Alumno central'));
                $teacherIndex = collect($ordered)->search(fn ($text) => str_contains($text, 'Motivo docente central'));
                $prefectIndex = collect($ordered)->search(fn ($text) => str_contains($text, 'Prefectura central'));

                return $studentIndex !== false
                    && $teacherIndex !== false
                    && $prefectIndex !== false
                    && $studentIndex < $teacherIndex
                    && $teacherIndex < $prefectIndex;
            });
    }

    public function test_coordination_can_create_report_from_call_or_in_person_visit(): void
    {
        $scenario = $this->incidentScenario();

        $this->actingAs($scenario['coordinatorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('coordination.reports.create'))
            ->assertOk()
            ->assertSee('Levantar reporte')
            ->assertSee('Persona que reporta');

        $this->actingAs($scenario['coordinatorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('coordination.reports.store'), [
                'received_via' => 'phone',
                'reporter_name' => 'Tutor que llama',
                'reporter_contact' => '5555555555',
                'category' => 'communication',
                'priority' => 3,
                'subject' => 'Tutor solicita respuesta',
                'description' => 'La tutora reporta que no ha recibido seguimiento sobre su hijo del grupo 5005.',
            ])
            ->assertRedirect(route('coordination.reports.index'));

        $report = CoordinationReport::query()->firstOrFail();

        $this->assertSame($scenario['campus']->id, (int) $report->campus_id);
        $this->assertSame($scenario['coordinatorUser']->id, (int) $report->reported_by);
        $this->assertSame('Tutor que llama', $report->reporter_name);

        $this->actingAs($scenario['coordinatorUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('coordination.reports.index'))
            ->assertOk()
            ->assertSee('Coordinacion')
            ->assertSee('Tutor solicita respuesta')
            ->assertSee('Dar seguimiento');
    }

    public function test_roles_cannot_access_other_incident_report_portals(): void
    {
        $scenario = $this->incidentScenario();

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('prefect.reports.index'))
            ->assertForbidden();

        $this->actingAs($scenario['prefectUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('student.incident-reports.index'))
            ->assertForbidden();

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('coordination.student-incident-reports.index'))
            ->assertForbidden();
    }

    private function incidentScenario(): array
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

        $coordinatorUser = $this->userWithRole('coordinator', $campus);
        $studentUser = $this->studentUser($campus, $group);
        $prefectUser = $this->userWithRole('prefect', $campus);
        $teacherUser = $this->userWithRole('teacher', $campus);

        return compact(
            'campus',
            'modality',
            'level',
            'group',
            'coordinatorUser',
            'studentUser',
            'prefectUser',
            'teacherUser'
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
