<?php

namespace App\Console\Commands;

use App\Models\AcademicSession;
use App\Models\Activity;
use App\Models\CoordinationReport;
use App\Models\CyclePartial;
use App\Models\EvaluationCriterion;
use App\Models\Grade;
use App\Models\SchoolCase;
use App\Models\SchoolCaseAction;
use App\Models\SchoolCaseEntry;
use App\Models\SchoolCycle;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\AcademicSessionGeneratorService;
use App\Services\DidacticPlanActivityGeneratorService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SeedFinalReportsDemoDataCommand extends Command
{
    protected $signature = 'demo:seed-final-reports
        {--cycle= : ID, codigo o nombre del ciclo; por defecto usa el ciclo activo mas reciente}
        {--weeks=4 : Semanas lectivas a poblar para asistencia, actividades y calificaciones}
        {--per-group=25 : Alumnos ficticios por grupo activo}
        {--password=123123123 : Contrasena inicial para alumnos/tutores ficticios}
        {--fresh : Elimina datos demo anteriores antes de sembrar}';

    protected $description = 'Puebla datos demo integrales para disenar reportes finales.';

    private const MARKER = 'DEMO FINAL AMS';

    public function handle(
        AcademicSessionGeneratorService $sessionGenerator,
        DidacticPlanActivityGeneratorService $planActivityGenerator
    ): int {
        $cycle = $this->resolveCycle();
        $weeks = max(1, min(12, (int) $this->option('weeks')));
        $days = $weeks * 5;

        $this->info('Preparando datos demo integrales...');
        $this->line('Ciclo: ' . $cycle->name);

        $this->call('fake:cycle-students', [
            '--per-group' => max(1, min(60, (int) $this->option('per-group'))),
            '--password' => (string) $this->option('password'),
        ]);

        $sessionsCreated = $this->generateSessions($cycle, $sessionGenerator);

        $operationalOptions = [
            '--cycle' => (string) $cycle->id,
            '--days' => $days,
        ];
        if ((bool) $this->option('fresh')) {
            $operationalOptions['--fresh'] = true;
        }
        $this->call('demo:seed-operational-week', $operationalOptions);

        $summary = DB::transaction(function () use ($cycle, $weeks, $planActivityGenerator) {
            if ((bool) $this->option('fresh')) {
                $this->cleanupDemoData();
            }

            $assignments = $this->activeAssignments($cycle);
            $partials = $cycle->partials()->where('is_active', true)->get();
            $actor = $this->coordinationUser();

            $criteriaCount = $this->ensureCriteria($assignments, $partials);
            $planActivities = $this->generateActivitiesFromFinalPlans($assignments, $planActivityGenerator);
            $activitySummary = $this->seedActivitiesAndGrades($assignments, $cycle, $weeks);
            $coordinationReports = $this->seedCoordinationReports($cycle, $actor);
            $schoolCases = $this->seedSchoolCases($cycle, $actor);

            return [
                'assignments' => $assignments->count(),
                'criteria' => $criteriaCount,
                'plan_session_activities' => $planActivities,
                'activities' => $activitySummary['activities'],
                'grades' => $activitySummary['grades'],
                'coordination_reports' => $coordinationReports,
                'school_cases' => $schoolCases,
            ];
        });

        $summary['sessions_created'] = $sessionsCreated;

        $this->info('Datos demo integrales listos.');
        $this->table(
            ['Metrica', 'Total'],
            [
                ['Sesiones nuevas generadas', $summary['sessions_created']],
                ['Asignaciones activas procesadas', $summary['assignments']],
                ['Rubros creados/verificados', $summary['criteria']],
                ['Actividades desde planeacion final', $summary['plan_session_activities']],
                ['Actividades demo creadas/verificadas', $summary['activities']],
                ['Calificaciones creadas/actualizadas', $summary['grades']],
                ['Reportes de tutor/coordinacion', $summary['coordination_reports']],
                ['Casos escolares', $summary['school_cases']],
            ]
        );

        return self::SUCCESS;
    }

    private function resolveCycle(): SchoolCycle
    {
        $cycleOption = trim((string) $this->option('cycle'));

        if ($cycleOption !== '') {
            return SchoolCycle::query()
                ->whereKey($cycleOption)
                ->orWhere('code', $cycleOption)
                ->orWhere('name', 'like', '%' . $cycleOption . '%')
                ->orderByDesc('start_date')
                ->firstOrFail();
        }

        return SchoolCycle::query()
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->firstOrFail();
    }

    private function generateSessions(SchoolCycle $cycle, AcademicSessionGeneratorService $sessionGenerator): int
    {
        $created = 0;

        $this->activeAssignments($cycle)->each(function (TeachingAssignment $assignment) use (&$created, $sessionGenerator) {
            $created += $sessionGenerator->generateForAssignment($assignment);
        });

        return $created;
    }

    private function cleanupDemoData(): void
    {
        $activityIds = Activity::query()
            ->where('title', 'like', '%' . self::MARKER . '%')
            ->orWhere('description', 'like', '%' . self::MARKER . '%')
            ->pluck('id');

        if ($activityIds->isNotEmpty()) {
            Grade::query()->whereIn('activity_id', $activityIds)->delete();
            Activity::query()->whereIn('id', $activityIds)->delete();
        }

        $caseIds = SchoolCase::query()
            ->where('subject', 'like', '%' . self::MARKER . '%')
            ->orWhere('description', 'like', '%' . self::MARKER . '%')
            ->pluck('id');

        if ($caseIds->isNotEmpty()) {
            SchoolCaseAction::query()->whereIn('school_case_id', $caseIds)->delete();
            SchoolCaseEntry::query()->whereIn('school_case_id', $caseIds)->delete();
            SchoolCase::query()->whereIn('id', $caseIds)->delete();
        }

        CoordinationReport::query()
            ->where('subject', 'like', '%' . self::MARKER . '%')
            ->orWhere('description', 'like', '%' . self::MARKER . '%')
            ->delete();
    }

    private function activeAssignments(SchoolCycle $cycle): Collection
    {
        return TeachingAssignment::query()
            ->where('is_active', true)
            ->whereHas('schoolCycleGroup', fn ($query) => $query->where('school_cycle_id', $cycle->id))
            ->with([
                'teacher.user',
                'group.students.user',
                'students.user',
                'subject',
                'schoolCycleGroup',
                'didacticPlans.items',
                'evaluationCriteria',
            ])
            ->orderBy('group_id')
            ->orderBy('subject_id')
            ->orderBy('section_type')
            ->get();
    }

    private function ensureCriteria(Collection $assignments, Collection $partials): int
    {
        $count = 0;
        $criteria = [
            ['Asistencia', 10],
            ['Evaluacion continua', 50],
            ['Examen de periodo', 40],
        ];

        foreach ($assignments as $assignment) {
            foreach ($partials as $partial) {
                foreach ($criteria as [$name, $percentage]) {
                    EvaluationCriterion::query()->updateOrCreate(
                        [
                            'teaching_assignment_id' => $assignment->id,
                            'cycle_partial_id' => $partial->id,
                            'name' => $name,
                        ],
                        ['percentage' => $percentage]
                    );
                    $count++;
                }
            }
        }

        return $count;
    }

    private function generateActivitiesFromFinalPlans(
        Collection $assignments,
        DidacticPlanActivityGeneratorService $generator
    ): int {
        $count = 0;

        foreach ($assignments as $assignment) {
            $plan = $assignment->didacticPlans
                ->where('status', 'final')
                ->sortByDesc('updated_at')
                ->first();

            if (! $plan) {
                continue;
            }

            $result = $generator->generate($plan, ['replace' => false]);
            $count += (int) ($result['session_activities'] ?? 0);
        }

        return $count;
    }

    private function seedActivitiesAndGrades(Collection $assignments, SchoolCycle $cycle, int $weeks): array
    {
        $activityCount = 0;
        $gradeCount = 0;
        $endDate = $cycle->start_date->copy()->addWeeks($weeks)->endOfWeek();

        foreach ($assignments as $assignment) {
            $students = $this->studentsForAssignment($assignment);
            if ($students->isEmpty()) {
                continue;
            }

            $sessions = AcademicSession::query()
                ->where('teaching_assignment_id', $assignment->id)
                ->where('is_cancelled', false)
                ->whereBetween('session_date', [
                    $cycle->start_date->toDateString(),
                    $endDate->toDateString(),
                ])
                ->orderBy('session_date')
                ->get();

            if ($sessions->isEmpty()) {
                continue;
            }

            $buckets = $sessions->groupBy(fn (AcademicSession $session) => $session->session_date->format('o-W'));
            foreach ($buckets->take($weeks)->values() as $weekIndex => $weekSessions) {
                $session = $weekSessions->first();
                $partial = $this->partialForDate($cycle, $session->session_date);
                $criterion = $this->continuousCriterion($assignment, $partial);

                if (! $criterion || ! $session->academic_period_id) {
                    continue;
                }

                $title = sprintf(
                    '%s - %s semana %s',
                    self::MARKER,
                    $assignment->subject?->name ?: 'Actividad',
                    $weekIndex + 1
                );

                $activity = Activity::query()->updateOrCreate(
                    [
                        'teaching_assignment_id' => $assignment->id,
                        'title' => $title,
                    ],
                    [
                        'session_activity_id' => null,
                        'evaluation_criterion_id' => $criterion->id,
                        'academic_period_id' => $session->academic_period_id,
                        'max_score' => 10,
                        'due_date' => $session->session_date->copy()->addDays(3)->toDateString(),
                        'description' => self::MARKER . ' - Evidencia demo para tablero de entregables y reportes finales.',
                        'evaluation_mode' => 'individual',
                        'is_active' => true,
                    ]
                );
                $activityCount++;

                foreach ($students as $student) {
                    Grade::query()->updateOrCreate(
                        [
                            'activity_id' => $activity->id,
                            'student_id' => $student->id,
                        ],
                        [
                            'score' => $this->demoScore($student->id, $activity->id),
                            'comments' => self::MARKER . ' - Calificacion demo.',
                        ]
                    );
                    $gradeCount++;
                }
            }
        }

        return ['activities' => $activityCount, 'grades' => $gradeCount];
    }

    private function seedCoordinationReports(SchoolCycle $cycle, User $actor): int
    {
        $students = $this->cycleStudents($cycle)->take(18)->values();
        $templates = [
            ['phone', 'academic', 2, 'Tutor solicita retroalimentacion academica'],
            ['email', 'attendance', 3, 'Tutor reporta falta de respuesta sobre asistencias'],
            ['in_person', 'behavioral', 2, 'Alumno reporta conflicto en salon'],
            ['phone', 'facilities', 1, 'Reporte sobre proyector de aula'],
            ['in_person', 'other', 2, 'Solicitud de seguimiento por cambio de grupo'],
        ];

        foreach ($students as $index => $student) {
            [$via, $category, $priority, $subject] = $templates[$index % count($templates)];
            $reviewed = $index % 4 === 0;
            $guardian = $student->guardian;

            CoordinationReport::query()->create([
                'campus_id' => $cycle->campus_id,
                'reported_by' => $actor->id,
                'received_via' => $via,
                'reporter_name' => $guardian?->name ?: 'Tutor demo',
                'reporter_contact' => $guardian?->email ?: 'tutor.demo@stress.local',
                'category' => $category,
                'subject' => self::MARKER . ' - ' . $subject,
                'description' => self::MARKER . ' - Reporte recibido sobre '
                    . ($student->user?->name ?: 'alumno demo')
                    . ', matricula ' . $student->enrollment_number
                    . ', grupo ' . ($student->group?->name ?: 'N/D')
                    . '. Se captura informacion suficiente para seguimiento.',
                'priority' => $priority,
                'status' => $reviewed ? 'reviewed' : 'open',
                'reviewed_by' => $reviewed ? $actor->id : null,
                'reviewed_at' => $reviewed ? now()->subDays(1) : null,
            ]);
        }

        return $students->count();
    }

    private function seedSchoolCases(SchoolCycle $cycle, User $actor): int
    {
        $students = $this->cycleStudents($cycle)->skip(4)->take(12)->values();
        $assignees = $this->caseAssignees($actor);
        $statuses = [
            SchoolCase::STATUS_NEW,
            SchoolCase::STATUS_ASSIGNED,
            SchoolCase::STATUS_IN_PROGRESS,
            SchoolCase::STATUS_WAITING_RESPONSE,
            SchoolCase::STATUS_RESOLVED,
            SchoolCase::STATUS_CLOSED,
        ];
        $priorities = ['low', 'medium', 'high'];

        foreach ($students as $index => $student) {
            $status = $statuses[$index % count($statuses)];
            $assignee = $assignees->get($index % $assignees->count(), $actor);
            $closed = in_array($status, [SchoolCase::STATUS_RESOLVED, SchoolCase::STATUS_CLOSED], true);

            $case = SchoolCase::query()->create([
                'case_number' => 'DF-' . now()->format('ymd') . '-' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'campus_id' => $cycle->campus_id,
                'source_type' => 'coordination_report',
                'source_user_id' => $actor->id,
                'target_type' => $index % 5 === 0 ? 'situation' : 'student',
                'student_id' => $index % 5 === 0 ? null : $student->id,
                'group_id' => $student->group_id,
                'teacher_id' => null,
                'guardian_user_id' => $student->guardian_user_id,
                'location' => $index % 5 === 0 ? 'Salon ' . ($student->group?->name ?: 'N/D') : null,
                'category' => ['academic', 'attendance', 'behavioral', 'family', 'facilities'][$index % 5],
                'priority' => $priorities[$index % count($priorities)],
                'status' => $status,
                'subject' => self::MARKER . ' - Caso escolar demo ' . ($index + 1),
                'description' => self::MARKER . ' - Caso creado para validar tablero final, responsables, acciones y cierre.',
                'assigned_to' => in_array($status, [SchoolCase::STATUS_NEW, SchoolCase::STATUS_REVIEWED], true) ? null : $assignee->id,
                'due_at' => now()->addDays(($index % 7) + 1),
                'public_response' => $closed ? 'Se atendio el caso y se registro respuesta para el tutor o interesado.' : null,
                'closed_at' => $closed ? now()->subHours($index + 1) : null,
                'closed_by' => $closed ? $actor->id : null,
            ]);

            SchoolCaseEntry::query()->create([
                'school_case_id' => $case->id,
                'user_id' => $actor->id,
                'entry_type' => 'note',
                'visibility' => 'internal',
                'body' => self::MARKER . ' - Nota interna inicial con resumen y contexto del seguimiento.',
                'meta' => ['demo' => true],
            ]);

            SchoolCaseEntry::query()->create([
                'school_case_id' => $case->id,
                'user_id' => $actor->id,
                'entry_type' => 'response',
                'visibility' => 'public',
                'body' => self::MARKER . ' - Acuse de recibido para quien genero el reporte.',
                'meta' => ['demo' => true],
            ]);

            foreach (['Contactar tutor', 'Solicitar observacion docente'] as $position => $title) {
                $completed = $closed || ($status === SchoolCase::STATUS_IN_PROGRESS && $position === 0);
                SchoolCaseAction::query()->create([
                    'school_case_id' => $case->id,
                    'title' => self::MARKER . ' - ' . $title,
                    'assigned_to' => $assignee->id,
                    'due_at' => now()->addDays($position + 1),
                    'status' => $completed ? 'completed' : 'pending',
                    'completed_at' => $completed ? now()->subHours($position + 2) : null,
                    'notes' => self::MARKER . ' - Accion demo para medir pendientes y tiempos de respuesta.',
                ]);
            }
        }

        return $students->count();
    }

    private function partialForDate(SchoolCycle $cycle, Carbon $date): ?CyclePartial
    {
        return $cycle->partials
            ->first(fn (CyclePartial $partial) => $date->betweenIncluded($partial->start_date, $partial->end_date));
    }

    private function continuousCriterion(TeachingAssignment $assignment, ?CyclePartial $partial): ?EvaluationCriterion
    {
        if (! $partial) {
            return null;
        }

        return EvaluationCriterion::query()
            ->where('teaching_assignment_id', $assignment->id)
            ->where('cycle_partial_id', $partial->id)
            ->where('name', 'like', '%continua%')
            ->first();
    }

    private function studentsForAssignment(TeachingAssignment $assignment): Collection
    {
        return ($assignment->students->isNotEmpty()
            ? $assignment->students
            : ($assignment->group?->students ?? collect()))
            ->where('is_active', true)
            ->values();
    }

    private function cycleStudents(SchoolCycle $cycle): Collection
    {
        return Student::query()
            ->where('is_active', true)
            ->whereHas('group.cycleConfigurations', fn ($query) => $query
                ->where('school_cycle_id', $cycle->id)
                ->where('is_active', true))
            ->with(['user', 'guardian', 'group'])
            ->orderBy('group_id')
            ->orderBy('id')
            ->get();
    }

    private function coordinationUser(): User
    {
        return User::query()->find(7)
            ?: User::role('coordinator')->orderBy('id')->first()
            ?: User::role('admin')->orderBy('id')->first()
            ?: User::query()->orderBy('id')->firstOrFail();
    }

    private function caseAssignees(User $fallback): Collection
    {
        $users = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['coordinator', 'admin', 'direction']))
            ->orderBy('id')
            ->get();

        return $users->isNotEmpty() ? $users : collect([$fallback]);
    }

    private function demoScore(int $studentId, int $activityId): float
    {
        $score = 6 + ((abs(crc32($studentId . '|' . $activityId . '|' . self::MARKER)) % 41) / 10);

        return round(min(10, $score), 1);
    }
}
