<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherStudentReport;
use App\Models\TeachingAssignment;
use App\Models\User;
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
            'status' => ['nullable', 'in:open,reviewed'],
            'severity' => ['nullable', 'integer', 'between:1,3'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
        ]);

        $reports = TeacherStudentReport::query()
            ->with(['teacher.user', 'student.user', 'group'])
            ->when(!empty($allowedGroupIds), fn ($q) => $q->whereIn('group_id', $allowedGroupIds))
            ->when($filters['status'] ?? null, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($filters['severity'] ?? null, function ($query, $severity) {
                $query->where('severity', $severity);
            })
            ->when($filters['group_id'] ?? null, function ($query, $groupId) {
                $query->where('group_id', $groupId);
            })
            ->when($filters['teacher_id'] ?? null, function ($query, $teacherId) {
                $query->where('teacher_id', $teacherId);
            })
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('severity')
            ->orderByDesc('created_at')
            ->get();

        $groups = Group::query()
            ->where('is_active', true)
            ->when(!empty($allowedGroupIds), fn ($q) => $q->whereIn('id', $allowedGroupIds))
            ->orderBy('name')
            ->get();

        $teachers = Teacher::query()
            ->where('is_active', true)
            ->with('user')
            ->get()
            ->sortBy('user.name')
            ->values();

        return view('coordination.reports.index', [
            'reports' => $reports,
            'typeOptions' => self::TYPE_OPTIONS,
            'filters' => $filters,
            'groups' => $groups,
            'teachers' => $teachers,
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

    private function activeCycleIdForCampus(int $activeCampusId): ?int
    {
        return SchoolCycle::query()
            ->where('is_active', true)
            ->when($activeCampusId > 0, fn ($q) => $this->applyCampusFilterToCycleQuery($q, $activeCampusId))
            ->orderByDesc('start_date')
            ->value('id');
    }

    private function applyCampusFilterToCycleQuery($query, int $activeCampusId): void
    {
        $query->where(function ($nested) use ($activeCampusId) {
            $nested->where('campus_id', $activeCampusId)
                ->orWhereHas('campuses', fn ($campuses) => $campuses->where('campuses.id', $activeCampusId));
        });
    }
}

