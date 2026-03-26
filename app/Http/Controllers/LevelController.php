<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Level;
use App\Models\Modality;
use App\Http\Controllers\Traits\ActivatableController;

class LevelController extends Controller
{
    use ActivatableController;

    protected $activeColumn = 'is_active';

    public function index() {
        $levels = Level::get();
        return view('levels.index', compact('levels'));
    }

    public function create() {
        $modalities = Modality::get();
        return view('levels.create', compact('modalities'));
    }

    public function store(Request $request) {
        $request->validate([
            'modality_id' => 'required',
            'name' => 'required|string|max:255'
        ]);
        $level = Level::create([
            'modality_id' => $request->modality_id,
            'name' => $request->name,
            'is_active' => true
        ]);
        return redirect()->route('levels.index')->with('info', 'Registro guardado exitosamente');
    }

    public function show(Level $level) {
        return view('levels.show', compact('level'));
    }

    public function edit(Level $level) {
        $modalities = Modality::get();
        return view('levels.edit', compact('level', 'modalities'));
    }

    public function update(Request $request, $id) {
        $request->validate([
            'modality_id' => 'required',
            'name' => 'required|string|max:255',
        ]);
        Level::find($id)->update([
            'modality_id' => $request->modality_id,
            'name' => $request->name
        ]);
        return redirect()->route('levels.index')->with('info', 'Registro actualizado exitosamente');
    }


}
