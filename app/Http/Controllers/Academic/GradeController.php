<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Activity;
use App\Models\Grade;
use App\Models\TeachingAssignment;
use Illuminate\Support\Facades\DB;

class GradeController extends Controller
{
    public function index(Activity $activity)
    {
        // 🔒 Seguridad
        abort_if(
            $activity->assignment->teacher_id !== auth()->user()->teacher->id,
            403
        );
    
        $group = $activity->assignment->group;
    
        $students = $group->students()
            ->where('is_active', true)
            ->get();
    
        $grades = $activity->grades()
            ->get()
            ->keyBy('student_id');
    
        return view(
            'grades.index',
            compact('activity', 'students', 'grades')
        );
    }

    public function store(Request $request, Activity $activity)
    {
        // 🔒 Seguridad
        abort_if(
            $activity->assignment->teacher_id !== auth()->user()->teacher->id,
            403
        );
    
        // 📥 Validación base
        $data = $request->validate([
            'grades' => 'required|array',
            'grades.*' => 'nullable|numeric|min:0|max:' . $activity->max_score,
        ]);
    
        DB::transaction(function () use ($data, $activity) {
            foreach ($data['grades'] as $studentId => $score) {
    
                // Permite dejar en blanco (no calificado aún)
                if ($score === null || $score === '') {
                    continue;
                }
    
                Grade::updateOrCreate(
                    [
                        'activity_id' => $activity->id,
                        'student_id'  => $studentId,
                    ],
                    [
                        'score' => $score,
                    ]
                );
            }
        });
    
        return redirect()
        ->route('assignments.show', [
            $activity->assignment,
            'tab' => 'activities'
        ])
        ->with('success', 'Calificaciones guardadas correctamente.');
    }   

    public function myGrades() {
        $student = auth()->user()->student;
        return view('grades.student', ['student' => $student,'averages' => app(GradeService::class)->studentAverages($student)]);
    }

    public function updateInline(Request $request, Grade $grade) {
        $data = $request->validate([
            'score' => 'nullable|numeric|min:0|max:10',
            'comments' => 'nullable|string|max:500',
        ]);
        $grade->update($data);
        return response()->json($grade);
    }

    public function massive(TeachingAssignment $assignment) {
        $assignment->load(['group.students.user','activities' => function ($q) {$q->orderBy('due_date');},'activities.grades']);
        $students = $assignment->group->students;
        $activities = $assignment->activities;
        DB::transaction(function () use ($students, $activities) {
            foreach ($activities as $activity) {
                $existingGrades = $activity->grades->keyBy('student_id');
                foreach ($students as $student) {
                    if (! $existingGrades->has($student->id)) {
                        Grade::create([
                            'activity_id' => $activity->id,
                            'student_id'  => $student->id,
                            'score'       => 10,
                            'comments'    => null,
                        ]);
                    }
                }
            }
        });
        $assignment->load('activities.grades');
        return view('grades.massive', compact('assignment','students','activities'));
    }
}
