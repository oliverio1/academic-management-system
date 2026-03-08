<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TeachingAssignment;
use App\Models\AcademicSession;

class TeacherClassSessionController extends Controller
{
        /**
     * Lista de sesiones de una clase
     */
    public function index(TeachingAssignment $teachingAssignment)
    {
        $teacher = auth()->user()->teacher;

        abort_if(
            $teachingAssignment->teacher_id !== $teacher->id,
            403
        );

        $sessions = AcademicSession::query()
            ->where('teaching_assignment_id', $teachingAssignment->id)
            ->where('is_cancelled', false)
            ->withCount(['attendances', 'sessionActivity'])
            ->orderByDesc('session_date')
            ->get();

        return view('teacher.classes.sessions.index', [
            'assignment' => $teachingAssignment,
            'sessions'   => $sessions,
        ]);
    }
}
