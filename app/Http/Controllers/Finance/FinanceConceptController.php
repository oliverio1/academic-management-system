<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\FinanceConcept;
use Illuminate\Http\Request;

class FinanceConceptController extends Controller
{
    public function index()
    {
        $concepts = FinanceConcept::query()->orderBy('name')->get();
        return view('coordination.finance.concepts.index', compact('concepts'));
    }

    public function create()
    {
        return view('coordination.finance.concepts.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'default_amount' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['campus_id'] = (int) session('active_campus_id', 0) ?: null;
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        FinanceConcept::create($data);

        return redirect()->route('coordination.finance.concepts.index')->with('success', 'Concepto creado.');
    }

    public function edit(FinanceConcept $concept)
    {
        return view('coordination.finance.concepts.edit', compact('concept'));
    }

    public function update(Request $request, FinanceConcept $concept)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'default_amount' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $concept->update($data);

        return redirect()->route('coordination.finance.concepts.index')->with('success', 'Concepto actualizado.');
    }
}

