<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Practice;
use App\Models\PracticeSubmission;
use App\Models\Team;

class StudentPracticeController extends Controller
{
    public function index() {
        $student = auth()->user()->student;
        $practices = Practice::whereHas('teachingAssignment.teams.students', function ($q) use ($student) {
            $q->where('students.id', $student->id);
        })
        ->with('teachingAssignment.subject')
        ->get();
        return view('student.practices.index', compact('practices'));
    }

    public function show(Practice $practice) {
        $student = auth()->user()->student;
        $team = Team::where('teaching_assignment_id', $practice->teaching_assignment_id)
            ->whereHas('students', fn ($q) =>
                $q->where('student_id', $student->id)
            )->firstOrFail();
        $submission = PracticeSubmission::firstOrCreate(
            [
                'practice_id' => $practice->id,
                'team_id' => $team->id,
            ],
            [
                'submitted_by' => auth()->id(),
                'status' => 'draft',
            ]
        );
        return view('student.practices.show', compact('practice', 'submission', 'team'));
    }

    public function store(Request $request, Practice $practice) {
        $request->validate([
            'questionnaire_answers' => 'nullable|array',
            'theoretical_framework' => 'nullable|string',
            'objectives' => 'nullable|string',
            'hypothesis' => 'nullable|string',
            'development' => 'nullable|string',
            'results' => 'nullable|string',
            'conclusions' => 'nullable|string',
            'references' => 'nullable|string',
        ]);
        $submission = PracticeSubmission::where('practice_id', $practice->id)
            ->where('team_id', $request->team_id)
            ->firstOrFail();

        if ($submission->status !== 'draft') {
            abort(403, 'La práctica ya fue enviada.');
        }
        $submission->update([
            ...$request->except('team_id'),
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);
        return redirect()->route('student.practices.index')->with('success', 'Práctica enviada correctamente.');
    }
}
