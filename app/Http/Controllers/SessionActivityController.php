<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\AcademicSession;
use App\Models\SessionActivity;
use App\Models\TemarioPoint;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\EconomicActaLockService;

class SessionActivityController extends Controller
{
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

        [$topicOptions, $subtopicOptions] = $this->buildTemarioSelectors($academicSession);

        return view('session_activities.create', [
            'session' => $academicSession,
            'activity' => $academicSession->sessionActivity,
            'criteria' => $criteria,
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
        $points = $academicSession->teachingAssignment
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

        $topicOptions = $points
            ->filter(fn ($point) => (int) $point['level'] === 2)
            ->map(function ($topic) use ($unitsByFirst) {
                $first = !empty($topic['key']) ? (explode('.', (string) $topic['key'])[0] ?? null) : null;
                $unit = $first ? ($unitsByFirst->get($first) ?? null) : null;

                return [
                    'id' => (int) $topic['id'],
                    'text' => $this->pointText($topic),
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

        return [$topicOptions, $subtopicOptions];
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
}
