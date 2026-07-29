<?php

namespace App\Http\Controllers;

use App\Models\TeachingAssignment;
use App\Models\AcademicPeriod;
use App\Models\CyclePartial;
use App\Models\SchoolCycle;
use App\Services\AcademicPerformanceService;
use App\Services\AttendanceService;
use App\Services\CurrentSchoolCycle;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Carbon\Carbon;

class ActaController extends Controller
{
    public function calificaciones(
        TeachingAssignment $teachingAssignment,
        AcademicPerformanceService $performance,
        AttendanceService $attendanceService
    ) {
        // Seguridad (coordinador / admin)
        abort_unless(
            auth()->user()->hasRole('coordinator') ||
            auth()->user()->teacher?->id === $teachingAssignment->teacher_id,
            403
        );

        $students = $teachingAssignment->group
            ->students()
            ->where('is_active', true)
            ->with('user')
            ->get();

        $students = $students->sortBy(function ($student) {
            return mb_strtolower($student->user->name ?? '');
        })->values();

        $activePeriod = $this->resolveActivePeriodForAssignment($teachingAssignment);
        $from = $activePeriod?->start_date
            ? Carbon::parse($activePeriod->start_date)->startOfDay()
            : null;
        $to = $activePeriod?->end_date
            ? Carbon::parse($activePeriod->end_date)->endOfDay()
            : null;

        $rows = $students->map(function ($student, $index) use (
            $teachingAssignment,
            $performance,
            $attendanceService,
            $from,
            $to
        ) {
            return [
                'num'        => $index + 1,
                'enrollment' => $student->enrollment_number,
                'name'       => $student->user->name,
                'grade'      => $performance->finalGradeForAssignment(
                    $student,
                    $teachingAssignment
                ),
                'attendance' => $attendanceService->attendancePercentage(
                    $teachingAssignment,
                    $student,
                    $from,
                    $to
                ),
            ];
        });

        return SnappyPdf::loadView(
            'actas.calificaciones',
            compact('teachingAssignment', 'rows', 'activePeriod')
        )
        ->setPaper('letter')
        ->setOption('encoding', 'UTF-8')
        ->setOption('disable-javascript', true)
        ->setOption('enable-local-file-access', true)
        ->download(
            'ACTA_CALIFICACIONES_'.$teachingAssignment->group->name.'.pdf'
        );
    }

    private function resolveActivePeriodForAssignment(TeachingAssignment $assignment): ?AcademicPeriod
    {
        $modalityId = $assignment->group->level->modality_id;

        $activeCycle = app(CurrentSchoolCycle::class)->get(auth()->user(), (int) session('active_campus_id', 0));
        if ($activeCycle && (int) $activeCycle->modality_id !== (int) $modalityId) {
            $activeCycle = null;
        }

        if ($activeCycle) {
            $partials = CyclePartial::query()
                ->where('school_cycle_id', $activeCycle->id)
                ->whereNotNull('academic_period_id')
                ->where('is_active', true)
                ->with('academicPeriod')
                ->orderBy('sort_order')
                ->get();

            $cyclePeriods = $partials
                ->pluck('academicPeriod')
                ->filter()
                ->values();

            $today = now()->toDateString();
            $current = $cyclePeriods->first(function ($period) use ($today) {
                return $period->start_date
                    && $period->end_date
                    && $period->start_date->toDateString() <= $today
                    && $period->end_date->toDateString() >= $today;
            });

            if ($current) {
                return $current;
            }

            $latest = $cyclePeriods
                ->sortByDesc(fn ($period) => optional($period->start_date)->toDateString())
                ->first();

            if ($latest) {
                return $latest;
            }
        }

        return AcademicPeriod::query()
            ->where('modality_id', $modalityId)
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->first();
    }
}
