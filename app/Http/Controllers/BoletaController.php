<?php

namespace App\Http\Controllers;

use App\Models\TeachingAssignment;
use App\Models\Student;
use App\Services\GradeService;
use Barryvdh\Snappy\Facades\SnappyPdf;

class BoletaController extends Controller
{
    public function show(
        TeachingAssignment $teachingAssignment,
        Student $student,
        GradeService $gradeService
    ) {
        // Seguridad básica
        abort_if(
            $teachingAssignment->teacher_id !== auth()->user()->teacher->id,
            403
        );

        $breakdown = $gradeService->breakdown(
            $teachingAssignment,
            $student
        );

        return view('boletas.show', compact(
            'teachingAssignment',
            'student',
            'breakdown'
        ));
    }

    public function pdf(
        TeachingAssignment $teachingAssignment,
        Student $student,
        GradeService $gradeService
    ) {
        abort_if(
            $teachingAssignment->teacher_id !== auth()->user()->teacher->id,
            403
        );

        $breakdown = $gradeService->breakdown(
            $teachingAssignment,
            $student
        );

        return SnappyPdf::loadView('boletas.show', compact(
            'teachingAssignment',
            'student',
            'breakdown'
        ))
        ->setPaper('letter')
        ->setOption('encoding', 'UTF-8')
        ->setOption('enable-local-file-access', true)
        ->setOption('footer-right', 'Página [page] de [toPage]')
        ->download('boleta_'.$student->enrollment_number.'.pdf');
    }
}