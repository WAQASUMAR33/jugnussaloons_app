<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountLedger;
use App\Models\Appointment;
use App\Models\Payroll;
use App\Models\PayrollDeduction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    /**
     * Display payroll list & calculation dashboard.
     */
    public function index(Request $request)
    {
        $monthYear = $request->input('month_year', date('Y-m'));
        $search = $request->input('search');
        $status = $request->input('status');

        $query = Payroll::with(['employee', 'deductionItems'])->where('month_year', $monthYear);

        if ($search) {
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone_no1', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

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

        // Precalculate current month commissions for employees
        $parts = explode('-', $monthYear);
        $year = (int) ($parts[0] ?? date('Y'));
        $month = (int) ($parts[1] ?? date('m'));

        $employeeCommissions = [];
        foreach ($employees as $emp) {
            $commissionsSum = AccountLedger::where('account_id', $emp->id)
                ->where('type', 'commission')
                ->whereYear('date', $year)
                ->whereMonth('date', $month)
                ->sum('credit');

            if ($commissionsSum <= 0) {
                $commissionsSum = Appointment::where('employee_id', $emp->id)
                    ->whereYear('appointment_date', $year)
                    ->whereMonth('appointment_date', $month)
                    ->sum('total_commission');
            }

            $employeeCommissions[$emp->id] = (float) $commissionsSum;
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

        $isAdmin = Auth::check() && (Auth::user()->hasRole('admin') || Auth::user()->role === 'admin');

        return view('manager.payroll.index', compact(
            'payrolls',
            'employees',
            'employeeCommissions',
            'employeeDeductionTotals',
            'employeeDeductionList',
            'employeeAttendanceStats',
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

        $isAdmin = Auth::check() && (Auth::user()->hasRole('admin') || Auth::user()->role === 'admin');

        return view('manager.payroll.deductions', compact(
            'deductions',
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
        $isAdmin = Auth::check() && (Auth::user()->hasRole('admin') || Auth::user()->role === 'admin');

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
        $isAdmin = Auth::check() && (Auth::user()->hasRole('admin') || Auth::user()->role === 'admin');

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
        $isAdmin = Auth::check() && (Auth::user()->hasRole('admin') || Auth::user()->role === 'admin');
        
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
            'status' => ['required', 'in:draft,approved,paid'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $baseSalary = (float) $validated['base_salary'];
        $allowedLeaves = (int) $validated['allowed_leaves'];
        $takenLeaves = (int) $validated['taken_leaves'];
        $totalComm = (float) ($validated['total_commission'] ?? 0);
        $bonus = (float) ($validated['bonus'] ?? 0);
        
        $itemizedSum = PayrollDeduction::where('account_id', $validated['account_id'])
            ->where('month_year', $validated['month_year'])
            ->sum('amount');

        $deductions = isset($validated['deductions']) && (float) $validated['deductions'] > 0
            ? (float) $validated['deductions']
            : (float) $itemizedSum;

        $perDayRate = $baseSalary > 0 ? ($baseSalary / 30) : 0;
        $extraLeaves = max(0, $takenLeaves - $allowedLeaves);
        $leaveDeduction = round($extraLeaves * $perDayRate, 2);

        $netSalary = max(0, round($baseSalary - $leaveDeduction + $totalComm + $bonus - $deductions, 2));

        DB::transaction(function () use ($validated, $baseSalary, $allowedLeaves, $takenLeaves, $leaveDeduction, $totalComm, $bonus, $deductions, $netSalary) {
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
                    'net_salary' => $netSalary,
                    'status' => $validated['status'],
                    'payment_date' => $validated['status'] === 'paid' ? ($validated['payment_date'] ?? date('Y-m-d')) : null,
                    'notes' => $validated['notes'] ?? null,
                ]
            );

            PayrollDeduction::where('account_id', $validated['account_id'])
                ->where('month_year', $validated['month_year'])
                ->update(['payroll_id' => $payroll->id]);

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
                    'description' => "Monthly Net Salary Disbursement for {$validated['month_year']} (Base: {$baseSalary}, Comm: {$totalComm}, Deduct: {$deductions}, Net: {$netSalary})",
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
        $isAdmin = Auth::check() && (Auth::user()->hasRole('admin') || Auth::user()->role === 'admin');

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
            'status' => ['required', 'in:draft,approved,paid'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $baseSalary = (float) $validated['base_salary'];
        $allowedLeaves = (int) $validated['allowed_leaves'];
        $takenLeaves = (int) $validated['taken_leaves'];
        $totalComm = (float) ($validated['total_commission'] ?? 0);
        $bonus = (float) ($validated['bonus'] ?? 0);
        $deductions = (float) ($validated['deductions'] ?? 0);

        $perDayRate = $baseSalary > 0 ? ($baseSalary / 30) : 0;
        $extraLeaves = max(0, $takenLeaves - $allowedLeaves);
        $leaveDeduction = round($extraLeaves * $perDayRate, 2);

        $netSalary = max(0, round($baseSalary - $leaveDeduction + $totalComm + $bonus - $deductions, 2));

        DB::transaction(function () use ($payroll, $validated, $baseSalary, $allowedLeaves, $takenLeaves, $leaveDeduction, $totalComm, $bonus, $deductions, $netSalary) {
            $payroll->update([
                'base_salary' => $baseSalary,
                'allowed_leaves' => $allowedLeaves,
                'taken_leaves' => $takenLeaves,
                'leave_deduction' => $leaveDeduction,
                'total_commission' => $totalComm,
                'bonus' => $bonus,
                'deductions' => $deductions,
                'net_salary' => $netSalary,
                'status' => $validated['status'],
                'payment_date' => $validated['status'] === 'paid' ? ($validated['payment_date'] ?? date('Y-m-d')) : null,
                'notes' => $validated['notes'] ?? null,
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
                    'description' => "Updated Monthly Net Salary Disbursement for {$payroll->month_year}",
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
        $isAdmin = Auth::check() && (Auth::user()->hasRole('admin') || Auth::user()->role === 'admin');

        if (!$isAdmin) {
            return redirect()->back()->with('error', '⚠️ Access Denied: Only Admin users can delete payroll records.');
        }

        $monthYear = $payroll->month_year;
        $refNo = "PAYROLL-{$monthYear}-{$payroll->account_id}";

        DB::transaction(function () use ($payroll, $refNo) {
            AccountLedger::where('reference_no', $refNo)->delete();
            $payroll->delete();
        });

        return redirect()->route('manager.payroll.index', ['month_year' => $monthYear])
            ->with('success', 'Payroll record deleted successfully!');
    }
}
