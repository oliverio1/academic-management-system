<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentFollowUp;
use Illuminate\Support\Facades\Auth;
use App\Notifications\StudentFollowUpRequested;
use App\Models\TeachingAssignment;
use App\Models\StudentFollowUpTeacher;
use Barryvdh\DomPDF\Facade\Pdf;

class StudentFollowUpController extends Controller
{
    public function index()
    {
        $followUps = StudentFollowUp::with([
                'student.group',
                'teachers',
            ])
            ->latest()
            ->get();

        return view('admin.follow_ups.index', compact('followUps'));
    }

    public function critical()
    {
        $followUps = StudentFollowUp::where('status', 'open')
            ->whereHas('teachers')
            ->whereDoesntHave('teacherResponses')
            ->where('created_at', '<=', now()->subDays(7))
            ->with(['student.user', 'teachers.teacher'])
            ->orderBy('created_at')
            ->get();
        return view('admin.follow_ups.critical', compact('followUps'));
    }

    public function create()
    {
        $students = Student::with('group')->get();

        return view('admin.follow_ups.create', compact('students'));
    }

    /**
     * Guardar solicitud de seguimiento
     */
    public function store(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'message' => 'nullable|string',
        ]);
  
        $exists = StudentFollowUp::where('student_id', $request->student_id)
            ->where('status', 'open')
            ->exists();

        abort_if(
            $exists,
            422,
            'El alumno ya tiene un seguimiento activo.'
        );
    
        // 1️⃣ Crear seguimiento
        $followUp = StudentFollowUp::create([
            'student_id'   => $request->student_id,
            'requested_by' => Auth::id(),
            'type'         => 'mixed',
            'message'      => $request->message,
        ]);
    
        // 2️⃣ Obtener alumno + grupo
        $student = Student::with('group')->findOrFail($request->student_id);
    
        // 3️⃣ Detectar profesores que le dan clase (únicos)
        $teacherIds = TeachingAssignment::where('group_id', $student->group_id)
            ->pluck('teacher_id')
            ->unique();
    
        // 4️⃣ Crear asignaciones + notificar a cada profesor
        foreach ($teacherIds as $teacherId) {
    
            $assignment = $followUp->teachers()->create([
                'teacher_id' => $teacherId,
            ]);

            $assignment->load([
                'followUp.student.user',
            ]);
    
            // 🔔 Notificar al usuario del profesor
            $teacherUser = $assignment->teacher->user;

            if ($teacherUser) {
                $teacherUser->notify(
                    new StudentFollowUpRequested($assignment)
                );
            }
        }
    
        // 5️⃣ Redirigir al detalle
        return redirect()
            ->route('coordination.follow-ups.show', $followUp)
            ->with('success', 'Seguimiento solicitado correctamente.');
    }

    /**
     * Ver detalle y progreso del seguimiento
     */
    public function show(StudentFollowUp $followUp)
    {
        $followUp->load([
            'student.group',
            'teachers.teacher.user',
            'teachers.response',
        ]);

        $total = $followUp->teachers->count();
        $answered = $followUp->teachers
            ->where('status', StudentFollowUpTeacher::STATUS_ANSWERED)
            ->count();

        $progress = $total > 0
            ? round(($answered / $total) * 100)
            : 0;

        return view('admin.follow_ups.show', compact('followUp', 'progress', 'answered', 'total'));
    }

    public function pdf(StudentFollowUp $followUp)
    {
        $followUp->load([
            'student.user',
            'student.group.level',
            'teachers.teacher.user',
            'teachers.response',
            'requester',
        ]);

        $rows = $followUp->teachers->map(function ($assignment) {
            $questionnaire = $assignment->response?->questionnaire ?? [];

            $behavioral = $questionnaire['behavior']
                ?? $questionnaire['behavioral_performance']
                ?? null;

            $academic = $questionnaire['academic']
                ?? $questionnaire['academic_performance']
                ?? null;

            $comments = $assignment->response?->comments
                ?? ($questionnaire['comments'] ?? null);

            return [
                'teacher' => $assignment->teacher?->user?->name ?? 'N/D',
                'behavioral' => $behavioral ?: 'Sin respuesta',
                'academic' => $academic ?: 'Sin respuesta',
                'comments' => $comments ?: 'Sin comentarios',
                'answered_at' => $assignment->answered_at,
            ];
        });

        $pdf = Pdf::loadView('admin.follow_ups.pdf', [
            'followUp' => $followUp,
            'rows' => $rows,
            'generatedAt' => now(),
        ])->setPaper('letter', 'portrait');

        return $pdf->download('seguimiento_'.$followUp->id.'.pdf');
    }
}
