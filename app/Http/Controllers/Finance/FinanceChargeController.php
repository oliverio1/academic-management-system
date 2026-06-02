<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\FinanceCharge;
use App\Models\Finance\FinanceConcept;
use App\Models\Group;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceChargeController extends Controller
{
    public function index(Request $request)
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        $studentId = $request->integer('student_id') ?: null;

        $charges = FinanceCharge::query()
            ->with(['student.user', 'student.group', 'concept'])
            ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
            ->when($studentId, fn ($q) => $q->where('student_id', $studentId))
            ->latest('due_date')
            ->get();

        return view('coordination.finance.charges.index', compact('charges', 'studentId'));
    }

    public function create()
    {
        $activeCampusId = (int) session('active_campus_id', 0);

        $concepts = FinanceConcept::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $activeCycleIds = SchoolCycle::query()
            ->where('is_active', true)
            ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $groupIds = SchoolCycleGroup::query()
            ->whereIn('school_cycle_id', $activeCycleIds)
            ->where('is_active', true)
            ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
            ->pluck('group_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $students = Student::query()
            ->with('user')
            ->whereIn('group_id', $groupIds)
            ->orderBy('group_id')
            ->get()
            ->sortBy(fn ($s) => mb_strtolower($s->user->name ?? ''))
            ->values();

        return view('coordination.finance.charges.create', compact('concepts', 'students'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'concept_id' => ['required', 'exists:finance_concepts,id'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'school_cycle_id' => ['nullable', 'exists:school_cycles,id'],
        ]);

        $data['campus_id'] = (int) session('active_campus_id', 0);
        $data['issued_at'] = now();
        $data['status'] = 'pending';
        $data['created_by'] = auth()->id();

        FinanceCharge::create($data);

        return redirect()->route('coordination.finance.charges.index')->with('success', 'Cargo creado.');
    }

    public function createMassive()
    {
        $activeCampusId = (int) session('active_campus_id', 0);

        $concepts = FinanceConcept::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $activeCycles = SchoolCycle::query()
            ->where('is_active', true)
            ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
            ->orderByDesc('start_date')
            ->get();

        $activeCycleIds = $activeCycles->pluck('id')->map(fn ($id) => (int) $id)->all();

        $groupIds = SchoolCycleGroup::query()
            ->whereIn('school_cycle_id', $activeCycleIds)
            ->where('is_active', true)
            ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
            ->pluck('group_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $groups = Group::query()
            ->whereIn('id', $groupIds)
            ->orderBy('name')
            ->get();

        return view('coordination.finance.charges.massive', compact('concepts', 'activeCycles', 'groups'));
    }

    public function storeMassive(Request $request)
    {
        $activeCampusId = (int) session('active_campus_id', 0);

        $data = $request->validate([
            'group_id' => ['required', 'exists:groups,id'],
            'concept_id' => ['required', 'exists:finance_concepts,id'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'school_cycle_id' => ['nullable', 'exists:school_cycles,id'],
        ]);

        $groupAllowed = SchoolCycleGroup::query()
            ->when($data['school_cycle_id'] ?? null, fn ($q) => $q->where('school_cycle_id', (int) $data['school_cycle_id']))
            ->where('group_id', (int) $data['group_id'])
            ->where('is_active', true)
            ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
            ->exists();

        if (! $groupAllowed) {
            return back()->withInput()->withErrors([
                'group_id' => 'El grupo no está disponible para el campus/ciclo activo.',
            ]);
        }

        $students = Student::query()
            ->where('group_id', (int) $data['group_id'])
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        if (empty($students)) {
            return back()->withInput()->withErrors([
                'group_id' => 'El grupo no tiene alumnos activos para generar cargos.',
            ]);
        }

        $created = 0;
        DB::transaction(function () use ($students, $data, $activeCampusId, &$created) {
            foreach ($students as $studentId) {
                FinanceCharge::create([
                    'campus_id' => $activeCampusId,
                    'school_cycle_id' => $data['school_cycle_id'] ?? null,
                    'student_id' => $studentId,
                    'concept_id' => (int) $data['concept_id'],
                    'reference' => $data['reference'] ?? null,
                    'description' => $data['description'],
                    'amount' => $data['amount'],
                    'due_date' => $data['due_date'],
                    'issued_at' => now(),
                    'status' => 'pending',
                    'created_by' => auth()->id(),
                ]);
                $created++;
            }
        });

        return redirect()->route('coordination.finance.charges.index')
            ->with('success', "Carga masiva completada. Cargos creados: {$created}.");
    }
}
