<?php

namespace App\Http\Controllers\Coordination;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\AcademicPeriod;
use App\Models\Attendance;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class StudentReportCardController extends Controller
{
    private function buildReportData(Student $student): array {
        /*
        |--------------------------------------------------------------------------
        | Relaciones base
        |--------------------------------------------------------------------------
        */
        $student->load([
            'user',
            'group.level',
            'group.modality',
            'group.subjects',
            'grades.activity.assignment.subject',
            'grades.activity.academicPeriod',
        ]);

        $subjects = $student->group->subjects;

        /*
        |--------------------------------------------------------------------------
        | Periodos SOLO de la modalidad del grupo
        |--------------------------------------------------------------------------
        */
        $modalityId = $student->group->level->modality_id;

        $periods = AcademicPeriod::query()
            ->where('modality_id', $modalityId)
            ->orderBy('start_date')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | ASISTENCIA POR MATERIA Y PERIODO (SQL, lógica oficial)
        |--------------------------------------------------------------------------
        */
        $attendanceByPeriod = Attendance::query()
            ->select(
                'subjects.id as subject_id',
                'academic_periods.id as period_id',
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
            ->join('academic_periods', function ($join) {
                $join->on('attendances.class_date', '>=', 'academic_periods.start_date')
                     ->on('attendances.class_date', '<=', 'academic_periods.end_date');
            })
            ->where('attendances.student_id', $student->id)
            ->whereIn('academic_periods.id', $periods->pluck('id'))
            ->groupBy('subjects.id', 'academic_periods.id')
            ->get()
            ->groupBy(['subject_id', 'period_id']);

        /*
        |--------------------------------------------------------------------------
        | Estructuras
        |--------------------------------------------------------------------------
        */
        $report = [];
        $periodTotals = [];
        $generalGrades = [];
        $generalAttendances = [];

        /*
        |--------------------------------------------------------------------------
        | Construcción de la boleta
        |--------------------------------------------------------------------------
        */
        foreach ($subjects as $subject) {

            $report[$subject->id] = [
                'nrc' => $subject->nrc,
                'name' => $subject->name,
                'periods' => [],
                'final' => [
                    'average' => null,
                    'attendance' => null,
                ],
            ];

            foreach ($periods as $period) {

                /* =====================
                 * CALIFICACIONES
                 * ===================== */
                $grades = $student->grades
                    ->filter(function ($grade) use ($subject, $period) {
                        return
                            $grade->activity &&
                            $grade->activity->assignment &&
                            $grade->activity->assignment->subject_id === $subject->id &&
                            $grade->activity->academic_period_id === $period->id;
                    })
                    ->pluck('score');

                $average = $grades->count()
                    ? round($grades->avg(), 1)
                    : null;

                /* =====================
                 * ASISTENCIA (desde SQL)
                 * ===================== */
                $attendanceRow =
                    $attendanceByPeriod[$subject->id][$period->id][0] ?? null;

                $attendancePercent =
                    $attendanceRow && $attendanceRow->total > 0
                        ? round(($attendanceRow->attended / $attendanceRow->total) * 100, 1)
                        : null;

                $report[$subject->id]['periods'][$period->id] = [
                    'average' => $average,
                    'attendance' => $attendancePercent,
                ];

                if ($average !== null) {
                    $periodTotals[$period->id]['grades'][] = $average;
                }

                if ($attendancePercent !== null) {
                    $periodTotals[$period->id]['attendance'][] = $attendancePercent;
                }
            }

            /* =====================
             * PROMEDIO FINAL POR MATERIA
             * ===================== */
            $subjectGrades = collect($report[$subject->id]['periods'])
                ->pluck('average')
                ->filter();

            $subjectAttendance = collect($report[$subject->id]['periods'])
                ->pluck('attendance')
                ->filter();

            $report[$subject->id]['final'] = [
                'average' => $subjectGrades->count()
                    ? round($subjectGrades->avg(), 1)
                    : null,

                'attendance' => $subjectAttendance->count()
                    ? round($subjectAttendance->avg(), 1)
                    : null,
            ];

            if ($report[$subject->id]['final']['average'] !== null) {
                $generalGrades[] = $report[$subject->id]['final']['average'];
            }

            if ($report[$subject->id]['final']['attendance'] !== null) {
                $generalAttendances[] = $report[$subject->id]['final']['attendance'];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | PROMEDIOS POR PERIODO
        |--------------------------------------------------------------------------
        */
        $periodAverages = [];

        foreach ($periodTotals as $periodId => $data) {
            $periodAverages[$periodId] = [
                'average' => isset($data['grades'])
                    ? round(collect($data['grades'])->avg(), 1)
                    : null,
                'attendance' => isset($data['attendance'])
                    ? round(collect($data['attendance'])->avg(), 1)
                    : null,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | PROMEDIOS GENERALES
        |--------------------------------------------------------------------------
        */
        $generalAverage = count($generalGrades)
            ? round(collect($generalGrades)->avg(), 1)
            : null;

        $generalAttendance = count($generalAttendances)
            ? round(collect($generalAttendances)->avg(), 1)
            : null;

        return compact('student','subjects','periods','report','periodAverages','generalAverage','generalAttendance');
    }

    public function show(Student $student) {
        return view('admin.students.report-card',$this->buildReportData($student));
    }

    public function pdf(Student $student) {
        $data = $this->buildReportData($student);
        $pdf = Pdf::loadView('admin.students.report-card-pdf',$data)->setPaper('letter', 'portrait');
        return $pdf->download('boleta_'.$student->enrollment_number.'.pdf');
    }
}
