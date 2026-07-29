<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Campus;
use App\Models\CyclePartial;
use App\Models\EvaluationCriterion;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Level;
use App\Models\Modality;
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

class StudentAcademicPerformanceFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-15 08:00:00'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['student', 'teacher'] as $role) {
            Role::findOrCreate($role);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_student_can_view_partial_academic_performance(): void
    {
        $scenario = $this->performanceScenario();

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('student.grades'))
            ->assertOk()
            ->assertSee('Desempeño académico')
            ->assertSee('Quimica III')
            ->assertSee('Primer parcial')
            ->assertSee('8.0')
            ->assertSee('50%')
            ->assertSee('1/2')
            ->assertSee('1/1')
            ->assertDontSee('Geografia');
    }

    public function test_student_menu_contains_academic_performance_link(): void
    {
        $scenario = $this->performanceScenario();

        $this->actingAs($scenario['studentUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('student.subjects'))
            ->assertOk()
            ->assertSee(route('student.grades'), false)
            ->assertSee('Desempeño académico');
    }

    private function performanceScenario(): array
    {
        $campus = Campus::create([
            'name' => 'Florida',
            'code' => 'FLORIDA',
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
        $otherGroup = Group::create([
            'level_id' => $level->id,
            'name' => '5006',
            'capacity' => 30,
            'is_active' => true,
        ]);
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

        $period = AcademicPeriod::create([
            'modality_id' => $modality->id,
            'name' => 'Primer parcial',
            'code' => 'P1',
            'start_date' => '2026-08-10',
            'end_date' => '2026-10-10',
            'is_active' => true,
        ]);
        $partial = CyclePartial::create([
            'school_cycle_id' => $cycle->id,
            'academic_period_id' => $period->id,
            'name' => 'Primer parcial',
            'code' => 'P1',
            'sort_order' => 1,
            'start_date' => '2026-08-10',
            'end_date' => '2026-10-10',
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
        $otherCycleGroup = SchoolCycleGroup::create([
            'school_cycle_id' => $cycle->id,
            'group_id' => $otherGroup->id,
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

        $teacherUser = User::factory()->create([
            'default_campus_id' => $campus->id,
            'name' => 'Profesor Quimica',
        ]);
        $teacherUser->assignRole('teacher');
        $teacherUser->campuses()->sync([$campus->id]);
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'is_active' => true]);

        $studentUser = User::factory()->create([
            'default_campus_id' => $campus->id,
            'name' => 'Alumno Desempeno',
        ]);
        $studentUser->assignRole('student');
        $studentUser->campuses()->sync([$campus->id]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'group_id' => $group->id,
            'enrollment_number' => 'U700001',
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
        $otherAssignment = TeachingAssignment::create([
            'teacher_id' => $teacher->id,
            'group_id' => $otherGroup->id,
            'school_cycle_group_id' => $otherCycleGroup->id,
            'subject_id' => $otherSubject->id,
            'section_number' => 1,
            'is_active' => true,
        ]);

        $schedule = Schedule::create([
            'teaching_assignment_id' => $assignment->id,
            'school_cycle_id' => $cycle->id,
            'section_number' => 1,
            'day_of_week' => 'monday',
            'start_time' => '07:00:00',
            'end_time' => '07:50:00',
            'type' => 'theory',
            'is_active' => true,
        ]);
        Schedule::create([
            'teaching_assignment_id' => $otherAssignment->id,
            'school_cycle_id' => $cycle->id,
            'section_number' => 1,
            'day_of_week' => 'tuesday',
            'start_time' => '07:00:00',
            'end_time' => '07:50:00',
            'type' => 'theory',
            'is_active' => true,
        ]);

        $criterion = EvaluationCriterion::create([
            'teaching_assignment_id' => $assignment->id,
            'cycle_partial_id' => $partial->id,
            'name' => 'Actividades',
            'percentage' => 100,
        ]);
        $activity = Activity::create([
            'teaching_assignment_id' => $assignment->id,
            'evaluation_criterion_id' => $criterion->id,
            'academic_period_id' => $period->id,
            'title' => 'Reporte de laboratorio',
            'max_score' => 10,
            'due_date' => '2026-09-12',
            'evaluation_mode' => 'individual',
            'is_active' => true,
        ]);
        Grade::create([
            'activity_id' => $activity->id,
            'student_id' => $student->id,
            'score' => 8,
        ]);

        $sessionA = AcademicSession::create([
            'teaching_assignment_id' => $assignment->id,
            'schedule_id' => $schedule->id,
            'academic_period_id' => $period->id,
            'session_date' => '2026-09-07',
            'start_time' => '07:00:00',
            'end_time' => '07:50:00',
            'is_cancelled' => false,
        ]);
        $sessionB = AcademicSession::create([
            'teaching_assignment_id' => $assignment->id,
            'schedule_id' => $schedule->id,
            'academic_period_id' => $period->id,
            'session_date' => '2026-09-14',
            'start_time' => '07:00:00',
            'end_time' => '07:50:00',
            'is_cancelled' => false,
        ]);
        Attendance::create([
            'academic_session_id' => $sessionA->id,
            'student_id' => $student->id,
            'status' => 'present',
        ]);
        Attendance::create([
            'academic_session_id' => $sessionB->id,
            'student_id' => $student->id,
            'status' => 'absent',
        ]);

        return compact('campus', 'studentUser', 'assignment');
    }
}
