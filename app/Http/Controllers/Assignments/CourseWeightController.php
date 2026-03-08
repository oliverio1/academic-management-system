<?php

namespace App\Http\Controllers\Assignments;

use App\Http\Controllers\Controller;
use App\Models\TeachingAssignment;
use Illuminate\Http\Request;

class CourseWeightController extends Controller
{
    protected array $activityTypes = [
        'exam' => 'Exámenes',
        'homework' => 'Tareas',
        'project' => 'Proyectos',
        'quiz' => 'Cuestionarios',
    ];

    public function edit($groupId, TeachingAssignment $assignment) {
        $weights = $assignment->weights->pluck('weight', 'activity_type')->toArray();
        return view('weights.edit', ['assignment' => $assignment,'activityTypes' => $this->activityTypes,'weights' => $weights]);
    }

    public function update(Request $request, $groupId, TeachingAssignment $assignment) {
        $total = array_sum($request->weights ?? []);
        if ($total !== 100.0) {
            return back()->withErrors(['weights' => 'La suma de las ponderaciones debe ser 100%'])->withInput();
        }
        foreach ($request->weights as $type => $weight) {
            $assignment->weights()->updateOrCreate(
                ['activity_type' => $type],
                ['weight' => $weight]
            );
        }
        return redirect()->route('groups.assignments.edit', $assignment->group)->with('info', 'Ponderaciones guardadas correctamente');
    }
}