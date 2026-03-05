<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Group;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\AttendanceJustification;
use App\Models\StudentFollowUp;
use App\Models\AcademicSession;
use App\Models\Schedule;
use App\Models\Announcement;
use App\Services\AdminAlerts\AdminAlertService;
use App\Services\DashboardService;
use App\Models\AnnouncementRecipient;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(AdminAlertService $alertService) {
        $user = Auth::user();
        if ($user->hasRole('admin')) {
            return $this->adminDashboard($alertService);
        }
        if ($user->hasRole('teacher')) {
            return $this->teacherDashboard();
        }
        if ($user->hasRole('student')) {
            return $this->studentDashboard();
        }
        return view('dashboard.default');
    }

    protected function adminDashboard(AdminAlertService $alertService) {
        $today = Carbon::today();
        $fromDate = Carbon::now()->subDays(7);
        $dashboard = new DashboardService;

        return view('dashboards.admin', [
            'alerts' => $dashboard->alerts(),
            'metrics' => $dashboard->metrics(),
        ]);
    }

    protected function studentDashboard() {
        return view('dashboards.student');     
    }

    protected function teacherDashboard() {
        $teacher = auth()->user()->teacher;

        return view('dashboards.teacher', [
            'todayClasses' => $this->todayClasses($teacher),
            'pendingAttendances' => $this->pendingAttendances($teacher),
            'announcements' => $this->announcements(),
            'notifications' => $this->followUpNotifications($teacher),
        ]);
    }

    protected function todayClasses($teacher) {
        $today = app()->environment('local')
            ? now()->subDays(2) // ⚠️ solo para pruebas
            : now();
    
        return AcademicSession::query()
            ->whereDate('session_date', $today)
            ->whereHas('teachingAssignment', function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id);
            })
            ->where('is_cancelled', false)
            ->with([
                'teachingAssignment.subject:id,name',
                'teachingAssignment.group:id,name',
            ])
            ->withCount('attendances','sessionActivity') // 👈 CLAVE
            ->orderBy('start_time')
            ->get()
            ->map(function ($session) {
    
                return (object) [
                    'session_id' => $session->id,
                    'subject'    => $session->teachingAssignment->subject->name,
                    'group'      => $session->teachingAssignment->group->name,
                    'time'       => $session->start_time . ' – ' . $session->end_time,
    
                    // 👇 ESTADOS QUE LA VISTA ESPERA
                    'attendance_closed'     => ! is_null($session->attendance_closed_at),
                    'attendance_registered' => $session->attendances_count > 0,
                    'activity_assigned'     => $session->session_activity_count > 0,
                ];
            });
    }
    

    protected function pendingAttendances($teacher) {
        return AcademicSession::query()
            ->whereHas('teachingAssignment', function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id);
            })
            ->where('is_cancelled', false)
            ->whereBetween('session_date', [
                now()->startOfWeek(),
                now()->endOfWeek(),
            ])
            ->with([
                'teachingAssignment.subject:id,name',
                'teachingAssignment.group:id,name',
            ])
            ->withCount('attendances') // 👈 CLAVE
            ->orderBy('session_date')
            ->limit(3)
            ->get()
            ->map(function ($session) {
        
                return (object) [
                    'session_id' => $session->id,
                    'subject'    => $session->teachingAssignment->subject->name,
                    'group'      => $session->teachingAssignment->group->name,
                    'date'       => $session->session_date->format('d/m/Y'),
        
                    // 👇 ESTADOS UX
                    'attendance_registered' => $session->attendances_count > 0,
                    'attendance_closed'     => ! is_null($session->attendance_closed_at),
                ];
            });
    }

    protected function announcements() {
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
                    'title'   => $announcement->title,
                    'excerpt' => str($announcement->content)->limit(120),
                    'date'    => optional($announcement->published_at)->format('d/m/Y'),
                ];
            });
    }

    protected function followUpNotifications($teacher) {
        return $teacher->user
            ->unreadNotifications
            ->where('data.type', 'student_follow_up')
            ->take(3)
            ->map(function ($notification) {

                return (object) [
                    'id'      => $notification->id,
                    'message' => $notification->data['message'] ?? 'Seguimiento académico pendiente',
                    'date'    => $notification->created_at->format('d/m/Y'),
                ];
            });
    }
}