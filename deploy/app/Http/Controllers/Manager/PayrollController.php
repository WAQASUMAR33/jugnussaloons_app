<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountLedger;
use App\Models\Appointment;
use App\Models\BankAccount;
use App\Models\Commission;
use App\Models\Payroll;
use App\Models\PayrollDeduction;
use App\Models\SalaryAdvance;
use App\Models\StaffAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    /**
     * Calculate total commissions earned by an employee in a given month from:
     * 1) Commissions table (Manager -> Commissions)
     * 2) Appointments table (total_commission)
     * 3) AccountLedger table (type = 'commission')
     */
    public static function calculateCommissionForMonth($employeeId, $year, $month): float
    {
        $commTableSum = (float) Commission::where('employee_id', $employeeId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->sum('total_amount');

        $appointmentsSum = (float) Appointment::where('employee_id', $employeeId)
            ->whereYear('appointment_date', $year)
            ->whereMonth('appointment_date', $month)
            ->sum('total_commission');

        $ledgerSum = (float) AccountLedger::where('account_id', $employeeId)
            ->where('type', 'commission')
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->sum('credit');

        return round($commTableSum + $appointmentsSum + $ledgerSum, 2);
    }

    /**
     * Check if user has permission to manage payroll (Admin or Manager).
     */
    public static function isAuthorized(): bool
    {
        if (!Auth::check()) {
            return false;
        }
        $user = Auth::user();
        return $user->hasRole('admin') || $user->role === 'admin' || $user->hasRole('manager') || $user->role === 'manager';
    }

    /**
     * Get complete auto-calculated stats for an employee for a specific month.
     */
    public static function getEmployeeMonthlyStats($accountId, $monthYear): array
    {
        $parts = explode('-', $monthYear);
        $year = (int) ($parts[0] ?? date('Y'));
        $month = (int) ($parts[1] ?? date('m'));

        $employee = Account::find($accountId);
        if (!$employee) {
            return [];
        }

        $baseSalary = (float) ($employee->salary ?? 0);

        // 1. Commission
        $totalCommission = self::calculateCommissionForMonth($accountId, $year, $month);

        // 2. Itemized Deductions
        $deductionsQuery = PayrollDeduction::where('account_id', $accountId)
            ->where('month_year', $monthYear);
        $totalDeductions = (float) $deductionsQuery->sum('amount');
        $deductionItems = $deductionsQuery->get()->toArray();

        // 3. Salary Advances
        $advancesQuery = SalaryAdvance::where('account_id', $accountId)
            ->where('month_year', $monthYear);
        $totalAdvances = (float) $advancesQuery->sum('amount');
        $advanceItems = $advancesQuery->get()->toArray();

        // 4. Attendance
        $attendances = StaffAttendance::where('account_id', $accountId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get();

        $presents = $attendances->where('status', 'present')->count();
        $lates = $attendances->where('status', 'late')->count();
        $halfDays = $attendances->where('status', 'half_day')->count();
        $leaves = $attendances->where('status', 'leave')->count();
        $absents = $attendances->where('status', 'absent')->count();
        $takenLeavesEquivalent = (float) ($absents + $leaves + ($halfDays * 0.5));

        $allowedLeaves = 2; // Standard default

        $perDayRate = $baseSalary > 0 ? ($baseSalary / 30) : 0;
        $extraLeaves = max(0, $takenLeavesEquivalent - $allowedLeaves);
        $leaveDeduction = round($extraLeaves * $perDayRate, 2);

        $netSalary = max(0, round($baseSalary - $leaveDeduction + $totalCommission - $totalDeductions - $totalAdvances, 2));

        return [
            'account_id' => $accountId,
            'employee_name' => $employee->name,
            'month_year' => $monthYear,
            'base_salary' => $baseSalary,
            'allowed_leaves' => $allowedLeaves,
            'taken_leaves' => $takenLeavesEquivalent,
            'leave_deduction' => $leaveDeduction,
            'total_commission' => $totalCommission,
            'deductions' => $totalDeductions,
            'deduction_items' => $deductionItems,
            'advance_salary' => $totalAdvances,
            'advance_items' => $advanceItems,
            'net_salary' => $netSalary,
            'attendance' => [
                'present' => $presents,
                'late' => $lates,
                'half_day' => $halfDays,
                'leave' => $leaves,
                'absent' => $absents,
                'total_logged' => $attendances->count(),
            ],
        ];
    }

    /**
     * Display payroll list & calculation dashboard.
     */
    public function index(Request $request)
    {
        $monthYear = $request->input('month_year', date('Y-m'));
        $search = $request->input('search');
        $status = $request->input('status');

        $query = Payroll::with(['employee', 'deductionItems', 'advanceItems'])->where('month_year', $monthYear);

        if ($search) {
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone_no1', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $allMonthPayrolls = (clone $query)->orderBy('created_at', 'desc')->get();
        $payrolls = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        // Fetch Employee accounts
        $employees = Account::whereHas('category', function ($q) {
            $q->where('title', 'like', '%Employee%')
              ->orWhere('title', 'like', '%Staff%');
        })->orderBy('name')->get();

        if ($employees->isEmpty()) {
            $employees = Account::whereHas('category', function ($q) {
                $q->where('title', 'not like', '%Supplier%')
                  ->where('title', 'not like', '%Vendor%');
            })->orWhereDoesntHave('category')->orderBy('name')->get();
        }

        // Precalculate current month commissions for employees from all sources (Commissions table, Appointments, Ledger)
        $parts = explode('-', $monthYear);
        $year = (int) ($parts[0] ?? date('Y'));
        $month = (int) ($parts[1] ?? date('m'));

        $employeeCommissions = [];
        foreach ($employees as $emp) {
            $employeeCommissions[$emp->id] = self::calculateCommissionForMonth($emp->id, $year, $month);
        }

        // Fetch itemized deductions for preloading in creation modal
        $deductions = PayrollDeduction::with(['employee', 'creator'])
            ->where('month_year', $monthYear)
            ->get();

        $employeeDeductionTotals = [];
        $employeeDeductionList = [];
        foreach ($employees as $emp) {
            $empDeds = $deductions->where('account_id', $emp->id);
            $employeeDeductionTotals[$emp->id] = (float) $empDeds->sum('amount');
            $employeeDeductionList[$emp->id] = $empDeds->values()->toArray();
        }

        // Fetch itemized advances for current month
        $advances = SalaryAdvance::with(['employee', 'creator', 'bankAccount'])
            ->where('month_year', $monthYear)
            ->get();

        $employeeAdvanceTotals = [];
        $employeeAdvanceList = [];
        foreach ($employees as $emp) {
            $empAdvs = $advances->where('account_id', $emp->id);
            $employeeAdvanceTotals[$emp->id] = (float) $empAdvs->sum('amount');
            $employeeAdvanceList[$emp->id] = $empAdvs->values()->toArray();
        }
        $totalAdvancesAmount = (float) $advances->sum('amount');

        // Fetch Attendance Summary for current month
        $attendanceRecords = \App\Models\StaffAttendance::whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get();

        $employeeAttendanceStats = [];
        foreach ($employees as $emp) {
            $empAtt = $attendanceRecords->where('account_id', $emp->id);
            $presents = $empAtt->where('status', 'present')->count();
            $lates = $empAtt->where('status', 'late')->count();
            $halfDays = $empAtt->where('status', 'half_day')->count();
            $leaves = $empAtt->where('status', 'leave')->count();
            $absents = $empAtt->where('status', 'absent')->count();

            // Total equivalent taken leaves / missed days (absents + leaves + half days * 0.5)
            $takenLeavesEquivalent = $absents + $leaves + ($halfDays * 0.5);

            $employeeAttendanceStats[$emp->id] = [
                'present' => $presents,
                'late' => $lates,
                'half_day' => $halfDays,
                'leave' => $leaves,
                'absent' => $absents,
                'total_days_logged' => $empAtt->count(),
                'calculated_taken_leaves' => $takenLeavesEquivalent,
            ];
        }

        $staffBatchPreview = [];
        foreach ($employees as $emp) {
            $existing = $payrolls->firstWhere('account_id', $emp->id);
            $comm = (float) ($existing ? $existing->total_commission : ($employeeCommissions[$emp->id] ?? 0));
            $ded = (float) ($existing ? $existing->deductions : ($employeeDeductionTotals[$emp->id] ?? 0));
            $adv = (float) ($existing ? $existing->advance_salary : ($employeeAdvanceTotals[$emp->id] ?? 0));
            $base = (float) ($existing ? $existing->base_salary : ($emp->salary ?? 0));
            $allowed = (int) ($existing ? $existing->allowed_leaves : 2);
            $taken = (int) ($existing ? $existing->taken_leaves : ($employeeAttendanceStats[$emp->id]['calculated_taken_leaves'] ?? 0));
            $bonus = (float) ($existing ? $existing->bonus : 0);
            $curStatus = $existing ? $existing->status : 'draft';

            $perDayRate = $base > 0 ? ($base / 30) : 0;
            $extraLeaves = max(0, $taken - $allowed);
            $leaveDed = round($extraLeaves * $perDayRate, 2);
            $net = max(0, round($base - $leaveDed + $comm + $bonus - $ded - $adv, 2));

            $staffBatchPreview[] = [
                'account_id' => $emp->id,
                'name' => $emp->name,
                'emp_type' => $emp->emp_type ?? 'Staff',
                'base_salary' => $base,
                'allowed_leaves' => $allowed,
                'taken_leaves' => $taken,
                'leave_deduction' => $leaveDed,
                'commission' => $comm,
                'bonus' => $bonus,
                'deductions' => $ded,
                'advance_salary' => $adv,
                'net_salary' => $net,
                'status' => $curStatus,
                'notes' => $existing ? ($existing->notes ?? '') : '',
                'has_existing' => (bool) $existing,
            ];
        }

        $isAdmin = self::isAuthorized();

        return view('manager.payroll.index', compact(
            'payrolls',
            'allMonthPayrolls',
            'employees',
            'employeeCommissions',
            'employeeDeductionTotals',
            'employeeDeductionList',
            'employeeAdvanceTotals',
            'employeeAdvanceList',
            'totalAdvancesAmount',
            'employeeAttendanceStats',
            'staffBatchPreview',
            'monthYear',
            'search',
            'status',
            'isAdmin'
        ));
    }

    /**
     * Display dedicated Staff Salary Deductions page.
     */
    public function deductionsIndex(Request $request)
    {
        $monthYear = $request->input('month_year', date('Y-m'));
        $search = $request->input('search');
        $employeeId = $request->input('employee_id');

        $query = PayrollDeduction::with(['employee', 'creator'])
            ->where('month_year', $monthYear);

        if ($employeeId) {
            $query->where('account_id', $employeeId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($eq) use ($search) {
                      $eq->where('name', 'like', "%{$search}%")
                         ->orWhere('phone_no1', 'like', "%{$search}%");
                  });
            });
        }

        $allDeductions = (clone $query)->orderBy('deduction_date', 'desc')->get();
        $deductions = $query->orderBy('deduction_date', 'desc')->paginate(20)->withQueryString();

        // Fetch Employee accounts
        $employees = Account::whereHas('category', function ($q) {
            $q->where('title', 'like', '%Employee%')
              ->orWhere('title', 'like', '%Staff%');
        })->orderBy('name')->get();

        if ($employees->isEmpty()) {
            $employees = Account::whereHas('category', function ($q) {
                $q->where('title', 'not like', '%Supplier%')
                  ->where('title', 'not like', '%Vendor%');
            })->orWhereDoesntHave('category')->orderBy('name')->get();
        }

        // Summary statistics
        $allMonthDeductions = PayrollDeduction::where('month_year', $monthYear)->get();
        $totalDeductionsAmount = (float) $allMonthDeductions->sum('amount');
        $affectedStaffCount = $allMonthDeductions->pluck('account_id')->unique()->count();
        $totalEntriesCount = $allMonthDeductions->count();

        $isAdmin = self::isAuthorized();

        return view('manager.payroll.deductions', compact(
            'deductions',
            'allDeductions',
            'employees',
            'monthYear',
            'search',
            'employeeId',
            'totalDeductionsAmount',
            'affectedStaffCount',
            'totalEntriesCount',
            'isAdmin'
        ));
    }

    /**
     * Record a specific salary deduction for a staff member (Admin Only).
     */
    public function storeDeduction(Request $request)
    {
        $isAdmin = self::isAuthorized();

        if (!$isAdmin) {
            return redirect()->back()->with('error', '⚠️ Access Denied: Only Admin users can record salary deductions.');
        }

        $validated = $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:255'],
            'deduction_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $monthYear = date('Y-m', strtotime($validated['deduction_date']));

        DB::transaction(function () use ($validated, $monthYear) {
            $payroll = Payroll::where('account_id', $validated['account_id'])
                ->where('month_year', $monthYear)
                ->first();

            PayrollDeduction::create([
                'account_id' => $validated['account_id'],
                'payroll_id' => $payroll ? $payroll->id : null,
                'month_year' => $monthYear,
                'amount' => $validated['amount'],
                'reason' => $validated['reason'],
                'deduction_date' => $validated['deduction_date'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            // Auto-update payroll deductions and net salary if payroll already exists
            if ($payroll) {
                $totalDeductions = PayrollDeduction::where('account_id', $payroll->account_id)
                    ->where('month_year', $payroll->month_year)
                    ->sum('amount');

                $netSalary = max(0, round($payroll->base_salary - $payroll->leave_deduction + $payroll->total_commission + $payroll->bonus - $totalDeductions, 2));

                $payroll->update([
                    'deductions' => $totalDeductions,
                    'net_salary' => $netSalary,
                ]);

                if ($payroll->status === 'paid') {
                    $employee = Account::find($payroll->account_id);
                    $refNo = "PAYROLL-{$payroll->month_year}-{$employee->id}";
                    AccountLedger::where('reference_no', $refNo)->update([
                        'credit' => $netSalary,
                    ]);
                }
            }
        });

        $employee = Account::find($validated['account_id']);

        return redirect()->back()
            ->with('success', "Salary deduction of PKR " . number_format($validated['amount'], 2) . " for {$employee->name} recorded successfully!");
    }

    /**
     * Delete an itemized deduction (Admin Only).
     */
    public function destroyDeduction(PayrollDeduction $deduction)
    {
        $isAdmin = self::isAuthorized();

        if (!$isAdmin) {
            return redirect()->back()->with('error', '⚠️ Access Denied: Only Admin users can delete salary deductions.');
        }

        $monthYear = $deduction->month_year;
        $accountId = $deduction->account_id;

        DB::transaction(function () use ($deduction, $monthYear, $accountId) {
            $deduction->delete();

            $payroll = Payroll::where('account_id', $accountId)
                ->where('month_year', $monthYear)
                ->first();

            if ($payroll) {
                $totalDeductions = PayrollDeduction::where('account_id', $accountId)
                    ->where('month_year', $monthYear)
                    ->sum('amount');

                $netSalary = max(0, round($payroll->base_salary - $payroll->leave_deduction + $payroll->total_commission + $payroll->bonus - $totalDeductions, 2));

                $payroll->update([
                    'deductions' => $totalDeductions,
                    'net_salary' => $netSalary,
                ]);

                if ($payroll->status === 'paid') {
                    $employee = Account::find($accountId);
                    $refNo = "PAYROLL-{$monthYear}-{$employee->id}";
                    AccountLedger::where('reference_no', $refNo)->update([
                        'credit' => $netSalary,
                    ]);
                }
            }
        });

        return redirect()->back()
            ->with('success', 'Salary deduction record removed successfully!');
    }

    /**
     * Store a newly created payroll record (Admin Only for Edit/Create).
     */
    public function store(Request $request)
    {
        $isAdmin = self::isAuthorized();
        
        if (!$isAdmin) {
            return redirect()->back()->with('error', '⚠️ Access Denied: Only Admin users can create or modify payroll records.');
        }

        $validated = $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'month_year' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'allowed_leaves' => ['required', 'integer', 'min:0'],
            'taken_leaves' => ['required', 'integer', 'min:0'],
            'total_commission' => ['nullable', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'advance_salary' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,approved,paid'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $baseSalary = (float) $validated['base_salary'];
        $allowedLeaves = (int) $validated['allowed_leaves'];
        $takenLeaves = (int) $validated['taken_leaves'];

        $parts = explode('-', $validated['month_year']);
        $year = (int) ($parts[0] ?? date('Y'));
        $month = (int) ($parts[1] ?? date('m'));
        $dbCommission = self::calculateCommissionForMonth($validated['account_id'], $year, $month);

        $totalComm = isset($validated['total_commission']) && $validated['total_commission'] !== '' && (float) $validated['total_commission'] > 0
            ? (float) $validated['total_commission']
            : (float) $dbCommission;

        $bonus = (float) ($validated['bonus'] ?? 0);
        
        $itemizedDeductSum = PayrollDeduction::where('account_id', $validated['account_id'])
            ->where('month_year', $validated['month_year'])
            ->sum('amount');

        $deductions = isset($validated['deductions']) && $validated['deductions'] !== '' && (float) $validated['deductions'] > 0
            ? (float) $validated['deductions']
            : (float) $itemizedDeductSum;

        $itemizedAdvanceSum = SalaryAdvance::where('account_id', $validated['account_id'])
            ->where('month_year', $validated['month_year'])
            ->sum('amount');

        $advanceSalary = isset($validated['advance_salary']) && $validated['advance_salary'] !== '' && (float) $validated['advance_salary'] > 0
            ? (float) $validated['advance_salary']
            : (float) $itemizedAdvanceSum;

        $perDayRate = $baseSalary > 0 ? ($baseSalary / 30) : 0;
        $extraLeaves = max(0, $takenLeaves - $allowedLeaves);
        $leaveDeduction = round($extraLeaves * $perDayRate, 2);

        $netSalary = max(0, round($baseSalary - $leaveDeduction + $totalComm + $bonus - $deductions - $advanceSalary, 2));

        DB::transaction(function () use ($validated, $baseSalary, $allowedLeaves, $takenLeaves, $leaveDeduction, $totalComm, $bonus, $deductions, $advanceSalary, $netSalary) {
            $payroll = Payroll::updateOrCreate(
                [
                    'account_id' => $validated['account_id'],
                    'month_year' => $validated['month_year'],
                ],
                [
                    'base_salary' => $baseSalary,
                    'allowed_leaves' => $allowedLeaves,
                    'taken_leaves' => $takenLeaves,
                    'leave_deduction' => $leaveDeduction,
                    'total_commission' => $totalComm,
                    'bonus' => $bonus,
                    'deductions' => $deductions,
                    'advance_salary' => $advanceSalary,
                    'net_salary' => $netSalary,
                    'status' => $validated['status'],
                    'payment_date' => $validated['status'] === 'paid' ? ($validated['payment_date'] ?? date('Y-m-d')) : null,
                    'notes' => $validated['notes'] ?? null,
                ]
            );

            PayrollDeduction::where('account_id', $validated['account_id'])
                ->where('month_year', $validated['month_year'])
                ->update(['payroll_id' => $payroll->id]);

            SalaryAdvance::where('account_id', $validated['account_id'])
                ->where('month_year', $validated['month_year'])
                ->update([
                    'payroll_id' => $payroll->id,
                    'status' => $validated['status'] === 'paid' ? 'recovered' : 'pending_recovery',
                ]);

            if ($validated['status'] === 'paid') {
                $employee = Account::find($validated['account_id']);
                $refNo = "PAYROLL-{$validated['month_year']}-{$employee->id}";

                AccountLedger::where('reference_no', $refNo)->delete();

                $newBalance = $employee->balance + $netSalary;
                AccountLedger::create([
                    'account_id' => $employee->id,
                    'date' => $validated['payment_date'] ?? date('Y-m-d'),
                    'type' => 'payment',
                    'reference_no' => $refNo,
                    'description' => "Monthly Net Salary Disbursement for {$validated['month_year']} (Base: {$baseSalary}, Comm: {$totalComm}, Bonus: {$bonus}, Deduct: {$deductions}, Advance: {$advanceSalary}, Net: {$netSalary})",
                    'debit' => 0.00,
                    'credit' => $netSalary,
                    'running_balance' => $newBalance,
                ]);

                $employee->update(['balance' => $newBalance]);
            }
        });

        return redirect()->route('manager.payroll.index', ['month_year' => $validated['month_year']])
            ->with('success', 'Employee payroll generated and net salary calculated successfully!');
    }

    /**
     * Update specified payroll record (Admin Only).
     */
    public function update(Request $request, Payroll $payroll)
    {
        $isAdmin = self::isAuthorized();

        if (!$isAdmin) {
            return redirect()->back()->with('error', '⚠️ Access Denied: Only Admin users can modify payroll records.');
        }

        $validated = $request->validate([
            'base_salary' => ['required', 'numeric', 'min:0'],
            'allowed_leaves' => ['required', 'integer', 'min:0'],
            'taken_leaves' => ['required', 'integer', 'min:0'],
            'total_commission' => ['nullable', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'advance_salary' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,approved,paid'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $baseSalary = (float) $validated['base_salary'];
        $allowedLeaves = (int) $validated['allowed_leaves'];
        $takenLeaves = (int) $validated['taken_leaves'];

        $parts = explode('-', $payroll->month_year);
        $year = (int) ($parts[0] ?? date('Y'));
        $month = (int) ($parts[1] ?? date('m'));
        $dbCommission = self::calculateCommissionForMonth($payroll->account_id, $year, $month);

        $totalComm = isset($validated['total_commission']) && $validated['total_commission'] !== '' && (float) $validated['total_commission'] > 0
            ? (float) $validated['total_commission']
            : (float) $dbCommission;

        $bonus = (float) ($validated['bonus'] ?? 0);

        $itemizedDeductSum = PayrollDeduction::where('account_id', $payroll->account_id)
            ->where('month_year', $payroll->month_year)
            ->sum('amount');

        $deductions = isset($validated['deductions']) && $validated['deductions'] !== '' && (float) $validated['deductions'] > 0
            ? (float) $validated['deductions']
            : (float) $itemizedDeductSum;

        $itemizedAdvanceSum = SalaryAdvance::where('account_id', $payroll->account_id)
            ->where('month_year', $payroll->month_year)
            ->sum('amount');

        $advanceSalary = isset($validated['advance_salary']) && $validated['advance_salary'] !== '' && (float) $validated['advance_salary'] > 0
            ? (float) $validated['advance_salary']
            : (float) $itemizedAdvanceSum;

        $perDayRate = $baseSalary > 0 ? ($baseSalary / 30) : 0;
        $extraLeaves = max(0, $takenLeaves - $allowedLeaves);
        $leaveDeduction = round($extraLeaves * $perDayRate, 2);

        $netSalary = max(0, round($baseSalary - $leaveDeduction + $totalComm + $bonus - $deductions - $advanceSalary, 2));

        DB::transaction(function () use ($payroll, $validated, $baseSalary, $allowedLeaves, $takenLeaves, $leaveDeduction, $totalComm, $bonus, $deductions, $advanceSalary, $netSalary) {
            $payroll->update([
                'base_salary' => $baseSalary,
                'allowed_leaves' => $allowedLeaves,
                'taken_leaves' => $takenLeaves,
                'leave_deduction' => $leaveDeduction,
                'total_commission' => $totalComm,
                'bonus' => $bonus,
                'deductions' => $deductions,
                'advance_salary' => $advanceSalary,
                'net_salary' => $netSalary,
                'status' => $validated['status'],
                'payment_date' => $validated['status'] === 'paid' ? ($validated['payment_date'] ?? date('Y-m-d')) : null,
                'notes' => $validated['notes'] ?? null,
            ]);

            SalaryAdvance::where('account_id', $payroll->account_id)
                ->where('month_year', $payroll->month_year)
                ->update([
                    'payroll_id' => $payroll->id,
                    'status' => $validated['status'] === 'paid' ? 'recovered' : 'pending_recovery',
                ]);

            if ($validated['status'] === 'paid') {
                $employee = Account::find($payroll->account_id);
                $refNo = "PAYROLL-{$payroll->month_year}-{$employee->id}";

                AccountLedger::where('reference_no', $refNo)->delete();

                $newBalance = $employee->balance + $netSalary;
                AccountLedger::create([
                    'account_id' => $employee->id,
                    'date' => $validated['payment_date'] ?? date('Y-m-d'),
                    'type' => 'payment',
                    'reference_no' => $refNo,
                    'description' => "Updated Monthly Net Salary Disbursement for {$payroll->month_year} (Base: {$baseSalary}, Comm: {$totalComm}, Bonus: {$bonus}, Deduct: {$deductions}, Advance: {$advanceSalary}, Net: {$netSalary})",
                    'debit' => 0.00,
                    'credit' => $netSalary,
                    'running_balance' => $newBalance,
                ]);

                $employee->update(['balance' => $newBalance]);
            }
        });

        return redirect()->route('manager.payroll.index', ['month_year' => $payroll->month_year])
            ->with('success', "Payroll for {$payroll->employee->name} updated successfully!");
    }

    /**
     * Remove specified payroll record (Admin Only).
     */
    public function destroy(Payroll $payroll)
    {
        $isAdmin = self::isAuthorized();

        if (!$isAdmin) {
            return redirect()->back()->with('error', '⚠️ Access Denied: Only Admin users can delete payroll records.');
        }

        $monthYear = $payroll->month_year;
        $refNo = "PAYROLL-{$monthYear}-{$payroll->account_id}";

        DB::transaction(function () use ($payroll, $refNo) {
            AccountLedger::where('reference_no', $refNo)->delete();
            SalaryAdvance::where('payroll_id', $payroll->id)->update([
                'payroll_id' => null,
                'status' => 'pending_recovery',
            ]);
            $payroll->delete();
        });

        return redirect()->route('manager.payroll.index', ['month_year' => $monthYear])
            ->with('success', 'Payroll record deleted successfully!');
    }

    /**
     * AJAX endpoint to fetch auto-calculated stats for an employee and month.
     */
    public function getEmployeeData(Request $request)
    {
        $accountId = $request->input('account_id');
        $monthYear = $request->input('month_year', date('Y-m'));

        if (!$accountId) {
            return response()->json(['error' => 'Account ID is required'], 400);
        }

        $stats = self::getEmployeeMonthlyStats($accountId, $monthYear);
        return response()->json($stats);
    }

    /**
     * Synchronize an existing payroll record with live database commissions, deductions, advances & attendance (Admin Only).
     */
    public function syncFromDatabase(Payroll $payroll)
    {
        $isAdmin = self::isAuthorized();
        if (!$isAdmin) {
            return redirect()->back()->with('error', '⚠️ Access Denied: Only Admin users can sync payroll records.');
        }

        $stats = self::getEmployeeMonthlyStats($payroll->account_id, $payroll->month_year);
        if (empty($stats)) {
            return redirect()->back()->with('error', 'Employee record not found.');
        }

        $baseSalary = (float) $payroll->base_salary > 0 ? (float) $payroll->base_salary : (float) $stats['base_salary'];
        $allowedLeaves = (int) $payroll->allowed_leaves;
        $takenLeaves = (int) $stats['taken_leaves'];
        $perDayRate = $baseSalary > 0 ? ($baseSalary / 30) : 0;
        $extraLeaves = max(0, $takenLeaves - $allowedLeaves);
        $leaveDeduction = round($extraLeaves * $perDayRate, 2);

        $totalComm = (float) $stats['total_commission'];
        $bonus = (float) $payroll->bonus;
        $deductions = (float) $stats['deductions'];
        $advanceSalary = (float) $stats['advance_salary'];

        $netSalary = max(0, round($baseSalary - $leaveDeduction + $totalComm + $bonus - $deductions - $advanceSalary, 2));

        DB::transaction(function () use ($payroll, $baseSalary, $allowedLeaves, $takenLeaves, $leaveDeduction, $totalComm, $deductions, $advanceSalary, $netSalary) {
            $payroll->update([
                'base_salary' => $baseSalary,
                'taken_leaves' => $takenLeaves,
                'leave_deduction' => $leaveDeduction,
                'total_commission' => $totalComm,
                'deductions' => $deductions,
                'advance_salary' => $advanceSalary,
                'net_salary' => $netSalary,
            ]);

            if ($payroll->status === 'paid') {
                $employee = Account::find($payroll->account_id);
                $refNo = "PAYROLL-{$payroll->month_year}-{$employee->id}";
                AccountLedger::where('reference_no', $refNo)->update([
                    'credit' => $netSalary,
                ]);
            }
        });

        return redirect()->back()->with('success', "Payroll for {$payroll->employee->name} synchronized with database (Commission: PKR " . number_format($totalComm, 2) . ", Deductions: PKR " . number_format($deductions, 2) . ")!");
    }

    /**
     * Auto-generate draft payroll for all staff for the specified month based on database records (Admin Only).
     */
    public function generateAllForMonth(Request $request)
    {
        $isAdmin = self::isAuthorized();
        if (!$isAdmin) {
            return redirect()->back()->with('error', '⚠️ Access Denied: Only Admin users can generate payroll records.');
        }

        $monthYear = $request->input('month_year', date('Y-m'));
        $batchData = $request->input('staff_payroll');

        // Handle submission from All-Staff Batch Worksheet Modal
        if (is_array($batchData) && count($batchData) > 0) {
            $savedCount = 0;
            DB::transaction(function () use ($batchData, $monthYear, &$savedCount) {
                foreach ($batchData as $row) {
                    $accountId = (int) ($row['account_id'] ?? 0);
                    if (!$accountId) continue;

                    $baseSalary = (float) ($row['base_salary'] ?? 0);
                    $allowedLeaves = (int) ($row['allowed_leaves'] ?? 2);
                    $takenLeaves = (int) ($row['taken_leaves'] ?? 0);
                    $perDayRate = $baseSalary > 0 ? ($baseSalary / 30) : 0;
                    $extraLeaves = max(0, $takenLeaves - $allowedLeaves);
                    $leaveDeduction = round($extraLeaves * $perDayRate, 2);

                    $totalComm = (float) ($row['commission'] ?? 0);
                    $bonus = (float) ($row['bonus'] ?? 0);
                    $deductions = (float) ($row['deductions'] ?? 0);
                    $advanceSalary = (float) ($row['advance_salary'] ?? 0);

                    $netSalary = max(0, round($baseSalary - $leaveDeduction + $totalComm + $bonus - $deductions - $advanceSalary, 2));
                    $status = in_array($row['status'] ?? '', ['draft', 'approved', 'paid']) ? $row['status'] : 'draft';
                    $notes = $row['notes'] ?? 'Batch generated for all staff.';
                    $paymentDate = ($status === 'paid') ? ($row['payment_date'] ?? date('Y-m-d')) : null;

                    $payroll = Payroll::updateOrCreate(
                        [
                            'account_id' => $accountId,
                            'month_year' => $monthYear,
                        ],
                        [
                            'base_salary' => $baseSalary,
                            'allowed_leaves' => $allowedLeaves,
                            'taken_leaves' => $takenLeaves,
                            'leave_deduction' => $leaveDeduction,
                            'total_commission' => $totalComm,
                            'bonus' => $bonus,
                            'deductions' => $deductions,
                            'advance_salary' => $advanceSalary,
                            'net_salary' => $netSalary,
                            'status' => $status,
                            'payment_date' => $paymentDate,
                            'notes' => $notes,
                        ]
                    );

                    PayrollDeduction::where('account_id', $accountId)
                        ->where('month_year', $monthYear)
                        ->update(['payroll_id' => $payroll->id]);

                    SalaryAdvance::where('account_id', $accountId)
                        ->where('month_year', $monthYear)
                        ->update([
                            'payroll_id' => $payroll->id,
                            'status' => $status === 'paid' ? 'recovered' : 'pending_recovery',
                        ]);

                    if ($status === 'paid') {
                        $employee = Account::find($accountId);
                        if ($employee) {
                            $refNo = "PAYROLL-{$monthYear}-{$employee->id}";
                            AccountLedger::where('reference_no', $refNo)->delete();

                            $newBalance = $employee->balance + $netSalary;
                            AccountLedger::create([
                                'account_id' => $employee->id,
                                'date' => $paymentDate ?? date('Y-m-d'),
                                'type' => 'payment',
                                'reference_no' => $refNo,
                                'description' => "Monthly Net Salary Disbursement for {$monthYear} (Base: {$baseSalary}, Comm: {$totalComm}, Bonus: {$bonus}, Deduct: {$deductions}, Advance: {$advanceSalary}, Net: {$netSalary})",
                                'debit' => 0.00,
                                'credit' => $netSalary,
                                'running_balance' => $newBalance,
                            ]);
                            $employee->update(['balance' => $newBalance]);
                        }
                    }

                    $savedCount++;
                }
            });

            return redirect()->route('manager.payroll.index', ['month_year' => $monthYear])
                ->with('success', "Successfully generated and saved payroll for all {$savedCount} staff members together for {$monthYear}!");
        }

        // Automatic DB Fetch & Generation for all staff
        $employees = Account::whereHas('category', function ($q) {
            $q->where('title', 'like', '%Employee%')
              ->orWhere('title', 'like', '%Staff%');
        })->orderBy('name')->get();

        if ($employees->isEmpty()) {
            $employees = Account::whereHas('category', function ($q) {
                $q->where('title', 'not like', '%Supplier%')
                  ->where('title', 'not like', '%Vendor%');
            })->orWhereDoesntHave('category')->orderBy('name')->get();
        }

        $generatedCount = 0;
        $updatedCount = 0;

        DB::transaction(function () use ($employees, $monthYear, &$generatedCount, &$updatedCount) {
            foreach ($employees as $emp) {
                $stats = self::getEmployeeMonthlyStats($emp->id, $monthYear);
                $existing = Payroll::where('account_id', $emp->id)->where('month_year', $monthYear)->first();

                // If already paid, do not overwrite
                if ($existing && $existing->status === 'paid') {
                    continue;
                }

                $baseSalary = (float) ($existing ? $existing->base_salary : $stats['base_salary']);
                $allowedLeaves = (int) ($existing ? $existing->allowed_leaves : $stats['allowed_leaves']);
                $takenLeaves = (int) $stats['taken_leaves'];
                $perDayRate = $baseSalary > 0 ? ($baseSalary / 30) : 0;
                $extraLeaves = max(0, $takenLeaves - $allowedLeaves);
                $leaveDeduction = round($extraLeaves * $perDayRate, 2);

                $totalComm = (float) $stats['total_commission'];
                $bonus = (float) ($existing ? $existing->bonus : 0);
                $deductions = (float) $stats['deductions'];
                $advanceSalary = (float) $stats['advance_salary'];

                $netSalary = max(0, round($baseSalary - $leaveDeduction + $totalComm + $bonus - $deductions - $advanceSalary, 2));

                $payroll = Payroll::updateOrCreate(
                    [
                        'account_id' => $emp->id,
                        'month_year' => $monthYear,
                    ],
                    [
                        'base_salary' => $baseSalary,
                        'allowed_leaves' => $allowedLeaves,
                        'taken_leaves' => $takenLeaves,
                        'leave_deduction' => $leaveDeduction,
                        'total_commission' => $totalComm,
                        'bonus' => $bonus,
                        'deductions' => $deductions,
                        'advance_salary' => $advanceSalary,
                        'net_salary' => $netSalary,
                        'status' => $existing ? $existing->status : 'draft',
                        'notes' => $existing ? $existing->notes : 'Auto-generated from database attendance, commissions and deductions.',
                    ]
                );

                PayrollDeduction::where('account_id', $emp->id)
                    ->where('month_year', $monthYear)
                    ->update(['payroll_id' => $payroll->id]);

                SalaryAdvance::where('account_id', $emp->id)
                    ->where('month_year', $monthYear)
                    ->update(['payroll_id' => $payroll->id]);

                if ($existing) {
                    $updatedCount++;
                } else {
                    $generatedCount++;
                }
            }
        });

        return redirect()->route('manager.payroll.index', ['month_year' => $monthYear])
            ->with('success', "Auto-generated {$generatedCount} new staff payroll(s) and updated {$updatedCount} draft record(s) for all staff members together!");
    }

    /**
     * Display dedicated Staff Advance Salary page.
     */
    public function advancesIndex(Request $request)
    {
        $monthYear = $request->input('month_year', date('Y-m'));
        $search = $request->input('search');
        $employeeId = $request->input('employee_id');
        $status = $request->input('status');

        $query = SalaryAdvance::with(['employee', 'creator', 'bankAccount', 'payroll'])
            ->where('month_year', $monthYear);

        if ($employeeId) {
            $query->where('account_id', $employeeId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhere('payment_mode', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($eq) use ($search) {
                      $eq->where('name', 'like', "%{$search}%")
                         ->orWhere('phone_no1', 'like', "%{$search}%");
                  });
            });
        }

        $advances = $query->orderBy('advance_date', 'desc')->paginate(20)->withQueryString();

        // Fetch Employee accounts
        $employees = Account::whereHas('category', function ($q) {
            $q->where('title', 'like', '%Employee%')
              ->orWhere('title', 'like', '%Staff%');
        })->orderBy('name')->get();

        if ($employees->isEmpty()) {
            $employees = Account::whereHas('category', function ($q) {
                $q->where('title', 'not like', '%Supplier%')
                  ->where('title', 'not like', '%Vendor%');
            })->orWhereDoesntHave('category')->orderBy('name')->get();
        }

        $bankAccounts = BankAccount::where('is_active', true)->orderBy('bank_name')->get();

        // Summary statistics
        $allMonthAdvances = SalaryAdvance::where('month_year', $monthYear)->get();
        $totalAdvancesAmount = (float) $allMonthAdvances->sum('amount');
        $totalRecoveredAmount = (float) $allMonthAdvances->where('status', 'recovered')->sum('amount');
        $totalPendingAmount = (float) $allMonthAdvances->where('status', '!=', 'recovered')->sum('amount');
        $affectedStaffCount = $allMonthAdvances->pluck('account_id')->unique()->count();
        $totalEntriesCount = $allMonthAdvances->count();

        $isAdmin = self::isAuthorized();

        return view('manager.payroll.advances', compact(
            'advances',
            'employees',
            'bankAccounts',
            'monthYear',
            'search',
            'employeeId',
            'status',
            'totalAdvancesAmount',
            'totalRecoveredAmount',
            'totalPendingAmount',
            'affectedStaffCount',
            'totalEntriesCount',
            'isAdmin'
        ));
    }

    /**
     * Record a salary advance for a staff member (Admin Only).
     */
    public function storeAdvance(Request $request)
    {
        $isAdmin = self::isAuthorized();

        if (!$isAdmin) {
            return redirect()->back()->with('error', '⚠️ Access Denied: Only Admin users can record salary advances.');
        }

        $validated = $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'advance_date' => ['required', 'date'],
            'month_year' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'payment_mode' => ['required', 'string', 'in:Cash,Bank Transfer,Cheque,Other'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,bankid'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'record_ledger' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($validated) {
            $payroll = Payroll::where('account_id', $validated['account_id'])
                ->where('month_year', $validated['month_year'])
                ->first();

            $status = ($payroll && $payroll->status === 'paid') ? 'recovered' : 'pending_recovery';

            $advance = SalaryAdvance::create([
                'account_id' => $validated['account_id'],
                'payroll_id' => $payroll ? $payroll->id : null,
                'month_year' => $validated['month_year'],
                'amount' => $validated['amount'],
                'advance_date' => $validated['advance_date'],
                'payment_mode' => $validated['payment_mode'],
                'bank_account_id' => $validated['payment_mode'] === 'Bank Transfer' ? ($validated['bank_account_id'] ?? null) : null,
                'reason' => $validated['reason'] ?? 'Advance Salary Request',
                'status' => $status,
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            // Auto-update payroll advance salary and net salary if payroll already exists
            if ($payroll) {
                $totalAdvances = SalaryAdvance::where('account_id', $payroll->account_id)
                    ->where('month_year', $payroll->month_year)
                    ->sum('amount');

                $netSalary = max(0, round($payroll->base_salary - $payroll->leave_deduction + $payroll->total_commission + $payroll->bonus - $payroll->deductions - $totalAdvances, 2));

                $payroll->update([
                    'advance_salary' => $totalAdvances,
                    'net_salary' => $netSalary,
                ]);

                if ($payroll->status === 'paid') {
                    $employee = Account::find($payroll->account_id);
                    $refNo = "PAYROLL-{$payroll->month_year}-{$employee->id}";
                    AccountLedger::where('reference_no', $refNo)->update([
                        'credit' => $netSalary,
                    ]);
                }
            }

            // Record immediate advance disbursement in staff ledger if requested
            if (!empty($validated['record_ledger'])) {
                $employee = Account::find($validated['account_id']);
                $refNo = "ADV-SAL-{$advance->id}";
                $newBalance = $employee->balance - $validated['amount'];
                AccountLedger::create([
                    'account_id' => $employee->id,
                    'date' => $validated['advance_date'],
                    'type' => 'payment',
                    'reference_no' => $refNo,
                    'description' => "Advance Salary Paid to Employee ({$validated['payment_mode']}) for {$validated['month_year']}: " . ($validated['reason'] ?? 'Advance'),
                    'debit' => $validated['amount'],
                    'credit' => 0.00,
                    'running_balance' => $newBalance,
                ]);
                $employee->update(['balance' => $newBalance]);
            }
        });

        $employee = Account::find($validated['account_id']);

        return redirect()->back()
            ->with('success', "Advance salary of PKR " . number_format($validated['amount'], 2) . " for {$employee->name} recorded successfully!");
    }

    /**
     * Delete a salary advance record (Admin Only).
     */
    public function destroyAdvance(SalaryAdvance $advance)
    {
        $isAdmin = self::isAuthorized();

        if (!$isAdmin) {
            return redirect()->back()->with('error', '⚠️ Access Denied: Only Admin users can delete salary advances.');
        }

        $monthYear = $advance->month_year;
        $accountId = $advance->account_id;
        $advId = $advance->id;

        DB::transaction(function () use ($advance, $monthYear, $accountId, $advId) {
            // Delete any ledger entry created for this advance
            AccountLedger::where('reference_no', "ADV-SAL-{$advId}")->delete();

            $advance->delete();

            $payroll = Payroll::where('account_id', $accountId)
                ->where('month_year', $monthYear)
                ->first();

            if ($payroll) {
                $totalAdvances = SalaryAdvance::where('account_id', $accountId)
                    ->where('month_year', $monthYear)
                    ->sum('amount');

                $netSalary = max(0, round($payroll->base_salary - $payroll->leave_deduction + $payroll->total_commission + $payroll->bonus - $payroll->deductions - $totalAdvances, 2));

                $payroll->update([
                    'advance_salary' => $totalAdvances,
                    'net_salary' => $netSalary,
                ]);

                if ($payroll->status === 'paid') {
                    $employee = Account::find($accountId);
                    $refNo = "PAYROLL-{$monthYear}-{$employee->id}";
                    AccountLedger::where('reference_no', $refNo)->update([
                        'credit' => $netSalary,
                    ]);
                }
            }
        });

        return redirect()->back()
            ->with('success', 'Salary advance record removed successfully!');
    }
}
