<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Models\AcademicSession;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\CoordinationReport;
use App\Models\CyclePartial;
use App\Models\DidacticPlan;
use App\Models\EvaluationCriterion;
use App\Models\Group;
use App\Models\PaperExam;
use App\Models\PrefectDailyAttendance;
use App\Models\PrefectIncidentReport;
use App\Models\SchoolCycle;
use App\Services\CurrentSchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\SchoolCase;
use App\Models\Student;
use App\Models\StudentFollowUp;
use App\Models\StudentIncidentReport;
use App\Models\TeacherStudentReport;
use App\Models\TeacherDocumentRequest;
use App\Models\TeacherDocumentRequestItem;
use App\Models\Temario;
use App\Models\TeachingAssignment;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->hasRole('coordinator')) {
            return $this->adminDashboard();
        }
        if ($user->hasRole('admin')) {
            return view('dashboard.admin');
        }
        if ($user->hasRole('teacher')) {
            return $this->teacherDashboard();
        }
        if ($user->hasRole('student')) {
            return $this->studentDashboard();
        }
        if ($user->hasRole('prefect') || $user->hasRole('prefector')) {
            return $this->prefectDashboard();
        }
        if ($user->hasRole('guardian') || $user->hasRole('tutor')) {
            return $this->tutorDashboard();
        }

        return redirect()->route('home');
    }

    protected function adminDashboard()
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        $tenantId = (string) tenant('id');
        $cacheKey = 'dashboard:coordinator:'
            . Carbon::today()->toDateString()
            . ':tenant:' . ($tenantId !== '' ? $tenantId : 'central')
            . ':campus:' . $activeCampusId;
        $ttl = now()->addMinutes(3);

        $data = Cache::remember($cacheKey, $ttl, function () use ($activeCampusId) {
            $dashboard = new DashboardService(null, $activeCampusId > 0 ? $activeCampusId : null);

            return [
                'alerts' => $dashboard->alerts(),
                'metrics' => $dashboard->metrics(),
            ];
        });

        return view('dashboards.admin', [
            'alerts' => $data['alerts'],
            'metrics' => $data['metrics'],
            'activeCycle' => $this->activeCycle(),
            'generatedAt' => now(),
            'pilot' => $this->pilotDashboardData($this->activeCycle(), $activeCampusId),
        ]);
    }

    private function pilotDashboardData(?SchoolCycle $cycle, int $activeCampusId): array
    {
        if (! $cycle) {
            return [
                'summary' => [],
                'documents' => ['total' => 0, 'delivered' => 0, 'pending' => 0, 'overdue' => 0, 'percentage' => 0],
                'partials' => collect(),
                'teacherRows' => collect(),
                'caseStatus' => collect(),
            ];
        }

        $cycleGroupIds = SchoolCycleGroup::query()
            ->where('school_cycle_id', $cycle->id)
            ->where('is_active', true)
            ->when($activeCampusId > 0, fn ($query) => $query->where('campus_id', $activeCampusId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $groupIds = SchoolCycleGroup::query()
            ->whereIn('id', $cycleGroupIds)
            ->pluck('group_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $assignments = TeachingAssignment::query()
            ->whereIn('school_cycle_group_id', $cycleGroupIds)
            ->where('is_active', true)
            ->with(['teacher.user:id,name', 'subject:id,name', 'group:id,name'])
            ->get();

        $assignmentIds = $assignments->pluck('id')->map(fn ($id) => (int) $id)->all();
        $studentIds = Student::query()
            ->whereIn('group_id', $groupIds)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $sessionIds = AcademicSession::query()
            ->whereIn('teaching_assignment_id', $assignmentIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $documentItems = $this->pilotDocumentItems($cycle, $activeCampusId);
        $documents = $this->pilotDocumentSummary($documentItems, $cycle);

        $start = $cycle->start_date?->toDateString();
        $end = $cycle->start_date?->copy()->addWeeks(4)->endOfWeek()->toDateString();

        return [
            'summary' => [
                'groups' => count($groupIds),
                'students' => count($studentIds),
                'teachers' => $assignments->pluck('teacher_id')->filter()->unique()->count(),
                'subjects' => $assignments->pluck('subject_id')->filter()->unique()->count(),
                'assignments' => $assignments->count(),
                'sessions' => count($sessionIds),
                'closed_sessions' => AcademicSession::query()->whereIn('id', $sessionIds)->whereNotNull('attendance_closed_at')->count(),
                'prefect_attendance' => PrefectDailyAttendance::query()
                    ->whereIn('group_id', $groupIds)
                    ->when($start && $end, fn ($query) => $query->whereBetween('attendance_date', [$start, $end]))
                    ->count(),
                'class_attendance' => Attendance::query()->whereIn('academic_session_id', $sessionIds)->count(),
                'criteria' => EvaluationCriterion::query()->whereIn('teaching_assignment_id', $assignmentIds)->count(),
                'activities' => \App\Models\Activity::query()->whereIn('teaching_assignment_id', $assignmentIds)->count(),
                'grades' => \App\Models\Grade::query()
                    ->whereHas('activity', fn ($query) => $query->whereIn('teaching_assignment_id', $assignmentIds))
                    ->count(),
                'reports' => $this->pilotReportCount($groupIds, $studentIds),
                'followups' => StudentFollowUp::query()->whereIn('student_id', $studentIds)->count(),
                'cases' => SchoolCase::query()
                    ->when($activeCampusId > 0, fn ($query) => $query->where('campus_id', $activeCampusId))
                    ->count(),
            ],
            'documents' => $documents,
            'partials' => $cycle->partials()->where('is_active', true)->get()->map(fn (CyclePartial $partial) => [
                'name' => $partial->name,
                'range' => $partial->start_date?->format('d/m/Y') . ' - ' . $partial->end_date?->format('d/m/Y'),
                'deadline' => $partial->teacher_capture_deadline_at?->format('d/m/Y H:i') ?: 'Sin fecha limite',
            ]),
            'teacherRows' => $this->pilotTeacherRows($documentItems, $cycle)->take(6),
            'caseStatus' => SchoolCase::query()
                ->when($activeCampusId > 0, fn ($query) => $query->where('campus_id', $activeCampusId))
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ];
    }

    private function pilotDocumentItems(SchoolCycle $cycle, int $activeCampusId): Collection
    {
        return TeacherDocumentRequestItem::query()
            ->with([
                'request:id,due_date,status,campus_id,school_cycle_id',
                'assignment:id,teacher_id,subject_id,group_id,school_cycle_group_id',
                'assignment.schoolCycleGroup:id,school_cycle_id,campus_id',
                'assignment.teacher:id,user_id',
                'assignment.teacher.user:id,name',
                'assignment.subject:id,name',
                'assignment.group:id,name',
                'latestSubmission',
                'editableContent',
            ])
            ->whereHas('request', fn ($query) => $query
                ->where('status', 'open')
                ->where('school_cycle_id', $cycle->id)
                ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId)))
            ->whereHas('assignment', fn ($query) => $query
                ->where('is_active', true)
                ->whereHas('schoolCycleGroup', fn ($cycleGroup) => $cycleGroup
                    ->where('school_cycle_id', $cycle->id)
                    ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))))
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

    private function pilotDocumentSummary(Collection $items, SchoolCycle $cycle): array
    {
        $today = now()->toDateString();
        $tracked = $items->map(function (TeacherDocumentRequestItem $item) use ($cycle, $today) {
            $delivered = (bool) $item->latestSubmission
                || (bool) $item->editableContent?->submitted_at
                || $this->pilotHasSystemArtifact($item, $cycle);
            $dueDate = $item->request?->due_date?->toDateString();
            $overdue = ! $delivered && $dueDate && $dueDate < $today;

            return [
                'item' => $item,
                'status' => $delivered ? 'delivered' : ($overdue ? 'overdue' : 'pending'),
            ];
        });

        $total = $tracked->count();
        $delivered = $tracked->where('status', 'delivered')->count();
        $overdue = $tracked->where('status', 'overdue')->count();

        return [
            'total' => $total,
            'delivered' => $delivered,
            'pending' => $tracked->where('status', 'pending')->count(),
            'overdue' => $overdue,
            'percentage' => $total > 0 ? round(($delivered / $total) * 100, 1) : 0,
        ];
    }

    private function pilotHasSystemArtifact(TeacherDocumentRequestItem $item, SchoolCycle $cycle): bool
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
                ->where('school_cycle_id', $cycle->id)
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
                ->where('school_cycle_id', $cycle->id)
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

    private function pilotTeacherRows(Collection $items, SchoolCycle $cycle): Collection
    {
        return $items
            ->groupBy(fn ($item) => (int) ($item->assignment?->teacher_id ?? 0))
            ->filter(fn ($rows, $teacherId) => (int) $teacherId > 0)
            ->map(function ($rows) use ($cycle) {
                $summary = $this->pilotDocumentSummary($rows, $cycle);

                return [
                    'teacher' => $rows->first()->assignment?->teacher,
                    'assignments' => $rows->pluck('teaching_assignment_id')->unique()->count(),
                    'percentage' => $summary['percentage'],
                    'overdue' => $summary['overdue'],
                    'pending' => $summary['pending'],
                    'delivered' => $summary['delivered'],
                    'total' => $summary['total'],
                ];
            })
            ->sortBy([
                fn ($row) => -1 * (int) $row['overdue'],
                fn ($row) => (float) $row['percentage'],
            ])
            ->values();
    }

    private function pilotReportCount(array $groupIds, array $studentIds): int
    {
        return TeacherStudentReport::query()->whereIn('group_id', $groupIds)->count()
            + StudentIncidentReport::query()->whereIn('student_id', $studentIds)->count()
            + PrefectIncidentReport::query()->count()
            + CoordinationReport::query()->count();
    }

    protected function studentDashboard()
    {
        return redirect()->route('student.subjects');
    }

    protected function teacherDashboard()
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher, 403);

        $activeCycle = $this->activeCycle();
        $activePeriodIds = $this->activeCyclePeriodIds($activeCycle);
        $activeCampusId = (int) session('active_campus_id', 0);

        $pendingDocumentItems = $this->pendingTeacherDocumentItemsCount($teacher, $activeCampusId, (int) ($activeCycle?->id ?? 0));

        return view('dashboards.teacher', [
            'todayClasses' => $this->todayClasses($teacher, $activePeriodIds),
            'pendingAttendances' => $this->pendingAttendances($teacher, $activePeriodIds),
            'announcements' => $this->announcements(),
            'notifications' => $this->institutionalNotifications($teacher->user),
            'pendingDocumentItems' => $pendingDocumentItems,
        ]);
    }

    private function pendingTeacherDocumentItemsCount($teacher, int $activeCampusId, int $activeCycleId): int
    {
        $items = TeacherDocumentRequestItem::query()
            ->with([
                'request:id,title,due_date,status,campus_id,school_cycle_id',
                'assignment:id,teacher_id,subject_id,group_id,school_cycle_group_id',
                'assignment.schoolCycleGroup:id,school_cycle_id',
                'latestSubmission',
                'editableContent',
            ])
            ->whereHas('assignment', fn ($q) => $q
                ->where('teacher_id', (int) $teacher->id)
                ->whereHas('schoolCycleGroup', fn ($qq) => $qq->where('school_cycle_id', $activeCycleId)))
            ->whereHas('request', fn ($q) => $q
                ->where('status', 'open')
                ->where('school_cycle_id', $activeCycleId)
                ->when($activeCampusId > 0, fn ($qq) => $qq->where('campus_id', $activeCampusId)))
            ->orderByDesc(
                TeacherDocumentRequest::select('due_date')
                    ->whereColumn('teacher_document_requests.id', 'teacher_document_request_items.request_id')
                    ->limit(1)
            )
            ->get()
            ->map(fn ($item) => $this->withTeacherDocumentTrackingStatus($item));

        return $this->deduplicateTeacherDocumentItems($items)
            ->where('tracking_status', '!=', 'delivered')
            ->count();
    }

    private function withTeacherDocumentTrackingStatus(TeacherDocumentRequestItem $item): TeacherDocumentRequestItem
    {
        $dueDate = optional($item->request)->due_date?->toDateString();
        $latest = $item->latestSubmission;
        $editableContent = $item->editableContent;
        $systemArtifact = $this->teacherDocumentSystemArtifactLabel($item);

        if ($latest || $editableContent?->submitted_at || $systemArtifact) {
            $status = 'delivered';
        } elseif ($dueDate && $dueDate < now()->toDateString()) {
            $status = 'overdue';
        } else {
            $status = 'pending';
        }

        $item->tracking_status = $status;

        return $item;
    }

    private function deduplicateTeacherDocumentItems($items)
    {
        return $items
            ->groupBy(fn ($item) => implode('|', [
                (int) ($item->request_id ?? 0),
                (int) ($item->assignment?->teacher_id ?? 0),
                (int) ($item->assignment?->school_cycle_group_id ?? 0),
                (int) ($item->assignment?->subject_id ?? 0),
                (string) ($item->document_type ?? ''),
            ]))
            ->map(function ($duplicates) {
                return $duplicates
                    ->sortBy(fn ($item) => match ($item->tracking_status) {
                        'delivered' => 0,
                        'pending' => 1,
                        default => 2,
                    })
                    ->first();
            })
            ->values();
    }

    private function teacherDocumentSystemArtifactLabel(TeacherDocumentRequestItem $item): ?string
    {
        $assignment = $item->assignment;
        if (! $assignment) {
            return null;
        }

        if ($item->document_type === 'temario') {
            return Temario::query()
                ->where('subject_id', (int) $assignment->subject_id)
                ->whereHas('points')
                ->exists()
                    ? 'Registrado en temarios'
                    : null;
        }

        if ($item->document_type === 'planeacion') {
            $cycleId = (int) ($item->request?->school_cycle_id ?? $assignment->schoolCycleGroup?->school_cycle_id ?? 0);
            $exists = DidacticPlan::query()
                ->where('school_cycle_id', $cycleId)
                ->whereHas('assignment', function ($query) use ($assignment) {
                    $query->where('teacher_id', (int) $assignment->teacher_id)
                        ->where('school_cycle_group_id', (int) $assignment->school_cycle_group_id)
                        ->where('subject_id', (int) $assignment->subject_id);
                })
                ->exists();

            return $exists ? 'Registrada en planeaciones' : null;
        }

        if ($this->isPartialExamDocumentType((string) $item->document_type)) {
            $cycleId = (int) ($item->request?->school_cycle_id ?? $assignment->schoolCycleGroup?->school_cycle_id ?? 0);
            $partialId = $this->partialIdForExamDocument($cycleId, (string) $item->document_type);
            if ($cycleId <= 0 || $partialId <= 0) {
                return null;
            }

            $exam = PaperExam::query()
                ->withCount('examQuestions')
                ->where('school_cycle_id', $cycleId)
                ->where('cycle_partial_id', $partialId)
                ->whereHas('examQuestions')
                ->whereHas('assignment', function ($query) use ($assignment) {
                    $query->where('teacher_id', (int) $assignment->teacher_id)
                        ->where('school_cycle_group_id', (int) $assignment->school_cycle_group_id)
                        ->where('subject_id', (int) $assignment->subject_id)
                        ->where('group_id', (int) $assignment->group_id);
                })
                ->orderByDesc('id')
                ->first();

            return $exam ? 'Examen configurado (' . $exam->exam_questions_count . ' pregunta(s))' : null;
        }

        return null;
    }

    private function isPartialExamDocumentType(string $documentType): bool
    {
        return (bool) preg_match('/^examen_parcial_[1-4]$/', $documentType);
    }

    private function partialIdForExamDocument(int $cycleId, string $documentType): int
    {
        if ($cycleId <= 0 || ! preg_match('/^examen_parcial_([1-4])$/', $documentType, $matches)) {
            return 0;
        }

        return (int) CyclePartial::query()
            ->where('school_cycle_id', $cycleId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->skip(((int) $matches[1]) - 1)
            ->value('id');
    }

    protected function todayClasses($teacher, array $activePeriodIds)
    {
        $today = app()->environment('local')
            ? now()->subDays(2)
            : now();

        if (empty($activePeriodIds)) {
            return collect();
        }

        return AcademicSession::query()
            ->whereDate('session_date', $today)
            ->whereHas('teachingAssignment', function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id);
            })
            ->whereIn('academic_period_id', $activePeriodIds)
            ->where('is_cancelled', false)
            ->with([
                'teachingAssignment.subject:id,name',
                'teachingAssignment.group:id,name',
            ])
            ->withCount('attendances', 'sessionActivity')
            ->orderBy('start_time')
            ->get()
            ->map(function ($session) {
                return (object) [
                    'session_id' => $session->id,
                    'subject' => $session->teachingAssignment->subject->name,
                    'group' => $session->teachingAssignment->group->name,
                    'time' => $session->start_time . ' - ' . $session->end_time,
                    'attendance_closed' => ! is_null($session->attendance_closed_at),
                    'attendance_registered' => $session->attendances_count > 0,
                    'activity_assigned' => $session->session_activity_count > 0,
                ];
            });
    }

    protected function pendingAttendances($teacher, array $activePeriodIds)
    {
        if (empty($activePeriodIds)) {
            return collect();
        }

        return AcademicSession::query()
            ->whereHas('teachingAssignment', function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id);
            })
            ->whereIn('academic_period_id', $activePeriodIds)
            ->where('is_cancelled', false)
            ->whereBetween('session_date', [
                now()->startOfWeek(),
                now()->endOfWeek(),
            ])
            ->with([
                'teachingAssignment.subject:id,name',
                'teachingAssignment.group:id,name',
            ])
            ->withCount('attendances')
            ->orderBy('session_date')
            ->limit(3)
            ->get()
            ->map(function ($session) {
                return (object) [
                    'session_id' => $session->id,
                    'subject' => $session->teachingAssignment->subject->name,
                    'group' => $session->teachingAssignment->group->name,
                    'date' => $session->session_date->format('d/m/Y'),
                    'attendance_registered' => $session->attendances_count > 0,
                    'attendance_closed' => ! is_null($session->attendance_closed_at),
                ];
            });
    }

    protected function announcements()
    {
        return Announcement::query()
            ->where('is_active', true)
            ->where('scope', 'internal')
            ->whereHas('recipients', function ($q) {
                $q->where('user_id', auth()->id());
            })
            ->orderByDesc('published_at')
            ->limit(3)
            ->get()
            ->map(function ($announcement) {
                return (object) [
                    'title' => $announcement->title,
                    'excerpt' => str($announcement->body)->limit(120),
                    'date' => optional($announcement->published_at)->format('d/m/Y'),
                ];
            });
    }

    protected function institutionalNotifications($user)
    {
        $systemNotes = $user->unreadNotifications()
            ->latest()
            ->take(6)
            ->get()
            ->map(function ($notification) {
                return (object) [
                    'title' => $notification->data['title'] ?? 'Notificacion del sistema',
                    'message' => $notification->data['message'] ?? 'Tienes una notificacion pendiente.',
                    'date' => optional($notification->created_at)->format('d/m/Y'),
                    'at' => optional($notification->created_at),
                ];
            });

        $announcements = Announcement::query()
            ->where('is_active', true)
            ->where('scope', 'internal')
            ->whereHas('recipients', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->latest('published_at')
            ->take(4)
            ->get()
            ->map(function ($announcement) {
                return (object) [
                    'title' => $announcement->title,
                    'message' => str($announcement->body)->limit(130),
                    'date' => optional($announcement->published_at)->format('d/m/Y'),
                    'at' => optional($announcement->published_at),
                ];
            });

        return $systemNotes
            ->concat($announcements)
            ->sortByDesc(fn ($item) => $item->at?->timestamp ?? 0)
            ->take(8)
            ->values();
    }

    protected function prefectDashboard()
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        $user = auth()->user();
        $allowedCampusIds = $user
            ? $user->campuses()->pluck('campuses.id')->map(fn ($id) => (int) $id)->all()
            : [];

        if ($activeCampusId <= 0 || ! in_array($activeCampusId, $allowedCampusIds, true)) {
            $activeCampusId = (int) ($allowedCampusIds[0] ?? 0);
            if ($activeCampusId > 0) {
                session(['active_campus_id' => $activeCampusId]);
            }
        }

        if ($activeCampusId <= 0) {
            return view('dashboards.prefect', [
                'groups' => collect(),
                'today' => now(),
            ]);
        }

        $today = now()->toDateString();

        $activeCycleIds = SchoolCycle::query()
            ->where('is_active', true)
            ->where(function ($q) use ($activeCampusId) {
                $q->where('campus_id', $activeCampusId)
                    ->orWhereHas('campuses', fn ($campuses) => $campuses->where('campuses.id', $activeCampusId));
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $activeGroupIds = SchoolCycleGroup::query()
            ->whereIn('school_cycle_id', $activeCycleIds)
            ->where('campus_id', $activeCampusId)
            ->where('is_active', true)
            ->pluck('group_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $groups = Group::query()
            ->with(['level.modality'])
            ->whereIn('id', $activeGroupIds)
            ->orderBy('name')
            ->get();

        $registeredGroupIds = PrefectDailyAttendance::query()
            ->whereIn('group_id', $groups->pluck('id')->all())
            ->whereDate('attendance_date', $today)
            ->select('group_id')
            ->distinct()
            ->pluck('group_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $groups = $groups->map(function ($group) use ($registeredGroupIds) {
            $group->attendance_registered_today = $registeredGroupIds->has((int) $group->id);
            return $group;
        });

        return view('dashboards.prefect', [
            'groups' => $groups,
            'today' => now(),
        ]);
    }

    protected function tutorDashboard()
    {
        return view('dashboards.tutor');
    }

    private function activeCycle(): ?SchoolCycle
    {
        $activeCampusId = (int) session('active_campus_id', 0);

        return app(CurrentSchoolCycle::class)->get(auth()->user(), $activeCampusId);
    }

    private function activeCyclePeriodIds(?SchoolCycle $cycle): array
    {
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
}
