<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Level;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Student;
use App\Models\StudentGroupHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CyclePromotionService
{
    public function __construct(
        protected AcademicPerformanceService $performanceService
    ) {
    }

    public function preview(SchoolCycle $sourceCycle, SchoolCycle $targetCycle, int $modalityId): array
    {
        $this->validateCycles($sourceCycle, $targetCycle, $modalityId);

        $sourceCycleGroups = SchoolCycleGroup::query()
            ->where('school_cycle_id', $sourceCycle->id)
            ->where('is_active', true)
            ->get();

        $sourceGroupIds = $sourceCycleGroups
            ->pluck('group_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $sourceCycleGroupIds = $sourceCycleGroups
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $targetGroupIds = SchoolCycleGroup::query()
            ->where('school_cycle_id', $targetCycle->id)
            ->where('is_active', true)
            ->pluck('group_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $targetGroupsByLevel = Group::query()
            ->whereIn('id', $targetGroupIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->groupBy('level_id')
            ->map(function ($groups) {
                return $groups->map(fn (Group $group) => [
                    'id' => (int) $group->id,
                    'name' => (string) $group->name,
                ])->values()->all();
            });

        $assignmentsByGroup = DB::table('teaching_assignments')
            ->select('id', 'group_id')
            ->whereIn('group_id', $sourceGroupIds)
            ->whereIn('school_cycle_group_id', $sourceCycleGroupIds)
            ->where('is_active', true)
            ->get()
            ->groupBy('group_id')
            ->map(function ($rows) {
                return \App\Models\TeachingAssignment::query()
                    ->with(['group.level.modality'])
                    ->whereIn('id', collect($rows)->pluck('id')->all())
                    ->get();
            });
        $assignmentIds = $assignmentsByGroup
            ->flatten(1)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $firstSecondPeriodIds = \App\Models\CyclePartial::query()
            ->where('school_cycle_id', $sourceCycle->id)
            ->whereNotNull('academic_period_id')
            ->where('sort_order', '<=', 2)
            ->orderBy('sort_order')
            ->pluck('academic_period_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $students = Student::query()
            ->where('is_active', true)
            ->whereHas('group.level', function ($q) use ($modalityId) {
                $q->where('modality_id', $modalityId);
            })
            ->when(
                ! empty($sourceGroupIds),
                fn ($q) => $q->whereIn('group_id', $sourceGroupIds),
                fn ($q) => $q->whereRaw('1 = 0')
            )
            ->with(['user', 'group.level'])
            ->orderBy('group_id')
            ->get();
        $studentIds = $students->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $finalGradesByStudentAssignment = collect();
        if ($assignmentIds->isNotEmpty() && $firstSecondPeriodIds->isNotEmpty() && $studentIds->isNotEmpty()) {
            $periodAverages = DB::table('grades')
                ->join('activities', 'grades.activity_id', '=', 'activities.id')
                ->whereIn('grades.student_id', $studentIds->all())
                ->whereIn('activities.teaching_assignment_id', $assignmentIds->all())
                ->whereIn('activities.academic_period_id', $firstSecondPeriodIds->all())
                ->groupBy('grades.student_id', 'activities.teaching_assignment_id', 'activities.academic_period_id')
                ->selectRaw('grades.student_id as student_id, activities.teaching_assignment_id as assignment_id, activities.academic_period_id as period_id, AVG(grades.score) as avg_score')
                ->get();

            $finalGradesByStudentAssignment = $periodAverages
                ->groupBy(fn ($row) => ((int) $row->student_id).'-'.((int) $row->assignment_id))
                ->map(function ($rows) {
                    $scores = collect($rows)
                        ->pluck('avg_score')
                        ->filter(fn ($value) => $value !== null)
                        ->map(fn ($value) => (float) $value)
                        ->values();

                    if ($scores->isEmpty()) {
                        return null;
                    }

                    return round($scores->avg(), 2);
                });
        }

        $historicalFinals = collect();
        if ($assignmentIds->isNotEmpty() && $studentIds->isNotEmpty()) {
            $historicalFinals = DB::table('student_assignment_historicals')
                ->where('school_cycle_id', $sourceCycle->id)
                ->whereIn('student_id', $studentIds->all())
                ->whereIn('teaching_assignment_id', $assignmentIds->all())
                ->whereNotNull('final_grade')
                ->get(['student_id', 'teaching_assignment_id', 'final_grade'])
                ->mapWithKeys(function ($row) {
                    $key = ((int) $row->student_id) . '-' . ((int) $row->teaching_assignment_id);
                    return [$key => round((float) $row->final_grade, 2)];
                });

            $historicalFinals->each(function ($value, $key) use (&$finalGradesByStudentAssignment) {
                if (! $finalGradesByStudentAssignment->has($key) || $finalGradesByStudentAssignment->get($key) === null) {
                    $finalGradesByStudentAssignment->put($key, $value);
                }
            });
        }

        $nextLevelMap = $this->buildNextLevelMap($modalityId);
        $levelNames = Level::query()
            ->where('modality_id', $modalityId)
            ->pluck('name', 'id');

        $rows = $students->map(function (Student $student) use ($nextLevelMap, $targetGroupsByLevel, $assignmentsByGroup, $finalGradesByStudentAssignment) {
            $currentGroup = $student->group;
            $currentLevel = $currentGroup?->level;
            $assignmentSet = collect();
            if ($currentGroup) {
                $assignmentSet = $assignmentsByGroup->get((int) $currentGroup->id, collect());
            }
            $promotionRule = $this->evaluatePromotionRule($student, $assignmentSet, $finalGradesByStudentAssignment);
            $repeatOptions = collect();
            $defaultRepeatGroupId = null;
            if ($currentLevel) {
                $repeatOptions = collect($targetGroupsByLevel->get((int) $currentLevel->id, []));
                if ($repeatOptions->count() === 1) {
                    $defaultRepeatGroupId = (int) ($repeatOptions->first()['id'] ?? 0);
                }
            }

            if (! $currentGroup || ! $currentLevel) {
                return array_merge(
                    $this->baseRow($student, 'sin_grupo', 'Sin grupo o nivel asignado'),
                    [
                        'current_group_id' => null,
                        'target_group_id' => null,
                        'default_action' => 'skip',
                        'promote_group_options' => [],
                        'repeat_group_options' => [],
                        'default_promote_group_id' => null,
                        'default_repeat_group_id' => null,
                        'failed_subjects' => $promotionRule['failed_subjects'],
                        'evaluated_subjects' => $promotionRule['evaluated_subjects'],
                        'promotion_rule' => $promotionRule['label'],
                    ]
                );
            }

            $nextLevelId = $nextLevelMap[$currentLevel->id] ?? null;

            if (! $nextLevelId) {
                return array_merge(
                    $this->baseRow($student, 'sin_nivel_siguiente', 'No existe un siguiente nivel configurado'),
                    [
                        'current_group_id' => $currentGroup->id,
                        'current_group' => $currentGroup->name,
                        'current_level' => $currentLevel->name,
                        'next_level' => null,
                        'target_group' => null,
                        'target_group_id' => null,
                        'default_action' => 'graduate',
                        'promote_group_options' => [],
                        'repeat_group_options' => $repeatOptions->all(),
                        'default_promote_group_id' => null,
                        'default_repeat_group_id' => $defaultRepeatGroupId,
                        'failed_subjects' => $promotionRule['failed_subjects'],
                        'evaluated_subjects' => $promotionRule['evaluated_subjects'],
                        'promotion_rule' => $promotionRule['label'],
                    ]
                );
            }

            $nextLevelName = $levelNames[$nextLevelId] ?? null;
            $promoteOptions = collect($targetGroupsByLevel->get((int) $nextLevelId, []));
            $defaultPromoteGroupId = null;
            if ($promoteOptions->count() === 1) {
                $defaultPromoteGroupId = (int) ($promoteOptions->first()['id'] ?? 0);
            }

            if ($promoteOptions->isEmpty()) {
                return array_merge(
                    $this->baseRow($student, 'sin_grupo_destino', 'No hay grupo activo en el siguiente nivel'),
                    [
                        'current_group_id' => $currentGroup->id,
                        'current_group' => $currentGroup->name,
                        'current_level' => $currentLevel->name,
                        'next_level' => $nextLevelName,
                        'target_group' => null,
                        'target_group_id' => null,
                        'default_action' => 'repeat',
                        'promote_group_options' => [],
                        'repeat_group_options' => $repeatOptions->all(),
                        'default_promote_group_id' => null,
                        'default_repeat_group_id' => $defaultRepeatGroupId,
                        'failed_subjects' => $promotionRule['failed_subjects'],
                        'evaluated_subjects' => $promotionRule['evaluated_subjects'],
                        'promotion_rule' => $promotionRule['label'],
                    ]
                );
            }

            $defaultAction = $promotionRule['can_promote'] ? 'promote' : 'repeat';
            $message = $promotionRule['can_promote']
                ? 'Listo para promocion (' . $promotionRule['label'] . ')'
                : 'Repite por regla academica (' . $promotionRule['label'] . ')';

            return array_merge(
                $this->baseRow($student, 'listo', $message),
                [
                    'current_group_id' => $currentGroup->id,
                    'current_group' => $currentGroup->name,
                    'current_level' => $currentLevel->name,
                    'next_level' => $nextLevelName,
                    'target_group' => $defaultPromoteGroupId
                        ? (string) $promoteOptions->firstWhere('id', $defaultPromoteGroupId)['name']
                        : null,
                    'target_group_id' => $defaultPromoteGroupId,
                    'default_action' => $defaultAction,
                    'promote_group_options' => $promoteOptions->all(),
                    'repeat_group_options' => $repeatOptions->all(),
                    'default_promote_group_id' => $defaultPromoteGroupId,
                    'default_repeat_group_id' => $defaultRepeatGroupId,
                    'failed_subjects' => $promotionRule['failed_subjects'],
                    'evaluated_subjects' => $promotionRule['evaluated_subjects'],
                    'promotion_rule' => $promotionRule['label'],
                ]
            );
        });

        return [
            'rows' => $rows->values(),
            'summary' => [
                'total' => $rows->count(),
                'ready' => $rows->where('status', 'listo')->count(),
                'without_next_level' => $rows->where('status', 'sin_nivel_siguiente')->count(),
                'without_target_group' => $rows->where('status', 'sin_grupo_destino')->count(),
                'without_group' => $rows->where('status', 'sin_grupo')->count(),
            ],
        ];
    }

    public function execute(
        SchoolCycle $sourceCycle,
        SchoolCycle $targetCycle,
        int $modalityId,
        array $actions = [],
        array $promoteGroups = [],
        array $repeatGroups = []
    ): array
    {
        $preview = $this->preview($sourceCycle, $targetCycle, $modalityId);
        $rows = collect($preview['rows']);
        $targetStart = Carbon::parse($targetCycle->start_date)->startOfDay();
        $closingDate = $targetStart->copy()->subDay()->toDateString();

        $promoted = 0;
        $repeated = 0;
        $graduated = 0;
        $skipped = 0;
        $errors = [];

        $rows->each(function (array $row) use (
            &$promoted,
            &$repeated,
            &$graduated,
            &$skipped,
            &$errors,
            $sourceCycle,
            $targetCycle,
            $targetStart,
            $closingDate,
            $actions,
            $promoteGroups,
            $repeatGroups
        ) {
            $action = $actions[(string) $row['student_id']] ?? ($row['default_action'] ?? 'skip');

            try {
                DB::transaction(function () use (
                    $row,
                    $sourceCycle,
                    $targetCycle,
                    $targetStart,
                    $closingDate,
                    $action,
                    $promoteGroups,
                    $repeatGroups,
                    &$promoted,
                    &$repeated,
                    &$graduated,
                    &$skipped
                ) {
                    $student = Student::query()->lockForUpdate()->find($row['student_id']);
                    if (! $student) {
                        $skipped++;
                        return;
                    }

                    if ($action === 'skip') {
                        $skipped++;
                        return;
                    }

                    if ($action === 'graduate') {
                        $this->closeCurrentHistory($student, $closingDate);
                        $student->update(['is_active' => false]);
                        $graduated++;
                        return;
                    }

                    if ($action === 'repeat') {
                        $allowedIds = collect($row['repeat_group_options'] ?? [])
                            ->pluck('id')
                            ->map(fn ($id) => (int) $id)
                            ->all();
                        $selectedGroupId = (int) ($repeatGroups[(string) $row['student_id']] ?? ($row['default_repeat_group_id'] ?? 0));
                        if ($selectedGroupId <= 0) {
                            throw new \InvalidArgumentException('Selecciona el grupo destino para repeticion.');
                        }
                        if (! in_array($selectedGroupId, $allowedIds, true)) {
                            throw new \InvalidArgumentException('El grupo de repeticion seleccionado no es valido para este alumno.');
                        }

                        $this->moveStudentToGroup(
                            $student,
                            $selectedGroupId,
                            $targetStart->toDateString(),
                            $closingDate,
                            'repeticion de grado de ciclo ' . $sourceCycle->code . ' a ' . $targetCycle->code
                        );
                        $student->update(['is_active' => true]);
                        $repeated++;
                        return;
                    }

                    if ($action === 'promote' && $row['status'] === 'listo') {
                        $allowedIds = collect($row['promote_group_options'] ?? [])
                            ->pluck('id')
                            ->map(fn ($id) => (int) $id)
                            ->all();
                        $selectedGroupId = (int) ($promoteGroups[(string) $row['student_id']] ?? ($row['default_promote_group_id'] ?? 0));
                        if ($selectedGroupId <= 0) {
                            throw new \InvalidArgumentException('Selecciona el grupo destino para promocion.');
                        }
                        if (! in_array($selectedGroupId, $allowedIds, true)) {
                            throw new \InvalidArgumentException('El grupo de promocion seleccionado no es valido para este alumno.');
                        }

                        $this->moveStudentToGroup(
                            $student,
                            $selectedGroupId,
                            $targetStart->toDateString(),
                            $closingDate,
                            'promocion de ciclo ' . $sourceCycle->code . ' a ' . $targetCycle->code
                        );
                        $student->update(['is_active' => true]);
                        $promoted++;
                        return;
                    }

                    $skipped++;
                });
            } catch (\Throwable $e) {
                $errors[] = $row['student_name'] . ': ' . $e->getMessage();
            }
        });

        return [
            'summary' => $preview['summary'],
            'promoted' => $promoted,
            'repeated' => $repeated,
            'graduated' => $graduated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    protected function validateCycles(SchoolCycle $sourceCycle, SchoolCycle $targetCycle, int $modalityId): void
    {
        if ((int) $sourceCycle->id === (int) $targetCycle->id) {
            throw new \InvalidArgumentException('El ciclo origen y destino deben ser distintos.');
        }

        if ((int) $sourceCycle->campus_id !== (int) $targetCycle->campus_id) {
            throw new \InvalidArgumentException('El ciclo origen y destino deben pertenecer al mismo campus.');
        }

        $sourceCycle->loadMissing('modalities');
        $targetCycle->loadMissing('modalities');

        if (! $sourceCycle->hasModality($modalityId) || ! $targetCycle->hasModality($modalityId)) {
            throw new \InvalidArgumentException('Ambos ciclos deben incluir la modalidad seleccionada.');
        }
    }

    protected function buildNextLevelMap(int $modalityId): array
    {
        $levels = Level::query()
            ->where('modality_id', $modalityId)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (Level $level) => $this->levelOrderKey($level->name))
            ->values();

        $map = [];

        foreach ($levels as $index => $level) {
            $map[$level->id] = $levels->get($index + 1)?->id;
        }

        return $map;
    }

    protected function resolveTargetGroup(Group $currentGroup, int $nextLevelId, array $allowedGroupIds): ?Group
    {
        if (empty($allowedGroupIds)) {
            return null;
        }

        $baseQuery = Group::query()
            ->whereIn('id', $allowedGroupIds)
            ->where('level_id', $nextLevelId)
            ->where('is_active', true);

        $promotedPattern = $this->promoteGroupNamePattern($currentGroup->name);
        if ($promotedPattern !== null) {
            $promotedMatch = (clone $baseQuery)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($promotedPattern)])
                ->first();

            if ($promotedMatch) {
                return $promotedMatch;
            }

            $promotedPrefixMatch = (clone $baseQuery)
                ->whereRaw('LOWER(name) LIKE ?', [mb_strtolower($promotedPattern) . ' %'])
                ->orderBy('name')
                ->first();

            if ($promotedPrefixMatch) {
                return $promotedPrefixMatch;
            }
        }

        $sameName = (clone $baseQuery)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($currentGroup->name))])
            ->first();

        if ($sameName) {
            return $sameName;
        }

        return $baseQuery->orderBy('name')->first();
    }

    protected function promoteGroupNamePattern(string $name): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        if (preg_match('/^([1-9])(\d.*)$/', $name, $matches) !== 1) {
            return null;
        }

        $currentGrade = (int) $matches[1];
        $rest = $matches[2];
        $nextGrade = $currentGrade + 1;

        if ($nextGrade > 6) {
            return null;
        }

        return (string) $nextGrade . $rest;
    }

    protected function levelOrderKey(string $levelName): int
    {
        $name = mb_strtolower(trim($levelName));

        if (preg_match('/\d+/', $name, $matches) === 1) {
            return (int) $matches[0];
        }

        $map = [
            'primero' => 1,
            'primer' => 1,
            'segundo' => 2,
            'tercero' => 3,
            'cuarto' => 4,
            'quinto' => 5,
            'sexto' => 6,
            'septimo' => 7,
            'séptimo' => 7,
            'octavo' => 8,
            'noveno' => 9,
            'decimo' => 10,
            'décimo' => 10,
        ];

        foreach ($map as $token => $value) {
            if (str_contains($name, $token)) {
                return $value;
            }
        }

        return PHP_INT_MAX;
    }

    protected function baseRow(Student $student, string $status, string $message): array
    {
        return [
            'student_id' => $student->id,
            'student_name' => $student->user?->name ?? ('Alumno #' . $student->id),
            'enrollment_number' => $student->enrollment_number,
            'status' => $status,
            'message' => $message,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int,\App\Models\TeachingAssignment>  $assignments
     * @return array{failed_subjects:int,evaluated_subjects:int,can_promote:bool,label:string}
     */
    protected function evaluatePromotionRule(Student $student, Collection $assignments, Collection $finalGradesByStudentAssignment): array
    {
        $failedSubjects = 0;
        $evaluatedSubjects = 0;

        foreach ($assignments as $assignment) {
            $key = (int) $student->id . '-' . (int) $assignment->id;
            $final = $finalGradesByStudentAssignment->get($key);
            if ($final === null) {
                continue;
            }

            $evaluatedSubjects++;

            if ((float) $final < 6.0) {
                $failedSubjects++;
            }
        }

        $canPromote = $failedSubjects <= 3;
        $label = $canPromote
            ? 'Promueve (reprobadas: ' . $failedSubjects . ' de ' . $evaluatedSubjects . ')'
            : 'Repite (reprobadas: ' . $failedSubjects . ' de ' . $evaluatedSubjects . ')';

        return [
            'failed_subjects' => $failedSubjects,
            'evaluated_subjects' => $evaluatedSubjects,
            'can_promote' => $canPromote,
            'label' => $label,
        ];
    }

    protected function moveStudentToGroup(
        Student $student,
        int $targetGroupId,
        string $targetStartDate,
        string $closingDate,
        string $reason
    ): void {
        $alreadyMoved = StudentGroupHistory::query()
            ->where('student_id', $student->id)
            ->where('group_id', $targetGroupId)
            ->whereDate('start_date', $targetStartDate)
            ->exists();

        if ($alreadyMoved && (int) $student->group_id === $targetGroupId) {
            return;
        }

        $this->closeCurrentHistory($student, $closingDate);

        StudentGroupHistory::firstOrCreate(
            [
                'student_id' => $student->id,
                'group_id' => $targetGroupId,
                'start_date' => $targetStartDate,
            ],
            [
                'end_date' => null,
                'reason' => $reason,
            ]
        );

        $student->update([
            'group_id' => $targetGroupId,
        ]);
    }

    protected function closeCurrentHistory(Student $student, string $closingDate): void
    {
        $currentHistory = StudentGroupHistory::query()
            ->where('student_id', $student->id)
            ->whereNull('end_date')
            ->orderByDesc('start_date')
            ->first();

        if (! $currentHistory) {
            return;
        }

        $historyStart = $currentHistory->start_date
            ? Carbon::parse($currentHistory->start_date)->toDateString()
            : null;

        $safeClosingDate = $historyStart && $historyStart > $closingDate
            ? $historyStart
            : $closingDate;

        $currentHistory->update([
            'end_date' => $safeClosingDate,
        ]);
    }
}
