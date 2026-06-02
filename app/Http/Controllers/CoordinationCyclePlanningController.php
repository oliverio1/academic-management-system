<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Level;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoordinationCyclePlanningController extends Controller
{
    public function index(Request $request): View
    {
        $activeCampusId = (int) $request->session()->get('active_campus_id', 0);
        $cycles = SchoolCycle::with(['modality', 'modalities'])
            ->when($activeCampusId > 0, fn ($q) => $q->whereHas('campuses', fn ($campusQuery) => $campusQuery->where('campuses.id', $activeCampusId)))
            ->orderByDesc('start_date')
            ->get();
        $selectedCycleId = (int) $request->query('school_cycle_id');
        $selectedModalityId = (int) $request->query('modality_id');
        $selectedCycle = $selectedCycleId
            ? SchoolCycle::with(['modality', 'modalities'])
                ->when($activeCampusId > 0, fn ($q) => $q->whereHas('campuses', fn ($campusQuery) => $campusQuery->where('campuses.id', $activeCampusId)))
                ->find($selectedCycleId)
            : null;
        if ($selectedCycle && $selectedModalityId <= 0) {
            $selectedModalityId = (int) ($selectedCycle->modalities->first()->id ?? $selectedCycle->modality_id);
        }

        $plannedGroups = collect();
        $availableGroups = collect();
        $subjectsByLevel = collect();
        $levels = collect();
        $plannedGroupIds = collect();

        if ($selectedCycle) {
            $plannedGroups = SchoolCycleGroup::query()
                ->with(['group.level.modality', 'subjects'])
                ->where('school_cycle_id', $selectedCycle->id)
                ->where('campus_id', $activeCampusId > 0 ? $activeCampusId : (int) optional($selectedCycle->campus)->id)
                ->where('modality_id', $selectedModalityId)
                ->where('is_active', true)
                ->orderBy('group_id')
                ->get();

            $plannedGroupIds = $plannedGroups->pluck('group_id')->map(fn ($id) => (int) $id)->values();

            $availableGroups = Group::query()
                ->with(['level.modality', 'subjects' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
                ->where('is_active', true)
                ->whereHas('level', function ($q) use ($selectedModalityId) {
                    $q->where('modality_id', $selectedModalityId);
                })
                ->orderBy('level_id')
                ->orderBy('name')
                ->get();

            $levelIds = $plannedGroups
                ->pluck('group.level_id')
                ->filter()
                ->unique()
                ->values();

            $subjectsByLevel = Subject::query()
                ->whereIn('level_id', $levelIds)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->groupBy('level_id');

            $levels = Level::query()
                ->where('modality_id', $selectedModalityId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        return view('coordination.cycle_planning.index', [
            'cycles' => $cycles,
            'selectedCycle' => $selectedCycle,
            'plannedGroups' => $plannedGroups,
            'availableGroups' => $availableGroups,
            'subjectsByLevel' => $subjectsByLevel,
            'levels' => $levels,
            'selectedModalityId' => $selectedModalityId,
            'plannedGroupIds' => $plannedGroupIds,
        ]);
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $activeCampusId = (int) $request->session()->get('active_campus_id', 0);
        if ($activeCampusId <= 0) {
            return back()->with('error', 'Selecciona un campus activo para planear el ciclo.');
        }

        $data = $request->validate([
            'school_cycle_id' => 'required|exists:school_cycles,id',
            'modality_id' => 'required|exists:modalities,id',
            'group_id' => 'required|exists:groups,id',
        ]);

        $cycle = SchoolCycle::with(['modality', 'modalities'])->findOrFail($data['school_cycle_id']);
        if ($activeCampusId > 0 && ! $cycle->campuses()->where('campuses.id', $activeCampusId)->exists()) {
            return back()->with('error', 'El ciclo no pertenece al campus activo.');
        }
        $group = Group::with('level.modality')->findOrFail($data['group_id']);
        $selectedModalityId = (int) $data['modality_id'];

        if (! $cycle->hasModality($selectedModalityId)) {
            return back()->with('error', 'La modalidad no pertenece al ciclo seleccionado.');
        }

        if ((int) $group->level->modality_id !== $selectedModalityId) {
            return back()->with('error', 'El grupo no corresponde a la modalidad seleccionada.');
        }

        SchoolCycleGroup::updateOrCreate(
            [
                'tenant_id' => (string) tenant('id'),
                'school_cycle_id' => $cycle->id,
                'group_id' => $group->id,
                'campus_id' => $activeCampusId,
                'modality_id' => $selectedModalityId,
            ],
            [
                'section_count' => 1,
                'is_active' => true,
            ]
        );

        return redirect()
            ->route('coordination.cycle-planning.index', ['school_cycle_id' => $cycle->id, 'modality_id' => $selectedModalityId])
            ->with('info', 'Grupo agregado al ciclo.');
    }

    public function storeNewGroup(Request $request): RedirectResponse
    {
        $activeCampusId = (int) $request->session()->get('active_campus_id', 0);
        if ($activeCampusId <= 0) {
            return back()->with('error', 'Selecciona un campus activo para planear el ciclo.');
        }

        $data = $request->validate([
            'school_cycle_id' => 'required|exists:school_cycles,id',
            'modality_id' => 'required|exists:modalities,id',
            'level_id' => 'required|exists:levels,id',
            'group_name' => 'required|string|max:255',
            'capacity' => 'nullable|integer|min:1|max:1000',
        ]);

        $cycle = SchoolCycle::with(['modality', 'modalities'])->findOrFail($data['school_cycle_id']);
        if ($activeCampusId > 0 && ! $cycle->campuses()->where('campuses.id', $activeCampusId)->exists()) {
            return back()->with('error', 'El ciclo no pertenece al campus activo.');
        }
        $level = Level::query()->findOrFail($data['level_id']);
        $selectedModalityId = (int) $data['modality_id'];

        if (! $cycle->hasModality($selectedModalityId)) {
            return back()->with('error', 'La modalidad no pertenece al ciclo seleccionado.');
        }

        if ((int) $level->modality_id !== $selectedModalityId) {
            return back()->with('error', 'El nivel no corresponde a la modalidad seleccionada.');
        }

        $groupName = $this->normalizeGroupName($data['group_name']);
        $existingGroup = Group::query()
            ->where('level_id', $level->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($groupName)])
            ->first();

        if ($existingGroup) {
            $cycleGroup = SchoolCycleGroup::firstOrCreate(
                [
                    'tenant_id' => (string) tenant('id'),
                    'school_cycle_id' => $cycle->id,
                    'group_id' => $existingGroup->id,
                    'campus_id' => $activeCampusId,
                    'modality_id' => $selectedModalityId,
                ],
                [
                    'section_count' => 1,
                    'is_active' => true,
                ]
            );

            if (! $cycleGroup->is_active) {
                $cycleGroup->update(['is_active' => true]);
            }

            $subjectIds = Subject::query()
                ->where('level_id', $level->id)
                ->where('is_active', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (! empty($subjectIds)) {
                $cycleGroup->subjects()->syncWithoutDetaching($subjectIds);
            }

            return redirect()
                ->route('coordination.cycle-planning.index', ['school_cycle_id' => $cycle->id, 'modality_id' => $selectedModalityId])
                ->with('info', 'El grupo ya existia y fue agregado a la planeacion del ciclo.');
        }

        $group = Group::create([
            'level_id' => $level->id,
            'name' => $groupName,
            'capacity' => $data['capacity'] ?? null,
            'is_active' => true,
        ]);

        $cycleGroup = SchoolCycleGroup::create([
            'tenant_id' => (string) tenant('id'),
            'school_cycle_id' => $cycle->id,
            'group_id' => $group->id,
            'campus_id' => $activeCampusId,
            'modality_id' => $selectedModalityId,
            'section_count' => 1,
            'is_active' => true,
        ]);

        $subjectIds = Subject::query()
            ->where('level_id', $level->id)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (! empty($subjectIds)) {
            $cycleGroup->subjects()->sync($subjectIds);
        }

        return redirect()
            ->route('coordination.cycle-planning.index', ['school_cycle_id' => $cycle->id, 'modality_id' => $selectedModalityId])
            ->with('info', 'Grupo nuevo creado y agregado al ciclo.');
    }

    public function updateSubjects(Request $request, SchoolCycleGroup $cycleGroup): RedirectResponse
    {
        $activeCampusId = (int) $request->session()->get('active_campus_id', 0);
        if ($activeCampusId > 0 && (int) $cycleGroup->campus_id !== $activeCampusId) {
            return back()->with('error', 'Solo puedes editar planeacion del campus activo.');
        }

        $data = $request->validate([
            'subjects' => 'nullable|array',
            'subjects.*' => 'exists:subjects,id',
        ]);

        $cycleGroup->loadMissing('group.level');
        $subjectIds = collect($data['subjects'] ?? [])->map(fn ($id) => (int) $id)->all();

        $validSubjects = Subject::query()
            ->whereIn('id', $subjectIds)
            ->where('level_id', $cycleGroup->group->level_id)
            ->pluck('id')
            ->all();

        $cycleGroup->subjects()->sync($validSubjects);

        return redirect()
            ->route('coordination.cycle-planning.index', ['school_cycle_id' => $cycleGroup->school_cycle_id, 'modality_id' => $cycleGroup->modality_id ?: optional($cycleGroup->group->level)->modality_id])
            ->with('info', 'Materias del grupo actualizadas para el ciclo.');
    }

    public function updateGroup(Request $request, SchoolCycleGroup $cycleGroup): RedirectResponse
    {
        $activeCampusId = (int) $request->session()->get('active_campus_id', 0);
        if ($activeCampusId > 0 && (int) $cycleGroup->campus_id !== $activeCampusId) {
            return back()->with('error', 'Solo puedes editar planeacion del campus activo.');
        }

        $cycleGroup->loadMissing('group');

        $data = $request->validate([
            'group_name' => 'required|string|max:255',
            'capacity' => 'nullable|integer|min:1|max:1000',
        ]);

        $group = $cycleGroup->group;
        if (! $group) {
            return back()->with('error', 'No se encontro el grupo a editar.');
        }

        $groupName = $this->normalizeGroupName($data['group_name']);

        $existsSameLevel = Group::query()
            ->where('level_id', $group->level_id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($groupName)])
            ->where('id', '!=', $group->id)
            ->exists();

        if ($existsSameLevel) {
            return back()->with('error', 'Ya existe un grupo con ese nombre en el mismo nivel.');
        }

        $group->update([
            'name' => $groupName,
            'capacity' => $data['capacity'] ?? null,
        ]);

        return redirect()
            ->route('coordination.cycle-planning.index', ['school_cycle_id' => $cycleGroup->school_cycle_id, 'modality_id' => $cycleGroup->modality_id ?: optional($group->level)->modality_id])
            ->with('info', 'Datos del grupo actualizados para el ciclo.');
    }

    public function deactivateGroup(SchoolCycleGroup $cycleGroup): RedirectResponse
    {
        $activeCampusId = (int) request()->session()->get('active_campus_id', 0);
        if ($activeCampusId > 0 && (int) $cycleGroup->campus_id !== $activeCampusId) {
            return back()->with('error', 'Solo puedes editar planeacion del campus activo.');
        }

        $cycleGroup->update(['is_active' => false]);

        return redirect()
            ->route('coordination.cycle-planning.index', ['school_cycle_id' => $cycleGroup->school_cycle_id, 'modality_id' => $cycleGroup->modality_id ?: optional($cycleGroup->group->level)->modality_id])
            ->with('info', 'Grupo retirado de la planeacion del ciclo.');
    }

    private function normalizeGroupName(string $name): string
    {
        return trim(preg_replace('/\s*-\s*\d{2,4}-\d+(?:\s+\d+)?$/u', '', trim($name)) ?? $name);
    }

}
