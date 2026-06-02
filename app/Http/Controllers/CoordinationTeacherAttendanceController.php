<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\SchoolCycle;
use App\Models\Schedule;
use App\Models\Teacher;
use App\Models\TeacherCampusAttendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CoordinationTeacherAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $activeCampusId = (int) $request->session()->get('active_campus_id', 0);
        $campusId = (int) ($request->query('campus_id') ?: $activeCampusId);
        $date = $request->query('date') ?: now()->toDateString();
        $targetDate = Carbon::parse($date);

        $campuses = Campus::query()
            ->where('is_active', true)
            ->when($activeCampusId > 0, fn ($q) => $q->where('id', $activeCampusId))
            ->orderBy('name')
            ->get();
        if ($campusId <= 0 && $campuses->isNotEmpty()) {
            $campusId = (int) $campuses->first()->id;
        }
        if ($activeCampusId > 0 && $campusId !== $activeCampusId) {
            abort(404);
        }

        $teachers = Teacher::query()
            ->with('user')
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $records = TeacherCampusAttendance::query()
            ->where('campus_id', $campusId)
            ->whereDate('attendance_date', $targetDate->toDateString())
            ->get()
            ->keyBy('teacher_id');

        $rows = $teachers->map(function (Teacher $teacher) use ($records, $campusId, $targetDate) {
            $firstClassStart = $this->firstClassStartTime($teacher->id, $campusId, $targetDate);
            $record = $records->get((int) $teacher->id);

            $computedStatus = 'pending';
            if ($record && $record->check_in_time) {
                $computedStatus = $record->status;
            } elseif ($firstClassStart && now()->greaterThan($firstClassStart->copy()->addMinutes(10)) && $targetDate->isToday()) {
                $computedStatus = 'absent';
            }

            return [
                'teacher' => $teacher,
                'first_class_start' => $firstClassStart,
                'record' => $record,
                'status' => $computedStatus,
            ];
        })->sortBy(fn ($row) => mb_strtolower($row['teacher']->user->name ?? ''))->values();

        $summary = [
            'on_time' => $rows->where('status', 'on_time')->count(),
            'late' => $rows->where('status', 'late')->count(),
            'absent' => $rows->where('status', 'absent')->count(),
            'pending' => $rows->where('status', 'pending')->count(),
        ];

        return view('coordination.teacher_attendance.index', [
            'campuses' => $campuses,
            'campusId' => $campusId,
            'date' => $targetDate->toDateString(),
            'rows' => $rows,
            'summary' => $summary,
        ]);
    }

    public function storeManual(Request $request)
    {
        $activeCampusId = (int) $request->session()->get('active_campus_id', 0);
        $data = $request->validate([
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            'campus_id' => ['required', 'integer', 'exists:campuses,id'],
            'attendance_date' => ['required', 'date'],
            'status' => ['required', 'in:pending,on_time,late,absent'],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($activeCampusId > 0 && (int) $data['campus_id'] !== $activeCampusId) {
            return back()->withErrors([
                'campus_id' => 'El campus seleccionado no coincide con el campus activo.',
            ]);
        }

        $date = Carbon::parse($data['attendance_date']);
        $firstClassStart = $this->firstClassStartTime((int) $data['teacher_id'], (int) $data['campus_id'], $date);

        TeacherCampusAttendance::query()->updateOrCreate(
            [
                'teacher_id' => (int) $data['teacher_id'],
                'campus_id' => (int) $data['campus_id'],
                'attendance_date' => $date->toDateString(),
            ],
            [
                'first_class_start_time' => $firstClassStart?->format('H:i:s'),
                'status' => $data['status'],
                'check_in_time' => !empty($data['check_in_time']) ? ($data['check_in_time'] . ':00') : null,
                'check_out_time' => !empty($data['check_out_time']) ? ($data['check_out_time'] . ':00') : null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => (int) auth()->id(),
            ]
        );

        return back()->with('info', 'Asistencia docente actualizada manualmente.');
    }

    public function export(Request $request): StreamedResponse
    {
        $activeCampusId = (int) $request->session()->get('active_campus_id', 0);
        $campusId = (int) $request->query('campus_id');
        if ($activeCampusId > 0 && $campusId !== $activeCampusId) {
            abort(404);
        }
        $date = Carbon::parse((string) $request->query('date', now()->toDateString()));

        $rows = TeacherCampusAttendance::query()
            ->with(['teacher.user', 'campus'])
            ->where('campus_id', $campusId)
            ->whereDate('attendance_date', $date->toDateString())
            ->orderBy('teacher_id')
            ->get();

        $filename = 'asistencia_docente_' . $date->format('Ymd') . '_campus_' . $campusId . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Docente', 'Campus', 'Fecha', 'Primera clase', 'Entrada', 'Lat entrada', 'Lng entrada', 'Salida', 'Lat salida', 'Lng salida', 'Estatus', 'Minutos retardo', 'Notas']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->teacher->user->name ?? '-',
                    $row->campus->name ?? '-',
                    optional($row->attendance_date)->format('Y-m-d'),
                    $row->first_class_start_time,
                    $row->check_in_time,
                    $row->check_in_latitude,
                    $row->check_in_longitude,
                    $row->check_out_time,
                    $row->check_out_latitude,
                    $row->check_out_longitude,
                    $row->status,
                    $row->minutes_late,
                    $row->notes,
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function firstClassStartTime(int $teacherId, int $campusId, Carbon $date): ?Carbon
    {
        $cycle = SchoolCycle::query()
            ->where('campus_id', $campusId)
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->first();

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
