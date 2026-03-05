<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Activity;
use App\Models\Grade;
use App\Models\TeamGrade;

class ActivityGradingController extends Controller
{
    public function show(Activity $activity) {
        $activity->load([
            'evaluationCriterion',
            'teachingAssignment.group.students',
            'teachingAssignment.teams.students'
        ]);
        $teamScores = TeamGrade::where('activity_id', $activity->id)->pluck('score', 'team_id');

        return view('activities.grading.grade', compact('activity', 'teamScores'));
    }

    public function store(Request $request, Activity $activity) {
        if ($activity->evaluation_mode === 'individual') {

            foreach ($request->grades as $studentId => $grade) {
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

        } elseif ($activity->evaluation_mode === 'team') {

            foreach ($request->team_grades as $teamId => $grade) {
                TeamGrade::updateOrCreate(
                    [
                        'activity_id' => $activity->id,
                        'team_id' => $teamId,
                    ],
                    [
                        'score' => $grade,
                    ]
                );
            }
        }

        return redirect()->route('assignments.show', [
            $activity->teachingAssignment,
            'tab' => 'activities',
        ])->with('success', 'Calificaciones guardadas correctamente.');
    }
}
