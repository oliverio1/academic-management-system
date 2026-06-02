<?php

namespace App\Console\Commands;

use App\Models\AcademicSession;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Campus;
use App\Models\CyclePartial;
use App\Models\EvaluationCriterion;
use App\Models\Group;
use App\Models\PrefectDailyAttendance;
use App\Models\PrefectIncidentReport;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Student;
use App\Models\StudentIncidentReport;
use App\Models\TeacherStudentReport;
use App\Models\TeachingAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SimulateFloridaCycleData extends Command
{
    protected $signature = 'simulate:florida-cycle
        {--cycle= : ID o texto del ciclo (ej. 7 o 26-3 Florida)}
        {--campus=Florida : Nombre del campus objetivo}
        {--modality=Bachillerato : Nombre de modalidad}
        {--from=2026-05-11 : Fecha inicio (Y-m-d)}
        {--to=2026-06-05 : Fecha fin (Y-m-d)}
        {--skip-teacher-id= : ID de docente a excluir de actividades}
        {--teacher-reports=25 : Cantidad de reportes de docentes a simular}
        {--prefect-reports=10 : Cantidad de reportes de prefectura a simular}
        {--student-reports=18 : Cantidad de reportes de alumnos a simular}
        {--dry-run : Solo analiza y muestra conteos}';

    protected $description = 'Simula asistencias, actividades/calificaciones y reportes para pruebas del ciclo de Bachillerato Florida.';

    public function handle(): int
    {
        try {
            $from = Carbon::parse((string) $this->option('from'))->startOfDay();
            $to = Carbon::parse((string) $this->option('to'))->endOfDay();
        } catch (\Throwable) {
            $this->error('Fechas inválidas. Usa formato Y-m-d.');
            return self::FAILURE;
        }

        if ($from->gt($to)) {
            $this->error('La fecha inicial no puede ser mayor que la final.');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $campus = Campus::query()
            ->where('name', 'like', '%' . trim((string) $this->option('campus')) . '%')
            ->first();

        if (! $campus) {
            $this->error('No encontré el campus solicitado.');
            return self::FAILURE;
        }

        $cycleOption = trim((string) $this->option('cycle'));
        $cycleQuery = SchoolCycle::query()
            ->with(['modality', 'modalities'])
            ->where('campus_id', $campus->id);

        if ($cycleOption !== '') {
            if (is_numeric($cycleOption)) {
                $cycleQuery->where('id', (int) $cycleOption);
            } else {
                $cycleQuery->where(function ($q) use ($cycleOption) {
                    $q->where('name', 'like', '%' . $cycleOption . '%')
                        ->orWhere('code', 'like', '%' . $cycleOption . '%');
                });
            }
        } else {
            $cycleQuery->where('name', 'like', '%26-3%');
        }

        $cycle = $cycleQuery->orderByDesc('start_date')->first();
        if (! $cycle) {
            $this->error('No se encontró el ciclo objetivo para ese campus.');
            return self::FAILURE;
        }

        $modalityName = trim((string) $this->option('modality'));
        $modalityId = (int) optional($cycle->modality)->id;
        if ($modalityName !== '') {
            $matchByCurrent = optional($cycle->modality)->name
                && stripos((string) $cycle->modality->name, $modalityName) !== false;
            $matchByLinked = $cycle->modalities->contains(fn ($m) => stripos((string) $m->name, $modalityName) !== false);
            if (! $matchByCurrent && ! $matchByLinked) {
                $this->warn('El ciclo no parece vinculado a la modalidad indicada; continuaré con la modalidad del ciclo.');
            }
        }

        $cycleGroups = SchoolCycleGroup::query()
            ->where('school_cycle_id', $cycle->id)
            ->where('campus_id', $campus->id)
            ->where('is_active', true)
            ->when($modalityId > 0, fn ($q) => $q->where('modality_id', $modalityId))
            ->get(['id', 'group_id']);

        if ($cycleGroups->isEmpty()) {
            $this->error('No hay grupos activos del ciclo/modalidad/campus objetivo.');
            return self::FAILURE;
        }

        $groupIds = $cycleGroups->pluck('group_id')->map(fn ($id) => (int) $id)->unique()->values();
        $cycleGroupIds = $cycleGroups->pluck('id')->map(fn ($id) => (int) $id)->unique()->values();

        $groups = Group::query()->whereIn('id', $groupIds->all())->get()->keyBy('id');
        $students = Student::query()
            ->whereIn('group_id', $groupIds->all())
            ->where('is_active', true)
            ->get(['id', 'group_id']);

        if ($students->isEmpty()) {
            $this->error('No hay alumnos activos en esos grupos.');
            return self::FAILURE;
        }

        $studentsByGroup = $students->groupBy('group_id');

        $assignments = TeachingAssignment::query()
            ->with(['teacher.user', 'subject'])
            ->where('is_active', true)
            ->whereIn('group_id', $groupIds->all())
            ->whereIn('school_cycle_group_id', $cycleGroupIds->all())
            ->whereHas('schedules', fn ($q) => $q
                ->where('school_cycle_id', $cycle->id)
                ->where('is_active', true)
            )
            ->get();

        if ($assignments->isEmpty()) {
            $this->error('No hay asignaciones activas para esos grupos en el ciclo.');
            return self::FAILURE;
        }

        $partials = CyclePartial::query()
            ->where('school_cycle_id', $cycle->id)
            ->whereNotNull('academic_period_id')
            ->orderBy('start_date')
            ->get();

        if ($partials->isEmpty()) {
            $this->error('No hay parciales con periodo académico en el ciclo.');
            return self::FAILURE;
        }

        $periodIdByDate = [];
        foreach ($from->copy()->startOfDay()->daysUntil($to->copy()->addDay()) as $date) {
            $dateStr = $date->toDateString();
            $partial = $partials->first(fn ($p) =>
                $p->start_date && $p->end_date &&
                Carbon::parse($p->start_date)->startOfDay()->lte($date) &&
                Carbon::parse($p->end_date)->endOfDay()->gte($date)
            ) ?: $partials->first();
            $periodIdByDate[$dateStr] = (int) $partial->academic_period_id;
        }

        $skipTeacherId = (int) ($this->option('skip-teacher-id') ?: 0);
        if ($skipTeacherId <= 0) {
            $skipTeacherId = (int) $assignments
                ->pluck('teacher_id')
                ->filter()
                ->unique()
                ->sort()
                ->first();
        }

        $prefectUserId = (int) (User::role('prefect')->value('id') ?: 0);
        if ($prefectUserId <= 0) {
            $prefectUserId = (int) (User::role('coordinator')->value('id') ?: User::query()->value('id'));
        }

        $teacherReportsCount = max(1, (int) $this->option('teacher-reports'));
        $prefectReportsCount = max(1, (int) $this->option('prefect-reports'));
        $studentReportsCount = max(1, (int) $this->option('student-reports'));

        $this->line('Ciclo: ' . $cycle->name . ' (ID ' . $cycle->id . ')');
        $this->line('Campus: ' . $campus->name . ' (ID ' . $campus->id . ')');
        $this->line('Rango: ' . $from->toDateString() . ' a ' . $to->toDateString());
        $this->line('Grupos: ' . $groupIds->count() . ' | Alumnos: ' . $students->count() . ' | Asignaciones: ' . $assignments->count());
        $this->line('Docente excluido para actividades: ' . $skipTeacherId);

        if ($dryRun) {
            $this->comment('Dry-run activado: no se guardarán cambios.');
            return self::SUCCESS;
        }

        $stats = [
            'sessions_created' => 0,
            'attendance_rows' => 0,
            'prefect_rows' => 0,
            'activities_created' => 0,
            'grades_rows' => 0,
            'teacher_reports' => 0,
            'prefect_reports' => 0,
            'student_reports' => 0,
        ];

        DB::transaction(function () use (
            $assignments,
            $studentsByGroup,
            $from,
            $to,
            $cycle,
            $periodIdByDate,
            $students,
            $groups,
            $prefectUserId,
            $skipTeacherId,
            $teacherReportsCount,
            $prefectReportsCount,
            $studentReportsCount,
            &$stats
        ) {
            $sessionsByAssignment = collect();

            foreach ($assignments as $assignment) {
                $schedules = $assignment->schedules()
                    ->where('school_cycle_id', $cycle->id)
                    ->where('is_active', true)
                    ->get();

                $assignmentSessions = collect();

                foreach ($schedules as $schedule) {
                    foreach ($this->datesMatchingSchedule($from, $to, (string) $schedule->day_of_week) as $date) {
                        $dateStr = $date->toDateString();
                        $periodId = (int) ($periodIdByDate[$dateStr] ?? 0);
                        if ($periodId <= 0) {
                            continue;
                        }

                        $session = AcademicSession::query()->firstOrCreate(
                            [
                                'schedule_id' => (int) $schedule->id,
                                'session_date' => $dateStr,
                            ],
                            [
                                'teaching_assignment_id' => (int) $assignment->id,
                                'academic_period_id' => $periodId,
                                'start_time' => $schedule->start_time,
                                'end_time' => $schedule->end_time,
                                'is_cancelled' => false,
                            ]
                        );

                        if ($session->wasRecentlyCreated) {
                            $stats['sessions_created']++;
                        }

                        $assignmentSessions->push($session);
                    }
                }

                $sessionsByAssignment->put((int) $assignment->id, $assignmentSessions->unique('id')->values());
            }

            foreach ($sessionsByAssignment as $assignmentId => $sessions) {
                /** @var TeachingAssignment $assignment */
                $assignment = $assignments->firstWhere('id', (int) $assignmentId);
                if (! $assignment) {
                    continue;
                }

                $groupStudents = $studentsByGroup->get((int) $assignment->group_id, collect());
                if ($groupStudents->isEmpty()) {
                    continue;
                }

                foreach ($sessions as $session) {
                    foreach ($groupStudents as $student) {
                        Attendance::query()->updateOrCreate(
                            [
                                'academic_session_id' => (int) $session->id,
                                'student_id' => (int) $student->id,
                            ],
                            [
                                'status' => $this->randomAttendanceStatus(),
                            ]
                        );
                        $stats['attendance_rows']++;
                    }
                }
            }

            foreach ($this->weekdayDates($from, $to) as $date) {
                $dateStr = $date->toDateString();
                foreach ($students as $student) {
                    $group = $groups->get((int) $student->group_id);
                    if (! $group) {
                        continue;
                    }

                    PrefectDailyAttendance::query()->updateOrCreate(
                        [
                            'student_id' => (int) $student->id,
                            'attendance_date' => $dateStr,
                        ],
                        [
                            'group_id' => (int) $group->id,
                            'status' => $this->randomPrefectStatus(),
                            'recorded_by' => $prefectUserId,
                        ]
                    );
                    $stats['prefect_rows']++;
                }
            }

            foreach ($assignments as $assignment) {
                if ((int) $assignment->teacher_id === $skipTeacherId) {
                    continue;
                }

                $criterion = EvaluationCriterion::query()
                    ->where('teaching_assignment_id', (int) $assignment->id)
                    ->orderByDesc(DB::raw('cycle_partial_id IS NOT NULL'))
                    ->orderBy('id')
                    ->first();

                if (! $criterion) {
                    $criterion = EvaluationCriterion::query()->firstOrCreate(
                        [
                            'teaching_assignment_id' => (int) $assignment->id,
                            'name' => 'Simulacion',
                        ],
                        [
                            'percentage' => 100,
                        ]
                    );
                }

                $sessions = $sessionsByAssignment->get((int) $assignment->id, collect())
                    ->sortBy('session_date')
                    ->values();

                if ($sessions->isEmpty()) {
                    continue;
                }

                $groupStudents = $studentsByGroup->get((int) $assignment->group_id, collect());
                if ($groupStudents->isEmpty()) {
                    continue;
                }

                $sessionsByWeek = $sessions->groupBy(fn ($s) => Carbon::parse($s->session_date)->startOfWeek()->toDateString());
                foreach ($sessionsByWeek as $weekKey => $weekSessions) {
                    $session = $weekSessions->first();
                    $dueDate = Carbon::parse($session->session_date)->toDateString();
                    $periodId = (int) $session->academic_period_id;
                    if ($periodId <= 0) {
                        continue;
                    }

                    $title = '[SIM FL26-3] ' . ($assignment->subject->name ?? 'Actividad') . ' - Semana ' . Carbon::parse($weekKey)->format('Ymd');
                    $activity = Activity::query()->firstOrCreate(
                        [
                            'teaching_assignment_id' => (int) $assignment->id,
                            'title' => $title,
                        ],
                        [
                            'session_activity_id' => null,
                            'evaluation_criterion_id' => (int) $criterion->id,
                            'academic_period_id' => $periodId,
                            'max_score' => 10,
                            'due_date' => $dueDate,
                            'description' => 'Actividad de simulación para pruebas de alertas.',
                            'evaluation_mode' => 'individual',
                            'is_active' => true,
                        ]
                    );

                    if ($activity->wasRecentlyCreated) {
                        $stats['activities_created']++;
                    }

                    foreach ($groupStudents as $student) {
                        DB::table('grades')->updateOrInsert(
                            [
                                'activity_id' => (int) $activity->id,
                                'student_id' => (int) $student->id,
                            ],
                            [
                                'score' => $this->randomScore(),
                                'comments' => 'Simulación FL 26-3',
                                'updated_at' => now(),
                                'created_at' => now(),
                            ]
                        );
                        $stats['grades_rows']++;
                    }
                }
            }

            $teacherPool = $assignments
                ->filter(fn ($a) => $a->teacher_id && $a->group_id)
                ->values();

            for ($i = 1; $i <= $teacherReportsCount; $i++) {
                if ($teacherPool->isEmpty()) {
                    break;
                }
                $assignment = $teacherPool->random();
                $groupStudents = $studentsByGroup->get((int) $assignment->group_id, collect());
                if ($groupStudents->isEmpty()) {
                    continue;
                }
                $student = $groupStudents->random();

                TeacherStudentReport::query()->create([
                    'teacher_id' => (int) $assignment->teacher_id,
                    'group_id' => (int) $assignment->group_id,
                    'student_id' => (int) $student->id,
                    'report_type' => collect(['academic', 'behavioral', 'mixed'])->random(),
                    'reason' => '[SIM FL26-3] Reporte de prueba #' . $i . ' (' . now()->format('Y-m-d H:i:s') . ')',
                    'severity' => random_int(1, 3),
                    'status' => collect(['open', 'reviewed'])->random(),
                ]);
                $stats['teacher_reports']++;
            }

            $prefects = User::role('prefect')->pluck('id')->map(fn ($id) => (int) $id)->values();
            $prefectReporterId = (int) ($prefects->first() ?: $prefectUserId);
            $reportToOptions = ['teacher', 'prefect', 'coordination', 'psychologist', 'director', 'other'];
            $categoryOptions = ['facilities', 'classmates', 'academic', 'behavioral', 'other'];

            for ($i = 1; $i <= $prefectReportsCount; $i++) {
                PrefectIncidentReport::query()->create([
                    'reported_by' => $prefectReporterId,
                    'report_to' => $reportToOptions[array_rand($reportToOptions)],
                    'category' => $categoryOptions[array_rand($categoryOptions)],
                    'subject' => '[SIM FL26-3] Reporte prefectura #' . $i,
                    'description' => 'Simulación de reporte prefectura para pruebas de coordinación.',
                    'status' => collect(['open', 'reviewed', 'resolved'])->random(),
                ]);
                $stats['prefect_reports']++;
            }

            $studentPool = $students->values();
            for ($i = 1; $i <= $studentReportsCount; $i++) {
                if ($studentPool->isEmpty()) {
                    break;
                }
                $student = $studentPool->random();
                StudentIncidentReport::query()->create([
                    'student_id' => (int) $student->id,
                    'report_to' => $reportToOptions[array_rand($reportToOptions)],
                    'category' => $categoryOptions[array_rand($categoryOptions)],
                    'subject' => '[SIM FL26-3] Reporte alumno #' . $i,
                    'description' => 'Simulación de reporte alumno para pruebas de coordinación.',
                    'status' => collect(['open', 'reviewed', 'resolved'])->random(),
                ]);
                $stats['student_reports']++;
            }
        });

        $this->info('Simulación completada ✅');
        foreach ($stats as $key => $value) {
            $this->line(str_pad($key, 22, ' ') . ': ' . $value);
        }

        return self::SUCCESS;
    }

    private function weekdayDates(Carbon $from, Carbon $to): Collection
    {
        $dates = collect();
        $cursor = $from->copy()->startOfDay();
        while ($cursor->lte($to)) {
            if ($cursor->isWeekday()) {
                $dates->push($cursor->copy());
            }
            $cursor->addDay();
        }
        return $dates;
    }

    private function datesMatchingSchedule(Carbon $from, Carbon $to, string $dayOfWeek): Collection
    {
        $targetIso = $this->resolveDayOfWeekIso($dayOfWeek);
        if (! $targetIso) {
            return collect();
        }

        $dates = collect();
        $cursor = $from->copy()->startOfDay();
        while ($cursor->lte($to)) {
            if ($cursor->dayOfWeekIso === $targetIso) {
                $dates->push($cursor->copy());
            }
            $cursor->addDay();
        }
        return $dates;
    }

    private function resolveDayOfWeekIso(string $value): ?int
    {
        $value = strtolower(trim($value));
        if (is_numeric($value)) {
            $num = (int) $value;
            return $num >= 1 && $num <= 7 ? $num : null;
        }

        return match ($value) {
            'monday', 'lunes' => 1,
            'tuesday', 'martes' => 2,
            'wednesday', 'miercoles', 'miércoles' => 3,
            'thursday', 'jueves' => 4,
            'friday', 'viernes' => 5,
            'saturday', 'sabado', 'sábado' => 6,
            'sunday', 'domingo' => 7,
            default => null,
        };
    }

    private function randomAttendanceStatus(): string
    {
        $pool = array_merge(
            array_fill(0, 84, 'present'),
            array_fill(0, 8, 'late'),
            array_fill(0, 8, 'absent')
        );
        return $pool[array_rand($pool)];
    }

    private function randomPrefectStatus(): string
    {
        $pool = array_merge(
            array_fill(0, 88, 'present'),
            array_fill(0, 6, 'late'),
            array_fill(0, 6, 'absent')
        );
        return $pool[array_rand($pool)];
    }

    private function randomScore(): float
    {
        return round(mt_rand(60, 100) / 10, 1);
    }
}

