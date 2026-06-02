<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\FinanceCharge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceDashboardController extends Controller
{
    public function index(Request $request)
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        $today = now()->toDateString();

        $rows = FinanceCharge::query()
            ->join('students', 'students.id', '=', 'finance_charges.student_id')
            ->join('users', 'users.id', '=', 'students.user_id')
            ->leftJoin('groups', 'groups.id', '=', 'students.group_id')
            ->leftJoin('finance_payment_applications as fpa', 'fpa.charge_id', '=', 'finance_charges.id')
            ->when($activeCampusId > 0, fn ($q) => $q->where('finance_charges.campus_id', $activeCampusId))
            ->whereIn('finance_charges.status', ['pending', 'partial'])
            ->whereDate('finance_charges.due_date', '<', $today)
            ->groupBy('finance_charges.student_id', 'users.name', 'groups.name')
            ->selectRaw('
                finance_charges.student_id,
                users.name as student_name,
                MAX(groups.name) as group_name,
                SUM(finance_charges.amount) as total_charged,
                COALESCE(SUM(fpa.applied_amount),0) as total_applied,
                SUM(finance_charges.amount) - COALESCE(SUM(fpa.applied_amount),0) as overdue_balance
            ')
            ->orderByDesc('overdue_balance')
            ->get();

        return view('coordination.finance.dashboard', compact('rows'));
    }
}

