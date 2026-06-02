<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\FinanceCharge;
use App\Models\Finance\FinancePayment;
use App\Models\Finance\FinancePaymentApplication;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceStudentStatementController extends Controller
{
    public function index()
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        $students = $this->studentsForCampus($activeCampusId);

        return view('coordination.finance.statements.index', compact('students'));
    }

    public function show(Student $student)
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        abort_if($activeCampusId <= 0, 404);

        $charges = FinanceCharge::query()
            ->with('concept')
            ->where('student_id', $student->id)
            ->where('campus_id', $activeCampusId)
            ->latest('due_date')
            ->get();

        $payments = FinancePayment::query()
            ->with('applications.charge')
            ->where('student_id', $student->id)
            ->where('campus_id', $activeCampusId)
            ->latest('payment_date')
            ->get();

        $pendingCharges = $charges
            ->filter(fn ($charge) => in_array($charge->status, ['pending', 'partial'], true))
            ->values();

        return view('coordination.finance.statements.show', compact('student', 'charges', 'payments', 'pendingCharges'));
    }

    public function storePayment(Request $request, Student $student)
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        abort_if($activeCampusId <= 0, 404);

        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,transfer,card,other'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'applications' => ['nullable', 'array'],
            'applications.*.charge_id' => ['required_with:applications', 'exists:finance_charges,id'],
            'applications.*.amount' => ['required_with:applications', 'numeric', 'min:0.01'],
        ]);

        DB::transaction(function () use ($data, $student, $activeCampusId) {
            $payment = FinancePayment::create([
                'campus_id' => $activeCampusId,
                'student_id' => $student->id,
                'payment_date' => $data['payment_date'],
                'amount' => $data['amount'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'unapplied',
                'created_by' => auth()->id(),
            ]);

            $totalApplied = 0.0;
            foreach (($data['applications'] ?? []) as $row) {
                $charge = FinanceCharge::query()
                    ->where('id', (int) $row['charge_id'])
                    ->where('student_id', $student->id)
                    ->where('campus_id', $activeCampusId)
                    ->lockForUpdate()
                    ->first();

                if (! $charge) {
                    continue;
                }

                $pending = $charge->pendingAmount();
                $toApply = min((float) $row['amount'], $pending, (float) $data['amount'] - $totalApplied);
                if ($toApply <= 0) {
                    continue;
                }

                FinancePaymentApplication::create([
                    'payment_id' => $payment->id,
                    'charge_id' => $charge->id,
                    'applied_amount' => $toApply,
                    'applied_at' => now(),
                    'created_by' => auth()->id(),
                ]);

                $totalApplied += $toApply;
                $this->refreshChargeStatus($charge);
            }

            $payment->status = $totalApplied <= 0
                ? 'unapplied'
                : ($totalApplied < (float) $payment->amount ? 'partially_applied' : 'applied');
            $payment->save();
        });

        return redirect()->route('coordination.finance.statements.show', $student)->with('success', 'Pago registrado.');
    }

    private function refreshChargeStatus(FinanceCharge $charge): void
    {
        $applied = (float) $charge->applications()->sum('applied_amount');
        $amount = (float) $charge->amount;

        if ($applied <= 0) {
            $charge->status = 'pending';
        } elseif ($applied >= $amount) {
            $charge->status = 'paid';
        } else {
            $charge->status = 'partial';
        }

        $charge->save();
    }

    private function studentsForCampus(int $activeCampusId)
    {
        if ($activeCampusId <= 0) {
            return collect();
        }

        $activeCycleIds = SchoolCycle::query()
            ->where('is_active', true)
            ->where('campus_id', $activeCampusId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $groupIds = SchoolCycleGroup::query()
            ->whereIn('school_cycle_id', $activeCycleIds)
            ->where('campus_id', $activeCampusId)
            ->where('is_active', true)
            ->pluck('group_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        return Student::query()
            ->with('user', 'group')
            ->whereIn('group_id', $groupIds)
            ->get()
            ->sortBy(fn ($s) => mb_strtolower($s->user->name ?? ''))
            ->values();
    }
}

