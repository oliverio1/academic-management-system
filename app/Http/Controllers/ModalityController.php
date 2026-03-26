<?php

namespace App\Http\Controllers;

use App\Http\Requests\ModalityRequest;
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

    public function store(ModalityRequest $request) {
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

    public function update(ModalityRequest $request, $id) {
        Modality::findOrFail($id)->update([
            'name' => $request->name
        ]);
        return redirect()->route('modalities.index')->with('info', 'Registro actualizado exitosamente');
    }


}
