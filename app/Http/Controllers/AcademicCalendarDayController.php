<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AcademicCalendarDay;
use App\Models\Modality;
use Carbon\Carbon;

class AcademicCalendarDayController extends Controller
{
    public function index() {
        $days = AcademicCalendarDay::with('modality')->orderBy('date')->get();
        return view('admin.calendar.index', compact('days'));
    }

    public function create() {
        $modalities = Modality::get();
        return view('admin.calendar.create', compact('modalities'));
    }

    public function store(Request $request) {
        $request->validate([
            'type' => 'required|in:holiday,vacation',
            'name' => 'required|string|max:255',
            'modality_ids' => 'nullable|array',
            'modality_ids.*' => 'exists:modalities,id',
        ]);
        $modalityIds = collect($request->input('modality_ids', []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $targetModalities = $modalityIds->isEmpty()
            ? collect([null])
            : $modalityIds;
        if ($request->type === 'holiday') {
            $request->validate([
                'date' => ['required', 'date'],
            ]);
            foreach ($targetModalities as $modalityId) {
                AcademicCalendarDay::updateOrCreate(
                    [
                        'date' => Carbon::parse($request->date)->toDateString(),
                        'modality_id' => $modalityId,
                    ],
                    [
                        'type' => 'holiday',
                        'name' => $request->name,
                        'affects_teachers' => $request->has('affects_teachers'),
                        'affects_students' => $request->has('affects_students'),
                    ]
                );
            }
        }
        if ($request->type === 'vacation') {
            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);
            $period = Carbon::parse($request->start_date)
                ->daysUntil(Carbon::parse($request->end_date)->addDay());
            foreach ($period as $date) {
                foreach ($targetModalities as $modalityId) {
                    AcademicCalendarDay::updateOrCreate(
                        [
                            'date' => $date->toDateString(),
                            'modality_id' => $modalityId,
                        ],
                        [
                            'type' => 'vacation',
                            'name' => $request->name,
                            'modality_id' => $modalityId,
                            'affects_teachers' => $request->has('affects_teachers'),
                            'affects_students' => $request->has('affects_students'),
                        ]
                    );
                }
            }
        }
        return redirect()->route('academic-calendar-days.index')->with('success', 'Calendario actualizado correctamente');
    }

    public function destroy(AcademicCalendarDay $academicCalendarDay) {
        $academicCalendarDay->delete();
        return back()->with('success', 'Día eliminado');
    }
}
