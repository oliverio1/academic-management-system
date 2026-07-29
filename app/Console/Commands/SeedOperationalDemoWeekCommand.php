<?php

namespace App\Console\Commands;

use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\AttendanceJustification;
use App\Models\Group;
use App\Models\PrefectDailyAttendance;
use App\Models\PrefectIncidentReport;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Student;
use App\Models\StudentFollowUp;
use App\Models\StudentFollowUpResponse;
use App\Models\StudentFollowUpTeacher;
use App\Models\StudentGroupHistory;
use App\Models\StudentIncidentReport;
use App\Models\StudentSuspension;
use App\Models\TeacherStudentReport;
use App\Models\TeachingAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SeedOperationalDemoWeekCommand extends Command
{
    protected $signature = 'demo:seed-operational-week
        {--cycle= : ID o nombre del ciclo escolar; por defecto usa el ciclo activo mas reciente}
        {--start= : Fecha inicial YYYY-MM-DD; por defecto usa el inicio del ciclo}
        {--days=5 : Dias habiles a poblar}
        {--fresh : Elimina datos DEMO AMS anteriores antes de sembrar}';

    protected $description = 'Puebla una semana demo con asistencia de prefectura/docentes, reportes, justificantes, suspensiones y seguimientos.';

    private const MARKER = 'DEMO AMS';

    public function handle(): int
    {
        $cycle = $this->resolveCycle();
        $dates = $this->resolveDates($cycle);

        $summary = DB::transaction(function () use ($cycle, $dates) {
            if ((bool) $this->option('fresh')) {
                $this->cleanupDemoData();
            }

            $actor = $this->coordinationUser();
            $prefect = $this->ensurePrefectUser($cycle);
            $groups = $this->activeGroups($cycle);

            if ($groups->isEmpty()) {
                throw new \RuntimeException('No hay grupos activos con alumnos para poblar la semana demo.');
            }

            $students = $groups->pluck('students')->flatten();
            $this->ensureGroupHistories($students, $cycle);

            $summary = [
                'groups' => $groups->count(),
                'students' => $students->count(),
                'prefect_attendances' => $this->seedPrefectAttendance($groups, $dates, $prefect),
                'teacher_attendances' => $this->seedTeacherAttendance($cycle, $dates),
                'justifications' => $this->seedJustifications($groups, $dates, $actor),
                'suspensions' => $this->seedSuspensions($groups, $dates, $actor),
                'teacher_reports' => $this->seedTeacherReports($cycle, $dates, $actor),
                'student_reports' => $this->seedStudentReports($groups, $actor),
                'prefect_reports' => $this->seedPrefectReports($prefect, $actor),
                'follow_ups' => $this->seedFollowUps($groups, $cycle, $actor),
                'prefect_email' => $prefect->email,
            ];

            return $summary;
        });

        $this->info('Semana demo operativa poblada correctamente.');
        $this->line('Ciclo: ' . $cycle->name);
        $this->line('Fechas: ' . $dates->first()->format('d/m/Y') . ' a ' . $dates->last()->format('d/m/Y'));
        $this->table(
            ['Dato', 'Total'],
            [
                ['Grupos con alumnos', $summary['groups']],
                ['Alumnos considerados', $summary['students']],
                ['Asistencias de prefectura', $summary['prefect_attendances']],
                ['Asistencias por clase', $summary['teacher_attendances']],
                ['Justificantes', $summary['justifications']],
                ['Suspensiones', $summary['suspensions']],
                ['Reportes docentes', $summary['teacher_reports']],
                ['Reportes de alumnos', $summary['student_reports']],
                ['Reportes de prefectura', $summary['prefect_reports']],
                ['Seguimientos', $summary['follow_ups']],
            ]
        );
        $this->line('Usuario demo prefectura: ' . $summary['prefect_email'] . ' / password');

        return self::SUCCESS;
    }

    private function resolveCycle(): SchoolCycle
    {
        $cycleOption = $this->option('cycle');

        if ($cycleOption) {
            return SchoolCycle::query()
                ->whereKey($cycleOption)
                ->orWhere('name', 'like', '%' . $cycleOption . '%')
                ->orderByDesc('start_date')
                ->firstOrFail();
        }

        return SchoolCycle::query()
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->firstOrFail();
    }

    private function resolveDates(SchoolCycle $cycle): Collection
    {
        $targetDays = max(1, (int) $this->option('days'));
        $date = $this->option('start')
            ? Carbon::parse((string) $this->option('start'))->startOfDay()
            : $cycle->start_date->copy()->startOfDay();

        $dates = collect();
        while ($dates->count() < $targetDays) {
            if ($date->isWeekday()) {
                $dates->push($date->copy());
            }
            $date->addDay();
        }

        return $dates;
    }

    private function cleanupDemoData(): void
    {
        $followUpIds = StudentFollowUp::query()
            ->where('message', 'like', '%' . self::MARKER . '%')
            ->pluck('id');

        if ($followUpIds->isNotEmpty()) {
            $followUpTeacherIds = StudentFollowUpTeacher::query()
                ->whereIn('student_follow_up_id', $followUpIds)
                ->pluck('id');

            StudentFollowUpResponse::query()
                ->whereIn('student_follow_up_teacher_id', $followUpTeacherIds)
                ->delete();
            StudentFollowUpTeacher::query()->whereIn('student_follow_up_id', $followUpIds)->delete();
            StudentFollowUp::query()->whereIn('id', $followUpIds)->delete();
        }

        TeacherStudentReport::query()->where('reason', 'like', '%' . self::MARKER . '%')->delete();
        StudentIncidentReport::query()
            ->where('subject', 'like', '%' . self::MARKER . '%')
            ->orWhere('description', 'like', '%' . self::MARKER . '%')
            ->delete();
        PrefectIncidentReport::query()
            ->where('subject', 'like', '%' . self::MARKER . '%')
            ->orWhere('description', 'like', '%' . self::MARKER . '%')
            ->delete();
        AttendanceJustification::query()->where('reason', 'like', '%' . self::MARKER . '%')->delete();

        $suspensionIds = StudentSuspension::query()
            ->where('reason', 'like', '%' . self::MARKER . '%')
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
    }

    private function coordinationUser(): User
    {
        return User::query()->find(7)
            ?: User::role('coordinator')->orderBy('id')->first()
            ?: User::query()->orderBy('id')->firstOrFail();
    }

    private function ensurePrefectUser(SchoolCycle $cycle): User
    {
        Role::findOrCreate('prefect');

        $prefect = User::firstOrCreate(
            ['email' => 'prefectura.demo@ula.local'],
            [
                'name' => 'Prefectura Demo AMS',
                'default_campus_id' => $cycle->campus_id,
                'password' => Hash::make('password'),
            ]
        );

        if (! $prefect->hasRole('prefect')) {
            $prefect->assignRole('prefect');
        }

        if ($cycle->campus_id) {
            $prefect->campuses()->syncWithoutDetaching([(int) $cycle->campus_id]);
        }

        return $prefect;
    }

    private function activeGroups(SchoolCycle $cycle): Collection
    {
        return SchoolCycleGroup::query()
            ->where('school_cycle_id', $cycle->id)
            ->where('is_active', true)
            ->with(['group.students' => fn ($query) => $query
                ->where('is_active', true)
                ->with('user')
                ->orderBy('id')])
            ->get()
            ->pluck('group')
            ->filter(fn (?Group $group) => $group && $group->students->isNotEmpty())
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    private function ensureGroupHistories(Collection $students, SchoolCycle $cycle): void
    {
        foreach ($students as $student) {
            if (! $student->group_id) {
                continue;
            }

            StudentGroupHistory::firstOrCreate(
                [
                    'student_id' => $student->id,
                    'group_id' => $student->group_id,
                    'start_date' => $cycle->start_date->toDateString(),
                ],
                [
                    'end_date' => null,
                    'reason' => self::MARKER . ' - Historial activo para demostracion.',
                ]
            );
        }
    }

    private function seedPrefectAttendance(Collection $groups, Collection $dates, User $prefect): int
    {
        $count = 0;

        foreach ($dates as $date) {
            foreach ($groups as $group) {
                foreach ($group->students as $student) {
                    $score = $this->score($student->id, $date->toDateString(), 'prefect');
                    $status = $score < 7 ? 'absent' : 'present';

                    PrefectDailyAttendance::updateOrCreate(
                        [
                            'student_id' => $student->id,
                            'attendance_date' => $date->toDateString(),
                        ],
                        [
                            'group_id' => $group->id,
                            'status' => $status,
                            'recorded_by' => $prefect->id,
                        ]
                    );
                    $count++;
                }
            }
        }

        return $count;
    }

    private function seedTeacherAttendance(SchoolCycle $cycle, Collection $dates): int
    {
        $sessions = AcademicSession::query()
            ->whereBetween('session_date', [
                $dates->first()->toDateString(),
                $dates->last()->toDateString(),
            ])
            ->where('is_cancelled', false)
            ->whereHas('teachingAssignment.schoolCycleGroup', fn ($query) => $query
                ->where('school_cycle_id', $cycle->id))
            ->with(['teachingAssignment.students.user', 'teachingAssignment.group.students.user', 'teachingAssignment.teacher'])
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        $count = 0;

        foreach ($sessions as $session) {
            $assignment = $session->teachingAssignment;
            if (! $assignment) {
                continue;
            }

            $students = $assignment->students->isNotEmpty()
                ? $assignment->students
                : ($assignment->group?->students ?? collect());

            foreach ($students->where('is_active', true) as $student) {
                $prefectAttendance = PrefectDailyAttendance::query()
                    ->where('student_id', $student->id)
                    ->whereDate('attendance_date', $session->session_date)
                    ->first();

                $score = $this->score($student->id, $session->id, 'class');
                $status = match (true) {
                    $prefectAttendance?->status === 'absent' => 'absent',
                    $score < 4 => 'absent',
                    $score < 10 => 'late',
                    default => 'present',
                };

                Attendance::updateOrCreate(
                    [
                        'academic_session_id' => $session->id,
                        'student_id' => $student->id,
                    ],
                    [
                        'status' => $status,
                        'student_suspension_id' => null,
                        'is_suspension_locked' => false,
                    ]
                );
                $count++;
            }

            $closedAt = $session->session_date->copy()->setTimeFromTimeString((string) ($session->end_time ?: '14:30:00'));
            $session->forceFill([
                'attendance_closed_at' => $closedAt,
                'attendance_closed_by' => $assignment->teacher_id,
            ])->save();
        }

        return $count;
    }

    private function seedJustifications(Collection $groups, Collection $dates, User $actor): int
    {
        $students = $this->sampleStudents($groups, 8);
        $reasons = [
            'Consulta medica programada',
            'Tramite familiar impostergable',
            'Estudios de laboratorio',
            'Cita odontologica',
        ];

        foreach ($students as $index => $student) {
            $date = $dates->get(($index % max(1, $dates->count() - 1)) + 1, $dates->first());

            AttendanceJustification::create([
                'student_id' => $student->id,
                'from_date' => $date->toDateString(),
                'to_date' => $date->toDateString(),
                'reason' => self::MARKER . ' - ' . $reasons[$index % count($reasons)] . '.',
                'document_path' => null,
                'issued_by' => $actor->id,
                'issued_at' => now(),
            ]);

            PrefectDailyAttendance::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'attendance_date' => $date->toDateString(),
                ],
                [
                    'group_id' => $student->group_id,
                    'status' => 'justified',
                    'recorded_by' => $actor->id,
                ]
            );

            Attendance::query()
                ->where('student_id', $student->id)
                ->whereHas('academicSession', fn ($query) => $query->whereDate('session_date', $date->toDateString()))
                ->update(['status' => 'justified']);
        }

        return $students->count();
    }

    private function seedSuspensions(Collection $groups, Collection $dates, User $actor): int
    {
        $students = $this->sampleStudents($groups, 5, 9);
        $startDate = $dates->get(3, $dates->last());
        $endDate = $dates->last();
        $count = 0;

        foreach ($students as $index => $student) {
            $suspension = StudentSuspension::create([
                'group_id' => $student->group_id,
                'student_id' => $student->id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'reason' => self::MARKER . ' - Suspension demo por acumulacion de reportes conductuales.',
                'created_by' => $actor->id,
            ]);

            $sessions = AcademicSession::query()
                ->whereBetween('session_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->where('is_cancelled', false)
                ->whereHas('teachingAssignment', fn ($query) => $query->where('group_id', $student->group_id))
                ->get();

            foreach ($sessions as $session) {
                Attendance::updateOrCreate(
                    [
                        'academic_session_id' => $session->id,
                        'student_id' => $student->id,
                    ],
                    [
                        'status' => 'absent',
                        'student_suspension_id' => $suspension->id,
                        'is_suspension_locked' => true,
                    ]
                );
            }

            foreach ($dates->filter(fn (Carbon $date) => $date->betweenIncluded($startDate, $endDate)) as $date) {
                PrefectDailyAttendance::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'attendance_date' => $date->toDateString(),
                    ],
                    [
                        'group_id' => $student->group_id,
                        'status' => 'absent',
                        'recorded_by' => $actor->id,
                    ]
                );
            }

            $count++;
        }

        return $count;
    }

    private function seedTeacherReports(SchoolCycle $cycle, Collection $dates, User $actor): int
    {
        $assignments = TeachingAssignment::query()
            ->where('is_active', true)
            ->whereHas('schoolCycleGroup', fn ($query) => $query->where('school_cycle_id', $cycle->id))
            ->with(['students.user', 'group.students.user'])
            ->orderBy('id')
            ->get()
            ->filter(fn (TeachingAssignment $assignment) => $this->studentsForAssignment($assignment)->isNotEmpty())
            ->values();

        $templates = [
            ['academic', 1, 'No entrego actividad asignada y requiere seguimiento de habitos de estudio.'],
            ['behavioral', 2, 'Interrumpio la clase en repetidas ocasiones durante la sesion.'],
            ['mixed', 3, 'Acumula inasistencias y bajo desempeno en actividades recientes.'],
            ['academic', 2, 'Se observa dificultad para completar ejercicios sin acompanamiento.'],
        ];

        $count = 0;
        foreach ($assignments->take(28) as $index => $assignment) {
            $students = $this->studentsForAssignment($assignment)->values();
            $student = $students->get($index % $students->count());
            $template = $templates[$index % count($templates)];
            $reviewed = $index % 4 === 0;

            TeacherStudentReport::create([
                'teacher_id' => $assignment->teacher_id,
                'group_id' => $assignment->group_id,
                'student_id' => $student->id,
                'report_type' => $template[0],
                'reason' => self::MARKER . ' - ' . $template[2],
                'severity' => $template[1],
                'status' => $reviewed ? 'reviewed' : 'open',
                'reviewed_by' => $reviewed ? $actor->id : null,
                'reviewed_at' => $reviewed ? $dates->last()->copy()->setTime(13, 30) : null,
            ]);
            $count++;
        }

        return $count;
    }

    private function seedStudentReports(Collection $groups, User $actor): int
    {
        $students = $this->sampleStudents($groups, 7, 15);
        $subjects = [
            'Solicitud de apoyo academico',
            'Duda sobre seguimiento de asistencia',
            'Reporte de convivencia en aula',
            'Solicitud de revision de actividad',
        ];

        foreach ($students as $index => $student) {
            $status = $index % 3 === 0 ? 'reviewed' : 'open';
            StudentIncidentReport::create([
                'student_id' => $student->id,
                'report_to' => 'coordination',
                'category' => $index % 2 === 0 ? 'academic' : 'behavioral',
                'subject' => self::MARKER . ' - ' . $subjects[$index % count($subjects)],
                'description' => self::MARKER . ' - El alumno solicita revision y acompanamiento de coordinacion.',
                'status' => $status,
                'reviewed_by' => $status === 'reviewed' ? $actor->id : null,
                'reviewed_at' => $status === 'reviewed' ? now() : null,
            ]);
        }

        return $students->count();
    }

    private function seedPrefectReports(User $prefect, User $actor): int
    {
        $subjects = [
            ['discipline', 'Alumno fuera de salon en horario de clase'],
            ['attendance', 'Grupo con retraso en primera hora'],
            ['safety', 'Incidente menor en pasillo'],
            ['discipline', 'Uso de celular durante cambio de clase'],
            ['attendance', 'Alumno detectado ausente en clase posterior'],
            ['other', 'Solicitud de apoyo en entrada principal'],
        ];

        foreach ($subjects as $index => [$category, $subject]) {
            $status = $index % 3 === 0 ? 'reviewed' : 'open';
            PrefectIncidentReport::create([
                'reported_by' => $prefect->id,
                'report_to' => 'coordination',
                'category' => $category,
                'subject' => self::MARKER . ' - ' . $subject,
                'description' => self::MARKER . ' - Registro demo para mostrar el flujo de prefectura y revision.',
                'status' => $status,
                'reviewed_by' => $status === 'reviewed' ? $actor->id : null,
                'reviewed_at' => $status === 'reviewed' ? now() : null,
            ]);
        }

        return count($subjects);
    }

    private function seedFollowUps(Collection $groups, SchoolCycle $cycle, User $actor): int
    {
        $students = $this->sampleStudents($groups, 10, 25);
        $messages = [
            'Solicito comentarios sobre participacion y cumplimiento durante la semana.',
            'Favor de confirmar si el alumno requiere apoyo academico adicional.',
            'Se solicita observacion por inasistencias intermitentes.',
            'Favor de registrar si hubo avances despues del reporte de conducta.',
        ];

        foreach ($students as $index => $student) {
            $followUp = StudentFollowUp::create([
                'student_id' => $student->id,
                'requested_by' => $actor->id,
                'type' => $index % 2 === 0 ? 'mixed' : 'academic',
                'message' => self::MARKER . ' - ' . $messages[$index % count($messages)],
                'status' => 'open',
            ]);

            $teacherIds = TeachingAssignment::query()
                ->where('group_id', $student->group_id)
                ->where('is_active', true)
                ->whereHas('schoolCycleGroup', fn ($query) => $query->where('school_cycle_id', $cycle->id))
                ->pluck('teacher_id')
                ->filter()
                ->unique()
                ->values()
                ->take(4);

            foreach ($teacherIds as $position => $teacherId) {
                $state = $this->followUpState($index, $position, $teacherIds->count());
                $followUpTeacher = StudentFollowUpTeacher::create([
                    'student_follow_up_id' => $followUp->id,
                    'teacher_id' => $teacherId,
                    'status' => $state,
                    'answered_at' => $state === StudentFollowUpTeacher::STATUS_ANSWERED ? now()->subHours($position + 1) : null,
                ]);

                if ($state === StudentFollowUpTeacher::STATUS_ANSWERED) {
                    StudentFollowUpResponse::create([
                        'student_follow_up_teacher_id' => $followUpTeacher->id,
                        'questionnaire' => [
                            'academic_performance' => 'Cumple de forma irregular; requiere reforzar instrucciones y entrega de actividades.',
                            'behavioral_performance' => 'Se muestra respetuoso, aunque necesita mejorar puntualidad y participacion.',
                            'recommendations' => 'Mantener comunicacion con tutor y revisar avances en una semana.',
                        ],
                        'comments' => self::MARKER . ' - Respuesta docente demo para mostrar el seguimiento.',
                    ]);
                }
            }

            $followUp->checkAndCloseIfCompleted();
        }

        return $students->count();
    }

    private function studentsForAssignment(TeachingAssignment $assignment): Collection
    {
        return ($assignment->students->isNotEmpty()
            ? $assignment->students
            : ($assignment->group?->students ?? collect()))
            ->where('is_active', true)
            ->values();
    }

    private function sampleStudents(Collection $groups, int $limit, int $offset = 0): Collection
    {
        return $groups
            ->flatMap(fn (Group $group) => $group->students->values()->skip($offset % max(1, $group->students->count()))->take(1))
            ->filter()
            ->values()
            ->take($limit);
    }

    private function followUpState(int $followUpIndex, int $position, int $total): string
    {
        if ($followUpIndex % 3 === 0) {
            return StudentFollowUpTeacher::STATUS_ANSWERED;
        }

        if ($followUpIndex % 3 === 1 && $position === 0) {
            return StudentFollowUpTeacher::STATUS_ANSWERED;
        }

        return StudentFollowUpTeacher::STATUS_PENDING;
    }

    private function score(int|string ...$parts): int
    {
        return abs(crc32(implode('|', $parts))) % 100;
    }
}
