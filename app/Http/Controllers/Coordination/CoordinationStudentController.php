<?php

namespace App\Http\Controllers\Coordination;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\Attendance;
use App\Models\Grade;
use App\Models\Activity;
use App\Models\StudentFollowUp;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\AcademicCalendarService;
use App\Services\StudentFollowUpFlagService;

class CoordinationStudentController extends Controller
{
    public function index(StudentFollowUpFlagService $flagService) {
        $students = Student::with([
            'user',
            'group',
            'groupHistories',
        ])->get();

        $priorityOrder = [
            'high'   => 0,
            'medium' => 1,
            'low'    => 2,
            'none'   => 3,
        ];

        $students->each(function ($student) use ($flagService) {
            $student->followup_flags = $flagService->flagsFor($student);
            $student->priority = $flagService->priorityFor($student);
            $student->has_active_follow_up =
                $student->followUps()
                    ->where('status', 'open')
                    ->exists();
        });
        
        $students = $students->sortBy(function ($student) use ($priorityOrder) {
            return $priorityOrder[$student->priority] ?? 99;
        })->values();

        return view('admin.students.index', compact('students'));
    }

    public function show(Student $student) {
        $student->load(['group.level', 'group.modality',]);
        return view('admin.students.show', compact('student'));
    }
    
    public function followups(Student $student)
    {
        $followUps = StudentFollowUp::query()
            ->where('student_id', $student->id)
            ->with([
                'requester',
                'teachers.teacher.user',
            ])
            ->latest()
            ->get();
    
        return view('admin.students.partials.followups', compact(
            'student',
            'followUps'
        ));
    }
}