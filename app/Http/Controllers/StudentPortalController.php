<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\Activity;
use App\Models\Grade;
use App\Models\StudentFollowUp;
use App\Models\TeacherStudentReport;
use App\Models\TeacherDocumentSubmission;
use App\Models\TeachingAssignment;
use App\Services\AcademicPerformanceService;
use Illuminate\Support\Collection;

class StudentPortalController extends Controller
{
    private const DOC_TYPE_LABELS = [
        'reglamento' => 'Reglamento',
        'planeacion' => 'Planeación',
        'programa_operativo' => 'Programa operativo',
        'examenes' => 'Exámenes',
        'guias' => 'Guías',
        'instrumentos_evaluacion' => 'Instrumentos de evaluación',
        'evidencias' => 'Evidencias',
        'otros' => 'Otros',
    ];

    private const REPORT_TYPE_LABELS = [
        'academic' => 'Academico',
        'behavioral' => 'Conductual',
        'mixed' => 'Mixto',
    ];

    public function subjects()
    {
        $student = auth()->user()->student;
        $activeCampusId = (int) session('active_campus_id', 0);

        $assignments = TeachingAssignment::query()
            ->with([
                'subject',
                'teacher.user',
                'schedules' => function ($query) use ($activeCampusId) {
                    $query->where('is_active', true)
                        ->when(
                            $activeCampusId > 0,
                            fn ($q) => $q->whereHas('schoolCycle', fn ($sq) => $this->applyCampusFilterToCycleQuery($sq, $activeCampusId))
                        );
                },
            ])
            ->where('group_id', $student->group_id)
            ->where('is_active', true)
            ->when(
                $activeCampusId > 0,
                fn ($query) => $query->whereHas('schedules.schoolCycle', fn ($q) => $this->applyCampusFilterToCycleQuery($q, $activeCampusId))
            )
            ->orderBy('subject_id')
            ->get();

        $scheduleBlocks = $this->buildScheduleBlocks($assignments);

        return view('student.portal.subjects', [
            'student' => $student,
            'assignments' => $assignments,
            'scheduleBlocks' => $scheduleBlocks,
        ]);
    }

    public function subjectShow(
        TeachingAssignment $assignment,
        AcademicPerformanceService $performance
    )
    {
        $student = auth()->user()->student;

        abort_unless(
            $assignment->group_id === $student->group_id && $assignment->is_active,
            403
        );

        $assignment->load(['subject', 'teacher.user', 'group.level.modality']);

        $period = AcademicPeriod::query()
            ->where('modality_id', $assignment->group->level->modality_id)
            ->where('is_active', true)
            ->first();

        if (! $period) {
            $period = AcademicPeriod::query()
                ->where('modality_id', $assignment->group->level->modality_id)
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->first();
        }

        $sessionsQuery = AcademicSession::query()
            ->where('teaching_assignment_id', $assignment->id)
            ->where('is_cancelled', false)
            ->when($period, fn ($q) => $q->where('academic_period_id', $period->id))
            ->orderBy('session_date')
            ->orderBy('start_time');

        $sessions = $sessionsQuery
            ->with(['attendances' => fn ($q) => $q->where('student_id', $student->id)])
            ->get();

        $totalSessions = $sessions->count();
        $attendedSessions = $sessions->filter(function ($session) {
            $status = optional($session->attendances->first())->status;
            return in_array($status, ['present', 'late', 'justified'], true);
        })->count();

        $attendancePercentage = $totalSessions > 0
            ? round(($attendedSessions / $totalSessions) * 100, 1)
            : 0;

        $activities = Activity::query()
            ->where('teaching_assignment_id', $assignment->id)
            ->where('is_active', true)
            ->when($period, fn ($q) => $q->where('academic_period_id', $period->id))
            ->orderBy('due_date')
            ->orderBy('title')
            ->get();

        $practices = $assignment->practices()
            ->with('submissions.team.students')
            ->orderBy('due_date')
            ->orderBy('number')
            ->get();

        $grades = Grade::query()
            ->where('student_id', $student->id)
            ->whereIn('activity_id', $activities->pluck('id'))
            ->get()
            ->keyBy('activity_id');

        $breakdown = $performance->breakdownForAssignment($student, $assignment);
        $activeCampusId = (int) session('active_campus_id', 0);

        $visibleDocs = TeacherDocumentSubmission::query()
            ->with(['item:id,document_type,teaching_assignment_id,is_student_visible'])
            ->whereHas('item', fn ($q) => $q
                ->where('is_student_visible', true)
                ->whereHas('assignment', fn ($aq) => $aq
                    ->where('group_id', (int) $assignment->group_id)
                    ->where('subject_id', (int) $assignment->subject_id)
                    ->when(
                        $activeCampusId > 0,
                        fn ($x) => $x->whereHas('schedules.schoolCycle', fn ($sq) => $this->applyCampusFilterToCycleQuery($sq, $activeCampusId))
                    )
                )
            )
            ->whereIn('status', ['submitted', 'reviewed'])
            ->orderByDesc('submitted_at')
            ->get()
            ->filter(fn ($row) => !empty($row->item))
            ->groupBy(fn ($row) => $row->item->document_type)
            ->map(fn ($rows) => $rows->first())
            ->values();

        return view('student.portal.subject_show', [
            'student' => $student,
            'assignment' => $assignment,
            'period' => $period,
            'sessions' => $sessions,
            'attendancePercentage' => $attendancePercentage,
            'activities' => $activities,
            'practices' => $practices,
            'grades' => $grades,
            'gradeBreakdownRows' => $breakdown['rows'] ?? [],
            'finalGrade' => $breakdown['final'] ?? null,
            'visibleDocs' => $visibleDocs,
            'docTypeLabels' => self::DOC_TYPE_LABELS,
        ]);
    }

    public function reports()
    {
        $student = auth()->user()->student;

        $reports = TeacherStudentReport::query()
            ->with(['teacher.user', 'group'])
            ->where('student_id', $student->id)
            ->orderByDesc('created_at')
            ->get();

        return view('student.portal.reports', [
            'student' => $student,
            'reports' => $reports,
            'typeLabels' => self::REPORT_TYPE_LABELS,
        ]);
    }

    public function followUps()
    {
        $student = auth()->user()->student;

        $followUps = StudentFollowUp::query()
            ->with(['requester', 'teachers.teacher.user'])
            ->where('student_id', $student->id)
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->get();

        return view('student.portal.followups', [
            'student' => $student,
            'followUps' => $followUps,
        ]);
    }

    private function buildScheduleBlocks(Collection $assignments): Collection
    {
        $dayMap = [
            'monday' => 'lunes',
            'tuesday' => 'martes',
            'wednesday' => 'miercoles',
            'thursday' => 'jueves',
            'friday' => 'viernes',
            'saturday' => 'sabado',
            'sunday' => 'domingo',
            'lunes' => 'lunes',
            'martes' => 'martes',
            'miercoles' => 'miercoles',
            'jueves' => 'jueves',
            'viernes' => 'viernes',
            'sabado' => 'sabado',
            'domingo' => 'domingo',
        ];

        $blocks = collect();

        foreach ($assignments as $assignment) {
            foreach ($assignment->schedules as $schedule) {
                if (! $schedule->is_active) {
                    continue;
                }

                $dayRaw = strtolower(trim((string) $schedule->day_of_week));
                $day = $dayMap[$dayRaw] ?? $dayRaw;
                $slot = substr((string) $schedule->start_time, 0, 5).'-'.substr((string) $schedule->end_time, 0, 5);

                $blocks->push([
                    'day' => $day,
                    'slot' => $slot,
                    'assignment' => $assignment,
                    'type' => $schedule->type,
                ]);
            }
        }

        return $blocks;
    }

    private function applyCampusFilterToCycleQuery($query, int $activeCampusId): void
    {
        $query->where(function ($nested) use ($activeCampusId) {
            $nested->where('campus_id', $activeCampusId)
                ->orWhereHas('campuses', fn ($campuses) => $campuses->where('campuses.id', $activeCampusId));
        });
    }
}
