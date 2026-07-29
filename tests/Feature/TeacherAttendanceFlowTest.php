<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\Campus;
use App\Models\CyclePartial;
use App\Models\EvaluationCriterion;
use App\Models\Group;
use App\Models\Level;
use App\Models\Modality;
use App\Models\Schedule;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\SessionActivity;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Temario;
use App\Models\TemarioPoint;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\AcademicSessionGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TeacherAttendanceFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-20 08:00:00'));

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

    public function test_teacher_can_open_own_attendance_session(): void
    {
        $scenario = $this->attendanceScenario();

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('attendance.take', $scenario['session']))
            ->assertOk()
            ->assertSee('Pasar lista')
            ->assertSee('Vista tabla')
            ->assertSee('JUSTIFICADA')
            ->assertSee('1/A', false)
            ->assertSee('0/F', false)
            ->assertSee('2/J', false)
            ->assertSee('3/R', false)
            ->assertSee($scenario['students'][0]->user->name);
    }

    public function test_teacher_classes_buttons_use_solid_colors_by_state(): void
    {
        $scenario = $this->attendanceScenario();

        $response = $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.classes.index'))
            ->assertOk()
            ->assertSee('Configurar rubros')
            ->assertSee('btn-danger', false)
            ->assertSee('btn-info', false)
            ->assertSee('btn-secondary', false)
            ->assertDontSee('btn-outline-info', false)
            ->assertDontSee('btn-outline-success', false);

        $this->assertStringNotContainsString('Rubros configurados', $response->getContent());

        EvaluationCriterion::create([
            'teaching_assignment_id' => $scenario['assignment']->id,
            'cycle_partial_id' => $scenario['partial']->id,
            'name' => 'Actividades',
            'percentage' => 100,
        ]);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.classes.index'))
            ->assertOk()
            ->assertSee('Rubros configurados')
            ->assertSee('btn-success', false);
    }

    public function test_teacher_classes_groups_sectioned_assignments_in_one_card(): void
    {
        $scenario = $this->attendanceScenario();

        $scenario['cycleGroup']->update(['section_count' => 2]);

        $sectionASchedule = Schedule::create([
            'teaching_assignment_id' => $scenario['assignment']->id,
            'school_cycle_id' => $scenario['cycle']->id,
            'section_number' => 1,
            'day_of_week' => 'miercoles',
            'start_time' => now()->subMinutes(10)->format('H:i:s'),
            'end_time' => now()->addMinutes(40)->format('H:i:s'),
            'type' => 'Dividida',
            'is_active' => true,
        ]);
        AcademicSession::create([
            'teaching_assignment_id' => $scenario['assignment']->id,
            'schedule_id' => $sectionASchedule->id,
            'academic_period_id' => $scenario['period']->id,
            'session_date' => now()->toDateString(),
            'start_time' => $sectionASchedule->start_time,
            'end_time' => $sectionASchedule->end_time,
            'is_cancelled' => false,
        ]);

        $labAssignment = TeachingAssignment::create([
            'teacher_id' => $scenario['teacherUser']->teacher->id,
            'group_id' => $scenario['group']->id,
            'school_cycle_group_id' => $scenario['cycleGroup']->id,
            'subject_id' => $scenario['subject']->id,
            'section_number' => 2,
            'is_active' => true,
        ]);
        $labSchedule = Schedule::create([
            'teaching_assignment_id' => $labAssignment->id,
            'school_cycle_id' => $scenario['cycle']->id,
            'section_number' => 2,
            'day_of_week' => 'martes',
            'start_time' => now()->subMinutes(5)->format('H:i:s'),
            'end_time' => now()->addMinutes(45)->format('H:i:s'),
            'type' => 'Dividida',
            'is_active' => true,
        ]);
        AcademicSession::create([
            'teaching_assignment_id' => $labAssignment->id,
            'schedule_id' => $labSchedule->id,
            'academic_period_id' => $scenario['period']->id,
            'session_date' => now()->toDateString(),
            'start_time' => $labSchedule->start_time,
            'end_time' => $labSchedule->end_time,
            'is_cancelled' => false,
        ]);

        $response = $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.classes.index'))
            ->assertOk()
            ->assertSee('Ver sesiones')
            ->assertSee('Grupo 5005');

        $this->assertSame(
            1,
            substr_count($response->getContent(), '<h5 class="card-title mb-1">Quimica III</h5>')
        );

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.classes.sessions.index', $scenario['assignment']))
            ->assertOk()
            ->assertSee('Clase dividida')
            ->assertSee('Sección A')
            ->assertSee('Sección B')
            ->assertSee('session-row-sectioned', false);
    }

    public function test_teacher_cannot_open_another_teacher_attendance_session(): void
    {
        $scenario = $this->attendanceScenario();
        $otherScenario = $this->attendanceScenario([
            'campus' => $scenario['campus'],
            'modality' => $scenario['modality'],
            'level' => $scenario['level'],
            'cycle' => $scenario['cycle'],
            'period' => $scenario['period'],
            'group_name' => '5002',
        ]);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('attendance.take', $otherScenario['session']))
            ->assertForbidden();
    }

    public function test_teacher_stores_attendance_for_assigned_students(): void
    {
        $scenario = $this->attendanceScenario();
        [$firstStudent, $secondStudent] = $scenario['students'];

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('attendance.store', $scenario['session']), [
                'attendance' => [
                    $firstStudent->id => 'present',
                    $secondStudent->id => 'late',
                ],
            ])
            ->assertRedirect(route('teacher.classes.sessions.index', $scenario['assignment']));

        $this->assertDatabaseHas('attendances', [
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $firstStudent->id,
            'status' => 'present',
        ]);
        $this->assertDatabaseHas('attendances', [
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $secondStudent->id,
            'status' => 'late',
        ]);
    }

    public function test_teacher_can_use_massive_attendance_sheet_for_editable_test_cycle(): void
    {
        config(['attendance.editable_cycle_codes_for_testing' => ['26-27']]);

        $scenario = $this->attendanceScenario(['cycle_code' => '26-27']);
        [$firstStudent, $secondStudent] = $scenario['students'];

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('attendance.massive', [
                'assignment' => $scenario['assignment'],
                'mode' => 'week',
                'date' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Asistencia masiva')
            ->assertSee('Ciclo de prueba')
            ->assertSee($firstStudent->user->name)
            ->assertSee('1/A', false)
            ->assertSee('0/F', false);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('attendance.massive.store', $scenario['assignment']), [
                'mode' => 'week',
                'date' => now()->toDateString(),
                'attendance' => [
                    $scenario['session']->id => [
                        $firstStudent->id => 'present',
                        $secondStudent->id => 'absent',
                    ],
                ],
            ])
            ->assertRedirect(route('attendance.massive', [
                'assignment' => $scenario['assignment'],
                'mode' => 'week',
                'date' => now()->toDateString(),
            ]));

        $this->assertDatabaseHas('attendances', [
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $firstStudent->id,
            'status' => 'present',
        ]);
        $this->assertDatabaseHas('attendances', [
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $secondStudent->id,
            'status' => 'absent',
        ]);
    }

    public function test_teacher_can_use_massive_session_activity_sheet_with_evaluation(): void
    {
        config(['attendance.editable_cycle_codes_for_testing' => ['26-27']]);

        $scenario = $this->attendanceScenario(['cycle_code' => '26-27']);
        $criterion = EvaluationCriterion::create([
            'teaching_assignment_id' => $scenario['assignment']->id,
            'cycle_partial_id' => $scenario['partial']->id,
            'name' => 'Trabajo en clase',
            'percentage' => 100,
        ]);
        $temario = $this->temarioPointsForAssignment($scenario['assignment']);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('session.activities.massive', [
                'assignment' => $scenario['assignment'],
                'mode' => 'week',
                'date' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Actividades masivas')
            ->assertSee('Cuenta para evaluacion')
            ->assertSee('Unidad')
            ->assertSee('Tema')
            ->assertSee('Subtemas')
            ->assertSee('Cinematica')
            ->assertSee('Movimiento circular')
            ->assertSee('Trabajo en clase');

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('session.activities.massive.store', $scenario['assignment']), [
                'mode' => 'week',
                'date' => now()->toDateString(),
                'activities' => [
                    $scenario['session']->id => [
                        'title' => 'Movimiento circular y actividad ludica',
                        'description' => 'Discusion grupal y ejercicios',
                        'temario_unit_id' => '1',
                        'temario_point_id' => $temario['topic']->id,
                        'temario_subtopic_ids' => [$temario['subtopic']->id],
                        'is_evaluable' => '1',
                        'evaluation_title' => 'Ejercicios de movimiento circular',
                        'evaluation_criterion_id' => $criterion->id,
                    ],
                ],
            ])
            ->assertRedirect(route('session.activities.massive', [
                'assignment' => $scenario['assignment'],
                'mode' => 'week',
                'date' => now()->toDateString(),
            ]));

        $sessionActivity = SessionActivity::query()->where('academic_session_id', $scenario['session']->id)->firstOrFail();
        $this->assertSame('Movimiento circular y actividad ludica', $sessionActivity->title);
        $this->assertSame((int) $criterion->id, (int) $sessionActivity->evaluation_criterion_id);
        $this->assertSame((int) $temario['topic']->id, (int) $sessionActivity->temario_point_id);
        $this->assertSame([(int) $temario['subtopic']->id], $sessionActivity->temario_subtopic_ids);

        $this->assertDatabaseHas('activities', [
            'session_activity_id' => $sessionActivity->id,
            'teaching_assignment_id' => $scenario['assignment']->id,
            'evaluation_criterion_id' => $criterion->id,
            'title' => 'Ejercicios de movimiento circular',
        ]);
    }

    public function test_teacher_can_select_unit_topic_and_subtopics_in_session_activity_form(): void
    {
        $scenario = $this->attendanceScenario();
        $temario = $this->temarioPointsForAssignment($scenario['assignment']);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('session.activities.create', $scenario['session']))
            ->assertOk()
            ->assertSee('Unidad del temario')
            ->assertSee('Tema del temario')
            ->assertSee('Subtemas vistos')
            ->assertSee('Cinematica')
            ->assertSee('Movimiento circular')
            ->assertSee('Velocidad angular');

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('session.activities.store', $scenario['session']), [
                'title' => 'Discusion guiada de movimiento circular',
                'description' => 'Trabajo con ejemplos en pizarron',
                'temario_unit_id' => '1',
                'temario_point_id' => $temario['topic']->id,
                'temario_subtopic_ids' => [$temario['subtopic']->id],
            ])
            ->assertRedirect(route('teacher.classes.sessions.index', $scenario['assignment']));

        $sessionActivity = SessionActivity::query()
            ->where('academic_session_id', $scenario['session']->id)
            ->firstOrFail();

        $this->assertSame('Discusion guiada de movimiento circular', $sessionActivity->title);
        $this->assertSame((int) $temario['topic']->id, (int) $sessionActivity->temario_point_id);
        $this->assertSame([(int) $temario['subtopic']->id], $sessionActivity->temario_subtopic_ids);
    }

    public function test_teacher_attendance_ignores_students_not_enrolled_in_assignment(): void
    {
        $scenario = $this->attendanceScenario();
        $outsider = $this->studentUser($scenario['campus'], $scenario['group'])->student;

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('attendance.store', $scenario['session']), [
                'attendance' => [
                    $scenario['students'][0]->id => 'present',
                    $outsider->id => 'absent',
                ],
            ])
            ->assertRedirect(route('teacher.classes.sessions.index', $scenario['assignment']));

        $this->assertDatabaseHas('attendances', [
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $scenario['students'][0]->id,
            'status' => 'present',
        ]);
        $this->assertDatabaseMissing('attendances', [
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $outsider->id,
        ]);
    }

    public function test_teacher_cannot_overwrite_justified_or_suspension_locked_attendance(): void
    {
        $scenario = $this->attendanceScenario();
        [$justifiedStudent, $suspendedStudent] = $scenario['students'];

        Attendance::create([
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $justifiedStudent->id,
            'status' => 'justified',
        ]);
        Attendance::create([
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $suspendedStudent->id,
            'status' => 'absent',
            'is_suspension_locked' => true,
        ]);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('attendance.store', $scenario['session']), [
                'attendance' => [
                    $justifiedStudent->id => 'present',
                    $suspendedStudent->id => 'present',
                ],
            ])
            ->assertRedirect(route('teacher.classes.sessions.index', $scenario['assignment']));

        $this->assertDatabaseHas('attendances', [
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $justifiedStudent->id,
            'status' => 'justified',
        ]);
        $this->assertDatabaseHas('attendances', [
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $suspendedStudent->id,
            'status' => 'absent',
            'is_suspension_locked' => true,
        ]);
    }

    public function test_teacher_can_override_all_attendance_locks_for_editable_test_cycle(): void
    {
        config(['attendance.editable_cycle_codes_for_testing' => ['26-27']]);

        $scenario = $this->attendanceScenario([
            'cycle_code' => '26-27',
            'session_date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '23:00:00',
        ]);
        [$justifiedStudent, $suspendedStudent] = $scenario['students'];
        $scenario['period']->update(['is_active' => false]);
        $scenario['session']->update(['attendance_closed_at' => now()]);

        Attendance::create([
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $justifiedStudent->id,
            'status' => 'justified',
        ]);
        Attendance::create([
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $suspendedStudent->id,
            'status' => 'absent',
            'is_suspension_locked' => true,
        ]);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('attendance.edit', $scenario['session']))
            ->assertOk()
            ->assertSee('Ciclo de prueba');

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('attendance.store', $scenario['session']), [
                'attendance' => [
                    $justifiedStudent->id => 'present',
                    $suspendedStudent->id => 'present',
                ],
            ])
            ->assertRedirect(route('teacher.classes.sessions.index', $scenario['assignment']));

        $this->assertDatabaseHas('attendances', [
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $justifiedStudent->id,
            'status' => 'present',
        ]);
        $this->assertDatabaseHas('attendances', [
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $suspendedStudent->id,
            'status' => 'present',
            'student_suspension_id' => null,
            'is_suspension_locked' => false,
        ]);
    }

    public function test_teacher_cannot_store_attendance_before_capture_window(): void
    {
        $scenario = $this->attendanceScenario([
            'session_date' => now()->addDay()->toDateString(),
            'start_time' => '23:00:00',
        ]);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('attendance.store', $scenario['session']), [
                'attendance' => [
                    $scenario['students'][0]->id => 'present',
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('attendances', [
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $scenario['students'][0]->id,
        ]);
    }

    public function test_teacher_can_store_future_attendance_when_local_testing_flag_is_enabled(): void
    {
        config(['app.env' => 'local']);
        config(['attendance.allow_future_capture_local' => true]);

        $scenario = $this->attendanceScenario([
            'session_date' => now()->addWeeks(3)->toDateString(),
            'start_time' => '23:00:00',
        ]);
        $this->mock(AcademicSessionGeneratorService::class, function ($mock) {
            $mock->shouldReceive('generateForAssignment')->andReturn(0);
        });

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('attendance.store', $scenario['session']), [
                'attendance' => [
                    $scenario['students'][0]->id => 'present',
                ],
            ])
            ->assertRedirect(route('teacher.classes.sessions.index', $scenario['assignment']));

        $this->assertDatabaseHas('attendances', [
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $scenario['students'][0]->id,
            'status' => 'present',
        ]);
    }

    public function test_teacher_sessions_enable_future_attendance_button_when_local_testing_flag_is_enabled(): void
    {
        config(['app.env' => 'local']);
        config(['attendance.allow_future_capture_local' => true]);

        $scenario = $this->attendanceScenario([
            'session_date' => now()->addWeeks(3)->toDateString(),
            'start_time' => '23:00:00',
        ]);

        $response = $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.classes.sessions.index', $scenario['assignment']))
            ->assertOk()
            ->assertSee('Tomar')
            ->assertSee('Configura los rubros de evaluacion para registrar actividades')
            ->assertDontSee('Disponible desde');

        $this->assertSame(
            1,
            substr_count($response->getContent(), 'Configura los rubros de evaluacion para registrar actividades')
        );
    }

    public function test_teacher_sessions_enable_activity_button_when_evaluation_criteria_exist(): void
    {
        config(['app.env' => 'local']);
        config(['attendance.allow_future_capture_local' => true]);

        $scenario = $this->attendanceScenario([
            'session_date' => now()->addWeeks(3)->toDateString(),
            'start_time' => '23:00:00',
        ]);

        EvaluationCriterion::create([
            'teaching_assignment_id' => $scenario['assignment']->id,
            'name' => 'Tareas',
            'percentage' => 100,
        ]);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.classes.sessions.index', $scenario['assignment']))
            ->assertOk()
            ->assertSee('Asignar')
            ->assertDontSee('Configura los rubros de evaluacion para registrar actividades');
    }

    public function test_future_attendance_testing_flag_is_ignored_outside_local_environment(): void
    {
        config(['app.env' => 'production']);
        config(['attendance.allow_future_capture_local' => true]);

        $scenario = $this->attendanceScenario([
            'session_date' => now()->addWeeks(3)->toDateString(),
            'start_time' => '23:00:00',
        ]);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('attendance.store', $scenario['session']), [
                'attendance' => [
                    $scenario['students'][0]->id => 'present',
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('attendances', [
            'academic_session_id' => $scenario['session']->id,
            'student_id' => $scenario['students'][0]->id,
        ]);
    }

    private function attendanceScenario(array $overrides = []): array
    {
        $campus = $overrides['campus'] ?? Campus::create([
            'name' => 'Florida',
            'code' => 'FLORIDA'.uniqid(),
            'is_active' => true,
        ]);
        $modality = $overrides['modality'] ?? Modality::create([
            'name' => 'PREPARATORIA',
            'is_active' => true,
        ]);
        $level = $overrides['level'] ?? Level::create([
            'modality_id' => $modality->id,
            'name' => 'Quinto',
            'is_active' => true,
        ]);
        $group = Group::create([
            'level_id' => $level->id,
            'name' => $overrides['group_name'] ?? '5005',
            'capacity' => 30,
            'is_active' => true,
        ]);
        $cycle = $overrides['cycle'] ?? SchoolCycle::create([
            'campus_id' => $campus->id,
            'modality_id' => $modality->id,
            'name' => 'Preparatoria 2026-2027',
            'code' => $overrides['cycle_code'] ?? 'PREPA-'.uniqid(),
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonths(10)->toDateString(),
            'is_active' => true,
        ]);
        $period = $overrides['period'] ?? AcademicPeriod::create([
            'modality_id' => $modality->id,
            'name' => 'Primer parcial',
            'code' => 'P1'.uniqid(),
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'is_active' => true,
        ]);
        $partial = CyclePartial::firstOrCreate(
            [
                'school_cycle_id' => $cycle->id,
                'sort_order' => 1,
            ],
            [
                'academic_period_id' => $period->id,
                'name' => 'Primer parcial',
                'code' => 'P1'.uniqid(),
                'start_date' => $period->start_date,
                'end_date' => $period->end_date,
                'is_active' => true,
            ]
        );
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
        $cycleGroup->subjects()->syncWithoutDetaching([$subject->id]);

        $teacherUser = $this->teacherUser($campus);
        $assignment = TeachingAssignment::create([
            'teacher_id' => $teacherUser->teacher->id,
            'group_id' => $group->id,
            'school_cycle_group_id' => $cycleGroup->id,
            'subject_id' => $subject->id,
            'section_number' => 1,
            'is_active' => true,
        ]);
        $schedule = Schedule::create([
            'teaching_assignment_id' => $assignment->id,
            'school_cycle_id' => $cycle->id,
            'section_number' => 1,
            'day_of_week' => 'lunes',
            'start_time' => $overrides['start_time'] ?? now()->subMinutes(5)->format('H:i:s'),
            'end_time' => $overrides['end_time'] ?? now()->addMinutes(45)->format('H:i:s'),
            'type' => 'theory',
            'is_active' => true,
        ]);
        $session = AcademicSession::create([
            'teaching_assignment_id' => $assignment->id,
            'schedule_id' => $schedule->id,
            'academic_period_id' => $period->id,
            'session_date' => $overrides['session_date'] ?? now()->toDateString(),
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'is_cancelled' => false,
        ]);

        $students = collect([
            $this->studentUser($campus, $group)->student,
            $this->studentUser($campus, $group)->student,
        ]);
        $assignment->students()->sync($students->pluck('id')->all());

        return compact(
            'campus',
            'modality',
            'level',
            'group',
            'cycle',
            'period',
            'partial',
            'cycleGroup',
            'subject',
            'teacherUser',
            'assignment',
            'schedule',
            'session',
            'students'
        );
    }

    private function temarioPointsForAssignment(TeachingAssignment $assignment): array
    {
        $temario = Temario::create([
            'subject_id' => $assignment->subject_id,
            'title' => 'Temario de prueba',
        ]);

        $unit = TemarioPoint::create([
            'temario_id' => $temario->id,
            'position' => 1,
            'label' => '1',
            'level' => 1,
            'type' => 'conceptual',
            'content' => 'Cinematica',
        ]);
        $topic = TemarioPoint::create([
            'temario_id' => $temario->id,
            'position' => 2,
            'label' => '1.1',
            'level' => 2,
            'type' => 'conceptual',
            'content' => 'Movimiento circular',
        ]);
        $subtopic = TemarioPoint::create([
            'temario_id' => $temario->id,
            'position' => 3,
            'label' => '1.1.1',
            'level' => 3,
            'type' => 'conceptual',
            'content' => 'Velocidad angular',
        ]);

        return compact('temario', 'unit', 'topic', 'subtopic');
    }

    private function teacherUser(Campus $campus): User
    {
        $user = User::factory()->create(['default_campus_id' => $campus->id]);
        $user->assignRole('teacher');
        $user->campuses()->sync([$campus->id]);
        Teacher::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        return $user->refresh()->load('teacher');
    }

    private function studentUser(Campus $campus, Group $group): User
    {
        $user = User::factory()->create(['default_campus_id' => $campus->id]);
        $user->assignRole('student');
        $user->campuses()->sync([$campus->id]);
        Student::create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'enrollment_number' => 'U'.random_int(100000, 999999),
            'is_active' => true,
        ]);

        return $user->refresh()->load('student');
    }
}
