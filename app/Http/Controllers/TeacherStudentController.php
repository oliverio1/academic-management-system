<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\Activity;
use App\Models\Grade;
use App\Models\SchoolCycle;
use App\Models\TeacherStudentReport;
use App\Services\CurrentSchoolCycle;
use Illuminate\Support\Collection;

class TeacherStudentController extends Controller
{
    /**
     * Grupos del profesor
     */
    public function index()
    {
        $teacher = auth()->user()->teacher;
        $activeCycle = $this->activeCycle();

        $groupCards = collect();

        if ($teacher && $activeCycle) {
            $assignments = TeachingAssignment::query()
                ->where('teacher_id', $teacher->id)
                ->where('is_active', true)
                ->whereHas('schedules', function ($q) use ($activeCycle) {
                    $q->where('school_cycle_id', $activeCycle->id)
                        ->where('is_active', true);
                })
                ->with(['group.students.user', 'group.level', 'subject'])
                ->get();

            $groupCards = $assignments
                ->groupBy('group_id')
                ->map(function (Collection $groupAssignments) {
                    $group = $groupAssignments->first()->group;
                    $subjects = $groupAssignments
                        ->pluck('subject.name')
                        ->filter()
                        ->unique()
                        ->sort()
                        ->values();

                    return [
                        'group' => $group,
                        'level' => $group?->level?->name,
                        'subjects' => $subjects,
                        'assignments' => $groupAssignments->count(),
                        'students_count' => $group?->students?->where('is_active', true)->count() ?? 0,
                    ];
                })
                ->sortBy(fn ($card) => $card['group']->name ?? '')
                ->values();
        }

        return view('teacher.students.index', [
            'groupCards' => $groupCards,
            'activeCycle' => $activeCycle,
        ]);
    }

    /**
     * Alumnos de un grupo
     */
    public function group(Group $group)
    {
        $teacher = auth()->user()->teacher;
        $activeCycle = $this->activeCycle();

        $assignments = $this->assignmentsForTeacherGroup($teacher?->id, $group->id, $activeCycle);
        abort_unless($assignments->isNotEmpty(), 403);

        $students = $group->students()
            ->with('user')
            ->where('students.is_active', true)
            ->join('users', 'users.id', '=', 'students.user_id')
            ->orderBy('users.name')
            ->select('students.*')
            ->get();

        $assignmentIds = $assignments->pluck('id')->all();
        $sessionIds = AcademicSession::whereIn('teaching_assignment_id', $assignmentIds)
            ->where('session_date', '<=', now())
            ->pluck('id');

        $attendanceStats = Attendance::whereIn('academic_session_id', $sessionIds)
            ->selectRaw('
                student_id,
                COUNT(*) as total,
                SUM(CASE WHEN status IN ("present", "late", "justified") THEN 1 ELSE 0 END) as attended
            ')
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $activityIds = Activity::whereIn('teaching_assignment_id', $assignmentIds)
            ->where('is_active', true)
            ->pluck('id');

        $activityStats = Grade::whereIn('activity_id', $activityIds)
            ->selectRaw('student_id, COUNT(DISTINCT activity_id) as delivered, AVG(score) as average_score')
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $totalActivities = $activityIds->count();

        return view('teacher.students.group', [
            'group'    => $group,
            'students' => $students,
            'assignments' => $assignments,
            'activeCycle' => $activeCycle,
            'attendanceStats'  => $attendanceStats,
            'activityStats'    => $activityStats,
            'totalActivities'  => $totalActivities,
        ]);
    }

    /**
     * Perfil académico del alumno (solo lectura)
     */
    public function show(Student $student)
    {
        $teacher = auth()->user()->teacher;
        $activeCycle = $this->activeCycle();
        $student->loadMissing(['user', 'group.level', 'guardian']);

        $assignments = $this->assignmentsForTeacherGroup($teacher?->id, (int) $student->group_id, $activeCycle);
        abort_unless($assignments->isNotEmpty(), 403);

        $assignmentIds = $assignments->pluck('id')->all();
        $sessionIds = AcademicSession::whereIn('teaching_assignment_id', $assignmentIds)
            ->where('session_date', '<=', now())
            ->pluck('id');

        $attendanceStats = Attendance::whereIn('academic_session_id', $sessionIds)
            ->where('student_id', $student->id)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status IN ("present", "late", "justified") THEN 1 ELSE 0 END) as attended
            ')
            ->first();

        $activityIds = Activity::whereIn('teaching_assignment_id', $assignmentIds)
            ->where('is_active', true)
            ->pluck('id');

        $deliveredActivities = Grade::whereIn('activity_id', $activityIds)
            ->where('student_id', $student->id)
            ->distinct('activity_id')
            ->count('activity_id');
        
        $totalActivities = $activityIds->count();

        $summaryBySubject = $assignments->map(function ($assignment) use ($student) {

            // SESIONES
            $sessionIds = AcademicSession::where('teaching_assignment_id', $assignment->id)
                ->where('session_date', '<=', now())
                ->pluck('id');
        
            $attendance = Attendance::whereIn('academic_session_id', $sessionIds)
                ->where('student_id', $student->id)
                ->selectRaw('
                    COUNT(*) as total,
                    SUM(CASE WHEN status IN ("present", "late", "justified") THEN 1 ELSE 0 END) as attended
                ')
                ->first();
        
            // ACTIVIDADES
            $activityIds = Activity::where('teaching_assignment_id', $assignment->id)
                ->where('is_active', true)
                ->pluck('id');
        
            $grades = Grade::whereIn('activity_id', $activityIds)
                ->where('student_id', $student->id);
        
            $delivered = (clone $grades)
                ->distinct('activity_id')
                ->count('activity_id');
        
            $averageScore = (clone $grades)->avg('score');
            
        
            return [
                'assignment_id' => $assignment->id,
                'subject' => $assignment->subject->name,
        
                'attendance' => [
                    'attended' => $attendance->attended ?? 0,
                    'total'    => $attendance->total ?? 0,
                    'percent'  => ($attendance && $attendance->total > 0)
                        ? round(($attendance->attended / $attendance->total) * 100)
                        : null,
                ],
        
                'activities' => [
                    'delivered' => $delivered,
                    'total'     => $activityIds->count(),
                    'average'   => $averageScore !== null ? round($averageScore, 1) : null,
                ],
            ];
        });

        $recentAttendance = Attendance::query()
            ->where('student_id', $student->id)
            ->whereIn('academic_session_id', $sessionIds)
            ->with([
                'academicSession.teachingAssignment.subject',
                'academicSession.schedule',
                'academicSession.sessionActivity',
            ])
            ->whereHas('academicSession', fn ($q) => $q->where('session_date', '<=', now()))
            ->get()
            ->sortByDesc(fn (Attendance $attendance) => optional($attendance->academicSession?->session_date)->timestamp ?? 0)
            ->take(20)
            ->values();

        $activityRows = Activity::query()
            ->whereIn('id', $activityIds)
            ->with([
                'assignment.subject',
                'evaluationCriterion',
                'academicPeriod',
                'grades' => fn ($q) => $q->where('student_id', $student->id),
            ])
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(function (Activity $activity) {
                $grade = $activity->grades->first();

                return [
                    'activity' => $activity,
                    'grade' => $grade,
                    'status' => $grade ? 'captured' : 'pending',
                ];
            });

        $reports = TeacherStudentReport::query()
            ->where('teacher_id', $teacher->id)
            ->where('student_id', $student->id)
            ->with(['group'])
            ->latest()
            ->limit(10)
            ->get();

        $attendanceBreakdown = Attendance::whereIn('academic_session_id', $sessionIds)
            ->where('student_id', $student->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('teacher.students.show', [
            'student'             => $student,
            'activeCycle'         => $activeCycle,
            'attendanceStats'     => $attendanceStats,
            'attendanceBreakdown' => $attendanceBreakdown,
            'deliveredActivities' => $deliveredActivities,
            'totalActivities'     => $totalActivities,
            'recentAttendance'    => $recentAttendance,
            'activityRows'        => $activityRows,
            'reports'             => $reports,
            'summaryBySubject'    => $summaryBySubject,
        ]);
    }

    private function assignmentsForTeacherGroup(?int $teacherId, int $groupId, ?SchoolCycle $cycle)
    {
        if (! $teacherId || ! $cycle) {
            return collect();
        }

        return TeachingAssignment::query()
            ->where('teacher_id', $teacherId)
            ->where('group_id', $groupId)
            ->where('is_active', true)
            ->whereHas('schedules', fn ($q) => $q
                ->where('school_cycle_id', $cycle->id)
                ->where('is_active', true)
            )
            ->with(['subject', 'group.level', 'schedules'])
            ->orderBy('subject_id')
            ->orderBy('section_number')
            ->get();
    }

    private function activeCycle(): ?SchoolCycle
    {
        $activeCampusId = (int) session('active_campus_id', 0);

        return app(CurrentSchoolCycle::class)->get(auth()->user(), $activeCampusId);
    }
}
