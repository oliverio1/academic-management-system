<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\TeachingAssignment;
use App\Services\TeacherPerformanceService;

class TeacherPerformanceController extends Controller
{
    public function index() {
        $assignments = auth()->user()->teacher->teachingAssignments()->with('group', 'subject')->get();
        return view('teacher.performance.select',compact('assignments'));
    }

    public function show(TeachingAssignment $assignment,TeacherPerformanceService $service) {
        abort_if(
            $assignment->teacher_id !== auth()->user()->teacher->id,
            403
        );
        $rows = $service->buildTable($assignment);
        return view('teacher.performance.table',compact('assignment', 'rows'));
    }
}
