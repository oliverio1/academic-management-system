<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\PrefectDailyAttendance;
use App\Models\AcademicCalendarDay;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrefectGroupAttendanceController extends Controller
{
    public function index()
    {
        $activeCampusId = $this->resolveActiveCampusId();
        if ($activeCampusId <= 0) {
            return view('prefect.groups.index', ['groups' => collect()]);
        }

        $activeCycleIds = SchoolCycle::query()
            ->where('is_active', true)
            ->where(function ($q) use ($activeCampusId) {
                $q->where('campus_id', $activeCampusId)
                    ->orWhereHas('campuses', fn ($campuses) => $campuses->where('campuses.id', $activeCampusId));
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $activeGroupIds = SchoolCycleGroup::query()
            ->whereIn('school_cycle_id', $activeCycleIds)
            ->where('campus_id', $activeCampusId)
            ->where('is_active', true)
            ->pluck('group_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $groups = Group::query()
            ->with(['level.modality'])
            ->whereIn('id', $activeGroupIds)
            ->orderBy('name')
            ->get();

        return view('prefect.groups.index', [
            'groups' => $groups,
        ]);
    }

    public function attendance(Group $group, Request $request)
    {
        $activeCampusId = $this->resolveActiveCampusId();
        abort_if($activeCampusId <= 0, 404);
        $group->load('level.modality');

        $hasGroupInActiveCycle = SchoolCycleGroup::query()
            ->where('group_id', $group->id)
            ->where('is_active', true)
            ->whereHas('schoolCycle', function ($q) use ($activeCampusId) {
                $q->where('is_active', true);
                $this->applyCampusFilterToCycleQuery($q, $activeCampusId);
            })
            ->exists();

        abort_unless($hasGroupInActiveCycle, 404);

        $cycle = SchoolCycle::query()
            ->where('modality_id', $group->level->modality_id)
            ->where('is_active', true)
            ->where(function ($q) use ($activeCampusId) {
                $q->where('campus_id', $activeCampusId)
                    ->orWhereHas('campuses', fn ($campuses) => $campuses->where('campuses.id', $activeCampusId));
            })
            ->orderByDesc('start_date')
            ->first();

        $attendanceDates = collect();
        if ($cycle) {
            $nonWorkingDates = AcademicCalendarDay::query()
                ->whereIn('type', ['holiday', 'vacation'])
                ->whereDate('date', '>=', $cycle->start_date->toDateString())
                ->whereDate('date', '<=', $cycle->end_date->toDateString())
                ->where(function ($q) use ($group) {
                    $q->whereNull('modality_id')
                        ->orWhere('modality_id', (int) $group->level->modality_id);
                })
                ->pluck('date')
                ->map(fn ($date) => Carbon::parse($date)->toDateString())
                ->flip();

            $cursor = $cycle->start_date->copy()->startOfDay();
            $end = $cycle->end_date->copy()->startOfDay();

            while ($cursor->lte($end)) {
                $date = $cursor->toDateString();
                $isWeekend = in_array($cursor->dayOfWeekIso, [6, 7], true);

                if (! $isWeekend && ! $nonWorkingDates->has($date)) {
                    $attendanceDates->push($date);
                }
                $cursor->addDay();
            }
        }

        $registeredDates = PrefectDailyAttendance::query()
            ->where('group_id', $group->id)
            ->whereIn('attendance_date', $attendanceDates)
            ->select('attendance_date')
            ->distinct()
            ->pluck('attendance_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->flip();

        $rows = $attendanceDates->map(function ($date, $index) use ($registeredDates) {
            $asCarbon = Carbon::parse($date);
            $canRegister = $this->canManageAttendanceDate($asCarbon);

            return [
                'num' => $index + 1,
                'date' => $asCarbon,
                'is_registered' => $registeredDates->has($date),
                'can_register' => $canRegister,
                'available_from' => $asCarbon->copy()->setTime(7, 0, 0),
            ];
        });

        return view('prefect.groups.attendance-index', [
            'group' => $group,
            'cycle' => $cycle,
            'rows' => $rows,
        ]);
    }

    public function attendanceDay(Group $group, string $date)
    {
        $this->assertGroupInActiveCampus($group);
        $dateCarbon = Carbon::parse($date);
        $date = $dateCarbon->toDateString();

        if (! $this->canManageAttendanceDate($dateCarbon)) {
            return redirect()
                ->route('prefect.groups.attendance', ['group' => $group->id])
                ->with('warning', 'La asistencia de este día se habilita a partir de las 07:00 del mismo día.');
        }

        $students = $group->students()
            ->where('is_active', true)
            ->join('users', 'users.id', '=', 'students.user_id')
            ->orderBy('users.name')
            ->select('students.*')
            ->with('user')
            ->get();

        $existing = PrefectDailyAttendance::query()
            ->where('group_id', $group->id)
            ->whereDate('attendance_date', $date)
            ->get()
            ->keyBy('student_id');

        return view('prefect.groups.attendance', [
            'group' => $group,
            'students' => $students,
            'date' => $date,
            'existing' => $existing,
        ]);
    }

    public function store(Group $group, Request $request)
    {
        $this->assertGroupInActiveCampus($group);
        $data = $request->validate([
            'attendance_date' => ['required', 'date'],
            'attendance' => ['required', 'array'],
            'attendance.*' => ['required', 'in:present,absent'],
        ]);

        $dateCarbon = Carbon::parse($data['attendance_date']);
        $date = $dateCarbon->toDateString();

        if (! $this->canManageAttendanceDate($dateCarbon)) {
            return redirect()
                ->route('prefect.groups.attendance', ['group' => $group->id])
                ->with('warning', 'No puedes registrar asistencia antes del día correspondiente o antes de las 07:00.');
        }

        $validStudentIds = $group->students()
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($data, $group, $date, $validStudentIds) {
            foreach ($data['attendance'] as $studentId => $status) {
                if (! in_array((int) $studentId, $validStudentIds, true)) {
                    continue;
                }

                PrefectDailyAttendance::updateOrCreate(
                    [
                        'group_id' => $group->id,
                        'student_id' => $studentId,
                        'attendance_date' => $date,
                    ],
                    [
                        'status' => $status,
                        'recorded_by' => auth()->id(),
                    ]
                );
            }
        });

        return redirect()
            ->route('prefect.groups.attendance', ['group' => $group->id])
            ->with('info', 'Asistencia global guardada correctamente.');
    }

    private function assertGroupInActiveCampus(Group $group): void
    {
        $activeCampusId = $this->resolveActiveCampusId();
        abort_if($activeCampusId <= 0, 404);

        $belongs = SchoolCycleGroup::query()
            ->where('group_id', (int) $group->id)
            ->where('is_active', true)
            ->whereHas('schoolCycle', function ($q) use ($activeCampusId) {
                $q->where('is_active', true);
                $this->applyCampusFilterToCycleQuery($q, $activeCampusId);
            })
            ->exists();

        abort_unless($belongs, 404);
    }

    private function resolveActiveCampusId(): int
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        $user = auth()->user();
        $allowedCampusIds = $user
            ? $user->campuses()->pluck('campuses.id')->map(fn ($id) => (int) $id)->all()
            : [];

        if ($activeCampusId <= 0 || ! in_array($activeCampusId, $allowedCampusIds, true)) {
            $activeCampusId = (int) ($allowedCampusIds[0] ?? 0);
            if ($activeCampusId > 0) {
                session(['active_campus_id' => $activeCampusId]);
            }
        }

        return $activeCampusId;
    }

    private function canManageAttendanceDate(Carbon $date): bool
    {
        $now = now();
        $targetDate = $date->toDateString();
        $today = $now->toDateString();

        if ($targetDate < $today) {
            return true;
        }

        if ($targetDate > $today) {
            return false;
        }

        $openAt = $date->copy()->setTime(7, 0, 0);
        return $now->greaterThanOrEqualTo($openAt);
    }

    private function applyCampusFilterToCycleQuery($query, int $activeCampusId): void
    {
        $query->where(function ($nested) use ($activeCampusId) {
            $nested->where('campus_id', $activeCampusId)
                ->orWhereHas('campuses', fn ($campuses) => $campuses->where('campuses.id', $activeCampusId));
        });
    }
}
