<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Campus;
use App\Models\CyclePartial;
use App\Models\EvaluationCriterion;
use App\Models\Group;
use App\Models\Level;
use App\Models\Modality;
use App\Models\Schedule;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TeacherActivityCloneFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['teacher', 'student'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_teacher_clones_activities_to_same_subject_assignment(): void
    {
        $scenario = $this->activityScenario();

        $sourceCriterion = EvaluationCriterion::create([
            'teaching_assignment_id' => $scenario['sourceAssignment']->id,
            'cycle_partial_id' => $scenario['partial']->id,
            'name' => 'Actividades',
            'percentage' => 60,
        ]);
        $targetCriterion = EvaluationCriterion::create([
            'teaching_assignment_id' => $scenario['targetAssignment']->id,
            'cycle_partial_id' => $scenario['partial']->id,
            'name' => 'Actividades',
            'percentage' => 60,
        ]);
        $secondTargetCriterion = EvaluationCriterion::create([
            'teaching_assignment_id' => $scenario['secondTargetAssignment']->id,
            'cycle_partial_id' => $scenario['partial']->id,
            'name' => 'Actividades',
            'percentage' => 60,
        ]);
        EvaluationCriterion::create([
            'teaching_assignment_id' => $scenario['otherSubjectAssignment']->id,
            'cycle_partial_id' => $scenario['partial']->id,
            'name' => 'Actividades',
            'percentage' => 60,
        ]);

        $activity = Activity::create([
            'teaching_assignment_id' => $scenario['sourceAssignment']->id,
            'evaluation_criterion_id' => $sourceCriterion->id,
            'academic_period_id' => $scenario['period']->id,
            'title' => 'Cuestionario de enlaces quimicos',
            'max_score' => 10,
            'due_date' => now()->addWeek()->toDateString(),
            'description' => 'Resolver cuestionario en plataforma.',
            'evaluation_mode' => 'individual',
            'is_active' => true,
        ]);
        Activity::create([
            'teaching_assignment_id' => $scenario['sourceAssignment']->id,
            'evaluation_criterion_id' => $sourceCriterion->id,
            'academic_period_id' => $scenario['period']->id,
            'title' => 'Actividad no seleccionada',
            'max_score' => 10,
            'due_date' => now()->addWeeks(2)->toDateString(),
            'description' => 'No debe clonarse.',
            'evaluation_mode' => 'individual',
            'is_active' => true,
        ]);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->get(route('teacher.evaluation.activities', $scenario['sourceAssignment']))
            ->assertOk()
            ->assertSee('Clonar actividades a la misma materia')
            ->assertSee('Grupo 5004')
            ->assertSee('Grupo 5005')
            ->assertDontSee('Grupo 6001');

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('teacher.evaluation.activities.clone-same-subject', $scenario['sourceAssignment']), [
                'to_assignment_ids' => [
                    $scenario['targetAssignment']->id,
                    $scenario['secondTargetAssignment']->id,
                ],
                'activity_ids' => [$activity->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('activities', [
            'teaching_assignment_id' => $scenario['targetAssignment']->id,
            'evaluation_criterion_id' => $targetCriterion->id,
            'academic_period_id' => $scenario['period']->id,
            'title' => 'Cuestionario de enlaces quimicos',
            'max_score' => 10,
            'description' => 'Resolver cuestionario en plataforma.',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('activities', [
            'teaching_assignment_id' => $scenario['secondTargetAssignment']->id,
            'evaluation_criterion_id' => $secondTargetCriterion->id,
            'academic_period_id' => $scenario['period']->id,
            'title' => 'Cuestionario de enlaces quimicos',
            'description' => 'Resolver cuestionario en plataforma.',
            'is_active' => true,
        ]);
        $this->assertDatabaseMissing('activities', [
            'teaching_assignment_id' => $scenario['targetAssignment']->id,
            'title' => 'Actividad no seleccionada',
        ]);
        $this->assertDatabaseMissing('activities', [
            'teaching_assignment_id' => $scenario['secondTargetAssignment']->id,
            'title' => 'Actividad no seleccionada',
        ]);
        $this->assertDatabaseMissing('activities', [
            'teaching_assignment_id' => $scenario['otherSubjectAssignment']->id,
            'title' => 'Cuestionario de enlaces quimicos',
        ]);
    }

    public function test_teacher_cannot_clone_activities_without_matching_target_criteria(): void
    {
        $scenario = $this->activityScenario();

        $sourceCriterion = EvaluationCriterion::create([
            'teaching_assignment_id' => $scenario['sourceAssignment']->id,
            'cycle_partial_id' => $scenario['partial']->id,
            'name' => 'Actividades',
            'percentage' => 100,
        ]);
        $activity = Activity::create([
            'teaching_assignment_id' => $scenario['sourceAssignment']->id,
            'evaluation_criterion_id' => $sourceCriterion->id,
            'academic_period_id' => $scenario['period']->id,
            'title' => 'Actividad sin rubro destino',
            'max_score' => 10,
            'due_date' => now()->addWeek()->toDateString(),
            'evaluation_mode' => 'individual',
            'is_active' => true,
        ]);

        $this->actingAs($scenario['teacherUser'])
            ->withSession(['active_campus_id' => $scenario['campus']->id])
            ->post(route('teacher.evaluation.activities.clone-same-subject', $scenario['sourceAssignment']), [
                'to_assignment_ids' => [$scenario['targetAssignment']->id],
                'activity_ids' => [$activity->id],
            ])
            ->assertSessionHasErrors('clone');

        $this->assertDatabaseMissing('activities', [
            'teaching_assignment_id' => $scenario['targetAssignment']->id,
            'title' => 'Actividad sin rubro destino',
        ]);
    }

    private function activityScenario(): array
    {
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
        $partial = CyclePartial::create([
            'school_cycle_id' => $cycle->id,
            'academic_period_id' => $period->id,
            'name' => 'Primer parcial',
            'code' => 'P1',
            'sort_order' => 1,
            'start_date' => $period->start_date,
            'end_date' => $period->end_date,
            'is_active' => true,
        ]);
        $chemistry = Subject::create([
            'level_id' => $level->id,
            'name' => 'Quimica III',
            'hours_per_week' => 3,
            'type' => Subject::TYPE_THEORETICAL,
            'is_active' => true,
        ]);
        $math = Subject::create([
            'level_id' => $level->id,
            'name' => 'Matematicas V',
            'hours_per_week' => 5,
            'type' => Subject::TYPE_THEORETICAL,
            'is_active' => true,
        ]);

        $teacherUser = $this->teacherUser($campus);
        $sourceAssignment = $this->assignment($teacherUser->teacher->id, $cycle, $level, $chemistry, '5003');
        $targetAssignment = $this->assignment($teacherUser->teacher->id, $cycle, $level, $chemistry, '5005');
        $secondTargetAssignment = $this->assignment($teacherUser->teacher->id, $cycle, $level, $chemistry, '5004');
        $otherSubjectAssignment = $this->assignment($teacherUser->teacher->id, $cycle, $level, $math, '6001');

        return compact(
            'campus',
            'teacherUser',
            'cycle',
            'period',
            'partial',
            'sourceAssignment',
            'targetAssignment',
            'secondTargetAssignment',
            'otherSubjectAssignment'
        );
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

    private function assignment(int $teacherId, SchoolCycle $cycle, Level $level, Subject $subject, string $groupName): TeachingAssignment
    {
        $group = Group::create([
            'level_id' => $level->id,
            'name' => $groupName,
            'capacity' => 30,
            'is_active' => true,
        ]);
        $cycleGroup = SchoolCycleGroup::create([
            'school_cycle_id' => $cycle->id,
            'group_id' => $group->id,
            'campus_id' => $cycle->campus_id,
            'modality_id' => $cycle->modality_id,
            'section_count' => 1,
            'is_active' => true,
        ]);
        $cycleGroup->subjects()->syncWithoutDetaching([$subject->id]);

        $assignment = TeachingAssignment::create([
            'teacher_id' => $teacherId,
            'group_id' => $group->id,
            'school_cycle_group_id' => $cycleGroup->id,
            'subject_id' => $subject->id,
            'section_number' => 1,
            'is_active' => true,
        ]);

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

        return $assignment;
    }
}
