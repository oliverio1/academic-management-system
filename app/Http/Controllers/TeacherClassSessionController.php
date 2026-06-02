<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Activity;
use App\Models\TeachingAssignment;
use App\Models\AcademicSession;
use App\Models\SchoolCycle;
use App\Models\TemarioPoint;
use App\Services\AcademicSessionGeneratorService;

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

        // Asegura sesiones para todos los periodos de la modalidad (incluidos parciales deshabilitados).
        $sessionGenerator->generateForAssignment($teachingAssignment);

        $sessions = AcademicSession::query()
            ->where('teaching_assignment_id', $teachingAssignment->id)
            ->where('is_cancelled', false)
            ->whereIn('academic_period_id', $activeCyclePeriodIds)
            ->with('academicPeriod')
            ->with('sessionActivity.evaluationCriterion')
            ->with('sessionActivity.temarioPoint')
            ->withCount(['attendances', 'sessionActivity'])
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        if ($sessions->isEmpty()) {
            $sessions = AcademicSession::query()
                ->where('teaching_assignment_id', $teachingAssignment->id)
                ->where('is_cancelled', false)
                ->whereIn('academic_period_id', $activeCyclePeriodIds)
                ->with('academicPeriod')
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
            ->where('teaching_assignment_id', $teachingAssignment->id)
            ->whereNull('session_activity_id')
            ->whereIn('academic_period_id', $activeCyclePeriodIds)
            ->with(['evaluationCriterion:id,name', 'academicPeriod:id,name'])
            ->withCount('grades')
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->get();

        return view('teacher.classes.sessions.index', [
            'assignment' => $teachingAssignment,
            'sessions'   => $sessions,
            'manualActivities' => $manualActivities,
        ]);
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
        $activeCampusId = (int) session('active_campus_id', 0);

        $activeCycle = SchoolCycle::query()
            ->where('is_active', true)
            ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
            ->orderByDesc('start_date')
            ->first();

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
