<?php

namespace App\Http\Controllers;

use App\Models\Modality;
use App\Models\Campus;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\CyclePartial;
use App\Models\Group;
use App\Services\CyclePartialDefaultsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolCycleController extends Controller
{
    public function index()
    {
        $cycles = SchoolCycle::with(['campus', 'campuses', 'modality', 'modalities'])->orderByDesc('start_date')->get();
        return view('school_cycles.index', compact('cycles'));
    }

    public function create()
    {
        $modalities = Modality::orderBy('name')->get();
        $campuses = Campus::query()->where('is_active', true)->orderBy('name')->get();
        $cycles = SchoolCycle::with(['campus', 'campuses', 'modality', 'modalities'])->orderByDesc('start_date')->get();
        return view('school_cycles.create', compact('modalities', 'campuses', 'cycles'));
    }

    public function store(Request $request, CyclePartialDefaultsService $partialDefaults)
    {
        $data = $request->validate([
            'modality_ids' => 'required|array|min:1',
            'modality_ids.*' => 'exists:modalities,id',
            'campus_ids' => 'nullable|array',
            'campus_ids.*' => 'exists:campuses,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:40|unique:school_cycles,code',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'clone_configuration' => 'nullable|boolean',
            'source_cycle_id' => 'nullable|exists:school_cycles,id',
            'clone_as_new_groups' => 'nullable|boolean',
        ]);

        $modalityIds = collect($data['modality_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $primaryModalityId = (int) $modalityIds->first();
        $campusIds = collect($data['campus_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $primaryCampusId = $campusIds->first();

        DB::transaction(function () use ($data, $partialDefaults, $modalityIds, $primaryModalityId, $campusIds, $primaryCampusId) {
            $newCycle = SchoolCycle::create([
                'modality_id' => $primaryModalityId,
                'campus_id' => $primaryCampusId ? (int) $primaryCampusId : null,
                'name' => $data['name'],
                'code' => $data['code'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_active' => true,
            ]);
            $newCycle->modalities()->sync($modalityIds->all());
            $newCycle->campuses()->sync($campusIds->all());

            if (! empty($data['clone_configuration'])) {
                if ($campusIds->isEmpty()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'campus_ids' => 'Para clonar configuración debes asociar al menos un campus.',
                    ]);
                }
                $sourceCycle = $this->resolveSourceCycle($data, $newCycle, $primaryModalityId);
                if ($sourceCycle) {
                    $this->cloneConfiguration(
                        $sourceCycle,
                        $newCycle,
                        $primaryModalityId,
                        ! empty($data['clone_as_new_groups'])
                    );
                }
            }

            $newCycle->loadMissing('modality');
            $partialDefaults->syncForCycle($newCycle);
        });

        return redirect()->route('school-cycles.index')->with('info', 'Ciclo escolar creado correctamente');
    }

    public function edit(SchoolCycle $schoolCycle)
    {
        $modalities = Modality::orderBy('name')->get();
        $campuses = Campus::query()->where('is_active', true)->orderBy('name')->get();
        return view('school_cycles.edit', compact('schoolCycle', 'modalities', 'campuses'));
    }

    public function update(Request $request, SchoolCycle $schoolCycle, CyclePartialDefaultsService $partialDefaults)
    {
        $data = $request->validate([
            'modality_ids' => 'required|array|min:1',
            'modality_ids.*' => 'exists:modalities,id',
            'campus_ids' => 'nullable|array',
            'campus_ids.*' => 'exists:campuses,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:40|unique:school_cycles,code,' . $schoolCycle->id,
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);
        $modalityIds = collect($data['modality_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $primaryModalityId = (int) $modalityIds->first();
        $campusIds = collect($data['campus_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $primaryCampusId = $campusIds->first();

        $newIsActive = $request->boolean('is_active');

        DB::transaction(function () use ($schoolCycle, $data, $newIsActive, $partialDefaults, $modalityIds, $primaryModalityId, $campusIds, $primaryCampusId) {
            $wasActive = (bool) $schoolCycle->is_active;

            $schoolCycle->update([
                'modality_id' => $primaryModalityId,
                'campus_id' => $primaryCampusId ? (int) $primaryCampusId : null,
                'name' => $data['name'],
                'code' => $data['code'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_active' => $newIsActive,
            ]);
            $schoolCycle->modalities()->sync($modalityIds->all());
            $schoolCycle->campuses()->sync($campusIds->all());
            $schoolCycle->loadMissing('modality');
            $partialDefaults->syncForCycle($schoolCycle);

            if ($wasActive && ! $newIsActive) {
                CyclePartial::query()
                    ->where('school_cycle_id', $schoolCycle->id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }
        });

        return redirect()->route('school-cycles.index')->with('info', 'Ciclo escolar actualizado correctamente');
    }

    public function destroy(SchoolCycle $schoolCycle)
    {
        $schoolCycle->delete();
        return back()->with('info', 'Ciclo escolar eliminado');
    }

    private function resolveSourceCycle(array $data, SchoolCycle $newCycle, int $modalityId): ?SchoolCycle
    {
        if (! empty($data['source_cycle_id'])) {
            $source = SchoolCycle::with('modalities')->find($data['source_cycle_id']);
            if (! $source) {
                return null;
            }

            if ((int) $source->campus_id !== (int) $newCycle->campus_id) {
                $sourceCampusIds = $source->campuses()->pluck('campuses.id')->map(fn ($id) => (int) $id)->all();
                $targetCampusIds = $newCycle->campuses()->pluck('campuses.id')->map(fn ($id) => (int) $id)->all();
                $shared = array_intersect($sourceCampusIds, $targetCampusIds);
                if (empty($shared)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'source_cycle_id' => 'El ciclo origen debe compartir al menos un campus con el ciclo nuevo.',
                    ]);
                }
            }

            if (! $source->hasModality($modalityId)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'source_cycle_id' => 'El ciclo origen debe incluir la modalidad seleccionada.',
                ]);
            }

            if ((int) $source->id === (int) $newCycle->id) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'source_cycle_id' => 'El ciclo origen no puede ser el mismo ciclo nuevo.',
                ]);
            }

            return $source;
        }

        return SchoolCycle::query()
            ->whereHas('modalities', fn ($q) => $q->where('modalities.id', $modalityId))
            ->where(function ($q) use ($newCycle) {
                $campusIds = $newCycle->campuses()->pluck('campuses.id')->all();
                if (! empty($campusIds)) {
                    $q->whereHas('campuses', fn ($qq) => $qq->whereIn('campuses.id', $campusIds));
                }
            })
            ->where('id', '!=', $newCycle->id)
            ->orderByDesc('start_date')
            ->first();
    }

    private function cloneConfiguration(SchoolCycle $sourceCycle, SchoolCycle $newCycle, int $modalityId, bool $cloneAsNewGroups): void
    {
        $sourceCycleGroups = SchoolCycleGroup::query()
            ->with(['group', 'subjects:id'])
            ->where('school_cycle_id', $sourceCycle->id)
            ->whereHas('group.level', fn ($q) => $q->where('modality_id', $modalityId))
            ->where('campus_id', $sourceCycle->campus_id)
            ->where('modality_id', $modalityId)
            ->where('is_active', true)
            ->get();

        if ($cloneAsNewGroups) {
            $this->cloneAsPhysicalGroups($newCycle, $sourceCycleGroups, $modalityId);
            return;
        }

        if ($sourceCycleGroups->isNotEmpty()) {
            foreach ($sourceCycleGroups as $sourceCycleGroup) {
                $newCycleGroup = SchoolCycleGroup::firstOrCreate(
                    [
                        'school_cycle_id' => $newCycle->id,
                        'group_id' => $sourceCycleGroup->group_id,
                        'campus_id' => $newCycle->campus_id,
                        'modality_id' => $modalityId,
                    ],
                    ['is_active' => true]
                );

                if (! $newCycleGroup->is_active) {
                    $newCycleGroup->update(['is_active' => true]);
                }

                $newCycleGroup->subjects()->sync(
                    $sourceCycleGroup->subjects->pluck('id')->map(fn ($id) => (int) $id)->all()
                );
            }

            return;
        }

        $groups = Group::query()
            ->with(['subjects' => fn ($q) => $q->where('is_active', true)])
            ->where('is_active', true)
            ->whereHas('level', fn ($q) => $q->where('modality_id', $modalityId))
            ->get();

        foreach ($groups as $group) {
            $newCycleGroup = SchoolCycleGroup::firstOrCreate(
                [
                    'school_cycle_id' => $newCycle->id,
                    'group_id' => $group->id,
                    'campus_id' => $newCycle->campus_id,
                    'modality_id' => $modalityId,
                ],
                ['is_active' => true]
            );

            if (! $newCycleGroup->is_active) {
                $newCycleGroup->update(['is_active' => true]);
            }

            $newCycleGroup->subjects()->sync(
                $group->subjects->pluck('id')->map(fn ($id) => (int) $id)->all()
            );
        }
    }

    private function cloneAsPhysicalGroups(SchoolCycle $newCycle, $sourceCycleGroups, int $modalityId): void
    {
        if ($sourceCycleGroups->isNotEmpty()) {
            foreach ($sourceCycleGroups as $sourceCycleGroup) {
                $sourceGroup = $sourceCycleGroup->group;
                if (! $sourceGroup) {
                    continue;
                }

                $newGroup = $this->resolveReusableGroup(
                    $sourceGroup->level_id,
                    $sourceGroup->name,
                    $sourceGroup->capacity
                );

                $subjectIds = $sourceCycleGroup->subjects->pluck('id')->map(fn ($id) => (int) $id)->all();
                $newGroup->subjects()->syncWithoutDetaching($subjectIds);

                $newCycleGroup = SchoolCycleGroup::firstOrCreate(
                    [
                        'school_cycle_id' => $newCycle->id,
                        'group_id' => $newGroup->id,
                        'campus_id' => $newCycle->campus_id,
                        'modality_id' => $modalityId,
                    ],
                    ['is_active' => true]
                );
                if (! $newCycleGroup->is_active) {
                    $newCycleGroup->update(['is_active' => true]);
                }
                $newCycleGroup->subjects()->sync($subjectIds);
            }

            return;
        }

        $groups = Group::query()
            ->with(['subjects' => fn ($q) => $q->where('is_active', true)])
            ->where('is_active', true)
            ->whereHas('level', fn ($q) => $q->where('modality_id', $modalityId))
            ->get();

        foreach ($groups as $group) {
            $newGroup = $this->resolveReusableGroup(
                $group->level_id,
                $group->name,
                $group->capacity
            );

            $subjectIds = $group->subjects->pluck('id')->map(fn ($id) => (int) $id)->all();
            $newGroup->subjects()->syncWithoutDetaching($subjectIds);

            $newCycleGroup = SchoolCycleGroup::firstOrCreate(
                [
                    'school_cycle_id' => $newCycle->id,
                    'group_id' => $newGroup->id,
                    'campus_id' => $newCycle->campus_id,
                    'modality_id' => $modalityId,
                ],
                ['is_active' => true]
            );
            if (! $newCycleGroup->is_active) {
                $newCycleGroup->update(['is_active' => true]);
            }
            $newCycleGroup->subjects()->sync($subjectIds);
        }
    }

    private function resolveReusableGroup(int $levelId, string $groupName, ?int $capacity): Group
    {
        $normalizedName = $this->normalizeGroupName($groupName);

        $existingGroup = Group::query()
            ->where('level_id', $levelId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($normalizedName)])
            ->first();

        if ($existingGroup) {
            if (! $existingGroup->is_active) {
                $existingGroup->update(['is_active' => true]);
            }

            return $existingGroup;
        }

        return Group::create([
            'level_id' => $levelId,
            'name' => $normalizedName,
            'capacity' => $capacity,
            'is_active' => true,
        ]);
    }

    private function normalizeGroupName(string $name): string
    {
        return trim(preg_replace('/\s*-\s*\d{2,4}-\d+(?:\s+\d+)?$/u', '', $name) ?? $name);
    }
}
