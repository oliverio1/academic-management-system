<?php

namespace App\Http\Controllers\Coordination;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\AcademicPeriod;
use App\Models\Attendance;
use App\Models\AcademicSession;
use App\Models\SchoolCycle;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class StudentReportCardController extends Controller
{
    private function buildReportData(Student $student): array {
        $student->load([
            'user',
            'group.level',
            'group.modality',
            'group.subjects',
            'grades.activity.assignment.subject',
            'grades.activity.academicPeriod',
        ]);
        $subjects = $student->group->subjects;
        $modalityId = $student->group->level->modality_id;
        $activeCycle = SchoolCycle::query()
            ->where('modality_id', $modalityId)
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->first();

        $cycleStart = null;
        $cycleEnd = null;

        if ($activeCycle) {
            $cycleStart = $activeCycle->start_date?->copy()->startOfDay();
            $cycleEnd = $activeCycle->end_date?->copy()->endOfDay();

            $periodIds = $activeCycle->partials()
                ->whereNotNull('academic_period_id')
                ->orderBy('sort_order')
                ->pluck('academic_period_id')
                ->unique()
                ->values();

            $periods = AcademicPeriod::query()
                ->whereIn('id', $periodIds)
                ->orderBy('start_date')
                ->get();
        } else {
            $periods = AcademicPeriod::query()
                ->where('modality_id', $modalityId)
                ->where('is_active', true)
                ->orderBy('start_date')
                ->get();
        }
        $totalSessionsByPeriod = AcademicSession::query()
            ->select(
                'subjects.id as subject_id',
                'academic_periods.id as period_id',
                DB::raw('COUNT(academic_sessions.id) as total_sessions')
            )
            ->join('teaching_assignments', 'academic_sessions.teaching_assignment_id', '=', 'teaching_assignments.id')
            ->join('subjects', 'teaching_assignments.subject_id', '=', 'subjects.id')
            ->join('academic_periods', 'academic_sessions.academic_period_id', '=', 'academic_periods.id')
            ->where('teaching_assignments.group_id', $student->group_id)
            ->where('academic_sessions.is_cancelled', false)
            ->whereIn('academic_periods.id', $periods->pluck('id'))
            ->when(
                $cycleStart && $cycleEnd,
                fn ($q) => $q->whereBetween('academic_sessions.session_date', [$cycleStart, $cycleEnd])
            )
            ->groupBy('subjects.id', 'academic_periods.id')
            ->get()
            ->groupBy(['subject_id', 'period_id']);

        $attendedByPeriod = Attendance::query()
            ->select(
                'subjects.id as subject_id',
                'academic_periods.id as period_id',
                DB::raw('COUNT(attendances.id) as attended_sessions')
            )
            ->join('academic_sessions', 'attendances.academic_session_id', '=', 'academic_sessions.id')
            ->join('teaching_assignments', 'academic_sessions.teaching_assignment_id', '=', 'teaching_assignments.id')
            ->join('subjects', 'teaching_assignments.subject_id', '=', 'subjects.id')
            ->join('academic_periods', 'academic_sessions.academic_period_id', '=', 'academic_periods.id')
            ->where('attendances.student_id', $student->id)
            ->whereIn('attendances.status', ['present', 'late', 'justified'])
            ->where('academic_sessions.is_cancelled', false)
            ->whereIn('academic_periods.id', $periods->pluck('id'))
            ->when(
                $cycleStart && $cycleEnd,
                fn ($q) => $q->whereBetween('academic_sessions.session_date', [$cycleStart, $cycleEnd])
            )
            ->groupBy('subjects.id', 'academic_periods.id')
            ->get()
            ->groupBy(['subject_id', 'period_id']);
        $report = [];
        $periodTotals = [];
        $generalGrades = [];
        $generalAttendances = [];
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
                $totalRow = $totalSessionsByPeriod[$subject->id][$period->id][0] ?? null;
                $attendedRow = $attendedByPeriod[$subject->id][$period->id][0] ?? null;
                $attendancePercent = ($totalRow && $totalRow->total_sessions > 0)
                    ? round(((int) ($attendedRow->attended_sessions ?? 0) / (int) $totalRow->total_sessions) * 100, 1)
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
