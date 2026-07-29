<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Activity;
use App\Models\CyclePartial;
use App\Models\TeachingAssignment;
use App\Models\AcademicSession;
use App\Models\EvaluationCriterion;
use App\Models\SchoolCycle;
use App\Models\TemarioPoint;
use App\Services\AcademicSessionGeneratorService;
use App\Services\CurrentSchoolCycle;
use Illuminate\Support\Collection;

class TeacherClassSessionController extends Controller
{
        /**
     * Lista de sesiones de una clase
     */
    public function index(
        TeachingAssignment $teachingAssignment,
        AcademicSessionGeneratorService $sessionGenerator
    )
    {
        $teacher = auth()->user()->teacher;
        $activeCyclePeriodIds = $this->activeCyclePeriodIds();

        abort_if(
            $teachingAssignment->teacher_id !== $teacher->id,
            403
        );

        $relatedAssignments = $this->relatedAssignments($teachingAssignment);
        $relatedAssignmentIds = $relatedAssignments->pluck('id')->map(fn ($id) => (int) $id)->all();

        // Asegura sesiones para todos los periodos de la modalidad (incluidos parciales deshabilitados).
        $relatedAssignments->each(fn (TeachingAssignment $assignment) => $sessionGenerator->generateForAssignment($assignment));

        $sessions = AcademicSession::query()
            ->whereIn('teaching_assignment_id', $relatedAssignmentIds)
            ->where('is_cancelled', false)
            ->whereIn('academic_period_id', $activeCyclePeriodIds)
            ->with(['academicPeriod', 'schedule.schoolCycle', 'teachingAssignment.schoolCycleGroup.schoolCycle'])
            ->with('sessionActivity.evaluationCriterion')
            ->with('sessionActivity.temarioPoint')
            ->withCount(['attendances', 'sessionActivity'])
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        if ($sessions->isEmpty()) {
            $sessions = AcademicSession::query()
                ->whereIn('teaching_assignment_id', $relatedAssignmentIds)
                ->where('is_cancelled', false)
                ->whereIn('academic_period_id', $activeCyclePeriodIds)
                ->with(['academicPeriod', 'schedule.schoolCycle', 'teachingAssignment.schoolCycleGroup.schoolCycle'])
                ->with('sessionActivity.evaluationCriterion')
                ->with('sessionActivity.temarioPoint')
                ->withCount(['attendances', 'sessionActivity'])
                ->orderBy('session_date')
                ->orderBy('start_time')
                ->get();
        }

        $points = $teachingAssignment->temarios()
            ->with(['points' => function ($query) {
                $query->orderBy('position');
            }])
            ->get()
            ->flatMap->points
            ->values();

        $unitsByFirstSegment = $points
            ->filter(fn (TemarioPoint $point) => (int) ($point->level ?? 1) === 1)
            ->mapWithKeys(function (TemarioPoint $point) {
                $first = $this->firstSegmentFromLabel((string) $point->label);
                return $first ? [$first => $point] : [];
            });

        foreach ($sessions as $session) {
            $activity = $session->sessionActivity;
            if (! $activity) {
                $session->temario_resume = [
                    'unit' => '-',
                    'topic' => '-',
                    'subtopics_count' => 0,
                ];
                continue;
            }

            $topicPoint = $activity->temarioPoint;
            $topicText = $topicPoint
                ? $this->formatTemarioPoint($topicPoint)
                : trim((string) ($activity->title ?? ''));
            $topicText = $topicText !== '' ? $topicText : '-';

            $unitText = '-';
            if ($topicPoint) {
                $first = $this->firstSegmentFromLabel((string) $topicPoint->label);
                $unitPoint = $first ? ($unitsByFirstSegment->get($first) ?? null) : null;
                if ($unitPoint) {
                    $unitText = $this->stripUnitObjective($this->formatTemarioPoint($unitPoint));
                }
            }

            $subtopicsCount = collect($activity->temario_subtopic_ids ?? [])
                ->filter(fn ($id) => !empty($id))
                ->unique()
                ->count();

            $session->temario_resume = [
                'unit' => $unitText,
                'topic' => $topicText,
                'subtopics_count' => $subtopicsCount,
            ];
        }

        $manualActivities = Activity::query()
            ->whereIn('teaching_assignment_id', $relatedAssignmentIds)
            ->whereNull('session_activity_id')
            ->whereIn('academic_period_id', $activeCyclePeriodIds)
            ->with(['evaluationCriterion:id,name', 'academicPeriod:id,name'])
            ->withCount('grades')
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->get();

        $periodHasCriteria = $this->criteriaMapForSessions($relatedAssignments, $sessions);

        return view('teacher.classes.sessions.index', [
            'assignment' => $teachingAssignment,
            'relatedAssignments' => $relatedAssignments,
            'sessions'   => $sessions,
            'manualActivities' => $manualActivities,
            'allowFutureAttendanceCapture' => $this->allowsFutureAttendanceCaptureForLocalTesting(),
            'editableAttendanceCycleCodes' => config('attendance.editable_cycle_codes_for_testing', []),
            'periodHasCriteria' => $periodHasCriteria,
        ]);
    }

    private function allowsFutureAttendanceCaptureForLocalTesting(): bool
    {
        return config('app.env') === 'local'
            && (bool) config('attendance.allow_future_capture_local');
    }

    private function relatedAssignments(TeachingAssignment $assignment): Collection
    {
        return TeachingAssignment::query()
            ->with(['subject', 'group'])
            ->where('teacher_id', $assignment->teacher_id)
            ->where('school_cycle_group_id', $assignment->school_cycle_group_id)
            ->where('group_id', $assignment->group_id)
            ->where('subject_id', $assignment->subject_id)
            ->where('is_active', true)
            ->orderByRaw('CASE WHEN section_number IS NULL OR section_number = 0 THEN 0 ELSE 1 END')
            ->orderBy('section_number')
            ->orderBy('id')
            ->get();
    }

    private function criteriaMapForSessions(Collection $assignments, Collection $sessions): array
    {
        $cycleId = (int) optional($assignments->first()?->schoolCycleGroup)->school_cycle_id;
        $periodIds = $sessions
            ->pluck('academic_period_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $partialIdsByPeriod = $cycleId > 0 && $periodIds->isNotEmpty()
            ? CyclePartial::query()
                ->where('school_cycle_id', $cycleId)
                ->whereIn('academic_period_id', $periodIds->all())
                ->get(['id', 'academic_period_id'])
                ->groupBy('academic_period_id')
                ->map(fn ($partials) => $partials->pluck('id')->map(fn ($id) => (int) $id)->all())
            : collect();

        return $sessions
            ->map(fn (AcademicSession $session) => [
                'assignment_id' => (int) $session->teaching_assignment_id,
                'period_id' => (int) $session->academic_period_id,
            ])
            ->unique(fn (array $item) => $item['assignment_id'].'|'.$item['period_id'])
            ->mapWithKeys(function (array $item) use ($partialIdsByPeriod) {
                $partialIds = $partialIdsByPeriod->get($item['period_id'], []);

                $exists = EvaluationCriterion::query()
                    ->where('teaching_assignment_id', $item['assignment_id'])
                    ->where(function ($query) use ($partialIds) {
                        $query->whereNull('cycle_partial_id');

                        if (! empty($partialIds)) {
                            $query->orWhereIn('cycle_partial_id', $partialIds);
                        }
                    })
                    ->exists();

                return [$item['assignment_id'].'|'.$item['period_id'] => $exists];
            })
            ->all();
    }

    private function formatTemarioPoint(TemarioPoint $point): string
    {
        $label = trim((string) ($point->label ?? ''));
        $content = trim((string) ($point->content ?? ''));
        return trim(($label !== '' ? $label . ' ' : '') . $content);
    }

    private function firstSegmentFromLabel(string $label): ?string
    {
        $key = $this->labelKey($label);
        if (!$key) {
            return null;
        }

        $parts = explode('.', $key);
        return $parts[0] ?? null;
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

    private function stripUnitObjective(string $text): string
    {
        $raw = trim($text);
        if ($raw === '') {
            return $raw;
        }

        return trim((string) preg_replace('/\s*\|\s*Objetivo\s+especifico:\s*.+$/ui', '', $raw));
    }

    private function activeCyclePeriodIds(): array
    {
        $activeCycle = app(CurrentSchoolCycle::class)->get(auth()->user(), (int) session('active_campus_id', 0));

        if (! $activeCycle) {
            return [];
        }

        return $activeCycle->partials()
            ->whereNotNull('academic_period_id')
            ->pluck('academic_period_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
