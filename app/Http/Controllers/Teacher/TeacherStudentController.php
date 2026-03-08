<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\Activity;
use App\Models\Grade;

class TeacherStudentController extends Controller
{
    /**
     * Grupos del profesor
     */
    public function index()
    {
        $teacher = auth()->user()->teacher;

        $groups = TeachingAssignment::where('teacher_id', $teacher->id)
            ->with('group')
            ->get()
            ->pluck('group')
            ->unique('id')
            ->values();

        return view('teacher.students.index', [
            'groups' => $groups,
        ]);
    }

    /**
     * Alumnos de un grupo
     */
    public function group(Group $group)
    {
        $teacher = auth()->user()->teacher;

        abort_unless(
            TeachingAssignment::where('teacher_id', $teacher->id)
                ->where('group_id', $group->id)
                ->exists(),
            403
        );

        $students = $group->students()
            ->where('students.is_active', true)
            ->join('users', 'users.id', '=', 'students.user_id')
            ->orderBy('users.name')
            ->select('students.*')
            ->get();

        $sessionIds = AcademicSession::whereHas('teachingAssignment', function ($q) use ($group, $teacher) {
                $q->where('group_id', $group->id)
                  ->where('teacher_id', $teacher->id);
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

        $activityIds = Activity::whereHas('teachingAssignment', function ($q) use ($group, $teacher) {
                $q->where('group_id', $group->id)
                  ->where('teacher_id', $teacher->id);
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

        abort_unless(
            TeachingAssignment::where('teacher_id', $teacher->id)
                ->where('group_id', $student->group_id)
                ->exists(),
            403
        );

        $sessionIds = AcademicSession::whereHas('teachingAssignment', function ($q) use ($teacher, $student) {
                $q->where('teacher_id', $teacher->id)
                ->where('group_id', $student->group_id);
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

        $activityIds = Activity::whereHas('teachingAssignment', function ($q) use ($teacher, $student) {
                $q->where('teacher_id', $teacher->id)
                  ->where('group_id', $student->group_id);
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
            ->with('subject')
            ->get()
            ->pluck('subject')
            ->unique('id');

        $assignments = $teacher->teachingAssignments()
            ->where('group_id', $student->group_id)
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
}