<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\StudentFollowUpTeacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Notifications\StudentFollowUpCompleted;
use App\Models\User;
use App\Models\StudentFollowUp;
use App\Models\StudentFollowUpResponse;
use Illuminate\Support\Facades\DB;

class TeacherFollowUpController extends Controller
{
    public function index(Request $request)
    {
        $teacher = auth()->user()->teacher;

        /**
         * 🟥 Seguimientos pendientes (NO contestados)
         */
        $pendingFollowUps = StudentFollowUpTeacher::where('teacher_id', $teacher->id)
            ->whereNull('answered_at')
            ->with([
                'studentFollowUp.student.user',
                'studentFollowUp.student.group',
            ])
            ->orderBy('created_at')
            ->get();
    
        /**
         * 🟦 Historial (SOLO contestados)
         */
        $answeredFollowUps = StudentFollowUpTeacher::where('teacher_id', $teacher->id)
            ->whereNotNull('answered_at')
            ->with([
                'studentFollowUp.student.user',
                'studentFollowUp.student.group',
                'response',
            ])
            ->orderByDesc('answered_at')
            ->get();
    
        return view('teacher.follow-ups.index', [
            'pendingFollowUps'      => $pendingFollowUps,
            'answeredFollowUps' => $answeredFollowUps,
        ]);
    }

    /**
     * Mostrar formulario para contestar seguimiento
     */
    public function show(StudentFollowUpTeacher $followUpTeacher)
    {
        $teacher = auth()->user()->teacher;
    
        // 🔒 Blindaje: solo el profesor asignado puede ver esto
        abort_unless(
            $followUpTeacher->teacher_id === $teacher->id,
            403
        );
    
        // Cargar todo el contexto necesario
        $followUpTeacher->load([
            'studentFollowUp.student.user',
            'studentFollowUp.student.group',
            'response',
        ]);
    
        return view('teacher.follow-ups.show', [
            'assignment' => $followUpTeacher,
        ]);
    }

    /**
     * Guardar respuesta del profesor
     */
    public function store(Request $request, StudentFollowUpTeacher $assignment)
    {
        abort_if(
            $assignment->teacher_id !== Auth::user()->teacher->id,
            403
        );

        abort_if(
            $assignment->status === StudentFollowUpTeacher::STATUS_ANSWERED,
            403
        );

        $request->validate([
            'questionnaire.academic_performance' => 'required|string',
            'questionnaire.behavioral_performance' => 'required|string',
            'comments' => 'nullable|string',
        ]);

        // 1. Guardar respuesta
        $assignment->response()->create([
            'questionnaire' => $request->questionnaire,
            'comments' => $request->comments,
        ]);

        $assignment->update([
            'status' => StudentFollowUpTeacher::STATUS_ANSWERED,
            'answered_at' => now(),
        ]);

        $followUp = $assignment->followUp;
        $followUp->checkAndCloseIfCompleted();

        $coordinator = User::find($followUp->requested_by);

        if ($coordinator) {
            $coordinator->notify(
                new StudentFollowUpCompleted(
                    $followUp->load('student.user')
                )
            );
        }

        return redirect()
            ->route('teacher.follow-ups.index')
            ->with('success', 'Seguimiento enviado correctamente.');
    }

    public function respond(Request $request, StudentFollowUpTeacher $followUpTeacher) {
        $teacher = auth()->user()->teacher;

        // 🔒 Blindaje
        abort_unless(
            $followUpTeacher->teacher_id === $teacher->id,
            403
        );
    
        abort_if(
            $followUpTeacher->answered_at !== null,
            403
        );
    
        // ✅ Validación
        $data = $request->validate([
            'behavior' => 'required|string',
            'academic' => 'required|string',
            'comments' => 'nullable|string',
        ]);
    
        DB::transaction(function () use ($data, $followUpTeacher) {
    
            // 🧾 Guardar respuesta como JSON
            StudentFollowUpResponse::create([
                'student_follow_up_teacher_id' => $followUpTeacher->id,
                'questionnaire' => [
                    'behavior' => $data['behavior'],
                    'academic' => $data['academic'],
                    'comments' => $data['comments'],
                ],
                'comments' => null, // ya no se usa separado
            ]);
    
            // ⏱ Marcar como respondido
            $followUpTeacher->update([
                'answered_at' => now(),
                'status' => 'answered',
            ]);
        });
    
        return redirect()
            ->route('teacher.follow-ups.index')
            ->with('success', 'Seguimiento contestado correctamente.');
    }
}
