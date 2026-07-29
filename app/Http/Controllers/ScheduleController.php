<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Schedule;
use App\Models\SchoolCycle;
use App\Models\TeachingAssignment;
use App\Services\AcademicSessionGeneratorService;
use App\Services\CurrentSchoolCycle;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly AcademicSessionGeneratorService $sessionGenerator
    ) {
    }

    public function index(Group $group, TeachingAssignment $assignment)
    {
        abort_if((string) $assignment->tenant_id !== $this->tenantId(), 404);
        $schedules = $assignment->schedules()->where('is_active', true)->get();
        return view('schedules.index', compact('group','assignment','schedules'));
    }

    public function store(Request $request, Group $group, TeachingAssignment $assignment)
    {
        abort_if((string) $assignment->tenant_id !== $this->tenantId(), 404);
        $request->validate([
            'day_of_week' => 'required|string',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
        ]);
        $schedule = Schedule::create([
            'teaching_assignment_id' => $assignment->id,
            'school_cycle_id' => (int) ($assignment->schoolCycleGroup?->school_cycle_id ?: app(CurrentSchoolCycle::class)->id($request->user())),
            'day_of_week' => $request->day_of_week,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'type' => $request->type,
        ]);

        $this->sessionGenerator->generateForSchedule($schedule);

        return back()->with('info', 'Horario agregado correctamente');
    }

    public function deactivate(Schedule $schedule)
    {
        abort_if((string) $schedule->tenant_id !== $this->tenantId(), 404);
        $schedule->update(['is_active' => false]);
        return back()->with('info', 'Horario eliminado');
    }

    private function tenantId(): string
    {
        $tenantId = (string) tenant('id');
        abort_if($tenantId === '', 403, 'Tenant no identificado.');

        return $tenantId;
    }
}
