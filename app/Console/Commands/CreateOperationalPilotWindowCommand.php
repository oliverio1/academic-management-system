<?php

namespace App\Console\Commands;

use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\AttendanceJustification;
use App\Models\CyclePartial;
use App\Models\EvaluationCriterion;
use App\Models\Group;
use App\Models\Practice;
use App\Models\SchoolCycle;
use App\Models\Student;
use App\Models\StudentFollowUp;
use App\Models\StudentSuspension;
use App\Models\TeacherStudentReport;
use App\Models\TeachingAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateOperationalPilotWindowCommand extends Command
{
    protected $signature = 'pilot:operational-window
        {--group=5005 : Grupo para la prueba}
        {--days=5 : Dias naturales de ventana piloto}
        {--cleanup : Solo elimina datos piloto existentes}';

    protected $description = 'Crea una ventana piloto para probar asistencia, entregables, reportes, seguimientos, justificantes y suspensiones.';

    private const MARKER = '[PILOTO]';

    public function handle(): int
    {
        $group = Group::query()->where('name', (string) $this->option('group'))->firstOrFail();
        $cycle = SchoolCycle::query()->where('is_active', true)->orderByDesc('start_date')->firstOrFail();
        $partial = CyclePartial::query()
            ->where('school_cycle_id', $cycle->id)
            ->whereNotNull('academic_period_id')
            ->orderBy('sort_order')
            ->firstOrFail();
        $period = AcademicPeriod::query()->findOrFail((int) $partial->academic_period_id);

        $start = now()->startOfWeek();
        $end = $start->copy()->addDays(max(1, (int) $this->option('days')) - 1);

        $this->cleanup($group, $start, $end);

        if ((bool) $this->option('cleanup')) {
            $this->info('Datos piloto eliminados.');
            return self::SUCCESS;
        }

        $summary = DB::transaction(function () use ($group, $cycle, $partial, $period, $start, $end) {
            $sessions = $this->createSessions($group, $cycle, $period, $start, $end);
            $students = Student::query()
                ->where('group_id', $group->id)
                ->where('is_active', true)
                ->with('user')
                ->orderBy('id')
                ->take(4)
                ->get();

            if ($students->count() < 3) {
                throw new \RuntimeException('El grupo necesita al menos 3 alumnos activos para esta prueba.');
            }

            $assignment = TeachingAssignment::query()
                ->where('group_id', $group->id)
                ->where('is_active', true)
                ->whereHas('subject', fn ($query) => $query->where('name', 'like', '%QUÍMICA%')->orWhere('name', 'like', '%QUIMICA%'))
                ->whereNull('section_type')
                ->with(['subject', 'teacher.user'])
                ->first()
                ?: TeachingAssignment::query()
                    ->where('group_id', $group->id)
                    ->where('is_active', true)
                    ->whereNull('section_type')
                    ->with(['subject', 'teacher.user'])
                    ->firstOrFail();

            $criterion = $this->ensureCriterion($assignment, $partial);
            $practice = $this->ensurePractice($assignment, $criterion, $period, $start);
            $justification = $this->createJustification($students[0], $start);
            $suspension = $this->createSuspension($students[1], $group, $start);
            $report = $this->createReport($assignment, $students[2], $group);
            $followUp = $this->createFollowUp($students[3], $assignment);

            $this->applyJustifiedAttendances($students[0], $group, $start);
            $this->applySuspensionAttendances($suspension, $group);

            return [
                'sessions' => $sessions,
                'practice' => $practice,
                'justification' => $justification,
                'suspension' => $suspension,
                'report' => $report,
                'follow_up' => $followUp,
                'assignment' => $assignment,
                'students' => $students,
            ];
        });

        $this->info('Ventana piloto creada correctamente.');
        $this->line('Grupo: ' . $group->name);
        $this->line('Fechas: ' . $start->toDateString() . ' a ' . $end->toDateString());
        $this->line('Sesiones piloto: ' . $summary['sessions']->count());
        $this->line('Entregable: #' . $summary['practice']->id . ' - ' . $summary['practice']->title);
        $this->line('Justificante: #' . $summary['justification']->id . ' para ' . $summary['students'][0]->user->name);
        $this->line('Suspension: #' . $summary['suspension']->id . ' para ' . $summary['students'][1]->user->name);
        $this->line('Reporte docente: #' . $summary['report']->id . ' para ' . $summary['students'][2]->user->name);
        $this->line('Seguimiento: #' . $summary['follow_up']->id . ' para ' . $summary['students'][3]->user->name);
        $this->line('Profesor base: ' . ($summary['assignment']->teacher?->user?->name ?? 'N/D') . ' / ' . ($summary['assignment']->subject?->name ?? 'N/D'));

        return self::SUCCESS;
    }

    private function cleanup(Group $group, Carbon $start, Carbon $end): void
    {
        DB::transaction(function () use ($group, $start, $end) {
            $pilotSessionIds = AcademicSession::query()
                ->whereHas('teachingAssignment', fn ($query) => $query->where('group_id', $group->id))
                ->whereBetween('session_date', [$start->toDateString(), $end->toDateString()])
                ->whereTime('start_time', '00:01:00')
                ->pluck('id');

            if ($pilotSessionIds->isNotEmpty()) {
                Attendance::query()->whereIn('academic_session_id', $pilotSessionIds)->delete();
                AcademicSession::query()->whereIn('id', $pilotSessionIds)->delete();
            }

            Practice::query()
                ->where('title', 'like', self::MARKER . '%')
                ->whereHas('teachingAssignment', fn ($query) => $query->where('group_id', $group->id))
                ->get()
                ->each(function (Practice $practice) {
                    if ($practice->activity_id) {
                        Activity::query()->whereKey($practice->activity_id)->delete();
                    }
                    $practice->delete();
                });

            Activity::query()
                ->where('title', 'like', self::MARKER . '%')
                ->whereHas('assignment', fn ($query) => $query->where('group_id', $group->id))
                ->delete();

            EvaluationCriterion::query()
                ->where('name', self::MARKER . ' Actividad piloto')
                ->whereIn('teaching_assignment_id', function ($query) use ($group) {
                    $query->select('id')
                        ->from('teaching_assignments')
                        ->where('group_id', $group->id);
                })
                ->delete();

            AttendanceJustification::query()
                ->where('reason', 'like', self::MARKER . '%')
                ->whereHas('student', fn ($query) => $query->where('group_id', $group->id))
                ->delete();

            $suspensionIds = StudentSuspension::query()
                ->where('group_id', $group->id)
                ->where('reason', 'like', self::MARKER . '%')
                ->pluck('id');

            if ($suspensionIds->isNotEmpty()) {
                Attendance::query()
                    ->whereIn('student_suspension_id', $suspensionIds)
                    ->update([
                        'student_suspension_id' => null,
                        'is_suspension_locked' => false,
                    ]);
                StudentSuspension::query()->whereIn('id', $suspensionIds)->delete();
            }

            TeacherStudentReport::query()
                ->where('reason', 'like', self::MARKER . '%')
                ->where('group_id', $group->id)
                ->delete();

            StudentFollowUp::query()
                ->where('message', 'like', self::MARKER . '%')
                ->whereHas('student', fn ($query) => $query->where('group_id', $group->id))
                ->delete();
        });
    }

    private function createSessions(Group $group, SchoolCycle $cycle, AcademicPeriod $period, Carbon $start, Carbon $end)
    {
        $dayMap = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'lunes' => 1,
            'martes' => 2,
            'miercoles' => 3,
            'jueves' => 4,
            'viernes' => 5,
        ];

        $schedules = TeachingAssignment::query()
            ->where('group_id', $group->id)
            ->where('is_active', true)
            ->with(['schedules' => fn ($query) => $query
                ->where('is_active', true)
                ->where('school_cycle_id', $cycle->id)
                ->orderBy('day_of_week')
                ->orderBy('start_time')])
            ->get()
            ->pluck('schedules')
            ->flatten()
            ->filter(fn ($schedule) => isset($dayMap[strtolower((string) $schedule->day_of_week)]))
            ->take(12)
            ->values();

        return $schedules->map(function ($schedule) use ($dayMap, $start, $end, $period) {
            $targetIso = $dayMap[strtolower((string) $schedule->day_of_week)];
            $date = $start->copy();
            while ($date->dayOfWeekIso !== $targetIso) {
                $date->addDay();
            }

            if ($date->gt($end)) {
                return null;
            }

            return AcademicSession::updateOrCreate(
                [
                    'schedule_id' => $schedule->id,
                    'session_date' => $date->toDateString(),
                ],
                [
                    'teaching_assignment_id' => $schedule->teaching_assignment_id,
                    'academic_period_id' => $period->id,
                    'start_time' => '00:01:00',
                    'end_time' => '00:50:00',
                    'is_cancelled' => false,
                ]
            );
        })->filter()->values();
    }

    private function ensureCriterion(TeachingAssignment $assignment, CyclePartial $partial): EvaluationCriterion
    {
        return EvaluationCriterion::firstOrCreate(
            [
                'teaching_assignment_id' => $assignment->id,
                'cycle_partial_id' => $partial->id,
                'name' => self::MARKER . ' Actividad piloto',
            ],
            ['percentage' => 10]
        );
    }

    private function ensurePractice(TeachingAssignment $assignment, EvaluationCriterion $criterion, AcademicPeriod $period, Carbon $start): Practice
    {
        $activity = Activity::create([
            'teaching_assignment_id' => $assignment->id,
            'evaluation_criterion_id' => $criterion->id,
            'academic_period_id' => $period->id,
            'title' => self::MARKER . ' Entregable de prueba',
            'description' => 'Actividad creada para probar el flujo de entregables.',
            'max_score' => 10,
            'due_date' => $start->copy()->addDays(3)->toDateString(),
            'evaluation_mode' => 'individual',
            'is_active' => true,
        ]);

        return Practice::create([
            'teaching_assignment_id' => $assignment->id,
            'activity_id' => $activity->id,
            'number' => 900,
            'kind' => 'task',
            'title' => self::MARKER . ' Entregable de prueba',
            'introduction' => 'Este entregable permite validar captura, envio, revision y PDF.',
            'instructions' => 'Completa los campos solicitados con texto breve y envia el reporte.',
            'procedure' => '1. Lee la consigna. 2. Redacta tus respuestas. 3. Guarda o envia.',
            'submission_fields' => ['objectives', 'development', 'conclusions'],
            'custom_submission_fields' => [
                ['id' => 'objetivo', 'label' => 'Objetivo de la actividad', 'required' => true],
                ['id' => 'desarrollo', 'label' => 'Desarrollo', 'required' => true],
                ['id' => 'conclusion', 'label' => 'Conclusion', 'required' => false],
            ],
            'realization_date' => $start->toDateString(),
            'due_date' => $start->copy()->addDays(3)->toDateString(),
        ]);
    }

    private function createJustification(Student $student, Carbon $start): AttendanceJustification
    {
        return AttendanceJustification::create([
            'student_id' => $student->id,
            'from_date' => $start->toDateString(),
            'to_date' => $start->toDateString(),
            'reason' => self::MARKER . ' Justificante de prueba por cita medica.',
            'document_path' => null,
            'issued_by' => $this->coordinatorUserId(),
            'issued_at' => now(),
        ]);
    }

    private function createSuspension(Student $student, Group $group, Carbon $start): StudentSuspension
    {
        $date = $start->copy()->addDay();

        return StudentSuspension::create([
            'group_id' => $group->id,
            'student_id' => $student->id,
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
            'reason' => self::MARKER . ' Suspension de prueba para validar bloqueo de asistencia.',
            'created_by' => $this->coordinatorUserId(),
        ]);
    }

    private function createReport(TeachingAssignment $assignment, Student $student, Group $group): TeacherStudentReport
    {
        return TeacherStudentReport::create([
            'teacher_id' => $assignment->teacher_id,
            'group_id' => $group->id,
            'student_id' => $student->id,
            'report_type' => 'mixed',
            'reason' => self::MARKER . ' Reporte de prueba para validar revision de coordinacion.',
            'severity' => 2,
            'status' => 'open',
        ]);
    }

    private function createFollowUp(Student $student, TeachingAssignment $assignment): StudentFollowUp
    {
        $followUp = StudentFollowUp::create([
            'student_id' => $student->id,
            'requested_by' => $this->coordinatorUserId(),
            'type' => 'mixed',
            'message' => self::MARKER . ' Seguimiento de prueba para validar respuestas docentes.',
            'status' => 'open',
        ]);

        TeachingAssignment::query()
            ->where('group_id', $student->group_id)
            ->where('is_active', true)
            ->pluck('teacher_id')
            ->unique()
            ->take(5)
            ->each(fn ($teacherId) => $followUp->teachers()->firstOrCreate(['teacher_id' => $teacherId]));

        $followUp->teachers()->firstOrCreate(['teacher_id' => $assignment->teacher_id]);

        return $followUp;
    }

    private function applyJustifiedAttendances(Student $student, Group $group, Carbon $date): void
    {
        $sessions = AcademicSession::query()
            ->whereHas('teachingAssignment', fn ($query) => $query->where('group_id', $group->id))
            ->whereDate('session_date', $date->toDateString())
            ->where('is_cancelled', false)
            ->get();

        foreach ($sessions as $session) {
            Attendance::updateOrCreate(
                [
                    'academic_session_id' => $session->id,
                    'student_id' => $student->id,
                ],
                ['status' => 'justified']
            );
        }
    }

    private function applySuspensionAttendances(StudentSuspension $suspension, Group $group): void
    {
        $sessions = AcademicSession::query()
            ->whereHas('teachingAssignment', fn ($query) => $query->where('group_id', $group->id))
            ->whereBetween('session_date', [
                $suspension->start_date->toDateString(),
                $suspension->end_date->toDateString(),
            ])
            ->where('is_cancelled', false)
            ->get();

        foreach ($sessions as $session) {
            Attendance::updateOrCreate(
                [
                    'academic_session_id' => $session->id,
                    'student_id' => $suspension->student_id,
                ],
                [
                    'status' => 'absent',
                    'student_suspension_id' => $suspension->id,
                    'is_suspension_locked' => true,
                ]
            );
        }
    }

    private function coordinatorUserId(): int
    {
        return (int) (
            User::role('coordinator')->orderBy('id')->value('id')
            ?: User::query()->orderBy('id')->value('id')
        );
    }
}
