<?php

namespace App\Http\Controllers\Assignments;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Schedule;
use App\Models\TeachingAssignment;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index(Group $group, TeachingAssignment $assignment)
    {
        $schedules = $assignment->schedules()->where('is_active', true)->get();
        return view('schedules.index', compact('group','assignment','schedules'));
    }

    public function store(Request $request, Group $group, TeachingAssignment $assignment)
    {
        $request->validate([
            'day_of_week' => 'required|string',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
        ]);
        Schedule::create([
            'teaching_assignment_id' => $assignment->id,
            'day_of_week' => $request->day_of_week,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'type' => $request->type,
        ]);
        return back()->with('info', 'Horario agregado correctamente');
    }

    public function deactivate(Schedule $schedule)
    {
        $schedule->update(['is_active' => false]);
        return back()->with('info', 'Horario eliminado');
    }
}