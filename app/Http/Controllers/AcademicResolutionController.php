<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AcademicResolutionController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'student_id'             => 'required|exists:students,id',
            'teaching_assignment_id' => 'required|exists:teaching_assignments,id',
            'academic_period_id'     => 'required|exists:academic_periods,id',
            'type'                   => 'required|in:override,repeat_previous,defer_next',
            'value'                  => 'nullable|numeric|min:0|max:10',
            'reason'                 => 'required|string|min:10',
        ]);

        AcademicResolution::create([
            'student_id'             => $request->student_id,
            'teaching_assignment_id' => $request->teaching_assignment_id,
            'academic_period_id'     => $request->academic_period_id,
            'type'                   => $request->type,
            'value'                  => $request->type === 'override'
                                        ? $request->value
                                        : null,
            'reason'                 => $request->reason,
            'resolved_by'            => auth()->id(),
        ]);

        return back()->with('success', 'Resolución académica registrada.');
    }
}
