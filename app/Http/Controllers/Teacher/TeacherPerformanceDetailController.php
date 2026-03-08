<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\TeachingAssignment;
use App\Models\Student;
use App\Models\EvaluationCriterion;
use App\Models\Grade;
use App\Models\Attendance;
use Illuminate\Http\Request;

class TeacherPerformanceDetailController extends Controller
{
    public function show(Request $request,TeachingAssignment $assignment,Student $student) {
        abort_if(
            $assignment->teacher_id !== auth()->user()->teacher->id,403);
        $type = $request->query('type');
        abort_unless(in_array($type, ['attendance', 'criterion', 'final']), 404);
        if ($type === 'attendance') {
            $records = $assignment->academicSessions->flatMap->attendances->where('student_id', $student->id)->sortBy('class_date');
            return view('teacher.performance.detail.attendance',compact('assignment', 'student', 'records'));
        }
        if ($type === 'criterion') {
            $criterionId = $request->query('criterion_id');
            $criterion = EvaluationCriterion::where('id', $criterionId)->where('teaching_assignment_id', $assignment->id)->firstOrFail();
            $activities = $criterion->activities()->with([
                'grades' => fn ($q) =>
                    $q->where('student_id', $student->id)
            ])->get();
            return view('teacher.performance.detail.criterion',compact('assignment','student','criterion','activities')
            );
        }
        if ($type === 'final') {
            $breakdown = app(\App\Services\GradeService::class)
                ->breakdownForStudent($assignment, $student->id);
            return view('teacher.performance.detail.final',compact('assignment', 'student', 'breakdown'));
        }
    }
}
