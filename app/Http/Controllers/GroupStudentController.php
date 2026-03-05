<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\Request;

class GroupStudentController extends Controller
{
    public function edit(Group $group) {
        $students = Student::where('is_active', true)->with('user')->orderBy('id')->get();
        $assigned = $group->students->pluck('id')->toArray();
        return view('groups.students', compact('group','students','assigned'));
    }

    public function update(Request $request, Group $group) {
        $studentIds = $request->input('students', []);
        foreach ($studentIds as $studentId) {
            $student = Student::find($studentId);
            if ($student->group_id !== $group->id) {
                if ($student->group_id) {
                    $student->groupHistory()->create([
                        'group_id' => $student->group_id,
                        'end_date' => now(),
                        'reason' => 'Cambio de grupo',
                    ]);
                }
                $student->update([
                    'group_id' => $group->id,
                ]);
            }
        }
        return redirect()->route('groups.index')->with('info', 'Alumnos asignados correctamente');
    }
}