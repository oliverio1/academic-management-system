<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TeachingAssignment;
use Illuminate\Support\Facades\DB;

class ActivityCloneController extends Controller
{
    public function clone(Request $request, TeachingAssignment $assignment) {
        $request->validate([
            'to_assignment_id' => 'required|exists:teaching_assignments,id',
            'activity_ids' => 'required|string',
            'due_date' => 'nullable|date',
        ]);
        $ids = explode(',', $request->activity_ids);
        $activities = $assignment->activities()->whereIn('id', $ids)->get();
        $teacherId = auth()->user()->teacher->id;
        abort_if($assignment->teacher_id !== $teacherId, 403);
        $to = TeachingAssignment::where('id', $request->to_assignment_id)->where('teacher_id', $teacherId)->firstOrFail();

        if ($to->activities()->whereHas('grades')->exists()) {
            return back()->withErrors('El grupo destino ya tiene calificaciones.');
        }
        DB::transaction(function () use ($activities, $to, $request) {
            foreach ($activities as $activity) {
                $to->activities()->create([
                    'title' => $activity->title,
                    'description' => $activity->description,
                    'evaluation_criterion_id' => $activity->evaluation_criterion_id,
                    'academic_period_id' => $activity->academic_period_id,
                    'max_score' => $activity->max_score,
                    'due_date' => $request->due_date ?? $activity->due_date,
                ]);
            }
        });
        return back()->with('success', 'Actividades clonadas correctamente.');
    }
}
