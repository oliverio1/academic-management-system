<?php

namespace App\Http\Controllers;

use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Services\CurrentSchoolCycle;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoordinationCycleSubjectController extends Controller
{
    public function index(Request $request): View
    {
        $activeCampusId = (int) $request->session()->get('active_campus_id', 0);

        $cycles = SchoolCycle::with(['modality', 'modalities'])
            ->when($activeCampusId > 0, fn ($query) => $query->whereHas('campuses', fn ($campusQuery) => $campusQuery->where('campuses.id', $activeCampusId)))
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->get();

        $currentCycle = app(CurrentSchoolCycle::class)->get($request->user(), $activeCampusId);
        $defaultCycleId = $currentCycle && $cycles->contains(fn ($cycle) => (int) $cycle->id === (int) $currentCycle->id)
            ? (int) $currentCycle->id
            : (int) ($cycles->first()?->id ?? 0);
        $selectedCycleId = (int) $request->query('school_cycle_id', $defaultCycleId);
        $selectedCycle = $selectedCycleId > 0
            ? $cycles->firstWhere('id', $selectedCycleId)
            : null;

        $selectedModalityId = (int) $request->query('modality_id');
        if ($selectedCycle && $selectedModalityId <= 0) {
            $selectedModalityId = (int) ($selectedCycle->modalities->first()->id ?? $selectedCycle->modality_id);
        }

        $cycleGroups = collect();
        $subjects = collect();

        if ($selectedCycle && $selectedModalityId > 0) {
            $cycleGroups = SchoolCycleGroup::query()
                ->with([
                    'group.level',
                    'subjects' => fn ($query) => $query
                        ->with([
                            'level',
                            'temarios' => fn ($temarioQuery) => $temarioQuery
                                ->withCount('points')
                                ->latest(),
                        ])
                        ->orderBy('name'),
                ])
                ->where('school_cycle_id', $selectedCycle->id)
                ->where('modality_id', $selectedModalityId)
                ->when($activeCampusId > 0, fn ($query) => $query->where('campus_id', $activeCampusId))
                ->where('is_active', true)
                ->orderBy('group_id')
                ->get();

            $subjects = $cycleGroups
                ->flatMap(function (SchoolCycleGroup $cycleGroup) {
                    return $cycleGroup->subjects->map(function ($subject) use ($cycleGroup) {
                        return [
                            'subject' => $subject,
                            'cycle_group' => $cycleGroup,
                        ];
                    });
                })
                ->groupBy(fn ($row) => $row['subject']->id)
                ->map(function ($rows) {
                    $subject = $rows->first()['subject'];
                    $groups = $rows
                        ->pluck('cycle_group.group.name')
                        ->filter()
                        ->unique()
                        ->sort()
                        ->values();

                    return [
                        'subject' => $subject,
                        'level' => $subject->level,
                        'groups' => $groups,
                        'temarios_count' => $subject->temarios->count(),
                        'points_count' => $subject->temarios->sum(fn ($temario) => (int) ($temario->points_count ?? 0)),
                        'latest_temario' => $subject->temarios->first(),
                    ];
                })
                ->sortBy(fn ($row) => sprintf(
                    '%s|%s',
                    optional($row['level'])->name ?? '',
                    $row['subject']->name
                ))
                ->values();
        }

        return view('coordination.cycle_subjects.index', [
            'cycles' => $cycles,
            'selectedCycle' => $selectedCycle,
            'selectedModalityId' => $selectedModalityId,
            'cycleGroups' => $cycleGroups,
            'subjects' => $subjects,
        ]);
    }
}
