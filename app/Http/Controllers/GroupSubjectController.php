<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\SchoolCycleGroup;
use App\Models\Subject;

class GroupSubjectController extends Controller
{
    public function edit(Group $group) {
        $subjects = Subject::where('level_id', $group->level_id)->where('is_active', true)->orderBy('name')->get();
        $assignedSubjects = $group->subjects->pluck('id')->toArray();
        return view('groups.subjects', compact('group','subjects','assignedSubjects'));
    }

    public function update(Group $group) {
        $subjectIds = collect(request()->input('subjects', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $group->subjects()->sync($subjectIds);

        $activeCampusId = (int) session('active_campus_id', 0);

        $cycleGroups = SchoolCycleGroup::query()
            ->where('group_id', $group->id)
            ->where('is_active', true)
            ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
            ->get();

        foreach ($cycleGroups as $cycleGroup) {
            $cycleGroup->subjects()->sync($subjectIds);
        }

        return redirect()->route('groups.index')->with('info', 'Materias asignadas correctamente al grupo');
    }
}
