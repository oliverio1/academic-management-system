<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Level;
use App\Services\GradeService;
use App\Services\AcademicPerformanceService;
use App\Http\Requests\GroupRequest;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    public function index() {
        $groups = Group::with('level')->get();
        return view('groups.index', compact('groups'));
    }

    public function create() {
        $levels = Level::orderBy('name')->get();
        return view('groups.create', compact('levels'));
    }

    public function store(GroupRequest $request) {
        Group::create([
            'level_id' => $request->level_id,
            'name' => $request->name,
            'capacity' => $request->capacity,
            'is_active' => true,
        ]);
        return redirect()->route('groups.index')->with('info', 'Grupo creado correctamente');
    }

    public function edit(Group $group) {
        $levels = Level::orderBy('name')->get();
        return view('groups.edit', compact('group', 'levels'));
    }

    public function update(GroupRequest $request, Group $group) {
        $group->update([
            'level_id' => $request->level_id,
            'name' => $request->name,
            'capacity' => $request->capacity,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('groups.index')->with('info', 'Grupo actualizado correctamente');
    }

    public function deactivate(Group $group) {
        $this->authorize('deactivate', $group);
        if (! $group->is_active) {
            return back()->with('info', 'El grupo ya está dado de baja');
        }
        $group->update(['is_active' => false]);
        return back()->with('info', 'Grupo dado de baja correctamente');
    }

    public function activate(Group $group) {
        $this->authorize('activate', $group);
        if ($group->is_active) {
            return back()->with('info', 'El grupo ya está activo');
        }
        $group->update(['is_active' => true]);
        return back()->with('info', 'Grupo activado correctamente');
    }

    public function show(
        Group $group,
        AcademicPerformanceService $performance
    ) {
        $group->load([
            'students.user',
            'assignments.subject',
            'assignments.schedules.attendances'
        ]);
    
        $students = $group->students;
    
        $generalAverage = $students->count()
            ? round(
                $students->avg(
                    fn ($student) =>
                        $performance->studentGeneralAverage($student)
                ),
                2
            )
            : null;
    
        $attendancePercentage = $this->groupAttendancePercentage($group);
    
        return view(
            'groups.show',
            compact(
                'group',
                'students',
                'generalAverage',
                'attendancePercentage'
            )
        );
    }

    protected function groupAttendancePercentage(Group $group): float {
        $attendances = collect();
        foreach ($group->assignments as $assignment) {
            foreach ($assignment->schedules as $schedule) {
                $attendances = $attendances->merge($schedule->attendances);
            }
        }
        if ($attendances->isEmpty()) {
            return 0;
        }
        $present = $attendances->where('status', 'present')->count();
        return round(($present / $attendances->count()) * 100, 2);
    }
}
