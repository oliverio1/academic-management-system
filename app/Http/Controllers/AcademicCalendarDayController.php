<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AcademicCalendarDay;
use App\Models\Modality;

class AcademicCalendarDayController extends Controller
{
    public function index() {
        $days = AcademicCalendarDay::orderBy('date')->get();
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
        ]);
        if ($request->type === 'holiday') {
            $request->validate([
                'date' => 'required|date|unique:academic_calendar_days,date',
            ]);
            AcademicCalendarDay::create([
                'date' => $request->date,
                'type' => 'holiday',
                'name' => $request->name,
                'affects_teachers' => $request->has('affects_teachers'),
                'affects_students' => $request->has('affects_students'),
            ]);
        }
        if ($request->type === 'vacation') {
            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);
            $period = Carbon::parse($request->start_date)->daysUntil(Carbon::parse($request->end_date));
            foreach ($period as $date) {
                AcademicCalendarDay::firstOrCreate(
                    ['date' => $date->toDateString()],
                    [
                        'type' => 'vacation',
                        'name' => $request->name,
                        'modality_id' => $request->modality_id,
                        'affects_teachers' => $request->has('affects_teachers'),
                        'affects_students' => $request->has('affects_students'),
                    ]
                );
            }
        }
        return redirect()->route('academic-calendar-days.index')->with('success', 'Calendario actualizado correctamente');
    }

    public function destroy(AcademicCalendarDay $academicCalendarDay) {
        $academicCalendarDay->delete();

        return back()->with('success', 'Día eliminado');
    }
}
