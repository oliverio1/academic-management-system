<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Campus;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Services\AcademicSessionGeneratorService;
use App\Services\CurrentSchoolCycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CoordinationScheduleController extends Controller
{
    private ?array $currentCyclePlanMap = null;
    private bool $hasCurrentCyclePlan = false;
    private ?SchoolCycle $currentCycle = null;

    public function __construct(
        private readonly AcademicSessionGeneratorService $sessionGenerator
    ) {
    }

    private const DAY_OPTIONS = [
        'monday' => 'Lunes',
        'tuesday' => 'Martes',
        'wednesday' => 'Miercoles',
        'thursday' => 'Jueves',
        'friday' => 'Viernes',
        'saturday' => 'Sabado',
        'sunday' => 'Domingo',
    ];

    public function index(Request $request)
    {
        $activeCampusId = $this->activeCampusId();

        $filters = $request->validate([
            'school_cycle_id' => ['nullable', 'integer', 'exists:school_cycles,id'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
        ]);

        $cycles = SchoolCycle::query()
            ->with('modality')
            ->when($activeCampusId > 0, function ($q) use ($activeCampusId) {
                $q->where(function ($qq) use ($activeCampusId) {
                    $qq->where('campus_id', $activeCampusId)
                        ->orWhereHas('campuses', fn ($cq) => $cq->where('campuses.id', $activeCampusId));
                });
            })
            ->orderByDesc('start_date')
            ->get();
        $selectedCycleId = (int) ($filters['school_cycle_id'] ?? 0);
        $selectedCycle = $selectedCycleId > 0
            ? $cycles->firstWhere('id', $selectedCycleId)
            : $this->resolveDefaultCycle($cycles);
        if ($selectedCycleId > 0 && ! $selectedCycle) {
            abort(404);
        }

        if ($selectedCycle) {
            $this->loadCyclePlanMap($selectedCycle);
            $filters['school_cycle_id'] = (int) $selectedCycle->id;
        }

        $schedules = Schedule::query()
            ->with(['schoolCycle.modality', 'assignment.group.level.modality', 'assignment.subject', 'assignment.teacher.user'])
            ->where('is_active', true)
            ->when($selectedCycle, fn ($query) => $query->where('school_cycle_id', $selectedCycle->id))
            ->when($activeCampusId > 0, function ($query) use ($activeCampusId) {
                $query->whereHas('assignment.schoolCycleGroup', function ($q) use ($activeCampusId) {
                    $q->where('campus_id', $activeCampusId);
                });
            })
            ->when($filters['group_id'] ?? null, function ($query, $groupId) {
                $query->whereHas('assignment', fn ($q) => $q->where('group_id', $groupId));
            })
            ->when($filters['teacher_id'] ?? null, function ($query, $teacherId) {
                $query->whereHas('assignment', fn ($q) => $q->where('teacher_id', $teacherId));
            })
            ->orderByRaw("FIELD(day_of_week,'monday','tuesday','wednesday','thursday','friday','saturday','sunday')")
            ->orderBy('section_number')
            ->orderBy('start_time')
            ->get();

        $groups = Group::query()
            ->where('is_active', true)
            ->when($selectedCycle, function ($query) use ($selectedCycle, $activeCampusId) {
                $groupIds = SchoolCycleGroup::query()
                    ->where('school_cycle_id', $selectedCycle->id)
                    ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
                    ->where('is_active', true)
                    ->pluck('group_id')
                    ->all();

                if (empty($groupIds)) {
                    $query->whereRaw('1 = 0');
                    return;
                }

                $query->whereIn('id', $groupIds);
            })
            ->orderBy('name')
            ->get();

        if (! empty($filters['group_id']) && ! $groups->pluck('id')->contains((int) $filters['group_id'])) {
            $filters['group_id'] = null;
        }

        $teachers = Teacher::query()->where('is_active', true)->with('user')->get()->sortBy('user.name');

        return view('coordination.schedules.index', [
            'schedules' => $schedules,
            'groups' => $groups,
            'teachers' => $teachers,
            'cycles' => $cycles,
            'dayOptions' => self::DAY_OPTIONS,
            'filters' => $filters,
            'activeCycle' => $selectedCycle,
            'usesCyclePlanning' => $this->hasCurrentCyclePlan,
        ]);
    }

    public function groupsCalendar(Request $request)
    {
        $activeCampusId = $this->activeCampusId();
        $activeCampus = $activeCampusId > 0 ? Campus::query()->find($activeCampusId) : null;

        $cycles = SchoolCycle::query()
            ->with('campus')
            ->when($activeCampusId > 0, function ($q) use ($activeCampusId) {
                $q->where(function ($qq) use ($activeCampusId) {
                    $qq->where('campus_id', $activeCampusId)
                        ->orWhereHas('campuses', fn ($cq) => $cq->where('campuses.id', $activeCampusId));
                });
            })
            ->orderByDesc('start_date')
            ->get();

        $selectedCycleId = (int) $request->query('school_cycle_id', 0);
        $activeCycle = $selectedCycleId > 0
            ? $cycles->firstWhere('id', $selectedCycleId)
            : $this->resolveDefaultCycle($cycles);

        if ($selectedCycleId > 0 && ! $activeCycle) {
            abort(404);
        }

        if (! $activeCycle) {
            return view('coordination.schedules.groups-calendar', [
                'activeCycle' => null,
                'cycles' => $cycles,
                'selectedCycleId' => null,
                'activeCampus' => $activeCampus,
                'dayOptions' => self::DAY_OPTIONS,
                'groupCalendars' => collect(),
                'cycleGroups' => collect(),
                'selectedGroupId' => null,
            ]);
        }

        $selectedGroupId = (int) $request->query('group_id', 0);

        $cycleGroups = SchoolCycleGroup::query()
            ->with('group.level.modality')
            ->where('school_cycle_id', $activeCycle->id)
            ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (SchoolCycleGroup $cg) => mb_strtolower((string) ($cg->group->name ?? '')));

        if ($selectedGroupId > 0 && ! $cycleGroups->contains(fn (SchoolCycleGroup $cg) => (int) $cg->group_id === $selectedGroupId)) {
            abort(404);
        }

        $calendarCycleGroups = $selectedGroupId > 0
            ? $cycleGroups->filter(fn (SchoolCycleGroup $cg) => (int) $cg->group_id === $selectedGroupId)->values()
            : $cycleGroups->values();

        $groupIds = $calendarCycleGroups->pluck('group_id')->map(fn ($id) => (int) $id)->all();

        $schedules = Schedule::query()
            ->with(['assignment.group', 'assignment.subject', 'assignment.teacher.user'])
            ->where('is_active', true)
            ->where('school_cycle_id', $activeCycle->id)
            ->whereHas('assignment', fn ($q) => $q->whereIn('group_id', $groupIds))
            ->get();

        $dayOrder = array_keys(self::DAY_OPTIONS);

        $groupCalendars = $calendarCycleGroups->map(function (SchoolCycleGroup $cycleGroup) use ($schedules, $dayOrder) {
            $groupSchedules = $schedules
                ->filter(fn (Schedule $s) => (int) ($s->assignment->group_id ?? 0) === (int) $cycleGroup->group_id)
                ->values();

            $timeSlots = $groupSchedules
                ->map(function (Schedule $s) {
                    $start = substr((string) $s->start_time, 0, 5);
                    $end = substr((string) $s->end_time, 0, 5);

                    return [
                        'key' => $start . '-' . $end,
                        'start' => $start,
                        'end' => $end,
                    ];
                })
                ->unique('key')
                ->sortBy('start')
                ->values();

            $matrix = [];
            foreach ($timeSlots as $slot) {
                foreach ($dayOrder as $dayKey) {
                    $cells = $groupSchedules->filter(function (Schedule $s) use ($slot, $dayKey) {
                        return $s->day_of_week === $dayKey
                            && substr((string) $s->start_time, 0, 5) === $slot['start']
                            && substr((string) $s->end_time, 0, 5) === $slot['end'];
                    })->sortBy(function (Schedule $s) {
                        return (int) ($s->section_number ?: ($s->assignment?->section_number ?? 1));
                    })->values();

                    $matrix[$slot['key']][$dayKey] = $cells;
                }
            }

            return [
                'group' => $cycleGroup->group,
                'timeSlots' => $timeSlots,
                'matrix' => $matrix,
                'totalSchedules' => $groupSchedules->count(),
            ];
        })->values();

        return view('coordination.schedules.groups-calendar', [
            'activeCycle' => $activeCycle,
            'cycles' => $cycles,
            'selectedCycleId' => (int) $activeCycle->id,
            'activeCampus' => $activeCampus,
            'dayOptions' => self::DAY_OPTIONS,
            'groupCalendars' => $groupCalendars,
            'cycleGroups' => $cycleGroups->values(),
            'selectedGroupId' => $selectedGroupId,
        ]);
    }

    public function create(Request $request)
    {
        $selectedCycleId = (int) $request->query('school_cycle_id', 0);
        return view('coordination.schedules.create', $this->formData($selectedCycleId > 0 ? $selectedCycleId : null));
    }

    public function store(Request $request)
    {
        $entries = $this->validateBatchPayload($request);
        $overlapWarnings = $this->collectBatchOverlapWarnings($entries);
        $allowOverlaps = $request->boolean('allow_overlaps');

        if (! empty($overlapWarnings) && ! $allowOverlaps) {
            return back()
                ->withInput()
                ->with('overlap_warnings', $overlapWarnings)
                ->with('overlap_requires_confirmation', true);
        }

        $duplicateWarnings = [];

        DB::transaction(function () use ($entries, &$duplicateWarnings) {
            foreach ($entries as $entry) {
                $this->persistScheduleEntry($entry, $duplicateWarnings);
            }
        });

        $redirect = redirect()
            ->route('coordination.schedules.index')
            ->with('info', 'Horarios creados correctamente');

        $allWarnings = array_values(array_unique(array_merge($overlapWarnings, $duplicateWarnings)));

        if (! empty($allWarnings)) {
            $redirect->with('schedule_warnings', $allWarnings);
        }

        return $redirect;
    }

    public function edit(Schedule $schedule)
    {
        abort_if((string) $schedule->tenant_id !== $this->tenantId(), 404);
        abort_if(! $schedule->is_active, 404);
        $this->assertScheduleInActiveCampus($schedule);

        $schedule->load('assignment');

        return view('coordination.schedules.edit', array_merge(
            $this->formData((int) $schedule->school_cycle_id ?: null),
            ['schedule' => $schedule]
        ));
    }

    public function update(Request $request, Schedule $schedule)
    {
        abort_if((string) $schedule->tenant_id !== $this->tenantId(), 404);
        abort_if(! $schedule->is_active, 404);
        $this->assertScheduleInActiveCampus($schedule);

        $data = $this->validatePayload($request);

        $this->ensureSubjectBelongsToGroup((int) $data['school_cycle_id'], (int) $data['group_id'], (int) $data['subject_id']);
        $this->ensureSectionAllowed((int) $data['school_cycle_id'], (int) $data['group_id'], (int) $data['section_number']);
        $overlapWarnings = $this->collectOverlapWarnings(
            (int) $data['school_cycle_id'],
            $data['group_id'],
            $data['teacher_id'],
            (int) $data['section_number'],
            $data['day_of_week'],
            $data['start_time'],
            $data['end_time'],
            $schedule->id
        );
        if (! empty($overlapWarnings)) {
            throw ValidationException::withMessages([
                'start_time' => implode(' ', $overlapWarnings),
            ]);
        }

        $assignment = $this->resolveAssignment(
            (int) $data['school_cycle_id'],
            $data['group_id'],
            $data['subject_id'],
            $data['teacher_id'],
            (int) $data['section_number']
        );

        $schedule->update([
            'tenant_id' => $this->tenantId(),
            'teaching_assignment_id' => $assignment->id,
            'school_cycle_id' => (int) $data['school_cycle_id'],
            'section_number' => (int) $data['section_number'],
            'day_of_week' => $data['day_of_week'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'type' => $data['type'] ?? null,
        ]);

        $this->sessionGenerator->generateForSchedule($schedule->fresh());

        return redirect()
            ->route('coordination.schedules.index')
            ->with('info', 'Horario actualizado correctamente');
    }

    public function destroy(Schedule $schedule)
    {
        abort_if((string) $schedule->tenant_id !== $this->tenantId(), 404);
        $this->assertScheduleInActiveCampus($schedule);
        $schedule->update(['is_active' => false]);

        return redirect()
            ->route('coordination.schedules.index')
            ->with('info', 'Horario eliminado correctamente');
    }

    private function formData(?int $selectedCycleId = null): array
    {
        $activeCampusId = $this->activeCampusId();
        $cycles = SchoolCycle::query()
            ->with('modality')
            ->when($activeCampusId > 0, function ($q) use ($activeCampusId) {
                $q->where(function ($qq) use ($activeCampusId) {
                    $qq->where('campus_id', $activeCampusId)
                        ->orWhereHas('campuses', fn ($cq) => $cq->where('campuses.id', $activeCampusId));
                });
            })
            ->orderByDesc('start_date')
            ->get();
        $selectedCycle = $selectedCycleId
            ? $cycles->firstWhere('id', $selectedCycleId)
            : $this->resolveDefaultCycle($cycles);

        if (! $selectedCycle && $cycles->isNotEmpty()) {
            $selectedCycle = $cycles->first();
        }

        if ($selectedCycle) {
            $this->loadCyclePlanMap($selectedCycle);
        } else {
            $this->currentCyclePlanMap = [];
            $this->hasCurrentCyclePlan = false;
            $this->currentCycle = null;
        }

        $groups = Group::query()->where('is_active', true)->orderBy('name')->get();

        if ($selectedCycle) {
            $selectedCycleGroupIds = SchoolCycleGroup::query()
                ->where('school_cycle_id', (int) $selectedCycle->id)
                ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
                ->where('is_active', true)
                ->pluck('group_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $groups = Group::query()
                ->where('is_active', true)
                ->when(! empty($selectedCycleGroupIds), fn ($q) => $q->whereIn('id', $selectedCycleGroupIds), fn ($q) => $q->whereRaw('1 = 0'))
                ->orderBy('name')
                ->get();
        }

        if ($this->hasCurrentCyclePlan) {
            $allowedGroupIds = array_keys($this->currentCyclePlanMap ?? []);

            $groups = Group::query()
                ->where('is_active', true)
                ->whereIn('id', $allowedGroupIds)
                ->orderBy('name')
                ->get();

            $subjectsById = Subject::query()
                ->where('is_active', true)
                ->whereIn(
                    'id',
                    collect($this->currentCyclePlanMap)->flatten()->unique()->values()->all()
                )
                ->get()
                ->keyBy('id');

            $groups->each(function (Group $group) use ($subjectsById) {
                $subjectIds = $this->currentCyclePlanMap[$group->id] ?? [];
                $subjects = collect($subjectIds)
                    ->map(fn ($subjectId) => $subjectsById->get($subjectId))
                    ->filter()
                    ->values();
                $group->setRelation('subjects', $subjects);
            });
        } else {
            $groups->load(['subjects' => fn ($q) => $q->where('is_active', true)->orderBy('name')]);
        }

        $subjects = Subject::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $teachers = Teacher::query()
            ->where('is_active', true)
            ->with(['user', 'subjects:id'])
            ->get()
            ->sortBy('user.name');

        $teacherSubjectsMap = $teachers
            ->mapWithKeys(function (Teacher $teacher) {
                return [
                    (int) $teacher->id => $teacher->subjects
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->values()
                        ->all(),
                ];
            })
            ->all();

        $cycleGroupRows = SchoolCycleGroup::query()
            ->with([
                'group:id,name',
                'subjects:id,name',
            ])
            ->whereIn('school_cycle_id', $cycles->pluck('id')->all())
            ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
            ->where('is_active', true)
            ->get();

        $subjectSectionsByCycleGroup = TeachingAssignment::query()
            ->selectRaw('school_cycle_group_id, subject_id, MAX(section_number) as max_section')
            ->whereIn('school_cycle_group_id', $cycleGroupRows->pluck('id')->all())
            ->groupBy('school_cycle_group_id', 'subject_id')
            ->get()
            ->groupBy('school_cycle_group_id')
            ->map(function ($rows) {
                return $rows
                    ->mapWithKeys(function ($row) {
                        return [
                            (int) $row->subject_id => max(1, min(3, (int) $row->max_section)),
                        ];
                    })
                    ->all();
            })
            ->all();

        $cycleGroupsMap = $cycleGroupRows
            ->groupBy('school_cycle_id')
            ->map(function ($rows) use ($subjectSectionsByCycleGroup) {
                return $rows->map(function (SchoolCycleGroup $row) use ($subjectSectionsByCycleGroup) {
                    $subjectSections = collect($subjectSectionsByCycleGroup[(int) $row->id] ?? []);
                    return [
                        'id' => (int) $row->group_id,
                        'name' => $row->group?->name ?? ('Grupo #' . $row->group_id),
                        'subjects' => $row->subjects
                            ->map(fn ($subject) => [
                                'id' => (int) $subject->id,
                                'name' => (string) $subject->name,
                                'section_count' => (int) ($subjectSections->get((int) $subject->id, 1)),
                            ])
                            ->values()
                            ->all(),
                    ];
                })->values()->all();
            })
            ->mapWithKeys(fn ($value, $key) => [(int) $key => $value])
            ->all();

        return [
            'groups' => $groups,
            'subjects' => $subjects,
            'teachers' => $teachers,
            'teacherSubjectsMap' => $teacherSubjectsMap,
            'cycleGroupsMap' => $cycleGroupsMap,
            'cycles' => $cycles,
            'selectedCycle' => $selectedCycle,
            'dayOptions' => self::DAY_OPTIONS,
            'activeCycle' => $selectedCycle,
            'usesCyclePlanning' => $this->hasCurrentCyclePlan,
        ];
    }

    private function validatePayload(Request $request): array
    {
        $data = $request->validate([
            'school_cycle_id' => ['required', 'integer', 'exists:school_cycles,id'],
            'group_id' => ['required', 'integer', 'exists:groups,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            'section_number' => ['required', 'integer', 'min:1', 'max:3'],
            'day_of_week' => ['required', Rule::in(array_keys(self::DAY_OPTIONS))],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'type' => ['nullable', 'string', 'max:50'],
        ]);

        $this->assertCycleInActiveCampus((int) $data['school_cycle_id']);

        return $data;
    }

    private function validateBatchPayload(Request $request): array
    {
        if ($request->has('entries')) {
            $data = $request->validate([
                'entries' => ['required', 'array', 'min:1'],
                'entries.*.school_cycle_id' => ['required', 'integer', 'exists:school_cycles,id'],
                'entries.*.group_id' => ['required', 'integer', 'exists:groups,id'],
                'entries.*.subject_id' => ['required', 'integer', 'exists:subjects,id'],
                'entries.*.teacher_id' => ['required', 'integer', 'exists:teachers,id'],
                'entries.*.section_number' => ['required', 'integer', 'min:1', 'max:3'],
                'entries.*.day_of_week' => ['required', Rule::in(array_keys(self::DAY_OPTIONS))],
                'entries.*.start_time' => ['required', 'date_format:H:i'],
                'entries.*.end_time' => ['required', 'date_format:H:i'],
                'entries.*.type' => ['nullable', 'string', 'max:50'],
            ]);

            foreach ($data['entries'] as $index => $entry) {
                $this->assertCycleInActiveCampus((int) $entry['school_cycle_id']);
                if ($entry['end_time'] <= $entry['start_time']) {
                    throw ValidationException::withMessages([
                        "entries.$index.end_time" => 'La hora de fin debe ser mayor que la hora de inicio.',
                    ]);
                }
            }

            return $data['entries'];
        }

        return [$this->validatePayload($request)];
    }

    private function persistScheduleEntry(array $data, array &$duplicateWarnings = []): void
    {
        $this->ensureSubjectBelongsToGroup((int) $data['school_cycle_id'], (int) $data['group_id'], (int) $data['subject_id']);
        $this->ensureSectionAllowed((int) $data['school_cycle_id'], (int) $data['group_id'], (int) $data['section_number']);

        $assignment = $this->resolveAssignment(
            (int) $data['school_cycle_id'],
            $data['group_id'],
            $data['subject_id'],
            $data['teacher_id'],
            (int) $data['section_number']
        );

        $existingExact = Schedule::query()
            ->where('teaching_assignment_id', $assignment->id)
            ->where('school_cycle_id', $data['school_cycle_id'])
            ->where('section_number', (int) $data['section_number'])
            ->where('day_of_week', $data['day_of_week'])
            ->where('start_time', $data['start_time'])
            ->where('end_time', $data['end_time'])
            ->first();

        if ($existingExact) {
            $wasInactive = ! (bool) $existingExact->is_active;

            $existingExact->update([
                'type' => $data['type'] ?? null,
                'is_active' => true,
            ]);

            $dayLabel = self::DAY_OPTIONS[$data['day_of_week']] ?? $data['day_of_week'];
            $duplicateWarnings[] = $wasInactive
                ? "Se reactivo un horario existente: {$dayLabel} {$data['start_time']}-{$data['end_time']} ({$assignment->group->name} / {$assignment->subject->name})."
                : "Horario duplicado detectado y omitido: {$dayLabel} {$data['start_time']}-{$data['end_time']} ({$assignment->group->name} / {$assignment->subject->name}).";

            if ($wasInactive) {
                $this->sessionGenerator->generateForSchedule($existingExact->fresh());
            }

            return;
        }

        $schedule = Schedule::create([
            'teaching_assignment_id' => $assignment->id,
            'school_cycle_id' => (int) $data['school_cycle_id'],
            'section_number' => (int) $data['section_number'],
            'section_type' => $assignment->section_type,
            'section_label' => $assignment->section_label,
            'day_of_week' => $data['day_of_week'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'type' => $data['type'] ?? null,
            'is_active' => true,
        ]);

        $this->sessionGenerator->generateForSchedule($schedule);
    }

    private function resolveAssignment(int $schoolCycleId, int $groupId, int $subjectId, int $teacherId, int $sectionNumber): TeachingAssignment
    {
        $tenantId = $this->tenantId();
        $cycle = SchoolCycle::query()->findOrFail($schoolCycleId);
        $group = Group::query()->with('level')->findOrFail($groupId);
        $modalityId = (int) ($group->level->modality_id ?? 0);

        if ($modalityId <= 0) {
            throw ValidationException::withMessages([
                'group_id' => 'El grupo seleccionado no tiene modalidad valida.',
            ]);
        }

        $activeCampusId = (int) session('active_campus_id', 0);
        $cycleCampusId = (int) ($cycle->campus_id ?? 0);
        $targetCampusId = $activeCampusId > 0 ? $activeCampusId : $cycleCampusId;

        if ($targetCampusId <= 0) {
            $targetCampusId = (int) SchoolCycleGroup::query()
                ->where('school_cycle_id', $schoolCycleId)
                ->where('group_id', $groupId)
                ->value('campus_id');
        }

        if ($targetCampusId <= 0) {
            throw ValidationException::withMessages([
                'school_cycle_id' => 'No se pudo determinar el campus activo para el ciclo seleccionado.',
            ]);
        }

        $cycleGroup = SchoolCycleGroup::firstOrCreate(
            [
                'school_cycle_id' => $schoolCycleId,
                'group_id' => $groupId,
                'campus_id' => $targetCampusId,
                'modality_id' => $modalityId,
            ],
            ['is_active' => true]
        );

        if (! $cycleGroup->is_active) {
            $cycleGroup->update(['is_active' => true]);
        }

        $sectionDescriptor = $this->sectionDescriptor(
            $sectionNumber,
            (string) Subject::query()->whereKey($subjectId)->value('name')
        );

        $assignment = TeachingAssignment::updateOrCreate(
            [
                'school_cycle_group_id' => $cycleGroup->id,
                'subject_id' => $subjectId,
                'section_number' => $sectionNumber,
            ],
            [
                'group_id' => $groupId,
                'teacher_id' => $teacherId,
                'section_type' => $sectionDescriptor['type'],
                'section_label' => $sectionDescriptor['label'],
                'is_active' => true,
            ]
        );

        if ((int) $assignment->teacher_id !== $teacherId || ! $assignment->is_active) {
            $assignment->update([
                'teacher_id' => $teacherId,
                'is_active' => true,
            ]);
        }

        if ((int) $assignment->group_id !== $groupId) {
            $assignment->update(['group_id' => $groupId]);
        }

        if ((int) ($assignment->school_cycle_group_id ?? 0) !== (int) $cycleGroup->id) {
            $assignment->update(['school_cycle_group_id' => $cycleGroup->id]);
        }

        if (! $assignment->is_active) {
            $assignment->update(['is_active' => true]);
        }

        return $assignment;
    }

    private function ensureSubjectBelongsToGroup(int $schoolCycleId, int $groupId, int $subjectId): void
    {
        $cycle = SchoolCycle::query()->find($schoolCycleId);
        $this->loadCyclePlanMap($cycle);

        if ($this->hasCurrentCyclePlan) {
            $belongs = in_array($subjectId, $this->currentCyclePlanMap[$groupId] ?? [], true);
        } else {
            $belongs = Group::query()
                ->whereKey($groupId)
                ->whereHas('subjects', fn ($q) => $q->where('subjects.id', $subjectId))
                ->exists();
        }

        if (! $belongs) {
            throw ValidationException::withMessages([
                'subject_id' => 'La materia seleccionada no esta asignada al grupo.',
            ]);
        }
    }

    private function collectBatchOverlapWarnings(array $entries): array
    {
        $warnings = [];

        foreach ($entries as $index => $entry) {
            $rowWarnings = $this->collectOverlapWarnings(
                (int) $entry['school_cycle_id'],
                (int) $entry['group_id'],
                (int) $entry['teacher_id'],
                (int) $entry['section_number'],
                (string) $entry['day_of_week'],
                (string) $entry['start_time'],
                (string) $entry['end_time']
            );

            foreach ($rowWarnings as $rowWarning) {
                $warnings[] = 'Fila ' . ($index + 1) . ': ' . $rowWarning;
            }
        }

        return $warnings;
    }

    private function collectOverlapWarnings(
        int $schoolCycleId,
        int $groupId,
        int $teacherId,
        int $sectionNumber,
        string $dayOfWeek,
        string $startTime,
        string $endTime,
        ?int $ignoreScheduleId = null
    ): array {
        $warnings = [];

        $baseQuery = Schedule::query()
            ->where('is_active', true)
            ->where('school_cycle_id', $schoolCycleId)
            ->where('day_of_week', $dayOfWeek)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->when($ignoreScheduleId, fn ($q) => $q->where('id', '!=', $ignoreScheduleId));

        $groupConflict = (clone $baseQuery)
            ->with(['assignment.group', 'assignment.subject', 'assignment.teacher.user'])
            ->whereHas('assignment', fn ($q) => $q->where('group_id', $groupId)->where('section_number', $sectionNumber))
            ->orderBy('start_time')
            ->first();

        if ($groupConflict) {
            $label = $this->conflictLabel($groupConflict);
            $warnings[] = "El grupo ya tiene otro horario en ese bloque: {$label}.";
        }

        $teacherConflict = (clone $baseQuery)
            ->with(['assignment.group', 'assignment.subject', 'assignment.teacher.user'])
            ->whereHas('assignment', fn ($q) => $q->where('teacher_id', $teacherId))
            ->orderBy('start_time')
            ->first();

        if ($teacherConflict) {
            $label = $this->conflictLabel($teacherConflict);
            $warnings[] = "El profesor ya tiene otro horario en ese bloque: {$label}.";
        }

        return $warnings;
    }

    private function conflictLabel(Schedule $schedule): string
    {
        $day = self::DAY_OPTIONS[$schedule->day_of_week] ?? ucfirst($schedule->day_of_week);
        $start = substr((string) $schedule->start_time, 0, 5);
        $end = substr((string) $schedule->end_time, 0, 5);
        $group = $schedule->assignment?->group?->name ?? 'N/D';
        $subject = $schedule->assignment?->subject?->name ?? 'N/D';
        $teacher = $schedule->assignment?->teacher?->user?->name ?? 'N/D';
        $section = (int) ($schedule->section_number ?: ($schedule->assignment?->section_number ?? 1));

        return "{$day} {$start}-{$end} (Grupo: {$group}, Sección: {$section}, Materia: {$subject}, Profesor: {$teacher})";
    }

    private function ensureSectionAllowed(int $schoolCycleId, int $groupId, int $sectionNumber): void
    {
        $maxSections = (int) SchoolCycleGroup::query()
            ->where('school_cycle_id', $schoolCycleId)
            ->where('group_id', $groupId)
            ->value('section_count');

        $maxSections = max(1, min(3, $maxSections > 0 ? $maxSections : 1));

        if ($sectionNumber < 1 || $sectionNumber > $maxSections) {
            throw ValidationException::withMessages([
                'section_number' => "La sección seleccionada no existe para este grupo en el ciclo (máximo: {$maxSections}).",
            ]);
        }
    }

    private function loadCyclePlanMap(?SchoolCycle $cycle): void
    {
        if ($this->currentCyclePlanMap !== null && (($this->currentCycle?->id ?? null) === ($cycle?->id ?? null))) {
            return;
        }

        $this->currentCycle = $cycle;

        if (! $cycle) {
            $this->currentCyclePlanMap = [];
            $this->hasCurrentCyclePlan = false;
            return;
        }

        $activeCampusId = $this->activeCampusId();
        $cycleGroups = SchoolCycleGroup::query()
            ->with('subjects:id')
            ->where('school_cycle_id', $cycle->id)
            ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
            ->where('is_active', true)
            ->get();

        if ($cycleGroups->isEmpty()) {
            $this->currentCyclePlanMap = [];
            $this->hasCurrentCyclePlan = false;
            return;
        }

        $this->currentCyclePlanMap = $cycleGroups
            ->mapWithKeys(fn (SchoolCycleGroup $cycleGroup) => [
                $cycleGroup->group_id => $cycleGroup->subjects->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ])
            ->all();

        $this->hasCurrentCyclePlan = true;
    }

    private function resolveDefaultCycle($cycles): ?SchoolCycle
    {
        $current = app(CurrentSchoolCycle::class)->get(auth()->user(), $this->activeCampusId());
        if ($current && $cycles->contains(fn ($cycle) => (int) $cycle->id === (int) $current->id)) {
            return $current;
        }

        return $cycles->firstWhere('is_active', true) ?: $cycles->first();
    }

    private function tenantId(): string
    {
        $tenantId = (string) tenant('id');
        abort_if($tenantId === '', 403, 'Tenant no identificado.');

        return $tenantId;
    }

    private function assertCycleInActiveCampus(int $schoolCycleId): void
    {
        $activeCampusId = $this->activeCampusId();
        if ($activeCampusId <= 0) {
            return;
        }

        $exists = SchoolCycle::query()
            ->whereKey($schoolCycleId)
            ->where(function ($q) use ($activeCampusId) {
                $q->where('campus_id', $activeCampusId)
                    ->orWhereHas('campuses', fn ($cq) => $cq->where('campuses.id', $activeCampusId));
            })
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'school_cycle_id' => 'El ciclo seleccionado no pertenece al campus activo.',
            ]);
        }
    }

    private function assertScheduleInActiveCampus(Schedule $schedule): void
    {
        $activeCampusId = $this->activeCampusId();
        if ($activeCampusId <= 0) {
            return;
        }

        $belongsToCampus = SchoolCycle::query()
            ->whereKey((int) $schedule->school_cycle_id)
            ->where(function ($q) use ($activeCampusId) {
                $q->where('campus_id', $activeCampusId)
                    ->orWhereHas('campuses', fn ($cq) => $cq->where('campuses.id', $activeCampusId));
            })
            ->exists();

        abort_if(! $belongsToCampus, 404);
    }

    private function activeCampusId(): int
    {
        return (int) session('active_campus_id', 0);
    }

    private function sectionDescriptor(int $sectionNumber, string $subjectName): array
    {
        $subjectKey = $this->normalizeKey($subjectName);

        if (str_contains($subjectKey, 'ingles') || str_contains($subjectKey, 'english')) {
            return [
                'type' => 'english',
                'label' => $sectionNumber === 2 ? 'AVANZADO' : 'BASICO',
            ];
        }

        if (str_contains($subjectKey, 'laboratorio') || str_contains($subjectKey, 'lab') || str_contains($subjectKey, 'taller')) {
            return [
                'type' => 'lab_taller',
                'label' => match ($sectionNumber) {
                    2 => 'B',
                    3 => 'C',
                    default => 'A',
                },
            ];
        }

        return [
            'type' => null,
            'label' => null,
        ];
    }

    private function normalizeKey(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = mb_strtolower(trim($value));

        return preg_replace('/\s+/', ' ', $value) ?? '';
    }
}
