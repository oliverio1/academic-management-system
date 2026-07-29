<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\CyclePartial;
use App\Models\DidacticPlan;
use App\Models\EvaluationCriterion;
use App\Models\Group;
use App\Models\Level;
use App\Models\Modality;
use App\Models\Schedule;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\SessionActivity;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Temario;
use App\Models\TemarioPoint;
use App\Models\User;
use App\Services\DidacticPlanActivityGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DidacticPlanActivityGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_activities_from_final_plan_only_for_theory_sessions(): void
    {
        $scenario = $this->scenario();
        $summary = app(DidacticPlanActivityGeneratorService::class)
            ->generate($scenario['plan']);

        $this->assertSame(2, $summary['session_activities_created']);
        $this->assertSame(2, $summary['activities_created']);

        $this->assertSame(2, SessionActivity::query()->count());
        $this->assertSame(2, \App\Models\Activity::query()->count());
        $this->assertDatabaseMissing('session_activities', [
            'academic_session_id' => $scenario['lab_session']->id,
        ]);
    }

    public function test_replace_updates_existing_session_activities_from_edited_plan(): void
    {
        $scenario = $this->scenario();
        $service = app(DidacticPlanActivityGeneratorService::class);
        $service->generate($scenario['plan']);

        $scenario['plan']->items()->first()->update([
            'development' => 'Contenido editado por el profesor.',
        ]);

        $summary = $service->generate($scenario['plan']->refresh(), ['replace' => true]);

        $this->assertSame(2, $summary['session_activities_updated']);
        $this->assertStringContainsString(
            'Contenido editado por el profesor.',
            SessionActivity::query()->orderBy('id')->first()->description
        );
    }

    private function scenario(): array
    {
        $modality = Modality::create(['name' => 'PREPARATORIA', 'is_active' => true]);
        $level = Level::create(['modality_id' => $modality->id, 'name' => 'Quinto', 'is_active' => true]);
        $group = Group::create(['level_id' => $level->id, 'name' => '5005', 'capacity' => 30, 'is_active' => true]);
        $cycle = SchoolCycle::create([
            'modality_id' => $modality->id,
            'name' => 'Preparatoria 2026-2027',
            'code' => 'PREPA',
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
            'is_active' => true,
        ]);
        $period = AcademicPeriod::create([
            'modality_id' => $modality->id,
            'name' => 'Primer parcial',
            'code' => 'P1',
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
            'is_active' => true,
        ]);
        $partial = CyclePartial::create([
            'school_cycle_id' => $cycle->id,
            'academic_period_id' => $period->id,
            'name' => 'Periodo 1',
            'code' => 'P1',
            'sort_order' => 1,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
            'is_active' => true,
        ]);
        $subject = Subject::create([
            'level_id' => $level->id,
            'name' => 'Quimica III',
            'hours_per_week' => 3,
            'type' => Subject::TYPE_THEORETICAL_PRACTICAL,
            'is_active' => true,
        ]);
        $teacherUser = User::factory()->create();
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'is_active' => true]);
        $cycleGroup = SchoolCycleGroup::create([
            'school_cycle_id' => $cycle->id,
            'group_id' => $group->id,
            'modality_id' => $modality->id,
            'section_count' => 1,
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

        $theoryMonday = $this->schedule($assignment, $cycle, 'lunes', '07:00:00', '07:50:00', null);
        $theoryThursday = $this->schedule($assignment, $cycle, 'jueves', '07:00:00', '07:50:00', null);
        $lab = $this->schedule($assignment, $cycle, 'martes', '07:00:00', '07:50:00', 'lab_taller');

        $sessionA = $this->academicSession($assignment, $period, $theoryMonday, '2026-08-03');
        $sessionB = $this->academicSession($assignment, $period, $theoryThursday, '2026-08-06');
        $labSession = $this->academicSession($assignment, $period, $lab, '2026-08-04');

        EvaluationCriterion::create([
            'teaching_assignment_id' => $assignment->id,
            'cycle_partial_id' => $partial->id,
            'name' => 'Evaluacion continua',
            'percentage' => 20,
        ]);

        $temario = Temario::create(['subject_id' => $subject->id, 'title' => 'Temario', 'description' => 'Objetivo general']);
        $unit = TemarioPoint::create(['temario_id' => $temario->id, 'position' => 1, 'label' => '1', 'level' => 1, 'type' => 'conceptual', 'content' => 'Unidad']);
        $topic = TemarioPoint::create(['temario_id' => $temario->id, 'position' => 2, 'label' => '1.1', 'level' => 2, 'type' => 'conceptual', 'content' => 'Tema']);
        $subtopic = TemarioPoint::create(['temario_id' => $temario->id, 'position' => 3, 'label' => '1.1.1', 'level' => 3, 'type' => 'conceptual', 'content' => 'Subtema']);

        $plan = DidacticPlan::create([
            'teaching_assignment_id' => $assignment->id,
            'school_cycle_id' => $cycle->id,
            'academic_period_id' => $period->id,
            'title' => 'Planeacion final',
            'status' => DidacticPlan::STATUS_FINAL,
            'is_active' => true,
        ]);
        foreach ([$sessionA, $sessionB] as $index => $session) {
            $plan->items()->create([
                'position' => $index + 1,
                'field_training_point_id' => $unit->id,
                'temario_point_id' => $topic->id,
                'temario_subtopic_ids' => [$subtopic->id],
                'opening' => 'Apertura',
                'development' => 'Desarrollo '.$index,
                'closing' => 'Cierre',
                'resources' => 'Cuaderno',
                'evaluation' => 'Ejercicios',
                'start_date' => $session->session_date,
                'end_date' => $session->session_date,
            ]);
        }

        return [
            'plan' => $plan,
            'lab_session' => $labSession,
        ];
    }

    private function schedule(TeachingAssignment $assignment, SchoolCycle $cycle, string $day, string $start, string $end, ?string $sectionType): Schedule
    {
        return Schedule::create([
            'teaching_assignment_id' => $assignment->id,
            'school_cycle_id' => $cycle->id,
            'section_number' => 1,
            'section_type' => $sectionType,
            'section_label' => $sectionType ? 'A' : null,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
            'type' => $sectionType ? 'lab' : 'theory',
            'is_active' => true,
        ]);
    }

    private function academicSession(TeachingAssignment $assignment, AcademicPeriod $period, Schedule $schedule, string $date): AcademicSession
    {
        return AcademicSession::create([
            'teaching_assignment_id' => $assignment->id,
            'schedule_id' => $schedule->id,
            'academic_period_id' => $period->id,
            'session_date' => $date,
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'is_cancelled' => false,
        ]);
    }
}
