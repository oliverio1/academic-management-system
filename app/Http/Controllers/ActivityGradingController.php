<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Grade;
use App\Models\TeachingAssignment;
use Illuminate\Http\Request;

class ActivityGradingController extends Controller
{
    public function show(Activity $activity)
    {
        $activity->load(['evaluationCriterion', 'teachingAssignment.group']);

        $students = $this->studentsForAssignment($activity->teachingAssignment)
            ->get()
            ->sortBy(fn ($student) => mb_strtolower(trim((string) optional($student->user)->name)))
            ->values();

        $activity->teachingAssignment->group->setRelation('students', $students);

        return view('activities.grading.grade', compact('activity'));
    }

    public function store(Request $request, Activity $activity)
    {
        $allowedStudentIds = $this->studentsForAssignment($activity->teachingAssignment)
            ->pluck('students.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ((array) $request->input('grades', []) as $studentId => $grade) {
            if (! in_array((int) $studentId, $allowedStudentIds, true)) {
                continue;
            }

            Grade::updateOrCreate(
                [
                    'activity_id' => $activity->id,
                    'student_id' => $studentId,
                ],
                [
                    'score' => $grade,
                ]
            );
        }

        return redirect()->route('assignments.show', [
            $activity->teachingAssignment,
            'tab' => 'activities',
        ])->with('success', 'Calificaciones guardadas correctamente.');
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

