<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\CoordinationReport;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Student;
use App\Models\StudentIncidentReport;
use App\Models\Teacher;
use App\Models\TeacherStudentReport;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Models\PrefectIncidentReport;
use App\Services\CurrentSchoolCycle;
use App\Notifications\CoordinatorReviewNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Http\Request;

class TeacherStudentReportController extends Controller
{
    private const TYPE_OPTIONS = [
        'academic' => 'Academico',
        'behavioral' => 'Conductual',
        'mixed' => 'Mixto',
    ];

    public function index()
    {
        $teacher = auth()->user()->teacher;
        $allowedGroupIds = $this->activeCampusGroupIds();

        $reports = TeacherStudentReport::query()
            ->with(['student.user', 'group'])
            ->where('teacher_id', $teacher->id)
            ->when(!empty($allowedGroupIds), fn ($q) => $q->whereIn('group_id', $allowedGroupIds))
            ->orderByDesc('created_at')
            ->get();

        return view('teacher.reports.index', [
            'reports' => $reports,
            'typeOptions' => self::TYPE_OPTIONS,
        ]);
    }

    public function create()
    {
        $teacher = auth()->user()->teacher;
        $activeCampusId = (int) session('active_campus_id', 0);
        $activeCycleId = $this->activeCycleIdForCampus($activeCampusId);

        $groupIds = TeachingAssignment::query()
            ->where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->when(
                $activeCampusId > 0,
                fn ($query) => $query->whereHas('schedules.schoolCycle', function ($cycleQuery) use ($activeCampusId) {
                    $this->applyCampusFilterToCycleQuery($cycleQuery, $activeCampusId);
                })
            )
            ->when(
                $activeCycleId,
                fn ($query) => $query->whereHas('schedules', function ($scheduleQuery) use ($activeCycleId) {
                    $scheduleQuery->where('school_cycle_id', (int) $activeCycleId)
                        ->where('is_active', true);
                })
            )
            ->pluck('group_id')
            ->unique()
            ->values();

        $groups = Group::query()
            ->with(['students' => function ($query) {
                $query->where('is_active', true)
                    ->with('user')
                    ->join('users', 'users.id', '=', 'students.user_id')
                    ->orderBy('users.name')
                    ->select('students.*');
            }])
            ->whereIn('id', $groupIds)
            ->orderBy('name')
            ->get();

        if ($activeCampusId > 0) {
            $groups->each(function ($group) use ($activeCampusId) {
                $filtered = $group->students->filter(function ($student) use ($activeCampusId) {
                    return (int) optional($student->user)->default_campus_id === $activeCampusId;
                })->values();

                $group->setRelation('students', $filtered);
            });
        }

        $studentsMap = $groups->mapWithKeys(function ($group) {
            return [
                $group->id => $group->students->map(fn ($student) => [
                    'id' => $student->id,
                    'name' => $student->user->name,
                ])->values(),
            ];
        });

        return view('teacher.reports.create', [
            'groups' => $groups,
            'studentsMap' => $studentsMap,
            'typeOptions' => self::TYPE_OPTIONS,
            'report' => null,
            'readOnly' => false,
        ]);
    }

    public function show(TeacherStudentReport $report)
    {
        $teacher = auth()->user()->teacher;
        abort_if((int) $report->teacher_id !== (int) $teacher->id, 403);

        $activeCampusId = (int) session('active_campus_id', 0);
        $activeCycleId = $this->activeCycleIdForCampus($activeCampusId);

        $groupIds = TeachingAssignment::query()
            ->where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->when(
                $activeCampusId > 0,
                fn ($query) => $query->whereHas('schedules.schoolCycle', function ($cycleQuery) use ($activeCampusId) {
                    $this->applyCampusFilterToCycleQuery($cycleQuery, $activeCampusId);
                })
            )
            ->when(
                $activeCycleId,
                fn ($query) => $query->whereHas('schedules', function ($scheduleQuery) use ($activeCycleId) {
                    $scheduleQuery->where('school_cycle_id', (int) $activeCycleId)
                        ->where('is_active', true);
                })
            )
            ->pluck('group_id')
            ->unique()
            ->values();

        $groups = Group::query()
            ->with(['students' => function ($query) {
                $query->where('is_active', true)
                    ->with('user')
                    ->join('users', 'users.id', '=', 'students.user_id')
                    ->orderBy('users.name')
                    ->select('students.*');
            }])
            ->whereIn('id', $groupIds)
            ->orderBy('name')
            ->get();

        if ($activeCampusId > 0) {
            $groups->each(function ($group) use ($activeCampusId) {
                $filtered = $group->students->filter(function ($student) use ($activeCampusId) {
                    return (int) optional($student->user)->default_campus_id === $activeCampusId;
                })->values();

                $group->setRelation('students', $filtered);
            });
        }

        $studentsMap = $groups->mapWithKeys(function ($group) {
            return [
                $group->id => $group->students->map(fn ($student) => [
                    'id' => $student->id,
                    'name' => $student->user->name,
                ])->values(),
            ];
        });

        return view('teacher.reports.create', [
            'groups' => $groups,
            'studentsMap' => $studentsMap,
            'typeOptions' => self::TYPE_OPTIONS,
            'report' => $report,
            'readOnly' => true,
        ]);
    }

    public function edit(TeacherStudentReport $report)
    {
        $teacher = auth()->user()->teacher;
        abort_if((int) $report->teacher_id !== (int) $teacher->id, 403);

        if ($report->status === 'reviewed' || !is_null($report->reviewed_at)) {
            return redirect()
                ->route('teacher.reports.index')
                ->with('info', 'Este reporte ya fue revisado por coordinaciÃ³n y no se puede editar.');
        }

        $activeCampusId = (int) session('active_campus_id', 0);
        $activeCycleId = $this->activeCycleIdForCampus($activeCampusId);

        $groupIds = TeachingAssignment::query()
            ->where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->when(
                $activeCampusId > 0,
                fn ($query) => $query->whereHas('schedules.schoolCycle', function ($cycleQuery) use ($activeCampusId) {
                    $this->applyCampusFilterToCycleQuery($cycleQuery, $activeCampusId);
                })
            )
            ->when(
                $activeCycleId,
                fn ($query) => $query->whereHas('schedules', function ($scheduleQuery) use ($activeCycleId) {
                    $scheduleQuery->where('school_cycle_id', (int) $activeCycleId)
                        ->where('is_active', true);
                })
            )
            ->pluck('group_id')
            ->unique()
            ->values();

        $groups = Group::query()
            ->with(['students' => function ($query) {
                $query->where('is_active', true)
                    ->with('user')
                    ->join('users', 'users.id', '=', 'students.user_id')
                    ->orderBy('users.name')
                    ->select('students.*');
            }])
            ->whereIn('id', $groupIds)
            ->orderBy('name')
            ->get();

        if ($activeCampusId > 0) {
            $groups->each(function ($group) use ($activeCampusId) {
                $filtered = $group->students->filter(function ($student) use ($activeCampusId) {
                    return (int) optional($student->user)->default_campus_id === $activeCampusId;
                })->values();

                $group->setRelation('students', $filtered);
            });
        }

        $studentsMap = $groups->mapWithKeys(function ($group) {
            return [
                $group->id => $group->students->map(fn ($student) => [
                    'id' => $student->id,
                    'name' => $student->user->name,
                ])->values(),
            ];
        });

        return view('teacher.reports.create', [
            'groups' => $groups,
            'studentsMap' => $studentsMap,
            'typeOptions' => self::TYPE_OPTIONS,
            'report' => $report,
            'readOnly' => false,
        ]);
    }

    public function store(Request $request)
    {
        $teacher = auth()->user()->teacher;
        $activeCampusId = (int) session('active_campus_id', 0);
        $activeCycleId = $this->activeCycleIdForCampus($activeCampusId);

        $data = $request->validate([
            'group_id' => ['required', 'integer', 'exists:groups,id'],
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'report_type' => ['required', 'in:academic,behavioral,mixed'],
            'reason' => ['required', 'string'],
            'severity' => ['required', 'integer', 'between:1,3'],
        ]);

        $hasAccessToGroup = TeachingAssignment::query()
            ->where('teacher_id', $teacher->id)
            ->where('group_id', $data['group_id'])
            ->where('is_active', true)
            ->when(
                $activeCampusId > 0,
                fn ($query) => $query->whereHas('schedules.schoolCycle', function ($cycleQuery) use ($activeCampusId) {
                    $this->applyCampusFilterToCycleQuery($cycleQuery, $activeCampusId);
                })
            )
            ->when(
                $activeCycleId,
                fn ($query) => $query->whereHas('schedules', function ($scheduleQuery) use ($activeCycleId) {
                    $scheduleQuery->where('school_cycle_id', (int) $activeCycleId)
                        ->where('is_active', true);
                })
            )
            ->exists();

        abort_if(! $hasAccessToGroup, 403);

        $belongsToGroup = Student::query()
            ->whereKey($data['student_id'])
            ->where('group_id', $data['group_id'])
            ->when(
                $activeCampusId > 0,
                fn ($q) => $q->whereHas('user', fn ($uq) => $uq->where('default_campus_id', $activeCampusId))
            )
            ->exists();

        abort_if(! $belongsToGroup, 422, 'El alumno seleccionado no pertenece al grupo.');

        $report = TeacherStudentReport::create([
            'teacher_id' => $teacher->id,
            'group_id' => $data['group_id'],
            'student_id' => $data['student_id'],
            'report_type' => $data['report_type'],
            'reason' => $data['reason'],
            'severity' => $data['severity'],
            'status' => 'open',
        ]);

        $studentName = optional($report->student?->user)->name ?? 'Alumno';
        $teacherName = optional($teacher->user)->name ?? 'Docente';

        User::role('coordinator')->get()->each(function (User $coordinator) use ($report, $studentName, $teacherName) {
            $coordinator->notify(new CoordinatorReviewNotification([
                'type' => 'teacher_student_report',
                'title' => 'Nuevo reporte docente',
                'message' => "Reporte de {$teacherName} para {$studentName}.",
                'url' => route('coordination.reports.index'),
                'meta' => [
                    'report_id' => $report->id,
                    'severity' => $report->severity,
                ],
            ]));
        });

        return redirect()
            ->route('teacher.reports.index')
            ->with('info', 'Reporte enviado a coordinacion correctamente.');
    }

    public function update(Request $request, TeacherStudentReport $report)
    {
        $teacher = auth()->user()->teacher;
        abort_if((int) $report->teacher_id !== (int) $teacher->id, 403);

        if ($report->status === 'reviewed' || !is_null($report->reviewed_at)) {
            return redirect()
                ->route('teacher.reports.index')
                ->with('info', 'Este reporte ya fue revisado por coordinaciÃ³n y no se puede editar.');
        }

        $activeCampusId = (int) session('active_campus_id', 0);
        $activeCycleId = $this->activeCycleIdForCampus($activeCampusId);

        $data = $request->validate([
            'group_id' => ['required', 'integer', 'exists:groups,id'],
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'report_type' => ['required', 'in:academic,behavioral,mixed'],
            'reason' => ['required', 'string'],
            'severity' => ['required', 'integer', 'between:1,3'],
        ]);

        $hasAccessToGroup = TeachingAssignment::query()
            ->where('teacher_id', $teacher->id)
            ->where('group_id', $data['group_id'])
            ->where('is_active', true)
            ->when(
                $activeCampusId > 0,
                fn ($query) => $query->whereHas('schedules.schoolCycle', function ($cycleQuery) use ($activeCampusId) {
                    $this->applyCampusFilterToCycleQuery($cycleQuery, $activeCampusId);
                })
            )
            ->when(
                $activeCycleId,
                fn ($query) => $query->whereHas('schedules', function ($scheduleQuery) use ($activeCycleId) {
                    $scheduleQuery->where('school_cycle_id', (int) $activeCycleId)
                        ->where('is_active', true);
                })
            )
            ->exists();

        abort_if(! $hasAccessToGroup, 403);

        $belongsToGroup = Student::query()
            ->whereKey($data['student_id'])
            ->where('group_id', $data['group_id'])
            ->when(
                $activeCampusId > 0,
                fn ($q) => $q->whereHas('user', fn ($uq) => $uq->where('default_campus_id', $activeCampusId))
            )
            ->exists();

        abort_if(! $belongsToGroup, 422, 'El alumno seleccionado no pertenece al grupo.');

        $report->update([
            'group_id' => $data['group_id'],
            'student_id' => $data['student_id'],
            'report_type' => $data['report_type'],
            'reason' => $data['reason'],
            'severity' => $data['severity'],
        ]);

        return redirect()
            ->route('teacher.reports.index')
            ->with('info', 'Reporte actualizado correctamente.');
    }

    public function coordinationIndex()
    {
        $allowedGroupIds = $this->activeCampusGroupIds();
        $filters = request()->validate([
            'status' => ['nullable', 'in:open,reviewed,resolved'],
            'source' => ['nullable', 'in:teacher,student,prefect,coordination'],
            'severity' => ['nullable', 'integer', 'between:1,3'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $teacherReports = TeacherStudentReport::query()
            ->with(['teacher.user', 'student.user', 'group'])
            ->when(!empty($allowedGroupIds) && $allowedGroupIds !== [0], fn ($q) => $q->whereIn('group_id', $allowedGroupIds))
            ->get()
            ->map(fn (TeacherStudentReport $report) => [
                'source' => 'teacher',
                'source_label' => 'Docente',
                'reporter' => $report->teacher?->user?->name ?? 'N/D',
                'student' => $report->student?->user?->name ?? '-',
                'group' => $report->group?->name ?? '-',
                'category' => self::TYPE_OPTIONS[$report->report_type] ?? $report->report_type,
                'subject' => 'Reporte docente',
                'description' => $report->reason,
                'status' => $report->status,
                'priority' => (int) $report->severity,
                'priority_label' => match ((int) $report->severity) {
                    3 => 'Alta',
                    2 => 'Media',
                    default => 'Baja',
                },
                'created_at' => $report->created_at,
                'review_route' => route('coordination.reports.review', $report),
                'resolve_route' => null,
                'case_route' => route('coordination.school-cases.create', [
                    'source_report_type' => 'teacher',
                    'source_report_id' => $report->id,
                ]),
            ]);

        $categoryOptions = [
            'facilities' => 'Instalaciones',
            'classmates' => 'Companeros',
            'academic' => 'Academico',
            'behavioral' => 'Conductual',
            'other' => 'Otro',
        ];

        $studentReports = StudentIncidentReport::query()
            ->with(['student.user', 'student.group'])
            ->when(session('active_campus_id'), function ($query, $campusId) {
                $query->whereHas('student.user', fn ($userQuery) => $userQuery->where('default_campus_id', $campusId));
            })
            ->get()
            ->map(fn (StudentIncidentReport $report) => [
                'source' => 'student',
                'source_label' => 'Alumno',
                'reporter' => $report->student?->user?->name ?? 'N/D',
                'student' => $report->student?->user?->name ?? '-',
                'group' => $report->student?->group?->name ?? '-',
                'category' => $categoryOptions[$report->category] ?? $report->category,
                'subject' => $report->subject,
                'description' => $report->description,
                'status' => $report->status,
                'priority' => 2,
                'priority_label' => 'Media',
                'created_at' => $report->created_at,
                'review_route' => route('coordination.student-incident-reports.update-status', $report),
                'resolve_route' => route('coordination.student-incident-reports.update-status', $report),
                'case_route' => route('coordination.school-cases.create', [
                    'source_report_type' => 'student',
                    'source_report_id' => $report->id,
                ]),
            ]);

        $prefectReports = PrefectIncidentReport::query()
            ->with('reporter')
            ->get()
            ->map(fn (PrefectIncidentReport $report) => [
                'source' => 'prefect',
                'source_label' => 'Prefectura',
                'reporter' => $report->reporter?->name ?? 'N/D',
                'student' => '-',
                'group' => '-',
                'category' => $categoryOptions[$report->category] ?? $report->category,
                'subject' => $report->subject,
                'description' => $report->description,
                'status' => $report->status,
                'priority' => 2,
                'priority_label' => 'Media',
                'created_at' => $report->created_at,
                'review_route' => route('coordination.prefect-reports.update-status', $report),
                'resolve_route' => route('coordination.prefect-reports.update-status', $report),
                'case_route' => route('coordination.school-cases.create', [
                    'source_report_type' => 'prefect',
                    'source_report_id' => $report->id,
                ]),
            ]);

        $coordinationCategoryOptions = [
            'academic' => 'Academico',
            'behavioral' => 'Conductual',
            'attendance' => 'Asistencia',
            'communication' => 'Comunicacion',
            'facilities' => 'Instalaciones',
            'technology' => 'Tecnologia',
            'administrative' => 'Administrativo',
            'safety' => 'Seguridad',
            'health' => 'Salud',
            'other' => 'Otro',
        ];

        $coordinationReports = CoordinationReport::query()
            ->with('reporter')
            ->when(session('active_campus_id'), fn ($query, $campusId) => $query->where(function ($inner) use ($campusId) {
                $inner->whereNull('campus_id')->orWhere('campus_id', $campusId);
            }))
            ->when($this->activeCycleIdForCampus((int) session('active_campus_id', 0)), fn ($query, $cycleId) => $query->where(function ($inner) use ($cycleId) {
                $inner->whereNull('school_cycle_id')->orWhere('school_cycle_id', (int) $cycleId);
            }))
            ->get()
            ->map(fn (CoordinationReport $report) => [
                'source' => 'coordination',
                'source_label' => 'Coordinacion',
                'reporter' => $report->reporter_name ?: ($report->reporter?->name ?? 'Coordinacion'),
                'student' => '-',
                'group' => '-',
                'category' => $coordinationCategoryOptions[$report->category] ?? $report->category,
                'subject' => $report->subject,
                'description' => trim(implode(' ', array_filter([
                    $report->reporter_contact ? 'Contacto: '.$report->reporter_contact.'.' : null,
                    $report->description,
                ]))),
                'status' => $report->status,
                'priority' => (int) $report->priority,
                'priority_label' => match ((int) $report->priority) {
                    3 => 'Alta',
                    2 => 'Media',
                    default => 'Baja',
                },
                'created_at' => $report->created_at,
                'review_route' => route('coordination.coordination-reports.update-status', $report),
                'resolve_route' => route('coordination.coordination-reports.update-status', $report),
                'case_route' => route('coordination.school-cases.create', [
                    'source_report_type' => 'coordination',
                    'source_report_id' => $report->id,
                ]),
            ]);

        $reports = $teacherReports
            ->concat($studentReports)
            ->concat($prefectReports)
            ->concat($coordinationReports)
            ->when($filters['source'] ?? null, fn ($items, $source) => $items->where('source', $source))
            ->when($filters['status'] ?? null, fn ($items, $status) => $items->where('status', $status))
            ->when($filters['severity'] ?? null, fn ($items, $severity) => $items->where('priority', (int) $severity))
            ->when($filters['q'] ?? null, function ($items, $term) {
                $needle = mb_strtolower($term);

                return $items->filter(function ($report) use ($needle) {
                    return str_contains(mb_strtolower(implode(' ', [
                        $report['reporter'],
                        $report['student'],
                        $report['group'],
                        $report['category'],
                        $report['subject'],
                        $report['description'],
                    ])), $needle);
                });
            })
            ->sort(function ($a, $b) {
                return ($this->reportStatusWeight($a['status']) <=> $this->reportStatusWeight($b['status']))
                    ?: ($b['created_at']->timestamp <=> $a['created_at']->timestamp)
                    ?: ($b['priority'] <=> $a['priority']);
            })
            ->values();

        return view('coordination.reports.index', [
            'reports' => $reports,
            'filters' => $filters,
            'sourceOptions' => [
                'teacher' => 'Docentes',
                'student' => 'Alumnos',
                'prefect' => 'Prefectura',
                'coordination' => 'Coordinacion',
            ],
        ]);
    }

    public function markReviewed(TeacherStudentReport $report)
    {
        $report->update([
            'status' => 'reviewed',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        DatabaseNotification::query()
            ->whereNull('read_at')
            ->where('data->type', 'teacher_student_report')
            ->where('data->meta->report_id', (int) $report->id)
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return back()->with('info', 'Reporte marcado como revisado.');
    }

    private function activeCampusGroupIds(): array
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        if ($activeCampusId <= 0) {
            return [];
        }

        $activeCycleId = $this->activeCycleIdForCampus($activeCampusId);

        if (! $activeCycleId) {
            return [0];
        }

        return SchoolCycleGroup::query()
            ->where('school_cycle_id', (int) $activeCycleId)
            ->where('campus_id', $activeCampusId)
            ->where('is_active', true)
            ->pluck('group_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function reportStatusWeight(string $status): int
    {
        return match ($status) {
            'open' => 0,
            'reviewed' => 1,
            'resolved' => 2,
            default => 3,
        };
    }

    private function activeCycleIdForCampus(int $activeCampusId): ?int
    {
        return app(CurrentSchoolCycle::class)->id(auth()->user(), $activeCampusId);
    }

    private function applyCampusFilterToCycleQuery($query, int $activeCampusId): void
    {
        $query->where(function ($nested) use ($activeCampusId) {
            $nested->where('campus_id', $activeCampusId)
                ->orWhereHas('campuses', fn ($campuses) => $campuses->where('campuses.id', $activeCampusId));
        });
    }
}

