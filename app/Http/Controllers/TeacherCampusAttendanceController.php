<?php

namespace App\Http\Controllers;

use App\Models\SchoolCycle;
use App\Models\Schedule;
use App\Models\TeacherCampusAttendance;
use App\Services\CurrentSchoolCycle;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TeacherCampusAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $teacher = $request->user()?->teacher;
        abort_if(! $teacher, 403);

        $activeCampusId = (int) $request->session()->get('active_campus_id', 0);
        abort_if($activeCampusId <= 0, 422, 'Selecciona un campus activo.');

        $today = now()->toDateString();
        $firstClassStart = $this->firstClassStartTime($teacher->id, $activeCampusId, now());

        $todayRecord = TeacherCampusAttendance::query()
            ->where('teacher_id', $teacher->id)
            ->where('campus_id', $activeCampusId)
            ->whereDate('attendance_date', $today)
            ->first();

        $history = TeacherCampusAttendance::query()
            ->with('campus')
            ->where('teacher_id', $teacher->id)
            ->orderByDesc('attendance_date')
            ->limit(15)
            ->get();

        return view('teacher.attendance.index', [
            'todayRecord' => $todayRecord,
            'firstClassStart' => $firstClassStart,
            'canClockAttendance' => (bool) $firstClassStart,
            'history' => $history,
        ]);
    }

    public function clock(Request $request)
    {
        $teacher = $request->user()?->teacher;
        abort_if(! $teacher, 403);

        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $activeCampusId = (int) $request->session()->get('active_campus_id', 0);
        abort_if($activeCampusId <= 0, 422, 'Selecciona un campus activo.');

        $now = now();
        $today = $now->toDateString();
        $firstClassStart = $this->firstClassStartTime($teacher->id, $activeCampusId, $now);

        if (! $firstClassStart) {
            return back()->with('warning', 'No tienes sesiones programadas hoy en este plantel.');
        }

        $record = TeacherCampusAttendance::query()->firstOrCreate(
            [
                'teacher_id' => $teacher->id,
                'campus_id' => $activeCampusId,
                'attendance_date' => $today,
            ],
            [
                'first_class_start_time' => $firstClassStart?->format('H:i:s'),
                'status' => 'pending',
                'recorded_by' => (int) $request->user()->id,
            ]
        );

        if (! $record->check_in_time) {
            $toleranceMinutes = 10;
            $status = 'on_time';
            $minutesLate = 0;

            if ($firstClassStart) {
                $allowed = $firstClassStart->copy()->addMinutes($toleranceMinutes);
                if ($now->greaterThan($allowed)) {
                    $status = 'late';
                    $minutesLate = max(0, $firstClassStart->diffInMinutes($now));
                }
            }

            $record->update([
                'first_class_start_time' => $record->first_class_start_time ?: $firstClassStart?->format('H:i:s'),
                'check_in_time' => $now->format('H:i:s'),
                'check_in_latitude' => $data['latitude'] ?? null,
                'check_in_longitude' => $data['longitude'] ?? null,
                'status' => $status,
                'minutes_late' => $minutesLate,
                'recorded_by' => (int) $request->user()->id,
            ]);

            return back()->with('info', 'Entrada registrada correctamente.');
        }

        if (! $record->check_out_time) {
            $record->update([
                'check_out_time' => $now->format('H:i:s'),
                'check_out_latitude' => $data['latitude'] ?? null,
                'check_out_longitude' => $data['longitude'] ?? null,
                'recorded_by' => (int) $request->user()->id,
            ]);

            return back()->with('info', 'Salida registrada correctamente.');
        }

        return back()->with('warning', 'Ya tienes entrada y salida registradas hoy.');
    }

    private function firstClassStartTime(int $teacherId, int $campusId, Carbon $date): ?Carbon
    {
        $cycle = app(CurrentSchoolCycle::class)->get(auth()->user(), $campusId);

        if (! $cycle) {
            return null;
        }

        $dayKey = strtolower($date->englishDayOfWeek);

        $firstTime = Schedule::query()
            ->join('teaching_assignments', 'teaching_assignments.id', '=', 'schedules.teaching_assignment_id')
            ->where('teaching_assignments.teacher_id', $teacherId)
            ->where('teaching_assignments.is_active', true)
            ->where('schedules.school_cycle_id', $cycle->id)
            ->where('schedules.is_active', true)
            ->where('schedules.day_of_week', $dayKey)
            ->orderBy('schedules.start_time')
            ->value('schedules.start_time');

        return $firstTime ? Carbon::parse($date->toDateString() . ' ' . $firstTime) : null;
    }
}
