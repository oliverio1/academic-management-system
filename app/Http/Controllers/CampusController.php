<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\ActivatableController;
use App\Http\Requests\CampusRequest;
use App\Models\Campus;

class CampusController extends Controller
{
    use ActivatableController;

    protected $activeColumn = 'is_active';

    public function index()
    {
        $campuses = Campus::query()->orderBy('name')->get();
        return view('campuses.index', compact('campuses'));
    }

    public function create()
    {
        return view('campuses.create');
    }

    public function store(CampusRequest $request)
    {
        Campus::create([
            'name' => $request->name,
            'code' => mb_strtoupper(trim((string) $request->code)),
            'is_active' => true,
        ]);

        return redirect()->route('campuses.index')->with('info', 'Campus creado correctamente');
    }

    public function show(Campus $campus)
    {
        return view('campuses.show', compact('campus'));
    }

    public function edit(Campus $campus)
    {
        return view('campuses.edit', compact('campus'));
    }

    public function update(CampusRequest $request, Campus $campus)
    {
        $campus->update([
            'name' => $request->name,
            'code' => mb_strtoupper(trim((string) $request->code)),
        ]);

        return redirect()->route('campuses.index')->with('info', 'Campus actualizado correctamente');
    }
}

