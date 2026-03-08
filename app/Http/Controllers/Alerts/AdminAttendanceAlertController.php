<?php

namespace App\Http\Controllers\Alerts;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Modality;
use App\Models\Attendance;
use App\Models\AcademicSession;
use App\Models\Student;
use App\Services\AcademicCalendarService;
use Carbon\Carbon;

class AdminAttendanceAlertController extends Controller
{
    public function fullDayAbsences(Modality $modality, AcademicCalendarService $calendar) {
        $limitDays = 3;
        $endDate = $calendar->getLastSchoolDay(now(), $modality->id);
        $since   = $calendar->subtractSchoolDays($endDate, 20, $modality->id);        
        // 1️⃣ Sesiones reales por día
        $sessionsByDate = AcademicSession::whereBetween('session_date', [$since, $endDate])
            ->where('is_cancelled', false)
            ->with(['attendances' => function ($q) {
                $q->select('academic_session_id', 'student_id', 'status');
            }])
            ->get()
            ->groupBy(fn ($s) => $s->session_date->toDateString());
        
        // 2️⃣ Detectar alumnos con días completos de inasistencia
        $studentsWithAbsentDays = collect();
        
        foreach ($sessionsByDate as $date => $sessions) {
        
            $byStudent = $sessions
                ->flatMap->attendances
                ->groupBy('student_id');
        
            foreach ($byStudent as $studentId => $records) {
        
                $total   = $records->count();
                $absents = $records->where('status', 'absent')->count();
        
                if ($total === $absents) {
                    $studentsWithAbsentDays->push($studentId);
                }
            }
        }
        
        // 3️⃣ Obtener alumnos
        $students = Student::whereHas('group.level.modality', fn ($q) =>
                $q->where('id', $modality->id)
            )
            ->whereIn('id', $studentsWithAbsentDays->unique())
            ->with('group.level')
            ->get();
        $results = [];
        foreach ($students as $student) {
            $byDate = $student->attendances->groupBy(fn ($a) => $a->class_date->toDateString());
            $streak = [];
            $current = [];
            $date = $since->copy();
            while ($date->lte($endDate)) {
                if ($calendar->isNonWorkingDay($date, $modality->id)) {
                    $date->addDay();
                    continue;
                }
                $records = $byDate->get($date->toDateString(), collect());
                if (
                    $records->isNotEmpty() &&
                    $records->count() === $records->where('status', 'absent')->count()
                ) {
                    $current[] = $date->toDateString();
                } else {
                    if (count($current) >= $limitDays) {
                        $streak[] = $current;
                    }
                    $current = [];
                }
                $date->addDay();
            }
            if (count($current) >= $limitDays) {
                $streak[] = $current;
            }
            if (! empty($streak)) {
                $results[] = [
                    'student' => $student,
                    'streaks' => $streak,
                ];
            }
        }
        return view('admin.alerts.full_day_absences', compact('modality','since','endDate','results'));
    }

    public function partialAttendance(Modality $modality, AcademicCalendarService $calendar) {
        $minPercentage = 0.5;
        $minDays = 1;
        $endDate = $calendar->getLastSchoolDay(now(), $modality->id);
        $since   = $calendar->subtractSchoolDays($endDate, 30, $modality->id);
        $students = Student::whereHas('groupHistories.group.level.modality', fn ($q) =>
            $q->where('id', $modality->id)
        )
        ->with([
            'groupHistories.group.level',
        ])
        ->get();
        $studentIds = $students->pluck('id');
        $sessionsByDate = AcademicSession::whereBetween('session_date', [$since, $endDate])
            ->where('is_cancelled', false)
            ->with([
                'teachingAssignment.subject',
                'attendances' => function ($q) use ($studentIds) {
                    $q->whereIn('student_id', $studentIds);
                },
            ])
            ->get()
            ->groupBy(fn ($s) => $s->session_date->toDateString());
        
        $results = [];
        
        foreach ($students as $student) {

            $partialDays = [];
        
            foreach ($sessionsByDate as $date => $sessions) {
        
                // Ignorar días no laborables
                if ($calendar->isNonWorkingDay(Carbon::parse($date), $modality->id)) {
                    continue;
                }
        
                // 🔑 SOLO sesiones DEL ALUMNO en ESE DÍA
                $groupIdForDate = $student->groupHistories
                    ->first(fn ($h) =>
                        Carbon::parse($date)->gte(Carbon::parse($h->start_date)) &&
                        (
                            ! $h->end_date ||
                            Carbon::parse($date)->lte(Carbon::parse($h->end_date))
                        )
                    )?->group_id;
                
                if (! $groupIdForDate) {
                    continue;
                }
                
                $studentSessions = $sessions->filter(fn ($session) =>
                    $session->teachingAssignment->group_id === $groupIdForDate
                );
        
                if ($studentSessions->isEmpty()) {
                    continue;
                }
        
                // Asistencias del alumno ese día
                $records = $studentSessions
                    ->flatMap->attendances
                    ->where('student_id', $student->id);
        
                if ($records->isEmpty()) {
                    continue;
                }
        
                $total  = $records->count();
                $absent = $records->where('status', 'absent')->count();
        
                if (
                    $total > 0 &&
                    ($absent / $total) >= $minPercentage &&
                    $absent < $total
                ) {
                    // 🔑 sesiones donde el alumno estuvo ausente
                    $absentSessionIds = $records
                        ->where('status', 'absent')
                        ->pluck('academic_session_id')
                        ->unique();
        
                    $partialDays[] = [
                        'date'   => $date,
                        'total'  => $total,
                        'absent' => $absent,
                        'subjects' => $studentSessions
                            ->whereIn('id', $absentSessionIds)
                            ->pluck('teachingAssignment.subject.name')
                            ->filter()
                            ->unique()
                            ->values(),
                    ];
                }
            }
        
            if (count($partialDays) >= $minDays) {
                $results[] = [
                    'student' => $student,
                    'days'    => $partialDays,
                ];
            }
        }
                
        return view('admin.alerts.partial_attendance', compact(
            'modality',
            'since',
            'endDate',
            'results'
        ));
    }
}
