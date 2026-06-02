<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Student;
use App\Models\StudentSuspension;
use App\Models\User;
use App\Notifications\StudentSuspensionNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CoordinationStudentSuspensionController extends Controller
{
    public function index()
    {
        $allowedGroupIds = $this->activeCampusGroupIds();

        $suspensions = StudentSuspension::query()
            ->with(['group', 'student.user', 'creator'])
            ->when(!empty($allowedGroupIds), fn ($q) => $q->whereIn('group_id', $allowedGroupIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        return view('coordination.suspensions.index', [
            'suspensions' => $suspensions,
        ]);
    }

    public function create()
    {
        return view('coordination.suspensions.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        DB::transaction(function () use ($data) {
            $suspension = StudentSuspension::create($data + [
                'created_by' => auth()->id(),
            ]);

            $this->applyAbsences($suspension);
            $this->dispatchNotices($suspension, 'registrada');
        });

        return redirect()
            ->route('coordination.suspensions.index')
            ->with('info', 'Suspension registrada correctamente.');
    }

    public function edit(StudentSuspension $suspension)
    {
        $this->assertSuspensionInActiveCampus($suspension);
        return view('coordination.suspensions.edit', array_merge(
            $this->formData($suspension->group_id),
            ['suspension' => $suspension]
        ));
    }

    public function update(Request $request, StudentSuspension $suspension)
    {
        $this->assertSuspensionInActiveCampus($suspension);
        $data = $this->validatedData($request);

        DB::transaction(function () use ($data, $suspension) {
            $this->releaseAbsenceLocks($suspension);
            $suspension->update($data);
            $this->applyAbsences($suspension->fresh());
            $this->dispatchNotices($suspension->fresh(), 'actualizada');
        });

        return redirect()
            ->route('coordination.suspensions.index')
            ->with('info', 'Suspension actualizada correctamente.');
    }

    public function destroy(StudentSuspension $suspension)
    {
        $this->assertSuspensionInActiveCampus($suspension);
        DB::transaction(function () use ($suspension) {
            $this->releaseAbsenceLocks($suspension);
            $suspension->delete();
        });

        return redirect()
            ->route('coordination.suspensions.index')
            ->with('info', 'Suspension eliminada.');
    }

    private function formData(?int $forceIncludeGroupId = null): array
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        $activeCycle = SchoolCycle::query()
            ->where('is_active', true)
            ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
            ->orderByDesc('start_date')
            ->first();

        $activeGroupIds = collect();
        if ($activeCycle) {
            $activeGroupIds = SchoolCycleGroup::query()
                ->where('school_cycle_id', $activeCycle->id)
                ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
                ->where('is_active', true)
                ->pluck('group_id');
        }

        $groups = Group::query()
            ->where('is_active', true)
            ->when(
                $activeGroupIds->isNotEmpty(),
                fn ($q) => $q->where(function ($inner) use ($activeGroupIds, $forceIncludeGroupId) {
                    $inner->whereIn('id', $activeGroupIds->all());
                    if ($forceIncludeGroupId) {
                        $inner->orWhere('id', $forceIncludeGroupId);
                    }
                }),
                fn ($q) => $q->whereRaw('1 = 0')
            )
            ->with(['students' => function ($q) {
                $q->where('is_active', true)
                  ->with('user')
                  ->join('users', 'users.id', '=', 'students.user_id')
                  ->orderBy('users.name')
                  ->select('students.*');
            }])
            ->orderBy('name')
            ->get();

        return [
            'groups' => $groups,
            'activeCycle' => $activeCycle,
        ];
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'group_id' => ['required', 'integer', 'exists:groups,id'],
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:3000'],
        ]);

        $belongs = Student::query()
            ->whereKey($data['student_id'])
            ->where('group_id', $data['group_id'])
            ->exists();

        abort_unless($belongs, 422, 'El alumno no pertenece al grupo seleccionado.');

        $allowedGroupIds = $this->activeCampusGroupIds();
        abort_unless(in_array((int) $data['group_id'], $allowedGroupIds, true), 422, 'El grupo seleccionado no pertenece al campus activo.');

        return $data;
    }

    private function applyAbsences(StudentSuspension $suspension): void
    {
        $sessions = AcademicSession::query()
            ->whereHas('teachingAssignment', fn ($q) => $q->where('group_id', $suspension->group_id))
            ->where('is_cancelled', false)
            ->whereBetween('session_date', [
                Carbon::parse($suspension->start_date)->toDateString(),
                Carbon::parse($suspension->end_date)->toDateString(),
            ])
            ->get(['id']);

        foreach ($sessions as $session) {
            $attendance = Attendance::firstOrNew([
                'academic_session_id' => $session->id,
                'student_id' => $suspension->student_id,
            ]);

            if (
                $attendance->exists
                && $attendance->status === 'justified'
                && (int) ($attendance->student_suspension_id ?? 0) !== (int) $suspension->id
            ) {
                continue;
            }

            $attendance->status = 'absent';
            $attendance->student_suspension_id = $suspension->id;
            $attendance->is_suspension_locked = true;
            $attendance->save();
        }
    }

    private function releaseAbsenceLocks(StudentSuspension $suspension): void
    {
        Attendance::query()
            ->where('student_suspension_id', $suspension->id)
            ->update([
                'student_suspension_id' => null,
                'is_suspension_locked' => false,
            ]);
    }

    private function dispatchNotices(StudentSuspension $suspension, string $action): void
    {
        $suspension->loadMissing(['student.user', 'group']);

        $teacherUsers = User::query()
            ->whereHas('teacher.assignments', fn ($q) => $q->where('group_id', $suspension->group_id))
            ->get();

        $prefectUsers = User::role('prefect')->get();

        $tutorUsers = User::query()
            ->whereIn('id', array_filter([
                $suspension->student->guardian_user_id,
            ]))
            ->get();

        $recipients = $teacherUsers
            ->concat($prefectUsers)
            ->concat($tutorUsers)
            ->unique('id')
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        $studentName = $suspension->student->user->name ?? 'Alumno';
        $groupName = $suspension->group->name ?? 'N/D';
        $from = Carbon::parse($suspension->start_date)->format('d/m/Y');
        $to = Carbon::parse($suspension->end_date)->format('d/m/Y');

        $title = "Suspension {$action}: {$studentName}";
        $message = "Se {$action} la suspension de {$studentName} ({$groupName}) del {$from} al {$to}.";

        foreach ($recipients as $recipient) {
            $url = route('dashboard');

            if ($recipient->hasRole('teacher')) {
                $url = route('teacher.students.show', $suspension->student_id);
            } elseif ($recipient->hasRole('prefect')) {
                $url = route('prefect.groups.attendance', $suspension->group_id);
            } elseif ($recipient->hasAnyRole(['guardian', 'tutor'])) {
                $url = route('tutor.subjects');
            }

            $recipient->notify(new StudentSuspensionNotification([
                'title' => $title,
                'message' => $message,
                'student_id' => $suspension->student_id,
                'group_id' => $suspension->group_id,
                'start_date' => $suspension->start_date->toDateString(),
                'end_date' => $suspension->end_date->toDateString(),
                'url' => $url,
            ]));
        }

        $announcement = Announcement::create([
            'title' => $title,
            'body' => $message . "\nMotivo: " . $suspension->reason,
            'scope' => 'internal',
            'audience' => 'specific',
            'is_active' => true,
            'published_at' => now(),
            'created_by' => auth()->id(),
        ]);

        $rows = $recipients->map(fn ($user) => [
            'announcement_id' => $announcement->id,
            'user_id' => $user->id,
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ])->values()->all();

        $announcement->recipients()->insert($rows);
    }

    private function activeCampusGroupIds(): array
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        if ($activeCampusId <= 0) {
            return [0];
        }

        $activeCycleId = SchoolCycle::query()
            ->where('is_active', true)
            ->where('campus_id', $activeCampusId)
            ->orderByDesc('start_date')
            ->value('id');

        if (! $activeCycleId) {
            return [0];
        }

        return SchoolCycleGroup::query()
            ->where('school_cycle_id', (int) $activeCycleId)
            ->where('campus_id', $activeCampusId)
            ->where('is_active', true)
            ->pluck('group_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function assertSuspensionInActiveCampus(StudentSuspension $suspension): void
    {
        $allowedGroupIds = $this->activeCampusGroupIds();
        abort_unless(in_array((int) $suspension->group_id, $allowedGroupIds, true), 404);
    }
}
