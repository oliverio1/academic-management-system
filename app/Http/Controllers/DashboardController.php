<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Models\AcademicSession;
use App\Models\Announcement;
use App\Models\Group;
use App\Models\PrefectDailyAttendance;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\TeacherDocumentRequestItem;
use App\Services\DashboardService;
use Carbon\Carbon;

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
        ]);
    }

    protected function studentDashboard()
    {
        return redirect()->route('student.subjects');
    }

    protected function teacherDashboard()
    {
        $teacher = auth()->user()->teacher;
        $activeCycle = $this->activeCycle();
        $activePeriodIds = $this->activeCyclePeriodIds($activeCycle);
        $activeCampusId = (int) session('active_campus_id', 0);

        $pendingDocumentItems = TeacherDocumentRequestItem::query()
            ->whereHas('assignment', fn ($q) => $q->where('teacher_id', (int) $teacher->id))
            ->whereHas('request', fn ($q) => $q->where('status', 'open')->when($activeCampusId > 0, fn ($qq) => $qq->where('campus_id', $activeCampusId)))
            ->whereDoesntHave('submissions')
            ->count();

        return view('dashboards.teacher', [
            'todayClasses' => $this->todayClasses($teacher, $activePeriodIds),
            'pendingAttendances' => $this->pendingAttendances($teacher, $activePeriodIds),
            'announcements' => $this->announcements(),
            'notifications' => $this->institutionalNotifications($teacher->user),
            'pendingDocumentItems' => $pendingDocumentItems,
        ]);
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

        return SchoolCycle::query()
            ->where('is_active', true)
            ->when($activeCampusId > 0, function ($q) use ($activeCampusId) {
                $q->where(function ($nested) use ($activeCampusId) {
                    $nested->where('campus_id', $activeCampusId)
                        ->orWhereHas('campuses', fn ($campuses) => $campuses->where('campuses.id', $activeCampusId));
                });
            })
            ->orderByDesc('start_date')
            ->first();
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
