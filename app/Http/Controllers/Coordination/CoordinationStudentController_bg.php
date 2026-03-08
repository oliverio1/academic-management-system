<?php

namespace App\Http\Controllers\Coordination;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\Attendance;
use App\Models\Grade;
use App\Models\Activity;
use App\Models\StudentFollowUp;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\AcademicCalendarService;
use App\Services\StudentFollowUpFlagService;

class CoordinationStudentController extends Controller
{
    public function index(StudentFollowUpFlagService $flagService) {
        $students = Student::with([
            'user',
            'group',
            'groupHistories',
        ])->get();

        $priorityOrder = [
            'high'   => 0,
            'medium' => 1,
            'low'    => 2,
            'none'   => 3,
        ];

        $students->each(function ($student) use ($flagService) {
            $student->followup_flags = $flagService->flagsFor($student);
            $student->priority = $flagService->priorityFor($student);
            $student->has_active_follow_up =
                $student->followUps()
                    ->where('status', 'open')
                    ->exists();
        });
        
        $students = $students->sortBy(function ($student) use ($priorityOrder) {
            return $priorityOrder[$student->priority] ?? 99;
        })->values();

        return view('admin.students.index', compact('students'));
    }

    public function show(Student $student) {
        $student->load(['group.level', 'group.modality',]);
        return view('admin.students.show', compact('student'));
    }

    public function general(Student $student) {
        return view('admin.students.partials.general', compact('student'));
    }

    public function attendance(Student $student) {
        $attendance = Attendance::query()
            ->selectRaw('
                subjects.id AS subject_id,
                subjects.name AS subject_name,
                COUNT(attendances.id) AS total,
                SUM(attendances.status = "present") AS presents
            ')
            ->join('schedules', 'attendances.schedule_id', '=', 'schedules.id')
            ->join('teaching_assignments', 'schedules.teaching_assignment_id', '=', 'teaching_assignments.id')
            ->join('subjects', 'teaching_assignments.subject_id', '=', 'subjects.id')
            ->where('attendances.student_id', $student->id)
            ->groupBy('subjects.id', 'subjects.name')
            ->orderBy('subjects.name')
            ->get();
        return view('admin.students.partials.attendance', compact(
            'student',
            'attendance'
        ));
    }

    public function grades(Student $student) {
        $grades = Grade::query()
            ->selectRaw('
                subjects.id AS subject_id,
                subjects.name AS subject_name,
                AVG(grades.score) AS average
            ')
            ->join('activities', 'grades.activity_id', '=', 'activities.id')
            ->join('teaching_assignments', 'activities.teaching_assignment_id', '=', 'teaching_assignments.id')
            ->join('subjects', 'teaching_assignments.subject_id', '=', 'subjects.id')
            ->where('grades.student_id', $student->id)
            ->groupBy('subjects.id', 'subjects.name')
            ->orderBy('subjects.name')
            ->get();
    
        return view('admin.students.partials.grades', compact(
            'student',
            'grades'
        ));
    }

    public function followups(Student $student)
    {
        $followUps = StudentFollowUp::query()
            ->where('student_id', $student->id)
            ->with([
                'requester',
                'teachers.teacher.user',
            ])
            ->latest()
            ->get();
    
        return view('admin.students.partials.followups', compact(
            'student',
            'followUps'
        ));
    }

    public function attendanceHistory(Student $student) {
        $endDate = request('end_date')
            ? Carbon::parse(request('end_date'))
            : now();
    
        // 1️⃣ Obtener los últimos 20 días lectivos reales
        $dates = Attendance::query()
            ->where('student_id', $student->id)
            ->whereDate('class_date', '<=', $endDate)
            ->distinct()
            ->orderByDesc('class_date')
            ->pluck('class_date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->sort()
            ->values();
    
        // 2️⃣ Obtener asistencias necesarias para la matriz
        $records = Attendance::query()
            ->select(
                'attendances.class_date',
                'attendances.status',
                'subjects.name as subject_name'
            )
            ->join('schedules', 'attendances.schedule_id', '=', 'schedules.id')
            ->join('teaching_assignments', 'schedules.teaching_assignment_id', '=', 'teaching_assignments.id')
            ->join('subjects', 'teaching_assignments.subject_id', '=', 'subjects.id')
            ->where('attendances.student_id', $student->id)
            ->whereIn('attendances.class_date', $dates)
            ->get();

        $subjects = \App\Models\Subject::query()
            ->join('teaching_assignments', 'subjects.id', '=', 'teaching_assignments.subject_id')
            ->join('groups', 'teaching_assignments.group_id', '=', 'groups.id')
            ->where('groups.id', $student->group_id)
            ->select('subjects.id', 'subjects.name')
            ->distinct()
            ->get();

            $subjectSchedules = Attendance::query()
            ->select(
                'subjects.name as subject_name',
                'attendances.class_date'
            )
            ->join('schedules', 'attendances.schedule_id', '=', 'schedules.id')
            ->join('teaching_assignments', 'schedules.teaching_assignment_id', '=', 'teaching_assignments.id')
            ->join('subjects', 'teaching_assignments.subject_id', '=', 'subjects.id')
            ->where('attendances.student_id', $student->id)
            ->whereIn('attendances.class_date', $dates)
            ->distinct()
            ->get();
        
        $imparted = [];
        
        foreach ($subjectSchedules as $row) {
            $dateKey = Carbon::parse($row->class_date)->format('Y-m-d');
            $imparted[$row->subject_name][$dateKey] = true;
        }
        
    
        // 3️⃣ Normalizar a matriz [materia][fecha] = 1|0
        $matrix = [];

        foreach ($subjects as $subject) {
            foreach ($dates as $date) {
                // Por defecto: no se imparte
                $matrix[$subject->name][$date] = null;
            }
        }
        
        // Ahora llenamos asistencias reales
        foreach ($records as $row) {
            $dateKey = Carbon::parse($row->class_date)->format('Y-m-d');
        
            $matrix[$row->subject_name][$dateKey] =
                $row->status === 'present' ? 1 : 0;
        }
    
        return view('admin.students.attendance-history', compact(
            'student',
            'dates',
            'matrix',
            'imparted',
            'endDate',
        ));
    }

    public function gradesHistory(Student $student, AcademicCalendarService $calendar)
    {
        $modalityId = $student->group?->level?->modality_id;

        // 1️⃣ Último día lectivo real
        $endDate = request('end_date')
            ? Carbon::parse(request('end_date'))
            : $calendar->getLastSchoolDay(now(), $modalityId);
    
        // 2️⃣ Día lectivo inicial (20 días atrás)
        $startDate = $calendar->subtractSchoolDays(
            $endDate,
            20,
            $modalityId
        );
    
        // 3️⃣ Construir arreglo de fechas lectivas (ordenadas)
        $dates = [];
        $cursor = $startDate->copy();
    
        while ($cursor->lte($endDate)) {
            if (
                !$cursor->isWeekend() &&
                !$calendar->isNonWorkingDay($cursor, $modalityId)
            ) {
                $dates[] = $cursor->toDateString();
            }
            $cursor->addDay();
        }
        $activities = Activity::query()
        ->select(
            'activities.id',
            'activities.title',
            DB::raw('DATE(activities.due_date) as activity_date'),
            'subjects.name as subject_name'
        )
        ->join(
            'teaching_assignments',
            'activities.teaching_assignment_id',
            '=',
            'teaching_assignments.id'
        )
        ->join(
            'subjects',
            'teaching_assignments.subject_id',
            '=',
            'subjects.id'
        )
        ->whereBetween(
            DB::raw('DATE(activities.due_date)'),
            [$startDate->toDateString(), $endDate->toDateString()]
        )
        ->where(
            'teaching_assignments.group_id',
            $student->group_id
        )
        ->get();
    
        /**
         * 3️⃣ Traer calificaciones del alumno
         */
        $grades = Grade::query()
            ->where('student_id', $student->id)
            ->get()
            ->keyBy('activity_id');
    

        $attendanceStats = Attendance::query()
            ->select(
                'subjects.name as subject_name',
                DB::raw('COUNT(attendances.id) as total'),
                DB::raw("
                    SUM(
                        CASE
                            WHEN attendances.status IN ('present', 'justified')
                            THEN 1
                            ELSE 0
                        END
                    ) as attended
                ")
            )
            ->join('schedules', 'attendances.schedule_id', '=', 'schedules.id')
            ->join('teaching_assignments', 'schedules.teaching_assignment_id', '=', 'teaching_assignments.id')
            ->join('subjects', 'teaching_assignments.subject_id', '=', 'subjects.id')
            ->where('attendances.student_id', $student->id)
            ->whereBetween('attendances.class_date', [
                $startDate->toDateString(),
                $endDate->toDateString()
            ])
            ->groupBy('subjects.name')
            ->get()
            ->keyBy('subject_name');

            
            /**
             * 4️⃣ Construir matriz:
             * [materia][fecha] = score | 'x'
             */
        $attendanceSummary = [];
        $matrix = [];
    
        foreach ($activities as $activity) {
        
            $subject = $activity->subject_name;
        
            if (!isset($matrix[$subject])) {
                $matrix[$subject] = [];
            }
        
            $matrix[$subject][] = [
                'date'  => $activity->activity_date,
                'title' => $activity->title,
                'score' => $grades->has($activity->id)
                    ? $grades[$activity->id]->score
                    : null,
            ];
        }

        foreach ($matrix as $subject => $values) {
            if (isset($attendanceStats[$subject]) && $attendanceStats[$subject]->total > 0) {
                $attendanceSummary[$subject] = round(
                    ($attendanceStats[$subject]->attended / $attendanceStats[$subject]->total) * 100,
                    1
                );
            } else {
                $attendanceSummary[$subject] = null;
            }
        }
        
        return view('admin.students.grades-history', compact(
            'student',
            'dates',
            'matrix',
            'attendanceSummary',
            'endDate'
        ));
    }
}