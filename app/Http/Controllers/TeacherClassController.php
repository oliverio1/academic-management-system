<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TeachingAssignment;
use App\Models\AcademicSession;

class TeacherClassController extends Controller
{
    public function index()
    {
        $teacher = auth()->user()->teacher;

        $assignments = TeachingAssignment::with(['subject', 'group'])
            ->where('teacher_id', $teacher->id)
            ->orderBy('subject_id')
            ->get();

        $schedules = TeachingAssignment::where('teacher_id', $teacher->id)
            ->with([
                'schedules.assignment.subject', // 👈 CLAVE
                'schedules.assignment.group',
            ])
            ->get()
            ->pluck('schedules')
            ->flatten();;

        return view('teacher.classes.index', [
            'assignments' => $assignments,
            'schedules' => $schedules,
        ]);
    }

    public function calendar()
    {
        $teacher = auth()->user()->teacher;

        $sessions = AcademicSession::query()
            ->whereHas('teachingAssignment', function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id);
            })
            ->whereBetween('session_date', [
                now()->startOfWeek(),
                now()->endOfWeek(),
            ])
            ->where('is_cancelled', false)
            ->with([
                'teachingAssignment.subject:id,name',
                'teachingAssignment.group:id,name',
            ])
            ->withCount(['attendances', 'sessionActivity'])
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        return view('teacher.classes.calendar', [
            'sessions' => $sessions,
        ]);
    }

    public function show(TeachingAssignment $teachingAssignment)
    {
        $teacher = auth()->user()->teacher;

        abort_if(
            $teachingAssignment->teacher_id !== $teacher->id,
            403
        );

        return view('teacher.classes.show', [
            'assignment' => $teachingAssignment,
        ]);
    }
}
