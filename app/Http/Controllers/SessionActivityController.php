<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\AcademicSession;
use App\Models\EvaluationCriterion;
use App\Models\SessionActivity;
use App\Models\TemarioPoint;
use App\Models\TeachingAssignment;
use App\Services\CurrentSchoolCycle;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\EconomicActaLockService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SessionActivityController extends Controller
{
    public function massive(
        Request $request,
        TeachingAssignment $assignment,
        EconomicActaLockService $lockService
    ) {
        $this->authorizeTeacherAssignment($assignment);

        $assignment->load(['subject', 'group', 'schoolCycleGroup.schoolCycle']);
        [$mode, $anchorDate, $from, $to] = $this->massiveRange($request);
        $activePeriodIds = $this->activeCyclePeriodIds();

        $sessions = AcademicSession::query()
            ->where('teaching_assignment_id', (int) $assignment->id)
            ->where('is_cancelled', false)
            ->when(! empty($activePeriodIds), fn ($query) => $query->whereIn('academic_period_id', $activePeriodIds))
            ->whereBetween('session_date', [$from->toDateString(), $to->toDateString()])
            ->with([
                'academicPeriod',
                'schedule.schoolCycle',
                'teachingAssignment.schoolCycleGroup.schoolCycle',
                'sessionActivity.evaluationCriterion',
                'sessionActivity.evaluableActivity',
            ])
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        $criteriaByPeriod = $sessions
            ->pluck('academic_period_id')
            ->filter()
            ->unique()
            ->mapWithKeys(function ($periodId) use ($assignment) {
                return [
                    (int) $periodId => EvaluationCriterion::query()
                        ->forAssignmentAndPeriod($assignment, (int) $periodId)
                        ->orderBy('name')
                        ->get(),
                ];
            });

        [$unitOptions, $topicOptions, $subtopicOptions] = $this->buildTemarioSelectorsForAssignment($assignment);

        $sessionLocks = $sessions
            ->mapWithKeys(fn (AcademicSession $session) => [
                (int) $session->id => $this->massiveSessionLock($session, $lockService),
            ])
            ->all();

        return view('session_activities.massive', [
            'assignment' => $assignment,
            'sessions' => $sessions,
            'criteriaByPeriod' => $criteriaByPeriod,
            'unitOptions' => $unitOptions,
            'topicOptions' => $topicOptions,
            'subtopicOptions' => $subtopicOptions,
            'sessionLocks' => $sessionLocks,
            'mode' => $mode,
            'anchorDate' => $anchorDate,
            'from' => $from,
            'to' => $to,
            'testCycleEditing' => $this->assignmentAllowsEditingForTesting($assignment),
        ]);
    }

    public function storeMassive(
        Request $request,
        TeachingAssignment $assignment,
        EconomicActaLockService $lockService
    ) {
        $this->authorizeTeacherAssignment($assignment);

        $data = $request->validate([
            'mode' => ['nullable', 'in:week,month'],
            'date' => ['nullable', 'date'],
            'activities' => ['required', 'array'],
            'activities.*.title' => ['nullable', 'string', 'max:255'],
            'activities.*.description' => ['nullable', 'string'],
            'activities.*.temario_point_id' => ['nullable', 'integer'],
            'activities.*.temario_subtopic_ids' => ['nullable', 'array'],
            'activities.*.temario_subtopic_ids.*' => ['integer'],
            'activities.*.is_evaluable' => ['nullable', 'boolean'],
            'activities.*.evaluation_title' => ['nullable', 'string', 'max:255'],
            'activities.*.evaluation_criterion_id' => ['nullable', 'integer'],
        ]);

        $sessionIds = collect($data['activities'])
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        $sessions = AcademicSession::query()
            ->whereIn('id', $sessionIds->all())
            ->where('teaching_assignment_id', (int) $assignment->id)
            ->with(['academicPeriod', 'schedule.schoolCycle', 'teachingAssignment.schoolCycleGroup.schoolCycle', 'sessionActivity.evaluableActivity'])
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($data, $assignment, $sessions, $lockService) {
            foreach ($data['activities'] as $sessionId => $row) {
                $session = $sessions->get((int) $sessionId);
                if (! $session) {
                    continue;
                }

                if (! $this->allowsEditingForTesting($session) && $this->massiveSessionLock($session, $lockService)['locked']) {
                    continue;
                }

                $title = trim((string) ($row['title'] ?? ''));
                $description = trim((string) ($row['description'] ?? ''));
                $topicPointId = (int) ($row['temario_point_id'] ?? 0);
                $subtopicIds = collect($row['temario_subtopic_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->unique()
                    ->values();
                $isEvaluable = (bool) ($row['is_evaluable'] ?? false);
                $criterionId = (int) ($row['evaluation_criterion_id'] ?? 0);
                $evaluationTitle = trim((string) ($row['evaluation_title'] ?? ''));

                if ($title === '' && ! $isEvaluable) {
                    continue;
                }

                if ($title === '') {
                    $title = $evaluationTitle !== '' ? $evaluationTitle : 'Actividad de clase';
                }

                $validCriterion = $criterionId > 0
                    ? EvaluationCriterion::query()
                        ->forAssignmentAndPeriod($assignment, (int) $session->academic_period_id)
                        ->whereKey($criterionId)
                        ->exists()
                    : false;

                if ($isEvaluable && ! $validCriterion) {
                    continue;
                }

                $topicPoint = $topicPointId > 0
                    ? TemarioPoint::query()
                        ->whereKey($topicPointId)
                        ->whereIn('temario_id', $assignment->temarios()->pluck('id'))
                        ->where('level', 2)
                        ->first()
                    : null;
                if ($topicPointId > 0 && ! $topicPoint) {
                    continue;
                }

                $validSubtopicIds = $this->validSubtopicIdsForTopic($assignment, $topicPoint, $subtopicIds);
                if (! $topicPoint && $subtopicIds->isNotEmpty()) {
                    $validSubtopicIds = collect();
                }

                $sessionActivity = SessionActivity::updateOrCreate(
                    ['academic_session_id' => (int) $session->id],
                    [
                        'title' => $title,
                        'description' => $description !== '' ? $description : null,
                        'temario_point_id' => $topicPoint?->id,
                        'temario_subtopic_ids' => $validSubtopicIds->all(),
                        'evaluation_criterion_id' => $isEvaluable ? $criterionId : null,
                    ]
                );

                $linkedActivity = $sessionActivity->evaluableActivity;
                if (! $isEvaluable) {
                    if ($linkedActivity && ! $linkedActivity->grades()->exists() && ! $linkedActivity->teamGrades()->exists()) {
                        $linkedActivity->delete();
                    }
                    continue;
                }

                Activity::updateOrCreate(
                    ['session_activity_id' => (int) $sessionActivity->id],
                    [
                        'teaching_assignment_id' => (int) $assignment->id,
                        'evaluation_criterion_id' => $criterionId,
                        'academic_period_id' => (int) $session->academic_period_id,
                        'title' => $evaluationTitle !== '' ? $evaluationTitle : $title,
                        'max_score' => 10,
                        'due_date' => $session->session_date,
                        'description' => $description !== '' ? $description : null,
                        'evaluation_mode' => 'individual',
                        'is_active' => true,
                    ]
                );
            }
        });

        return redirect()
            ->route('session.activities.massive', [
                'assignment' => $assignment,
                'mode' => $data['mode'] ?? 'week',
                'date' => $data['date'] ?? now()->toDateString(),
            ])
            ->with('success', 'Actividades masivas guardadas correctamente.');
    }

    public function create(AcademicSession $academicSession, EconomicActaLockService $lockService)
    {
        $teacherId = auth()->user()?->teacher?->id;

        abort_if(
            !$teacherId || $academicSession->teachingAssignment->teacher_id !== $teacherId,
            403
        );

        $periodDisabled = $academicSession->academicPeriod && ! $academicSession->academicPeriod->is_active;
        $isReadOnly = $periodDisabled
            || !is_null($academicSession->attendance_closed_at)
            || $lockService->isSessionLocked($academicSession);

        $criteria = $academicSession->teachingAssignment
            ->evaluationCriteria()
            ->forAssignmentAndPeriod(
                $academicSession->teachingAssignment,
                (int) $academicSession->academic_period_id
            )
            ->orderBy('name')
            ->get();

        [$unitOptions, $topicOptions, $subtopicOptions] = $this->buildTemarioSelectors($academicSession);

        return view('session_activities.create', [
            'session' => $academicSession,
            'activity' => $academicSession->sessionActivity,
            'criteria' => $criteria,
            'unitOptions' => $unitOptions,
            'topicOptions' => $topicOptions,
            'subtopicOptions' => $subtopicOptions,
            'isReadOnly' => $isReadOnly,
            'periodDisabled' => $periodDisabled,
        ]);
    }

    public function store(Request $request, AcademicSession $academicSession, EconomicActaLockService $lockService)
    {
        $teacherId = auth()->user()?->teacher?->id;

        abort_if(
            !$teacherId || $academicSession->teachingAssignment->teacher_id !== $teacherId,
            403
        );

        abort_if(
            $academicSession->academicPeriod && ! $academicSession->academicPeriod->is_active,
            403,
            'Este periodo esta deshabilitado por coordinacion. Solo consulta.'
        );

        abort_if(
            !is_null($academicSession->attendance_closed_at),
            403
        );

        abort_if(
            $lockService->isSessionLocked($academicSession),
            403,
            'El parcial de esta sesion ya tiene acta economica cerrada o enviada. Solo consulta.'
        );

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'temario_point_id' => [
                'nullable',
                'integer',
                Rule::exists('temario_points', 'id')->where(function ($query) use ($academicSession) {
                    $query
                        ->whereIn('temario_id', $academicSession->teachingAssignment->temarios()->pluck('id'))
                        ->where('level', 2);
                }),
            ],
            'temario_subtopic_ids' => 'nullable|array',
            'temario_subtopic_ids.*' => [
                'integer',
                Rule::exists('temario_points', 'id')->where(function ($query) use ($academicSession) {
                    $query
                        ->whereIn('temario_id', $academicSession->teachingAssignment->temarios()->pluck('id'))
                        ->where('level', '>=', 3);
                }),
            ],
            'evaluation_criterion_id' => [
                'nullable',
                'integer',
            ],
        ]);

        $criterionId = isset($data['evaluation_criterion_id'])
            ? (int) $data['evaluation_criterion_id']
            : null;

        if (!is_null($criterionId)) {
            $validCriterionIds = \App\Models\EvaluationCriterion::query()
                ->forAssignmentAndPeriod(
                    $academicSession->teachingAssignment,
                    (int) $academicSession->academic_period_id
                )
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (! in_array($criterionId, $validCriterionIds, true)) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'evaluation_criterion_id' => 'El rubro no pertenece al parcial de esta sesion.',
                    ]);
            }
        }

        $topicPoint = null;
        $topicKey = null;
        $subtopicIds = collect($data['temario_subtopic_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if (!empty($data['temario_point_id'])) {
            $topicPoint = TemarioPoint::query()->find($data['temario_point_id']);
            $topicKey = $this->labelKey((string) optional($topicPoint)->label);
        }

        if (!$topicPoint && $subtopicIds->isNotEmpty()) {
            return back()
                ->withInput()
                ->withErrors([
                    'temario_subtopic_ids' => 'Primero selecciona un tema para poder elegir subtemas.',
                ]);
        }

        if ($topicPoint && $subtopicIds->isNotEmpty()) {
            $invalidSubtopics = TemarioPoint::query()
                ->whereIn('id', $subtopicIds)
                ->get()
                ->first(function (TemarioPoint $subtopic) use ($topicKey) {
                    $subtopicKey = $this->labelKey((string) $subtopic->label);
                    return !($topicKey && $subtopicKey && str_starts_with($subtopicKey, $topicKey . '.'));
                });

            if ($invalidSubtopics) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'temario_subtopic_ids' => 'Uno o mas subtemas no corresponden al tema seleccionado.',
                    ]);
            }
        }

        $data['temario_subtopic_ids'] = $subtopicIds->all();

        $sessionActivity = SessionActivity::updateOrCreate(
            ['academic_session_id' => $academicSession->id],
            $data
        );

        $linkedActivity = $sessionActivity->evaluableActivity;
        $criterionId = $criterionId ?? null;

        if (is_null($criterionId)) {
            if ($linkedActivity && ($linkedActivity->grades()->exists() || $linkedActivity->teamGrades()->exists())) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'evaluation_criterion_id' => 'No puedes quitar el rubro porque esta actividad ya tiene calificaciones.',
                    ]);
            }

            $linkedActivity?->delete();
        } else {
            Activity::updateOrCreate(
                ['session_activity_id' => $sessionActivity->id],
                [
                    'teaching_assignment_id' => $academicSession->teaching_assignment_id,
                    'evaluation_criterion_id' => $criterionId,
                    'academic_period_id' => $academicSession->academic_period_id,
                    'title' => $data['title'],
                    'max_score' => 10,
                    'due_date' => $academicSession->session_date,
                    'description' => $data['description'] ?? null,
                    'evaluation_mode' => 'individual',
                    'is_active' => true,
                ]
            );
        }

        return redirect()
            ->route('teacher.classes.sessions.index', $academicSession->teachingAssignment)
            ->with('success', 'Actividad registrada correctamente.');
    }

    private function buildTemarioSelectors(AcademicSession $academicSession): array
    {
        return $this->buildTemarioSelectorsForAssignment($academicSession->teachingAssignment);
    }

    private function buildTemarioSelectorsForAssignment(TeachingAssignment $assignment): array
    {
        $points = $assignment
            ->temarios()
            ->with(['points' => function ($query) {
                $query->orderBy('position');
            }])
            ->orderBy('id')
            ->get()
            ->flatMap(function ($temario) {
                return $temario->points->map(function ($point) use ($temario) {
                    return [
                        'id' => (int) $point->id,
                        'label' => (string) ($point->label ?? ''),
                        'content' => (string) ($point->content ?? ''),
                        'level' => (int) ($point->level ?? 1),
                        'temario_title' => (string) ($temario->title ?? ''),
                        'key' => $this->labelKey((string) ($point->label ?? '')),
                    ];
                });
            })
            ->values();

        $unitsByFirst = $points
            ->filter(fn ($point) => (int) $point['level'] === 1 && !empty($point['key']))
            ->mapWithKeys(function ($unit) {
                $first = explode('.', (string) $unit['key'])[0] ?? null;
                return $first ? [$first => $unit] : [];
            });

        $unitOptions = $unitsByFirst
            ->map(fn ($unit, $first) => [
                'id' => (string) $first,
                'text' => $this->pointText($unit),
                'temario_title' => $unit['temario_title'],
            ])
            ->values();

        $topicOptions = $points
            ->filter(fn ($point) => (int) $point['level'] === 2)
            ->map(function ($topic) use ($unitsByFirst) {
                $first = !empty($topic['key']) ? (explode('.', (string) $topic['key'])[0] ?? null) : null;
                $unit = $first ? ($unitsByFirst->get($first) ?? null) : null;

                return [
                    'id' => (int) $topic['id'],
                    'text' => $this->pointText($topic),
                    'unit_id' => $first,
                    'unit_text' => $unit ? $this->pointText($unit) : null,
                    'key' => $topic['key'],
                    'temario_title' => $topic['temario_title'],
                ];
            })
            ->values();

        $topicKeyById = $topicOptions->mapWithKeys(fn ($topic) => [(int) $topic['id'] => (string) ($topic['key'] ?? '')]);

        $subtopicOptions = $points
            ->filter(fn ($point) => (int) $point['level'] >= 3)
            ->map(function ($subtopic) use ($topicKeyById) {
                $topicId = null;
                foreach ($topicKeyById as $candidateTopicId => $topicKey) {
                    if ($topicKey !== '' && !empty($subtopic['key']) && str_starts_with((string) $subtopic['key'], $topicKey . '.')) {
                        $topicId = (int) $candidateTopicId;
                        break;
                    }
                }

                return [
                    'id' => (int) $subtopic['id'],
                    'text' => $this->pointText($subtopic),
                    'topic_id' => $topicId,
                ];
            })
            ->filter(fn ($subtopic) => !empty($subtopic['topic_id']))
            ->values();

        return [$unitOptions, $topicOptions, $subtopicOptions];
    }

    private function validSubtopicIdsForTopic(TeachingAssignment $assignment, ?TemarioPoint $topicPoint, Collection $subtopicIds): Collection
    {
        if (! $topicPoint || $subtopicIds->isEmpty()) {
            return collect();
        }

        $topicKey = $this->labelKey((string) $topicPoint->label);
        if (! $topicKey) {
            return collect();
        }

        return TemarioPoint::query()
            ->whereIn('id', $subtopicIds->all())
            ->whereIn('temario_id', $assignment->temarios()->pluck('id'))
            ->where('level', '>=', 3)
            ->get()
            ->filter(function (TemarioPoint $subtopic) use ($topicKey) {
                $subtopicKey = $this->labelKey((string) $subtopic->label);

                return $subtopicKey && str_starts_with($subtopicKey, $topicKey . '.');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    private function pointText(array $point): string
    {
        $label = trim((string) ($point['label'] ?? ''));
        $content = trim((string) ($point['content'] ?? ''));
        return trim(($label !== '' ? $label . ' ' : '') . $content);
    }

    private function labelKey(string $label): ?string
    {
        $clean = trim($label);
        if ($clean === '') {
            return null;
        }

        if (preg_match('/^([0-9]+(?:\.[0-9]+)*)\.?$/', $clean, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function massiveRange(Request $request): array
    {
        $mode = $request->input('mode') === 'month' ? 'month' : 'week';
        $anchorDate = Carbon::parse($request->input('date', now()->toDateString()))->startOfDay();

        if ($mode === 'month') {
            return [$mode, $anchorDate, $anchorDate->copy()->startOfMonth(), $anchorDate->copy()->endOfMonth()];
        }

        return [$mode, $anchorDate, $anchorDate->copy()->startOfWeek(), $anchorDate->copy()->endOfWeek()];
    }

    private function massiveSessionLock(AcademicSession $session, EconomicActaLockService $lockService): array
    {
        if ($this->allowsEditingForTesting($session)) {
            return ['locked' => false, 'reason' => null];
        }

        if ($session->academicPeriod && ! $session->academicPeriod->is_active) {
            return ['locked' => true, 'reason' => 'Periodo cerrado'];
        }

        if ($session->isAttendanceClosed()) {
            return ['locked' => true, 'reason' => 'Semana cerrada'];
        }

        if ($lockService->isSessionLocked($session)) {
            return ['locked' => true, 'reason' => 'Acta cerrada'];
        }

        return ['locked' => false, 'reason' => null];
    }

    private function allowsEditingForTesting(AcademicSession $session): bool
    {
        $cycleCode = (string) (
            $session->teachingAssignment?->schoolCycleGroup?->schoolCycle?->code
            ?? $session->schedule?->schoolCycle?->code
            ?? ''
        );

        return $cycleCode !== ''
            && in_array($cycleCode, config('attendance.editable_cycle_codes_for_testing', []), true);
    }

    private function assignmentAllowsEditingForTesting(TeachingAssignment $assignment): bool
    {
        $cycleCode = (string) ($assignment->schoolCycleGroup?->schoolCycle?->code ?? '');

        return $cycleCode !== ''
            && in_array($cycleCode, config('attendance.editable_cycle_codes_for_testing', []), true);
    }

    private function activeCyclePeriodIds(): array
    {
        $cycle = app(CurrentSchoolCycle::class)->get(auth()->user(), (int) session('active_campus_id', 0));

        if (! $cycle) {
            return [];
        }

        return $cycle->partials()
            ->whereNotNull('academic_period_id')
            ->pluck('academic_period_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function authorizeTeacherAssignment(TeachingAssignment $assignment): void
    {
        $teacherId = (int) (auth()->user()?->teacher?->id ?? 0);

        abort_if($teacherId <= 0 || (int) $assignment->teacher_id !== $teacherId, 403);
    }
}
