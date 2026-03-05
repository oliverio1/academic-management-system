<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\TeachingAssignment;
use App\Models\AcademicPeriod;
use App\Models\EvaluationCriterion;
use Illuminate\Http\Request;
use App\Services\AcademicCalendarService;
use Carbon\Carbon;

class ActivityController extends Controller
{
    public function index(TeachingAssignment $assignment) {
        $activities = $assignment->activities()->where('is_active', true)->get();
        $otherAssignments = auth()->user()->teacher->teachingAssignments()->where('id', '!=', $assignment->id)->with('group', 'subject')->get();
        return view('activities.index', compact('assignment', 'activities', 'otherAssignments'));
    }

    public function create(TeachingAssignment $assignment)
    {
        abort_if($assignment->teacher_id !== auth()->user()->teacher->id,403);
        $periods = AcademicPeriod::where('modality_id',$assignment->group->level->modality_id)->orderBy('start_date')->get();
        $criteria = EvaluationCriterion::where('teaching_assignment_id',$assignment->id)->get();
        $activePeriod = AcademicPeriod::where('modality_id',$assignment->group->level->modality_id)->where('is_active', 1)->firstOrFail();
    
        return view('activities.create', compact('assignment','periods','criteria','activePeriod'));
    }

    public function sessionsByPeriod(
        TeachingAssignment $assignment,
        AcademicPeriod $period,
        AcademicCalendarService $calendar
    ) {
        abort_if(
            $assignment->teacher_id !== auth()->user()->teacher->id,
            403
        );
    
        $sessions = [];
        $cursor = $period->start_date->copy();
        $end = $period->end_date->copy();
    
        while ($cursor->lte($end)) {
            if (
                !$cursor->isWeekend() &&
                !$calendar->isNonWorkingDay(
                    $cursor,
                    $period->modality_id
                )
            ) {
                $dayName = $cursor->locale('es')->dayName;
    
                if (
                    $assignment->schedules()
                        ->where('day_of_week', $dayName)
                        ->exists()
                ) {
                    $sessions[] = $cursor->copy();
                }
            }
    
            $cursor->addDay();
        }
    
        $activities = Activity::where(
            'teaching_assignment_id',
            $assignment->id
        )
        ->where('academic_period_id', $period->id)
        ->get()
        ->keyBy(fn ($a) => $a->due_date->toDateString());

        $criteria = EvaluationCriterion::where(
            'teaching_assignment_id',
            $assignment->id
        )->get();
    
        return view(
            'activities.partials.sessions-table',
            compact('sessions', 'activities', 'period', 'criteria')
        );
    }
    

    public function store(Request $request, TeachingAssignment $assignment)
    {
        abort_if(
            $assignment->teacher_id !== auth()->user()->teacher->id,
            403
        );
    
        try {
            $data = $request->validate([
                'session_date' => 'required|date',
                'academic_period_id' => 'required|exists:academic_periods,id',
                'title' => 'required|string|max:255',
                'evaluation_criterion_id' => 'required|exists:evaluation_criteria,id',
                'max_score' => 'nullable|numeric|min:0',
                'description' => 'nullable|string',
                'evaluation_mode' => 'required|in:individual,team',
            ]);
    
            $period = AcademicPeriod::where('id', $data['academic_period_id'])
                ->where('modality_id', $assignment->group->level->modality_id)
                ->first();
    
            if (!$period) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Periodo inválido'
                ], 422);
            }
    
            Activity::updateOrCreate(
                [
                    'teaching_assignment_id' => $assignment->id,
                    'academic_period_id' => $period->id,
                    'due_date' => $data['session_date'],
                ],
                [
                    'title' => $data['title'],
                    'evaluation_criterion_id' => $data['evaluation_criterion_id'],
                    'evaluation_mode' => $data['evaluation_mode'],
                    'max_score' => $data['max_score'] ?? 10,
                    'description' => $data['description'],
                    'is_active' => true,
                ]
            );
    
            return response()->json(['ok' => true]);
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            \Log::error('Error guardando actividad', [
                'error' => $e->getMessage()
            ]);
    
            return response()->json([
                'ok' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    public function edit(Activity $activity) {
        $assignment = $activity->assignment;
        $criteria = EvaluationCriterion::where('teaching_assignment_id',$assignment->id)->get();
        $periods = AcademicPeriod::where('level_id', $assignment->group->level_id)->orderBy('start_date')->get();
        return view('activities.edit', compact('activity', 'assignment', 'periods', 'criteria'));
    }

    public function update(Request $request, Activity $activity) {
        $data = $request->validate([
            'name'               => 'required|string|max:255',
            'academic_period_id' => 'required|exists:academic_periods,id',
            'weight'             => 'required|numeric|min:0|max:100',
            'due_date'           => 'required|date',
            'description'        => 'nullable|string',
            'evaluation_criterion_id' => 'required|exists:evaluation_criteria,id',
        ]);
        $assignment = $activity->assignment;
        $currentWeight = Activity::where('teaching_assignment_id', $assignment->id)->where('academic_period_id', $data['academic_period_id'])->where('id', '!=', $activity->id)->sum('weight');
        if ($currentWeight + $data['weight'] > 100) {
            return back()->withInput()->withErrors(['weight' => 'La ponderación del periodo excede el 100%']);
        }
        $activity->update($data);
        return redirect()->route('activities.index', $assignment)->with('success', 'Actividad actualizada correctamente');
    }

    public function show(Activity $activity) {
        $assignment = TeachingAssignment::findOrFail($activity->teaching_assignment_id);
        $students = $assignment->group->students()->with(['user','grades' => fn ($q) => $q->where('activity_id', $activity->id)])->get();
        return view('activities.show', compact('activity', 'students'));
    }

    public function inlineUpdate(
        Request $request,
        TeachingAssignment $assignment
    ) {
        abort_if(
            $assignment->teacher_id !== auth()->user()->teacher->id,
            403
        );
    
        $period = AcademicPeriod::whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->firstOrFail();
    
        $activity = Activity::firstOrCreate(
            [
                'teaching_assignment_id' => $assignment->id,
                'academic_period_id' => $period->id,
                'due_date' => $request->session_date,
            ],
            [
                'max_score' => 10,
                'is_active' => true,
            ]
        );
    
        $activity->update([
            $request->field => $request->value,
        ]);
    
        return response()->json(['ok' => true]);
    }    
}
