<?php

namespace App\Http\Controllers;

use App\Models\PrefectDailyAttendance;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Services\CurrentSchoolCycle;
use Illuminate\Support\Facades\DB;

class CoordinationAttendanceRiskController extends Controller
{
    public function index()
    {
        $fromDate = now()->subDays(7);
        $minAbsences = 3;
        $activeCampusId = $this->resolveActiveCampusId();
        if ($activeCampusId <= 0) {
            return view('coordination.students.attendance-risk', ['students' => collect()]);
        }

        $activeCycleId = app(CurrentSchoolCycle::class)->id(auth()->user(), $activeCampusId);

        $activeGroupIds = SchoolCycleGroup::query()
            ->when($activeCycleId, fn ($query) => $query->where('school_cycle_id', (int) $activeCycleId), fn ($query) => $query->whereRaw('1 = 0'))
            ->where('campus_id', $activeCampusId)
            ->where('is_active', true)
            ->pluck('group_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $students = PrefectDailyAttendance::query()
            ->join('students', 'students.id', '=', 'prefect_daily_attendances.student_id')
            ->join('users', 'users.id', '=', 'students.user_id')
            ->leftJoin('groups', 'groups.id', '=', 'students.group_id')
            ->where('prefect_daily_attendances.status', 'absent')
            ->whereDate('prefect_daily_attendances.attendance_date', '>=', $fromDate->toDateString())
            ->when(
                ! empty($activeGroupIds),
                fn ($q) => $q->whereIn('prefect_daily_attendances.group_id', $activeGroupIds),
                fn ($q) => $q->whereRaw('1 = 0')
            )
            ->select(
                'prefect_daily_attendances.student_id',
                DB::raw('COUNT(*) as absences'),
                DB::raw('MAX(prefect_daily_attendances.attendance_date) as last_absence'),
                DB::raw('MAX(users.name) as student_name'),
                DB::raw('MAX(groups.name) as group_name')
            )
            ->groupBy('prefect_daily_attendances.student_id')
            ->having('absences', '>=', $minAbsences)
            ->orderByDesc('absences')
            ->get();

        return view('coordination.students.attendance-risk', compact('students'));
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
}
