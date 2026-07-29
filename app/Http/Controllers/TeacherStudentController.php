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
use App\Services\CurrentSchoolCycle;

class TeacherStudentController extends Controller
{
    /**
     * Grupos del profesor
     */
    public function index()
    {
        $teacher = auth()->user()->teacher;
        $activeCycle = $this->activeCycle();

        $groups = collect();

        if ($teacher && $activeCycle) {
            $groups = TeachingAssignment::query()
                ->where('teacher_id', $teacher->id)
                ->where('is_active', true)
                ->whereHas('schedules', function ($q) use ($activeCycle) {
                    $q->where('school_cycle_id', $activeCycle->id)
                        ->where('is_active', true);
                })
                ->with('group')
                ->get()
                ->pluck('group')
                ->filter()
                ->unique('id')
                ->values();
        }

        return view('teacher.students.index', [
            'groups' => $groups,
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

        abort_unless(
            TeachingAssignment::query()
                ->where('teacher_id', $teacher->id)
                ->where('group_id', $group->id)
                ->where('is_active', true)
                ->when(
                    $activeCycle,
                    fn ($q) => $q->whereHas('schedules', fn ($qq) => $qq
                        ->where('school_cycle_id', $activeCycle->id)
                        ->where('is_active', true)
                    ),
                    fn ($q) => $q->whereRaw('1 = 0')
                )
                ->exists(),
            403
        );

        $students = $group->students()
            ->where('students.is_active', true)
            ->join('users', 'users.id', '=', 'students.user_id')
            ->orderBy('users.name')
            ->select('students.*')
            ->get();

        $sessionIds = AcademicSession::whereHas('teachingAssignment', function ($q) use ($group, $teacher, $activeCycle) {
                $q->where('group_id', $group->id)
                  ->where('teacher_id', $teacher->id)
                  ->when(
                      $activeCycle,
                      fn ($qq) => $qq->whereHas('schedules', fn ($s) => $s
                          ->where('school_cycle_id', $activeCycle->id)
                          ->where('is_active', true)
                      )
                  );
            })
            ->where('session_date', '<=', now())
            ->pluck('id');

        $attendanceStats = Attendance::whereIn('academic_session_id', $sessionIds)
            ->selectRaw('
                student_id,
                COUNT(*) as total,
                SUM(status IN ("present", "late", "justified")) as attended
            ')
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $activityIds = Activity::whereHas('teachingAssignment', function ($q) use ($group, $teacher, $activeCycle) {
                $q->where('group_id', $group->id)
                  ->where('teacher_id', $teacher->id)
                  ->when(
                      $activeCycle,
                      fn ($qq) => $qq->whereHas('schedules', fn ($s) => $s
                          ->where('school_cycle_id', $activeCycle->id)
                          ->where('is_active', true)
                      )
                  );
            })
            ->where('is_active', true)
            ->pluck('id');

        $activityStats = Grade::whereIn('activity_id', $activityIds)
            ->selectRaw('student_id, COUNT(DISTINCT activity_id) as delivered')
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $totalActivities = $activityIds->count();

        return view('teacher.students.group', [
            'group'    => $group,
            'students' => $students,
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

        abort_unless(
            TeachingAssignment::where('teacher_id', $teacher->id)
                ->where('group_id', $student->group_id)
                ->when(
                    $activeCycle,
                    fn ($q) => $q->whereHas('schedules', fn ($qq) => $qq
                        ->where('school_cycle_id', $activeCycle->id)
                        ->where('is_active', true)
                    ),
                    fn ($q) => $q->whereRaw('1 = 0')
                )
                ->exists(),
            403
        );

        $sessionIds = AcademicSession::whereHas('teachingAssignment', function ($q) use ($teacher, $student, $activeCycle) {
                $q->where('teacher_id', $teacher->id)
                ->where('group_id', $student->group_id)
                ->when(
                    $activeCycle,
                    fn ($qq) => $qq->whereHas('schedules', fn ($s) => $s
                        ->where('school_cycle_id', $activeCycle->id)
                        ->where('is_active', true)
                    )
                );
            })
            ->where('session_date', '<=', now())
            ->pluck('id');

        $attendanceStats = Attendance::whereIn('academic_session_id', $sessionIds)
            ->where('student_id', $student->id)
            ->selectRaw('
                COUNT(*) as total,
                SUM(status IN ("present", "late", "justified")) as attended
            ')
            ->first();

        $activityIds = Activity::whereHas('teachingAssignment', function ($q) use ($teacher, $student, $activeCycle) {
                $q->where('teacher_id', $teacher->id)
                  ->where('group_id', $student->group_id)
                  ->when(
                      $activeCycle,
                      fn ($qq) => $qq->whereHas('schedules', fn ($s) => $s
                          ->where('school_cycle_id', $activeCycle->id)
                          ->where('is_active', true)
                      )
                  );
            })
            ->where('is_active', true)
            ->pluck('id');

        $deliveredActivities = Grade::whereIn('activity_id', $activityIds)
            ->where('student_id', $student->id)
            ->distinct('activity_id')
            ->count('activity_id');
        
        $totalActivities = $activityIds->count();

        $subjects = $teacher->teachingAssignments()
            ->where('group_id', $student->group_id)
            ->when(
                $activeCycle,
                fn ($q) => $q->whereHas('schedules', fn ($qq) => $qq
                    ->where('school_cycle_id', $activeCycle->id)
                    ->where('is_active', true)
                )
            )
            ->with('subject')
            ->get()
            ->pluck('subject')
            ->unique('id');

        $assignments = $teacher->teachingAssignments()
            ->where('group_id', $student->group_id)
            ->when(
                $activeCycle,
                fn ($q) => $q->whereHas('schedules', fn ($qq) => $qq
                    ->where('school_cycle_id', $activeCycle->id)
                    ->where('is_active', true)
                )
            )
            ->with('subject')
            ->get();
        
        $summaryBySubject = $assignments->map(function ($assignment) use ($student) {

            // SESIONES
            $sessionIds = AcademicSession::where('teaching_assignment_id', $assignment->id)
                ->where('session_date', '<=', now())
                ->pluck('id');
        
            $attendance = Attendance::whereIn('academic_session_id', $sessionIds)
                ->where('student_id', $student->id)
                ->selectRaw('
                    COUNT(*) as total,
                    SUM(status IN ("present", "late", "justified")) as attended
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
                    'average'   => $averageScore ? round($averageScore, 1) : null,
                ],
            ];
        });

        return view('teacher.students.show', [
            'student'             => $student,
            'attendanceStats'     => $attendanceStats,
            'deliveredActivities' => $deliveredActivities,
            'totalActivities'     => $totalActivities,
            'subjects'            => $subjects,
            'summaryBySubject'    => $summaryBySubject,
        ]);
    }

    private function activeCycle(): ?SchoolCycle
    {
        $activeCampusId = (int) session('active_campus_id', 0);

        return app(CurrentSchoolCycle::class)->get(auth()->user(), $activeCampusId);
    }
}
