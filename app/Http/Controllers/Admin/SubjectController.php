<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Level;
use App\Http\Requests\SubjectRequest;

class SubjectController extends Controller
{
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
            'type' => $request->type,
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
            'type' => $request->type,
            'is_active' => $request->has('is_active'),
        ]);
        return redirect()->route('subjects.index')->with('info', 'Materia actualizada correctamente');
    }

    public function deactivate(Subject $subject) {
        if (! $subject->is_active) {
            return back()->with('info', 'La materia ya está dada de baja');
        }
        $subject->update(['is_active' => false]);
        return back()->with('info', 'Materia dada de baja correctamente');
    }

    public function activate(Subject $subject) {
        if ($subject->is_active) {
            return back()->with('info', 'La materia ya está activa');
        }
        $subject->update(['is_active' => true]);
        return back()->with('info', 'Materia activada correctamente');
    }
}
