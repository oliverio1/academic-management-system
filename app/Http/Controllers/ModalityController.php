<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Modality;
use App\Http\Controllers\Traits\ActivatableController;

class ModalityController extends Controller
{
    use ActivatableController;

    protected $activeColumn = 'is_active';

    public function index() {
        $modalities = Modality::get();
        return view('modalities.index', compact('modalities'));
    }

    public function create() {
        return view('modalities.create');
    }

    public function store(Request $request) {
        $request->validate([
            'name' => 'required|string|max:255'
        ]);
        $modality = Modality::create([
            'name' => $request->name,
            'is_active' => true
        ]);
        return redirect()->route('modalities.index')->with('info', 'Registro guardado exitosamente');
    }

    public function show(Modality $modality) {
        return view('modalities.show', compact('modality'));
    }

    public function edit(Modality $modality) {
        return view('modalities.edit', compact('modality'));
    }

    public function update(Request $request, $id) {
        $request->validate([
            'name' => 'required|string|max:255'
        ]);
        Modality::find($id)->update([
            'name' => $request->name
        ]);
        return redirect()->route('modalities.index')->with('info', 'Registro actualizado exitosamente');
    }


}
