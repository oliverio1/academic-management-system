<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\SchoolCycle;
use App\Models\TeachingAssignment;
use App\Models\TemarioPoint;
use App\Services\CurrentSchoolCycle;
use Barryvdh\Snappy\Facades\SnappyPdf;

class TeacherKardexController extends Controller
{
    public function download(TeachingAssignment $teachingAssignment)
    {
        $teacher = auth()->user()->teacher;

        abort_if(
            $teachingAssignment->teacher_id !== $teacher->id,
            403
        );

        $teachingAssignment->load(['teacher.user', 'group.level.modality', 'subject', 'schedules', 'schoolCycleGroup']);

        $schoolCycle = app(CurrentSchoolCycle::class)->get(auth()->user(), (int) session('active_campus_id', 0));
        if ($schoolCycle && (int) ($teachingAssignment->schoolCycleGroup?->school_cycle_id ?? 0) !== (int) $schoolCycle->id) {
            $schoolCycle = null;
        }

        $period = AcademicPeriod::activeForModality(
            $teachingAssignment->group->level->modality_id
        );

        $sessions = AcademicSession::query()
            ->where('teaching_assignment_id', $teachingAssignment->id)
            ->where('is_cancelled', false)
            ->when(
                $schoolCycle,
                fn ($q) => $q->whereBetween('session_date', [
                    $schoolCycle->start_date->toDateString(),
                    $schoolCycle->end_date->toDateString(),
                ])
            )
            ->whereHas('sessionActivity')
            ->with(['sessionActivity.temarioPoint'])
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        $points = $teachingAssignment->temarios()
            ->with(['points' => function ($query) {
                $query->orderBy('position');
            }])
            ->get()
            ->flatMap->points
            ->values();

        $pointsById = $points->keyBy('id');
        $unitsByFirstSegment = $points
            ->filter(fn (TemarioPoint $point) => (int) ($point->level ?? 1) === 1)
            ->mapWithKeys(function (TemarioPoint $point) {
                $first = $this->firstSegmentFromLabel((string) $point->label);
                return $first ? [$first => $point] : [];
            });

        $rows = $sessions->values()->map(function ($session, $index) use ($pointsById, $unitsByFirstSegment) {
            $sessionActivity = $session->sessionActivity;
            $topicPoint = $sessionActivity?->temarioPoint;
            $topicText = $topicPoint ? $this->formatTemarioPoint($topicPoint) : ((string) ($sessionActivity?->title ?? '-'));

            $unitPoint = null;
            if ($topicPoint) {
                $first = $this->firstSegmentFromLabel((string) $topicPoint->label);
                $unitPoint = $first ? ($unitsByFirstSegment->get($first) ?? null) : null;
            }

            $subtopicText = '-';
            $subtopicIds = collect($sessionActivity?->temario_subtopic_ids ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values();

            if ($subtopicIds->isNotEmpty()) {
                $subtopicText = $subtopicIds
                    ->map(fn ($id) => $pointsById->get($id))
                    ->filter()
                    ->map(fn (TemarioPoint $point) => $this->formatTemarioPoint($point))
                    ->values()
                    ->implode('; ');
                $subtopicText = $subtopicText !== '' ? $subtopicText : '-';
            }

            return [
                'num' => $index + 1,
                'hour' => '1',
                'date' => optional($session->session_date)->format('d/m/Y'),
                'unit' => $unitPoint ? $this->formatTemarioPoint($unitPoint) : '-',
                'topic' => $topicText !== '' ? $topicText : '-',
                'subtopic' => $subtopicText,
            ];
        });

        $days = $teachingAssignment->schedules
            ->where('is_active', true)
            ->pluck('day_of_week')
            ->map(fn ($day) => $this->dayLabel((string) $day))
            ->unique()
            ->values()
            ->implode(', ');

        $scheduleText = $teachingAssignment->schedules
            ->where('is_active', true)
            ->map(fn ($s) => substr((string) $s->start_time, 0, 5) . '-' . substr((string) $s->end_time, 0, 5))
            ->unique()
            ->values()
            ->implode(', ');

        return SnappyPdf::loadView('teacher.kardex.pdf', [
            'assignment' => $teachingAssignment,
            'rows' => $rows,
            'period' => $period,
            'schoolCycle' => $schoolCycle,
            'days' => $days,
            'scheduleText' => $scheduleText,
        ])
            ->setPaper('letter', 'landscape')
            ->setOption('encoding', 'UTF-8')
            ->setOption('disable-javascript', true)
            ->setOption('enable-local-file-access', true)
            ->setOption('footer-right', 'Pagina [page] de [toPage]')
            ->inline('KARDEX_' . $teachingAssignment->group->name . '_' . $teachingAssignment->subject->name . '.pdf');
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

    private function dayLabel(string $day): string
    {
        $map = [
            'monday' => 'Lunes',
            'tuesday' => 'Martes',
            'wednesday' => 'Miercoles',
            'thursday' => 'Jueves',
            'friday' => 'Viernes',
            'saturday' => 'Sabado',
            'sunday' => 'Domingo',
            'lunes' => 'Lunes',
            'martes' => 'Martes',
            'miercoles' => 'Miercoles',
            'jueves' => 'Jueves',
            'viernes' => 'Viernes',
            'sabado' => 'Sabado',
            'domingo' => 'Domingo',
        ];

        $key = strtolower(trim($day));
        return $map[$key] ?? ucfirst($day);
    }
}
