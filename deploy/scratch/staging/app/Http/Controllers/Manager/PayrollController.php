<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountLedger;
use App\Models\Appointment;
use App\Models\Payroll;
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

        $query = Payroll::with('employee')->where('month_year', $monthYear);

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

        // Precalculate current month commissions for employees for seamless payroll creation
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

            // Fallback: If no ledger commission entry, sum from appointments
            if ($commissionsSum <= 0) {
                $commissionsSum = Appointment::where('employee_id', $emp->id)
                    ->whereYear('appointment_date', $year)
                    ->whereMonth('appointment_date', $month)
                    ->sum('total_commission');
            }

            $employeeCommissions[$emp->id] = (float) $commissionsSum;
        }

        $isAdmin = Auth::check() && (Auth::user()->hasRole('admin') || Auth::user()->role === 'admin');

        return view('manager.payroll.index', compact(
            'payrolls',
            'employees',
            'employeeCommissions',
            'monthYear',
            'search',
            'status',
            'isAdmin'
        ));
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
        $deductions = (float) ($validated['deductions'] ?? 0);

        // Auto Calculate Leave Deductions
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

            // Write Employee Ledger entry if status is marked as PAID
            if ($validated['status'] === 'paid') {
                $employee = Account::find($validated['account_id']);
                $refNo = "PAYROLL-{$validated['month_year']}-{$employee->id}";

                // Remove previous payroll ledger if exists
                AccountLedger::where('reference_no', $refNo)->delete();

                $newBalance = $employee->balance + $netSalary;
                AccountLedger::create([
                    'account_id' => $employee->id,
                    'date' => $validated['payment_date'] ?? date('Y-m-d'),
                    'type' => 'payment',
                    'reference_no' => $refNo,
                    'description' => "Monthly Net Salary Disbursement for {$validated['month_year']} (Base: {$baseSalary}, Comm: {$totalComm}, Net: {$netSalary})",
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
