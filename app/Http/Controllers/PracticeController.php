<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Practice;
use App\Models\TeachingAssignment;
use App\Services\PracticeActivityService;

class PracticeController extends Controller
{
    public function index(TeachingAssignment $assignment) {
        $practices = $assignment->practices()->orderBy('number')->get();
        return view('practices.index', compact('assignment', 'practices'));
    }

    public function create(TeachingAssignment $assignment) {
        return view('practices.create', compact('assignment'));
    }

    public function store(Request $request,TeachingAssignment $assignment,PracticeActivityService $service) {
        $data = $request->validate([
            'number' => 'required|integer|min:1',
            'title' => 'required|string|max:255',
            'instructions' => 'nullable|string',
            'due_date' => 'nullable|date',
            'questionnaire' => 'nullable|string',
        ]);
    
        $data['questionnaire'] = $data['questionnaire']
            ? json_decode($data['questionnaire'], true)
            : null;
    
        $service->createForAssignment($assignment, $data);
    
        return redirect()->route('practices.index', $assignment)->with('info', 'Práctica creada correctamente');
    }

    public function edit(Practice $practice) {
        $assignment = $practice->assignment;
        return view('practices.edit', compact('practice','assignment'));
    }

    public function update(Request $request, Practice $practice) {
        $data = $request->validate([
            'number' => 'required|integer|min:1',
            'title' => 'required|string|max:255',
            'instructions' => 'nullable|string',
            'due_date' => 'nullable|date',
            'questionnaire' => 'nullable|string',
        ]);
        $data['questionnaire'] = $data['questionnaire']
        ? json_decode($data['questionnaire'], true)
        : null;
        $practice->update($data);
        return redirect()->route('practices.index', $practice->teachingAssignment)->with('info', 'Práctica actualizada');
    }

    public function destroy(Practice $practice) {
        $assignment = $practice->teachingAssignment;
        $practice->delete();
        return redirect()->route('practices.index', $assignment)->with('info', 'Práctica eliminada');
    }
}
