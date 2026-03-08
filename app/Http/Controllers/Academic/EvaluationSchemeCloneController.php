<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\TeachingAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class EvaluationSchemeCloneController extends Controller
{
    public function clone(
        Request $request,
        TeachingAssignment $teachingAssignment
    ) {
        abort_if(
            $teachingAssignment->hasGrades(),
            403,
            'No puedes modificar criterios con calificaciones registradas.'
        );
    
        abort_if(
            $teachingAssignment->evaluationCriteria()->exists(),
            403,
            'Esta asignación ya tiene criterios definidos.'
        );
    
        $data = $request->validate([
            'source_assignment_id' => 'required|exists:teaching_assignments,id',
        ]);
    
        $source = TeachingAssignment::with('evaluationCriteria')
            ->where('teacher_id', auth()->user()->teacher->id)
            ->findOrFail($data['source_assignment_id']);
    
        \DB::transaction(function () use ($source, $teachingAssignment) {
            foreach ($source->evaluationCriteria as $criterion) {
                $teachingAssignment->evaluationCriteria()->create([
                    'name' => $criterion->name,
                    'percentage' => $criterion->percentage,
                ]);
            }
        });
    
        return redirect()
            ->route('teacher.assignments.show', [
                $teachingAssignment,
                'tab' => 'evaluation',
            ])
            ->with('success', 'Criterios clonados correctamente.');
    }
}