<?php

namespace App\Http\Controllers;

use App\Models\Modality;
use App\Models\Student;
use App\Services\AcademicPerformanceService;

class AdminAcademicAlertController extends Controller
{
    public function criticalSubjects(Modality $modality)
    {
        $performance = app(AcademicPerformanceService::class);
        $minScore = 6;

        $students = Student::whereHas(
                'group.level.modality',
                fn ($q) => $q->where('id', $modality->id)
            )
            ->with('group.assignments.subject')
            ->get();

        $results = [];

        foreach ($students as $student) {

            foreach ($student->group->assignments as $assignment) {

                $final = $performance
                    ->finalGradeForAssignment($student, $assignment);

                if ($final > 0 &&$final < $minScore && ! empty($breakdown['rows'])
                ) {
                    $results[$student->id]['student'] = $student;

                    $results[$student->id]['subjects'][] = [
                        'subject' => $assignment->subject,
                        'score'   => round($final, 2),
                    ];
                }
            }
        }

        $breakdown = $performance->riskBreakdown($student,$assignment);
        if (
            $final > 0 &&
            $final < $minScore &&
            ! empty($breakdown['rows'])
        ) {
            $results[$student->id]['student'] = $student;
        
            $results[$student->id]['subjects'][] = [
                'subject'   => $assignment->subject,
                'final'     => round($final, 2),
                'breakdown' => $breakdown['rows'],
            ];
        }

        return view('admin.alerts.critical_subjects', [
            'modality' => $modality,
            'results'  => $results,
        ]);
    }
}