<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Models\Level;
use App\Http\Requests\SubjectRequest;
use App\Http\Controllers\Traits\ActivatableController;

class SubjectController extends Controller
{
    use ActivatableController;

    protected $activeColumn = 'is_active';

    public function index() {
        $subjects = Subject::with('level')->get();
        return view('subjects.index', compact('subjects'));
    }

    public function create() {
        $levels = Level::orderBy('name')->get();
        return view('subjects.create', compact('levels'));
    }

    public function store(SubjectRequest $request) {
        Subject::create([
            'level_id' => $request->level_id,
            'name' => $request->name,
            'hours_per_week' => $request->hours_per_week,
            'weekly_theory_hours' => $request->weekly_theory_hours,
            'weekly_practice_hours' => $request->weekly_practice_hours,
            'annual_hours' => $request->annual_hours,
            'annual_theory_hours' => $request->annual_theory_hours,
            'annual_practice_hours' => $request->annual_practice_hours,
            'type' => $request->type,
            'subject_character' => $request->subject_character,
            'subject_key' => $request->subject_key,
            'is_active' => true,
        ]);
        return redirect()->route('subjects.index')->with('info', 'Materia creada correctamente');
    }

    public function edit(Subject $subject) {
        $levels = Level::orderBy('name')->get();
        return view('subjects.edit', compact('subject', 'levels'));
    }

    public function update(SubjectRequest $request, Subject $subject) {
        $subject->update([
            'level_id' => $request->level_id,
            'name' => $request->name,
            'hours_per_week' => $request->hours_per_week,
            'weekly_theory_hours' => $request->weekly_theory_hours,
            'weekly_practice_hours' => $request->weekly_practice_hours,
            'annual_hours' => $request->annual_hours,
            'annual_theory_hours' => $request->annual_theory_hours,
            'annual_practice_hours' => $request->annual_practice_hours,
            'type' => $request->type,
            'subject_character' => $request->subject_character,
            'subject_key' => $request->subject_key,
            'is_active' => $request->has('is_active'),
        ]);
        return redirect()->route('subjects.index')->with('info', 'Materia actualizada correctamente');
    }

    public function destroy(Subject $subject) {
        $subject->delete();
        return redirect()->route('subjects.index')->with('info', 'Materia eliminada correctamente');
    }
}
