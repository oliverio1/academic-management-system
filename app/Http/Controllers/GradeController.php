<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Grade;
use App\Models\TeachingAssignment;
use App\Services\EconomicActaLockService;
use App\Services\GradeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GradeController extends Controller
{
    public function index(Activity $activity)
    {
        abort_if(
            $activity->assignment->teacher_id !== auth()->user()->teacher->id,
            403
        );

        $students = $this->studentsForAssignment($activity->assignment)->get();
        $grades = $activity->grades()->get()->keyBy('student_id');

        return view('grades.index', compact('activity', 'students', 'grades'));
    }

    public function store(Request $request, Activity $activity, EconomicActaLockService $lockService)
    {
        abort_if(
            $activity->assignment->teacher_id !== auth()->user()->teacher->id,
            403
        );

        abort_if(
            $lockService->isActivityLocked($activity),
            403,
            'El parcial de esta actividad ya tiene acta economica cerrada o enviada. Solo consulta.'
        );

        $data = $request->validate([
            'grades' => 'required|array',
            'grades.*' => 'nullable|numeric|min:0|max:' . $activity->max_score,
        ]);

        $allowedStudentIds = $this->studentsForAssignment($activity->assignment)
            ->pluck('students.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        DB::transaction(function () use ($data, $activity, $allowedStudentIds) {
            foreach ($data['grades'] as $studentId => $score) {
                if (! in_array((int) $studentId, $allowedStudentIds, true)) {
                    continue;
                }

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
                'tab' => 'activities',
            ])
            ->with('success', 'Calificaciones guardadas correctamente.');
    }

    public function myGrades()
    {
        $student = auth()->user()->student;
        return view('grades.student', [
            'student' => $student,
            'averages' => app(GradeService::class)->studentAverages($student),
        ]);
    }

    public function updateInline(Request $request, Grade $grade, EconomicActaLockService $lockService)
    {
        $grade->loadMissing('activity.assignment.schedules');

        abort_if(
            $lockService->isActivityLocked($grade->activity),
            403,
            'El parcial de esta actividad ya tiene acta economica cerrada o enviada. Solo consulta.'
        );

        $data = $request->validate([
            'score' => 'nullable|numeric|min:0|max:10',
            'comments' => 'nullable|string|max:500',
        ]);

        $grade->update($data);
        return response()->json($grade);
    }

    public function massive(TeachingAssignment $assignment)
    {
        $assignment->load([
            'activities' => function ($q) {
                $q->orderBy('due_date');
            },
            'activities.grades',
        ]);

        $students = $this->studentsForAssignment($assignment)->get();
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
        return view('grades.massive', compact('assignment', 'students', 'activities'));
    }

    private function studentsForAssignment(TeachingAssignment $assignment)
    {
        if ($assignment->students()->exists()) {
            return $assignment->students()
                ->where('students.is_active', true)
                ->with('user')
                ->orderBy('students.id');
        }

        return $assignment->group->students()
            ->where('is_active', true)
            ->with('user')
            ->orderBy('id');
    }
}

