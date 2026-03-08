<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AttendanceJustification;

class TeacherJustificationController extends Controller
{
    public function index() {
        $teacherId = auth()->user()->teacher->id;
    
        $justifications = AttendanceJustification::whereHas(
            'student.attendances.academicSession.teachingAssignment',
            function ($q) use ($teacherId) {
                $q->where('teacher_id', $teacherId);
            }
        )
        ->with([
            'student.user',
            'student.attendances' => function ($q) use ($teacherId) {
                $q->where('status', 'justified')
                  ->whereHas(
                      'academicSession.teachingAssignment',
                      fn ($qa) => $qa->where('teacher_id', $teacherId)
                  );
            },
            'student.attendances.academicSession.teachingAssignment.subject',
            'student.attendances.academicSession.teachingAssignment.group',
        ])
        ->orderByDesc('created_at')
        ->get();
    
        return view(
            'teacher.justifications.index',
            compact('justifications')
        );
    }
}
