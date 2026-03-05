<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\GradeService;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\AcademicPeriod;

class ReportCardController extends Controller
{
    public function show(Student $student, GradeService $grades)
    {
        $group = $student->group;
        $periods = AcademicPeriod::where('modality_id', $group->modality_id)->orderBy('start_date')->get();
        $data = [];
        foreach ($periods as $period) {
            $subjects = [];
            foreach ($group->assignments as $assignment) {
                $subjects[] = [
                    'subject' => $assignment->subject->name,
                    'average' => $grades->subjectAverageByPeriod(
                        $student,
                        $assignment,
                        $period
                    ),
                ];
            }
            $data[] = [
                'period' => $period,
                'subjects' => $subjects,
                'general' => $grades->studentAverageByPeriod($student, $period),
            ];
        }
        return view('report_cards.show', compact('student', 'data'));
    }

    public function pdf(Student $student, GradeService $grades) {
        $group = $student->group;
        $subjects = $group->subjects;
        $subjectAverages = [];
        foreach ($subjects as $subject) {
            $subjectAverages[$subject->id] = $grades->subjectAverage($student, $subject->id);
        }
        $generalAverage = $grades->generalAverage($student);
        $pdf = Pdf::loadView('report_cards.pdf', compact('student','group','subjects','subjectAverages','generalAverage'));
        return $pdf->download('boleta_'.$student->id.'.pdf');
    }
}