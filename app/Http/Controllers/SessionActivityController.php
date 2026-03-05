<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AcademicSession;
use App\Models\SessionActivity;

class SessionActivityController extends Controller
{
    public function create(AcademicSession $academicSession) {
        $teacher = auth()->user()->teacher;

        abort_if(
            $academicSession->teachingAssignment->teacher_id !== $teacher->id,
            403
        );

        abort_if(
            ! is_null($academicSession->attendance_closed_at),
            403,
            'La semana académica ya está cerrada.'
        );

        return view('session_activities.create', [
            'session' => $academicSession,
            'activity' => $academicSession->sessionActivity,
        ]);
    }

    public function store(Request $request, AcademicSession $academicSession) {
        $teacher = auth()->user()->teacher;

        abort_if(
            $academicSession->teachingAssignment->teacher_id !== $teacher->id,
            403
        );

        abort_if(
            ! is_null($academicSession->attendance_closed_at),
            403
        );

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        SessionActivity::updateOrCreate(
            ['academic_session_id' => $academicSession->id],
            $data
        );

        return redirect()
            ->route('dashboard')
            ->with('success', 'Actividad registrada correctamente.');
    }
}
