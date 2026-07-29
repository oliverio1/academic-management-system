<?php 

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\AcademicPeriod;
use App\Models\Attendance;
use App\Models\AcademicSession;
use App\Models\AssignmentRemedialExam;
use App\Models\SchoolCycle;
use App\Models\CyclePartial;
use App\Models\EconomicActa;
use App\Models\EvaluationCriterion;
use App\Models\SchoolCycleGroup;
use App\Models\Student;
use App\Models\Subject;
use App\Services\CurrentSchoolCycle;
use App\Models\User;
use App\Services\AcademicPerformanceService;
use App\Services\EconomicActaLockService;
use App\Notifications\CoordinatorReviewNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeachingAssignmentController extends Controller
{
    public function edit(Group $group)
    {
        $cycleGroup = $this->resolveCycleGroup($group);
        $subjects = $this->subjectsForGroup($group);
        $teachers = Teacher::where('is_active', true)->with('subjects')->get();
        $assignmentsCollection = TeachingAssignment::query()
            ->where('group_id', $group->id)
            ->when(
                $cycleGroup,
                fn ($q) => $q->where('school_cycle_group_id', $cycleGroup->id),
                fn ($q) => $q->whereNull('school_cycle_group_id')
            )
            ->get();

        $assignments = $assignmentsCollection
            ->keyBy(fn (TeachingAssignment $assignment) => $assignment->subject_id.'-'.$assignment->section_number);

        $subjectSectionCounts = $subjects->mapWithKeys(function ($subject) use ($assignmentsCollection) {
            $maxSection = (int) $assignmentsCollection
                ->where('subject_id', $subject->id)
                ->max('section_number');
            return [(int) $subject->id => max(1, min(3, $maxSection ?: 1))];
        });

        return view('groups.assignments', compact('group', 'subjects', 'teachers', 'assignments', 'cycleGroup', 'subjectSectionCounts'));
    }

    public function update(Group $group)
    {
        $cycleGroup = $this->resolveCycleGroup($group);
        if (! $cycleGroup) {
            return back()->with('error', 'No hay ciclo activo para asignar materias de este grupo.');
        }

        $allowedSubjectIds = $this->subjectsForGroup($group)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $inputAssignments = collect(request('assignments', []));
        $inputNrcs = collect(request('nrcs', []));
        $inputSubjectSections = collect(request('subject_sections', []))
            ->mapWithKeys(fn ($value, $subjectId) => [(int) $subjectId => max(1, min(3, (int) $value))]);

        TeachingAssignment::where('group_id', $group->id)
            ->where('school_cycle_group_id', $cycleGroup->id)
            ->whereNotIn('subject_id', $allowedSubjectIds)
            ->delete();

        foreach ($allowedSubjectIds as $subjectId) {
            $sectionsForSubject = (int) ($inputSubjectSections[$subjectId] ?? 1);
            $subject = $this->subjectsForGroup($group)->firstWhere('id', $subjectId);
            for ($section = 1; $section <= 3; $section++) {
                $sectionDescriptor = $this->sectionDescriptor($section, (string) ($subject?->name ?? ''));

                if ($section > $sectionsForSubject) {
                    TeachingAssignment::query()
                        ->where('group_id', $group->id)
                        ->where('school_cycle_group_id', $cycleGroup->id)
                        ->where('subject_id', $subjectId)
                        ->where('section_number', $section)
                        ->delete();
                    continue;
                }

                $teacherId = (int) data_get($inputAssignments, $subjectId.'.'.$section, 0);
                $nrc = trim((string) data_get($inputNrcs, $subjectId.'.'.$section, ''));

                if (! $teacherId) {
                    TeachingAssignment::query()
                        ->where('group_id', $group->id)
                        ->where('school_cycle_group_id', $cycleGroup->id)
                        ->where('subject_id', $subjectId)
                        ->where('section_number', $section)
                        ->delete();
                    continue;
                }

                TeachingAssignment::updateOrCreate(
                    [
                        'group_id' => $group->id,
                        'school_cycle_group_id' => $cycleGroup->id,
                        'subject_id' => $subjectId,
                        'section_number' => $section,
                    ],
                    [
                        'teacher_id' => $teacherId,
                        'section_type' => $sectionDescriptor['type'],
                        'section_label' => $sectionDescriptor['label'],
                        'nrc' => $nrc !== '' ? mb_substr($nrc, 0, 30) : null,
                        'is_active' => true,
                    ]
                );
            }
        }

        return redirect()->route('groups.index')->with('info', 'Asignaciones guardadas correctamente');
    }

    public function myAssignments()
    {
        $teacher = auth()->user()->teacher;
        $activeCycle = $this->activeCycle();

        $assignments = TeachingAssignment::with(['group', 'subject', 'schoolCycleGroup'])
            ->where('teacher_id', $teacher->id)
            ->when($activeCycle, function ($q) use ($activeCycle) {
                $q->whereHas('schoolCycleGroup', function ($cycleGroupQuery) use ($activeCycle) {
                    $cycleGroupQuery->where('school_cycle_id', $activeCycle->id)
                        ->where('is_active', true);
                });
            })
            ->get();

        return view('teacher.assignments.index', compact('assignments'));
    }

    public function show(TeachingAssignment $teachingAssignment, AcademicPerformanceService $performance)
    {
        abort_if($teachingAssignment->teacher_id !== auth()->user()->teacher->id, 403);

        $tab = request('tab', 'evaluation');
        $gradesData = collect();

        $activeCycle = $this->activeCycle();

        abort_if(
            ! $teachingAssignment->schedules()
                ->where('school_cycle_id', (int) optional($activeCycle)->id)
                ->where('is_active', true)
                ->exists(),
            403
        );

        $activePeriod = null;
        $cyclePeriods = collect();
        $activePartial = null;
        $economicActa = null;
        $pendingReopenRequest = null;
        $partials = collect();

        if ($activeCycle) {
            $partials = CyclePartial::query()
                ->where('school_cycle_id', $activeCycle->id)
                ->whereNotNull('academic_period_id')
                ->with('academicPeriod')
                ->orderBy('sort_order')
                ->get();

            $cyclePeriods = $partials
                ->pluck('academicPeriod')
                ->filter()
                ->values();

            $requestedPeriodId = (int) request('period_id', 0);
            if ($requestedPeriodId > 0) {
                $activePeriod = $cyclePeriods->firstWhere('id', $requestedPeriodId);
            }

            if (! $activePeriod) {
                $today = now()->toDateString();
                $activePeriod = $cyclePeriods->first(function ($period) use ($today) {
                    return $period->start_date
                        && $period->end_date
                        && $period->start_date->toDateString() <= $today
                        && $period->end_date->toDateString() >= $today;
                });
            }

            if (! $activePeriod) {
                $activePeriod = $cyclePeriods
                    ->sortByDesc(fn ($period) => optional($period->start_date)->toDateString())
                    ->first();
            }

            if ($activePeriod) {
                $activePartial = $partials->firstWhere('academic_period_id', $activePeriod->id);
            }
        }

        if (! $activePeriod) {
            $activePeriod = AcademicPeriod::where('modality_id', $teachingAssignment->group->level->modality_id)
                ->where('is_active', 1)
                ->orderByDesc('start_date')
                ->first();
        }

        $group = $teachingAssignment->group;
        $studentsQuery = $this->studentsQueryForAssignment($teachingAssignment);
        $studentsForAssignment = $studentsQuery->get();

        $totalStudents = $studentsForAssignment->count();

        $firstSecondPeriodIds = $partials
            ->filter(fn ($partial) => (int) $partial->sort_order <= 2)
            ->pluck('academic_period_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($firstSecondPeriodIds->isEmpty() && $activePeriod) {
            $firstSecondPeriodIds = collect([(int) $activePeriod->id]);
        }

        $activities = $teachingAssignment->activities()
            ->when(
                $firstSecondPeriodIds->isNotEmpty(),
                fn ($q) => $q->whereIn('academic_period_id', $firstSecondPeriodIds->all()),
                fn ($q) => $q->where('academic_period_id', optional($activePeriod)->id)
            )
            ->when(
                $activeCycle && $activeCycle->start_date && $activeCycle->end_date,
                fn ($q) => $q->whereBetween('due_date', [
                    $activeCycle->start_date->toDateString(),
                    $activeCycle->end_date->toDateString(),
                ])
            )
            ->with('evaluationCriterion')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $activities->each(function ($activity) {
            $activity->graded_count = $activity->grades()->count();
        });

        $teachingAssignment->load([
            'group',
            'subject',
            'practices',
            'evaluationCriteria',
            'group.students'
        ]);

        $totalSessions = 0;
        if ($activePeriod) {
            $totalSessions = AcademicSession::where('teaching_assignment_id', $teachingAssignment->id)
                ->where('academic_period_id', $activePeriod->id)
                ->where('is_cancelled', false)
                ->count();
        }

        $attendanceSummary = $studentsForAssignment
            ->map(function ($student) use ($teachingAssignment, $activePeriod, $totalSessions) {
                $attended = 0;

                if ($activePeriod) {
                    $attended = Attendance::where('student_id', $student->id)
                        ->whereIn('status', ['present', 'late'])
                        ->whereHas('academicSession', function ($q) use ($teachingAssignment, $activePeriod) {
                            $q->where('teaching_assignment_id', $teachingAssignment->id)
                                ->where('academic_period_id', $activePeriod->id)
                                ->where('is_cancelled', false);
                        })
                        ->count();
                }

                return [
                    'student' => $student,
                    'attended' => $attended,
                    'total' => $totalSessions,
                    'percentage' => $totalSessions > 0 ? round(($attended / $totalSessions) * 100, 1) : 0,
                ];
            });

        $attendanceSessions = collect();
        if ($activePeriod) {
            $attendanceSessions = AcademicSession::where('teaching_assignment_id', $teachingAssignment->id)
                ->where('academic_period_id', $activePeriod->id)
                ->where('is_cancelled', false)
                ->with('schedule')
                ->orderBy('session_date')
                ->orderBy('start_time')
                ->get();
        }

        $teacherAssignments = TeachingAssignment::where('teacher_id', auth()->user()->teacher->id)
            ->where('id', '!=', $teachingAssignment->id)
            ->with('evaluationCriteria')
            ->get();

        $evaluationCriteriaByPartial = collect();
        if ($partials->isNotEmpty()) {
            $evaluationCriteriaByPartial = $partials->map(function (CyclePartial $partial) use ($teachingAssignment) {
                $criteria = EvaluationCriterion::query()
                    ->forAssignmentAndPartial($teachingAssignment, (int) $partial->id, false)
                    ->orderBy('name')
                    ->get();

                return [
                    'partial' => $partial,
                    'criteria' => $criteria,
                    'total' => round((float) $criteria->sum('percentage'), 2),
                ];
            });
        } else {
            $legacyCriteria = EvaluationCriterion::query()
                ->where('teaching_assignment_id', $teachingAssignment->id)
                ->whereNull('cycle_partial_id')
                ->orderBy('name')
                ->get();

            if ($legacyCriteria->isNotEmpty()) {
                $evaluationCriteriaByPartial = collect([[
                    'partial' => null,
                    'criteria' => $legacyCriteria,
                    'total' => round((float) $legacyCriteria->sum('percentage'), 2),
                ]]);
            }
        }

        if ($activePartial) {
            $economicActa = EconomicActa::query()
                ->where('teaching_assignment_id', $teachingAssignment->id)
                ->where('cycle_partial_id', $activePartial->id)
                ->first();
            if ($economicActa) {
                $pendingReopenRequest = $economicActa->reopenRequests()
                    ->where('status', 'pending')
                    ->latest('id')
                    ->first();
            }
        }

        if ($tab === 'grades') {
            $teachingAssignment->load([
                'evaluationCriteria.activities',
            ]);

            $periodForGrades = $performance->periodForAssignment($teachingAssignment);
            $remedials = collect();

            if ($periodForGrades) {
                $remedials = AssignmentRemedialExam::query()
                    ->where('teaching_assignment_id', $teachingAssignment->id)
                    ->where('academic_period_id', $periodForGrades->id)
                    ->get()
                    ->keyBy('student_id');
            }

            $gradesData = $studentsForAssignment
                ->sortBy(fn ($student) => mb_strtolower((string) optional($student->user)->name))
                ->values()
                ->map(function ($student) use ($teachingAssignment, $performance, $remedials) {
                    $outcome = $performance->promotionOutcomeForAssignment($student, $teachingAssignment);
                    $final = $outcome['final'];
                    $breakdown = $performance->breakdownForAssignment($student, $teachingAssignment);
                    $breakdown['final'] = $final;

                    return [
                        'student' => $student,
                        'final' => $final,
                        'status' => $performance->academicStatus($student, $teachingAssignment),
                        'breakdown' => $breakdown,
                        'remedial' => $remedials->get($student->id),
                        'promotion' => $outcome,
                    ];
                });
        }

        return view('teacher.assignments.show', compact(
            'teachingAssignment',
            'teacherAssignments',
            'evaluationCriteriaByPartial',
            'activeCycle',
            'activePeriod',
            'activePartial',
            'economicActa',
            'pendingReopenRequest',
            'activities',
            'totalStudents',
            'attendanceSummary',
            'totalSessions',
            'attendanceSessions',
            'tab',
            'gradesData'
        ));
    }

    public function submitEconomicActa(
        Request $request,
        TeachingAssignment $teachingAssignment
    ) {
        abort_if($teachingAssignment->teacher_id !== auth()->user()->teacher->id, 403);

        $data = $request->validate([
            'cycle_partial_id' => ['required', 'integer', 'exists:cycle_partials,id'],
        ]);

        $partial = CyclePartial::query()
            ->where('id', $data['cycle_partial_id'])
            ->firstOrFail();

        $belongs = $teachingAssignment->schedules()
            ->where('school_cycle_id', $partial->school_cycle_id)
            ->exists();

        abort_if(!$belongs, 404);

        $userId = (int) auth()->id();

        DB::transaction(function () use ($teachingAssignment, $partial, $userId) {
            $acta = EconomicActa::query()->firstOrCreate(
                [
                    'teaching_assignment_id' => $teachingAssignment->id,
                    'cycle_partial_id' => $partial->id,
                ],
                [
                    'status' => 'submitted',
                    'submitted_by' => $userId,
                    'submitted_at' => now(),
                ]
            );

            $fromStatus = $acta->wasRecentlyCreated ? null : $acta->status;

            abort_if(in_array($acta->status, ['closed', 'sent'], true), 422, 'El acta ya fue cerrada o enviada.');

            $acta->update([
                'status' => 'submitted',
                'submitted_by' => $userId,
                'submitted_at' => now(),
            ]);

            $acta->events()->create([
                'from_status' => $fromStatus,
                'to_status' => 'submitted',
                'changed_by' => $userId,
                'changed_at' => now(),
                'comment' => 'Enviada por el docente para cierre.',
            ]);
        });

        $coordinators = User::role('coordinator')->get();
        foreach ($coordinators as $coordinator) {
            $coordinator->notify(new CoordinatorReviewNotification([
                'type' => 'economic_acta_submitted',
                'title' => 'Acta enviada por docente',
                'message' => ($teachingAssignment->subject->name ?? 'Materia')
                    . ' del grupo ' . ($teachingAssignment->group->name ?? '-')
                    . ' fue cerrada por el docente y esta lista para revision.',
                'url' => route('coordination.economic-actas.index', [
                    'school_cycle_id' => $partial->school_cycle_id,
                    'partial_id' => $partial->id,
                ]),
            ]));
        }

        return redirect()
            ->route('assignments.show', [$teachingAssignment, 'tab' => 'grades'])
            ->with('info', 'Materia enviada a coordinacion para cierre de acta.');
    }

    public function storeRemedialExam(
        Request $request,
        TeachingAssignment $teachingAssignment,
        Student $student,
        AcademicPerformanceService $performance,
        EconomicActaLockService $lockService
    ) {
        abort_if($teachingAssignment->teacher_id !== auth()->user()->teacher->id, 403);
        $studentBelongsToAssignment = $this->studentsQueryForAssignment($teachingAssignment)
            ->where('students.id', $student->id)
            ->exists();
        abort_if(! $studentBelongsToAssignment, 404);

        $period = $performance->periodForAssignment($teachingAssignment);
        if (! $period) {
            return redirect()
                ->route('assignments.show', [$teachingAssignment, 'tab' => 'grades'])
                ->with('warning', 'No se pudo identificar el parcial activo para guardar ordinarios.');
        }

        $cycleId = (int) ($teachingAssignment->schoolCycleGroup?->school_cycle_id ?: 0);
        abort_if(
            $lockService->isAssignmentPeriodLocked(
                $teachingAssignment,
                (int) $period->id,
                $cycleId > 0 ? $cycleId : null
            ),
            403,
            'El parcial ya esta cerrado o enviado a coordinacion. No se pueden editar ordinarios.'
        );

        $data = $request->validate([
            'ordinario_a_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'ordinario_b_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'extraordinario_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
        ]);

        $currentOutcome = $performance->promotionOutcomeForAssignment($student, $teachingAssignment);
        $currentRoute = (string) ($currentOutcome['route'] ?? '');
        $isFinalLocked = in_array($currentRoute, ['ordinario_a_final', 'ordinario_b_final', 'extraordinario_final'], true);

        if ($isFinalLocked) {
            return redirect()
                ->route('assignments.show', [$teachingAssignment, 'tab' => 'grades'])
                ->with('warning', 'La calificacion final del alumno ya esta cerrada para esta materia.');
        }

        $ordAProvided = $request->filled('ordinario_a_score');
        $ordBProvided = $request->filled('ordinario_b_score');

        if ($currentRoute === 'pending_ordinario_b' && $ordAProvided) {
            return redirect()
                ->route('assignments.show', [$teachingAssignment, 'tab' => 'grades'])
                ->with('warning', 'El alumno ya curso Ordinario A. Captura Ordinario B o Extraordinario.');
        }

        if (in_array($currentRoute, ['pending_ordinario_a', 'base_direct'], true) && $ordBProvided) {
            return redirect()
                ->route('assignments.show', [$teachingAssignment, 'tab' => 'grades'])
                ->with('warning', 'Primero debes capturar Ordinario A antes de Ordinario B.');
        }

        $payload = collect($data)
            ->map(function ($value) {
                if ($value === null || $value === '') {
                    return null;
                }

                return round((float) $value, 2);
            })
            ->all();

        $hasAnyValue = collect($payload)->contains(fn ($value) => $value !== null);

        if (! $hasAnyValue) {
            AssignmentRemedialExam::query()
                ->where('teaching_assignment_id', $teachingAssignment->id)
                ->where('student_id', $student->id)
                ->where('academic_period_id', $period->id)
                ->delete();
        } else {
            AssignmentRemedialExam::updateOrCreate(
                [
                    'teaching_assignment_id' => $teachingAssignment->id,
                    'student_id' => $student->id,
                    'academic_period_id' => $period->id,
                ],
                $payload
            );
        }

        return redirect()
            ->route('assignments.show', [$teachingAssignment, 'tab' => 'grades'])
            ->with('info', 'Calificaciones de ordinario/extraordinario guardadas.');
    }

    public function create(TeachingAssignment $teachingAssignment)
    {
        abort_if($teachingAssignment->teacher_id !== auth()->user()->teacher->id, 403);

        abort_if(
            $teachingAssignment->hasGrades(),
            403,
            'No puedes configurar la evaluacion porque ya existen calificaciones.'
        );

        if ($teachingAssignment->evaluationCriteria()->exists()) {
            return redirect()
                ->route('teacher.evaluation.edit', $teachingAssignment)
                ->with('info', 'La evaluacion ya esta configurada. Puedes editarla.');
        }

        $teachingAssignment->load(['subject', 'group']);

        return view('teacher.evaluation.create', compact('teachingAssignment'));
    }

    private function subjectsForGroup(Group $group)
    {
        $groupSubjects = $group->subjects()->where('is_active', true)->get();

        $activeCycle = $this->activeCycle();
        $activeCampusId = (int) session('active_campus_id', 0);

        if (! $activeCycle) {
            return $groupSubjects->sortBy('name')->values();
        }

        $planned = SchoolCycleGroup::query()
            ->where('school_cycle_id', $activeCycle->id)
            ->where('group_id', $group->id)
            ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
            ->where('is_active', true)
            ->with(['subjects' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->first();

        if ($planned && $planned->subjects->isNotEmpty()) {
            return $planned->subjects
                ->merge($groupSubjects)
                ->unique('id')
                ->sortBy('name')
                ->values();
        }

        return $groupSubjects->sortBy('name')->values();
    }

    private function resolveCycleGroup(Group $group): ?SchoolCycleGroup
    {
        $activeCycle = $this->activeCycle();
        $activeCampusId = (int) session('active_campus_id', 0);

        if (! $activeCycle || $activeCampusId <= 0) {
            return null;
        }

        $modalityId = (int) optional($group->level)->modality_id;
        if ($modalityId <= 0) {
            return null;
        }

        return SchoolCycleGroup::query()
            ->firstOrCreate(
                [
                    'school_cycle_id' => $activeCycle->id,
                    'group_id' => $group->id,
                    'campus_id' => $activeCampusId,
                    'modality_id' => $modalityId,
                ],
                ['is_active' => true]
            );
    }

    private function activeCycle(): ?SchoolCycle
    {
        $activeCampusId = (int) session('active_campus_id', 0);

        return app(CurrentSchoolCycle::class)->get(auth()->user(), $activeCampusId);
    }

    public function editSections(Group $group, Subject $subject)
    {
        $cycleGroup = $this->resolveCycleGroup($group);
        abort_if(! $cycleGroup, 404, 'No hay ciclo activo para este grupo.');

        $sectionCount = max(
            1,
            min(3, (int) TeachingAssignment::query()
                ->where('group_id', $group->id)
                ->where('school_cycle_group_id', $cycleGroup->id)
                ->where('subject_id', $subject->id)
                ->max('section_number'))
        );
        $students = $group->students()
            ->where('is_active', true)
            ->with('user')
            ->get()
            ->sortBy(fn ($student) => mb_strtolower((string) optional($student->user)->name))
            ->values();

        $assignments = TeachingAssignment::query()
            ->where('group_id', $group->id)
            ->where('school_cycle_group_id', $cycleGroup->id)
            ->where('subject_id', $subject->id)
            ->whereBetween('section_number', [1, $sectionCount])
            ->with('students')
            ->get()
            ->keyBy('section_number');

        return view('groups.assignment-sections', compact(
            'group',
            'subject',
            'cycleGroup',
            'sectionCount',
            'students',
            'assignments'
        ));
    }

    public function updateSections(Request $request, Group $group, Subject $subject)
    {
        $cycleGroup = $this->resolveCycleGroup($group);
        abort_if(! $cycleGroup, 404, 'No hay ciclo activo para este grupo.');

        $sectionCount = max(
            1,
            min(3, (int) TeachingAssignment::query()
                ->where('group_id', $group->id)
                ->where('school_cycle_group_id', $cycleGroup->id)
                ->where('subject_id', $subject->id)
                ->max('section_number'))
        );
        $assignments = TeachingAssignment::query()
            ->where('group_id', $group->id)
            ->where('school_cycle_group_id', $cycleGroup->id)
            ->where('subject_id', $subject->id)
            ->whereBetween('section_number', [1, $sectionCount])
            ->get()
            ->keyBy('section_number');

        $allowedStudentIds = $group->students()
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $sectionsInput = collect($request->input('sections', []))
            ->map(function ($studentIds) {
                return collect((array) $studentIds)
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            });

        $assignedOnce = [];

        DB::transaction(function () use ($assignments, $sectionsInput, $allowedStudentIds, &$assignedOnce) {
            foreach ($assignments as $section => $assignment) {
                $studentsForSection = collect((array) $sectionsInput->get((int) $section, []))
                    ->filter(fn ($id) => in_array((int) $id, $allowedStudentIds, true))
                    ->reject(function ($id) use (&$assignedOnce) {
                        if (isset($assignedOnce[(int) $id])) {
                            return true;
                        }
                        $assignedOnce[(int) $id] = true;
                        return false;
                    })
                    ->values()
                    ->all();

                $assignment->students()->sync($studentsForSection);
            }
        });

        return redirect()
            ->route('groups.assignments.sections.edit', [$group, $subject])
            ->with('info', 'Secciones de alumnos guardadas correctamente.');
    }

    private function studentsQueryForAssignment(TeachingAssignment $teachingAssignment)
    {
        $hasSectionAssignments = $teachingAssignment->students()->exists();

        if ($hasSectionAssignments) {
            return $teachingAssignment->students()
                ->where('students.is_active', true)
                ->with('user');
        }

        return $teachingAssignment->group->students()
            ->where('is_active', true)
            ->with('user');
    }

    private function sectionDescriptor(int $sectionNumber, string $subjectName): array
    {
        $subjectKey = $this->normalizeKey($subjectName);

        if (str_contains($subjectKey, 'ingles') || str_contains($subjectKey, 'english')) {
            return [
                'type' => 'english',
                'label' => $sectionNumber === 2 ? 'AVANZADO' : 'BASICO',
            ];
        }

        if (str_contains($subjectKey, 'laboratorio') || str_contains($subjectKey, 'lab') || str_contains($subjectKey, 'taller')) {
            return [
                'type' => 'lab_taller',
                'label' => match ($sectionNumber) {
                    2 => 'B',
                    3 => 'C',
                    default => 'A',
                },
            ];
        }

        return [
            'type' => null,
            'label' => null,
        ];
    }

    private function normalizeKey(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = mb_strtolower(trim($value));

        return preg_replace('/\s+/', ' ', $value) ?? '';
    }
}
