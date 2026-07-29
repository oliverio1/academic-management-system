<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Activity;
use App\Models\CyclePartial;
use App\Models\DidacticPlan;
use App\Models\EconomicActa;
use App\Models\EvaluationCriterion;
use App\Models\Grade;
use App\Models\PaperExam;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Teacher;
use App\Models\TeacherCampusAttendance;
use App\Models\TeacherDocumentRequestItem;
use App\Models\Temario;
use App\Models\TeachingAssignment;
use App\Services\CurrentSchoolCycle;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CoordinationTeacherPerformanceController extends Controller
{
    public function index()
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        $cycle = $this->activeCycle($activeCampusId);

        $assignments = $cycle ? $this->cycleAssignments($cycle, $activeCampusId) : collect();
        $teacherRows = $assignments
            ->groupBy('teacher_id')
            ->map(fn (Collection $teacherAssignments) => $this->teacherSummaryRow($teacherAssignments->first()->teacher, $teacherAssignments, $cycle, $activeCampusId))
            ->sortBy(fn ($row) => mb_strtolower((string) ($row['teacher']?->user?->name ?? '')))
            ->values();

        $summary = [
            'teachers' => $teacherRows->count(),
            'assignments' => $assignments->count(),
            'documents_percentage' => $teacherRows->avg('documents.percentage') !== null ? round((float) $teacherRows->avg('documents.percentage'), 1) : 0,
            'attendance_capture_percentage' => $teacherRows->avg('attendance.percentage') !== null ? round((float) $teacherRows->avg('attendance.percentage'), 1) : 0,
            'evaluation_percentage' => $teacherRows->avg('evaluation.percentage') !== null ? round((float) $teacherRows->avg('evaluation.percentage'), 1) : 0,
        ];

        return view('coordination.teacher_performance.index', [
            'activeCycle' => $cycle,
            'teacherRows' => $teacherRows,
            'summary' => $summary,
        ]);
    }

    public function show(Teacher $teacher)
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        $cycle = $this->activeCycle($activeCampusId);
        abort_unless($cycle, 404);

        $assignments = $this->cycleAssignments($cycle, $activeCampusId)
            ->where('teacher_id', (int) $teacher->id)
            ->values();

        abort_if($assignments->isEmpty(), 404);

        $row = $this->teacherSummaryRow($teacher->loadMissing('user'), $assignments, $cycle, $activeCampusId);
        $partials = $cycle->partials()->where('is_active', true)->orderBy('sort_order')->get();
        $assignmentRows = $assignments->map(fn (TeachingAssignment $assignment) => $this->assignmentDetailRow($assignment, $cycle, $partials));
        $documentRows = $this->documentItemsForAssignments($assignments, $cycle, $activeCampusId)
            ->map(fn (TeacherDocumentRequestItem $item) => [
                'assignment' => $item->assignment,
                'label' => \App\Http\Controllers\CoordinationTeacherDocumentRequestController::DOCUMENT_TYPES[$item->document_type] ?? $item->document_type,
                'due_date' => $item->request?->due_date,
                'status' => $this->documentStatus($item, $cycle),
                'delivered_at' => $item->latestSubmission?->submitted_at
                    ?: $item->editableContent?->submitted_at,
                'system_artifact' => $this->hasSystemArtifact($item, $cycle),
            ])
            ->sortBy(fn ($item) => implode('|', [
                match ($item['status']) {
                    'overdue' => 0,
                    'pending' => 1,
                    default => 2,
                },
                $item['due_date']?->toDateString() ?? '9999-12-31',
                $item['assignment']?->subject?->name ?? '',
                $item['label'],
            ]))
            ->values();

        $campusAttendanceRows = TeacherCampusAttendance::query()
            ->where('teacher_id', (int) $teacher->id)
            ->when($activeCampusId > 0, fn ($query) => $query->where('campus_id', $activeCampusId))
            ->whereBetween('attendance_date', $this->cycleWindow($cycle))
            ->orderByDesc('attendance_date')
            ->limit(20)
            ->get();

        return view('coordination.teacher_performance.show', [
            'activeCycle' => $cycle,
            'teacher' => $teacher,
            'summaryRow' => $row,
            'assignmentRows' => $assignmentRows,
            'documentRows' => $documentRows,
            'campusAttendanceRows' => $campusAttendanceRows,
        ]);
    }

    private function activeCycle(int $activeCampusId): ?SchoolCycle
    {
        return app(CurrentSchoolCycle::class)->get(auth()->user(), $activeCampusId);
    }

    private function cycleAssignments(SchoolCycle $cycle, int $activeCampusId): Collection
    {
        $cycleGroupIds = SchoolCycleGroup::query()
            ->where('school_cycle_id', (int) $cycle->id)
            ->where('is_active', true)
            ->when($activeCampusId > 0, fn ($query) => $query->where('campus_id', $activeCampusId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return TeachingAssignment::query()
            ->with(['teacher.user', 'subject', 'group.students', 'students'])
            ->where('is_active', true)
            ->whereIn('school_cycle_group_id', $cycleGroupIds)
            ->orderBy('teacher_id')
            ->orderBy('group_id')
            ->orderBy('subject_id')
            ->get();
    }

    private function teacherSummaryRow(?Teacher $teacher, Collection $assignments, SchoolCycle $cycle, int $activeCampusId): array
    {
        $documents = $this->documentSummary($assignments, $cycle, $activeCampusId);
        $attendance = $this->attendanceSummary($assignments, $cycle);
        $evaluation = $this->evaluationSummary($assignments, $cycle);
        $campusAttendance = $teacher ? $this->campusAttendanceSummary($teacher, $cycle, $activeCampusId) : [
            'total' => 0,
            'on_time' => 0,
            'late' => 0,
            'absent' => 0,
            'pending' => 0,
        ];

        return [
            'teacher' => $teacher,
            'assignments' => $assignments->count(),
            'groups' => $assignments->pluck('group_id')->unique()->count(),
            'subjects' => $assignments->pluck('subject_id')->unique()->count(),
            'documents' => $documents,
            'attendance' => $attendance,
            'campus_attendance' => $campusAttendance,
            'evaluation' => $evaluation,
            'overall' => round(collect([
                $documents['percentage'],
                $attendance['percentage'],
                $evaluation['percentage'],
            ])->avg(), 1),
        ];
    }

    private function assignmentDetailRow(TeachingAssignment $assignment, SchoolCycle $cycle, Collection $partials): array
    {
        $assignments = collect([$assignment]);

        return [
            'assignment' => $assignment,
            'documents' => $this->documentSummary($assignments, $cycle, (int) ($assignment->schoolCycleGroup?->campus_id ?? session('active_campus_id', 0))),
            'attendance' => $this->attendanceSummary($assignments, $cycle),
            'evaluation' => $this->evaluationSummary($assignments, $cycle),
            'actas' => $partials->map(function (CyclePartial $partial) use ($assignment) {
                $acta = EconomicActa::query()
                    ->where('teaching_assignment_id', (int) $assignment->id)
                    ->where('cycle_partial_id', (int) $partial->id)
                    ->first();

                return [
                    'partial' => $partial,
                    'status' => $acta?->status ?? 'pending',
                    'submitted_at' => $acta?->submitted_at,
                    'closed_at' => $acta?->closed_at,
                    'sent_at' => $acta?->sent_at,
                ];
            }),
        ];
    }

    private function documentSummary(Collection $assignments, SchoolCycle $cycle, int $activeCampusId): array
    {
        $items = $this->documentItemsForAssignments($assignments, $cycle, $activeCampusId);
        $statuses = $items->map(fn (TeacherDocumentRequestItem $item) => $this->documentStatus($item, $cycle));
        $total = $statuses->count();
        $delivered = $statuses->filter(fn ($status) => $status === 'delivered')->count();

        return [
            'total' => $total,
            'delivered' => $delivered,
            'pending' => $statuses->filter(fn ($status) => $status === 'pending')->count(),
            'overdue' => $statuses->filter(fn ($status) => $status === 'overdue')->count(),
            'percentage' => $total > 0 ? round(($delivered / $total) * 100, 1) : 0,
        ];
    }

    private function documentItemsForAssignments(Collection $assignments, SchoolCycle $cycle, int $activeCampusId): Collection
    {
        $assignmentIds = $assignments->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (empty($assignmentIds)) {
            return collect();
        }

        return TeacherDocumentRequestItem::query()
            ->with([
                'request:id,due_date,status,campus_id,school_cycle_id',
                'assignment:id,teacher_id,subject_id,group_id,school_cycle_group_id',
                'assignment.subject:id,name',
                'assignment.group:id,name',
                'latestSubmission',
                'editableContent',
            ])
            ->whereIn('teaching_assignment_id', $assignmentIds)
            ->whereHas('request', fn ($query) => $query
                ->where('status', 'open')
                ->where('school_cycle_id', (int) $cycle->id)
                ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId)))
            ->get()
            ->groupBy(fn ($item) => implode('|', [
                (int) ($item->request_id ?? 0),
                (int) ($item->assignment?->teacher_id ?? 0),
                (int) ($item->assignment?->school_cycle_group_id ?? 0),
                (int) ($item->assignment?->subject_id ?? 0),
                (string) ($item->document_type ?? ''),
            ]))
            ->map->first()
            ->values();
    }

    private function documentStatus(TeacherDocumentRequestItem $item, SchoolCycle $cycle): string
    {
        $delivered = (bool) $item->latestSubmission
            || (bool) $item->editableContent?->submitted_at
            || $this->hasSystemArtifact($item, $cycle);

        if ($delivered) {
            return 'delivered';
        }

        $dueDate = $item->request?->due_date?->toDateString();

        return $dueDate && $dueDate < now()->toDateString() ? 'overdue' : 'pending';
    }

    private function hasSystemArtifact(TeacherDocumentRequestItem $item, SchoolCycle $cycle): bool
    {
        $assignment = $item->assignment;
        if (! $assignment) {
            return false;
        }

        if ($item->document_type === 'temario') {
            return Temario::query()
                ->where('subject_id', (int) $assignment->subject_id)
                ->whereHas('points')
                ->exists();
        }

        if ($item->document_type === 'planeacion') {
            return DidacticPlan::query()
                ->where('school_cycle_id', (int) $cycle->id)
                ->whereHas('assignment', fn ($query) => $query
                    ->where('teacher_id', (int) $assignment->teacher_id)
                    ->where('school_cycle_group_id', (int) $assignment->school_cycle_group_id)
                    ->where('subject_id', (int) $assignment->subject_id))
                ->exists();
        }

        if (preg_match('/^examen_parcial_([1-4])$/', (string) $item->document_type, $matches)) {
            $partialId = (int) $cycle->partials()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->skip(((int) $matches[1]) - 1)
                ->value('id');

            return $partialId > 0 && PaperExam::query()
                ->where('school_cycle_id', (int) $cycle->id)
                ->where('cycle_partial_id', $partialId)
                ->whereHas('examQuestions')
                ->whereHas('assignment', fn ($query) => $query
                    ->where('teacher_id', (int) $assignment->teacher_id)
                    ->where('school_cycle_group_id', (int) $assignment->school_cycle_group_id)
                    ->where('subject_id', (int) $assignment->subject_id))
                ->exists();
        }

        return false;
    }

    private function attendanceSummary(Collection $assignments, SchoolCycle $cycle): array
    {
        $assignmentIds = $assignments->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (empty($assignmentIds)) {
            return ['total' => 0, 'closed' => 0, 'registered' => 0, 'percentage' => 0];
        }

        $query = AcademicSession::query()
            ->whereIn('teaching_assignment_id', $assignmentIds)
            ->where('is_cancelled', false)
            ->whereBetween('session_date', $this->cycleWindow($cycle));

        $total = (clone $query)->count();
        $closed = (clone $query)->whereNotNull('attendance_closed_at')->count();
        $registered = (clone $query)->whereHas('attendances')->count();

        return [
            'total' => $total,
            'closed' => $closed,
            'registered' => $registered,
            'percentage' => $total > 0 ? round(($closed / $total) * 100, 1) : 0,
        ];
    }

    private function evaluationSummary(Collection $assignments, SchoolCycle $cycle): array
    {
        $assignmentIds = $assignments->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (empty($assignmentIds)) {
            return ['criteria' => 0, 'activities' => 0, 'graded' => 0, 'expected' => 0, 'percentage' => 0];
        }

        $criteria = EvaluationCriterion::query()
            ->whereIn('teaching_assignment_id', $assignmentIds)
            ->count();
        $activities = Activity::query()
            ->whereIn('teaching_assignment_id', $assignmentIds)
            ->where('is_active', true)
            ->whereBetween('due_date', $this->cycleWindow($cycle))
            ->get();

        $expected = $activities->sum(function (Activity $activity) use ($assignments) {
            $assignment = $assignments->firstWhere('id', (int) $activity->teaching_assignment_id);
            if (! $assignment) {
                return 0;
            }

            $students = $assignment->students->isNotEmpty()
                ? $assignment->students
                : ($assignment->group?->students ?? collect());

            return $students->where('is_active', true)->count();
        });

        $graded = Grade::query()
            ->whereIn('activity_id', $activities->pluck('id')->all())
            ->whereNotNull('score')
            ->count();

        return [
            'criteria' => $criteria,
            'activities' => $activities->count(),
            'graded' => $graded,
            'expected' => $expected,
            'percentage' => $expected > 0 ? round(($graded / $expected) * 100, 1) : 0,
        ];
    }

    private function campusAttendanceSummary(Teacher $teacher, SchoolCycle $cycle, int $activeCampusId): array
    {
        $rows = TeacherCampusAttendance::query()
            ->where('teacher_id', (int) $teacher->id)
            ->when($activeCampusId > 0, fn ($query) => $query->where('campus_id', $activeCampusId))
            ->whereBetween('attendance_date', $this->cycleWindow($cycle))
            ->get();

        return [
            'total' => $rows->count(),
            'on_time' => $rows->where('status', 'on_time')->count(),
            'late' => $rows->where('status', 'late')->count(),
            'absent' => $rows->where('status', 'absent')->count(),
            'pending' => $rows->where('status', 'pending')->count(),
        ];
    }

    private function cycleWindow(SchoolCycle $cycle): array
    {
        $from = Carbon::parse($cycle->start_date)->startOfDay();
        $to = Carbon::parse($cycle->end_date)->endOfDay();
        $demoTo = $from->copy()->addWeeks(4)->endOfWeek();

        if (app()->environment('local') && $demoTo->lt($to)) {
            $to = $demoTo;
        }

        return [$from->toDateString(), $to->toDateString()];
    }
}
