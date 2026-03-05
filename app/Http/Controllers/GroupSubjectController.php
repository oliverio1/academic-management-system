<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Subject;

class GroupSubjectController extends Controller
{
    public function edit(Group $group) {
        $subjects = Subject::where('level_id', $group->level_id)->where('is_active', true)->orderBy('name')->get();
        $assignedSubjects = $group->subjects->pluck('id')->toArray();
        return view('groups.subjects', compact('group','subjects','assignedSubjects'));
    }

    public function update(Group $group) {
        $subjectIds = request()->input('subjects', []);
        $group->subjects()->sync($subjectIds);
        return redirect()->route('groups.index')->with('info', 'Materias asignadas correctamente al grupo');
    }
}