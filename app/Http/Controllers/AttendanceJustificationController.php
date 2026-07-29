<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Services\CurrentSchoolCycle;
use App\Models\Student;
use App\Models\AttendanceJustification;
use App\Services\AttendanceJustificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceJustificationController extends Controller
{
    public function index()
    {
        $allowedGroupIds = $this->activeCampusGroupIds();

        $groups = Group::query()
            ->where('is_active', true)
            ->when(!empty($allowedGroupIds), fn ($q) => $q->whereIn('id', $allowedGroupIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->with([
                'level:id,name',
                'students' => function ($q) {
                    $q->where('is_active', true)
                        ->orderBy('enrollment_number')
                        ->with('user:id,name');
                },
            ])
            ->orderBy('name')
            ->get();

        $justifications = AttendanceJustification::query()
            ->with([
                'student.user:id,name',
                'student.group:id,name',
                'issuer:id,name',
            ])
            ->latest('issued_at')
            ->limit(20)
            ->get();

        return view('attendance_justifications.create', compact('groups', 'justifications'));
    }

    public function create()
    {
        return $this->index();
    }

    public function store(
        Request $request,
        AttendanceJustificationService $service
    ) {
        $data = $request->validate([
            'group_id' => 'required|exists:groups,id',
            'student_id' => [
                'required',
                Rule::exists('students', 'id')->where(function ($query) use ($request) {
                    $query->where('group_id', $request->group_id)
                        ->where('is_active', true);
                }),
            ],
            'from_date'  => 'required|date',
            'to_date'    => 'required|date|after_or_equal:from_date',
            'reason'     => 'required|string|max:255',
            'document'   => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',
        ]);

        $allowedGroupIds = $this->activeCampusGroupIds();
        if (! in_array((int) $data['group_id'], $allowedGroupIds, true)) {
            return back()
                ->withInput()
                ->withErrors([
                    'group_id' => 'El grupo seleccionado no pertenece al campus activo.',
                ]);
        }

        $path = null;
        if ($request->hasFile('document')) {
            $path = $request->file('document')
                ->store('attendance_justifications', 'public');
        }

        $service->justify(
            Student::findOrFail($data['student_id']),
            Carbon::parse($data['from_date']),
            Carbon::parse($data['to_date']),
            [
                'reason' => $data['reason'],
                'document_path' => $path,
            ],
            auth()->user()
        );

        return redirect()
            ->route('attendance_justifications.index')
            ->with('success', 'Justificante emitido y asistencias actualizadas.');
    }

    private function activeCampusGroupIds(): array
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        if ($activeCampusId <= 0) {
            $activeCampusId = (int) (auth()->user()?->campuses()->value('campuses.id') ?? 0);
        }

        if ($activeCampusId <= 0) {
            return [];
        }

        $activeCycleId = app(CurrentSchoolCycle::class)->id(auth()->user(), $activeCampusId);

        if (! $activeCycleId) {
            return [];
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
}
