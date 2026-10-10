<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Commission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommissionController extends Controller
{
    /**
     * Display a listing of commissions with multi-criteria filters and statistics.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $employeeId = $request->input('employee_id');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $singleDate = $request->input('date');

        $query = Commission::with('employee.category');

        // Search Filter (keyword in description, employee name or phone)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($eq) use ($search) {
                      $eq->where('name', 'like', "%{$search}%")
                         ->orWhere('phone_no1', 'like', "%{$search}%")
                         ->orWhere('card_no', 'like', "%{$search}%");
                  });
            });
        }

        // Employee Filter
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        // Date Range / Single Date Filters
        if ($singleDate) {
            $query->whereDate('date', $singleDate);
        } else {
            if ($fromDate) {
                $query->whereDate('date', '>=', $fromDate);
            }
            if ($toDate) {
                $query->whereDate('date', '<=', $toDate);
            }
        }

        // Calculate Overall Statistics based on active filters (before pagination)
        $statsQuery = clone $query;
        $totalWorkAmount = (float) $statsQuery->sum('amount_of_work');
        $totalCommissionAmount = (float) $statsQuery->sum('total_amount');
        $totalRecords = (int) $statsQuery->count();
        $avgCommissionRate = $totalWorkAmount > 0 
            ? round(($totalCommissionAmount / $totalWorkAmount) * 100, 2) 
            : 0.0;

        // Employee-wise breakdown statistics for the filtered dataset
        $employeeBreakdown = (clone $query)
            ->select(
                'employee_id',
                DB::raw('COUNT(*) as total_entries'),
                DB::raw('SUM(amount_of_work) as total_work'),
                DB::raw('SUM(total_amount) as total_commission')
            )
            ->groupBy('employee_id')
            ->with('employee')
            ->get();

        // Paginated List
        $commissions = $query->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        // Fetch Employee Accounts (filter accounts of type "Staff / Employee")
        $employees = Account::whereHas('category', function ($q) {
            $q->where('title', 'like', '%Employee%')
              ->orWhere('title', 'like', '%Staff%')
              ->orWhere('title', 'like', '%Barber%')
              ->orWhere('title', 'like', '%Stylist%');
        })->with('category')->orderBy('name')->get();

        if ($employees->isEmpty()) {
            $employees = Account::whereHas('category', function ($q) {
                $q->where('title', 'not like', '%Supplier%')
                  ->where('title', 'not like', '%Vendor%');
            })->orWhereDoesntHave('category')->with('category')->orderBy('name')->get();
        }

        return view('manager.commissions.index', compact(
            'commissions',
            'employees',
            'search',
            'employeeId',
            'fromDate',
            'toDate',
            'singleDate',
            'totalWorkAmount',
            'totalCommissionAmount',
            'totalRecords',
            'avgCommissionRate',
            'employeeBreakdown'
        ));
    }

    /**
     * Store a newly created commission record in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:accounts,id'],
            'amount_of_work' => ['required', 'numeric', 'min:0'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        // Prevent rapid duplicate submission within 2 seconds
        $recentDuplicate = Commission::where('employee_id', $validated['employee_id'])
            ->where('amount_of_work', $validated['amount_of_work'])
            ->where('total_amount', $validated['total_amount'])
            ->where('date', $validated['date'])
            ->where('created_at', '>=', now()->subSeconds(2))
            ->first();

        if ($recentDuplicate) {
            return redirect()->route('manager.commissions.index')
                ->with('success', 'Commission record created successfully!');
        }

        $commission = Commission::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Commission record added successfully.',
                'commission' => $commission->load('employee'),
            ]);
        }

        return redirect()->route('manager.commissions.index')
            ->with('success', 'Commission record created successfully!');
    }

    /**
     * Update the specified commission record in storage.
     */
    public function update(Request $request, Commission $commission)
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:accounts,id'],
            'amount_of_work' => ['required', 'numeric', 'min:0'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $commission->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Commission record updated successfully.',
                'commission' => $commission->load('employee'),
            ]);
        }

        return redirect()->route('manager.commissions.index')
            ->with('success', 'Commission record updated successfully!');
    }

    /**
     * Remove the specified commission record from storage.
     */
    public function destroy(Request $request, Commission $commission)
    {
        $commission->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Commission record deleted successfully.',
            ]);
        }

        return redirect()->route('manager.commissions.index')
            ->with('success', 'Commission record deleted successfully!');
    }

    /**
     * Generate and display official Printable Commission Statement Report.
     */
    public function print(Request $request)
    {
        $search = $request->input('search');
        $employeeId = $request->input('employee_id');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $singleDate = $request->input('date');

        $query = Commission::with(['employee.category']);

        // Search Filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($eq) use ($search) {
                      $eq->where('name', 'like', "%{$search}%")
                         ->orWhere('phone_no1', 'like', "%{$search}%")
                         ->orWhere('card_no', 'like', "%{$search}%");
                  });
            });
        }

        // Employee Filter
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        // Date Filters
        if ($singleDate) {
            $query->whereDate('date', $singleDate);
        } else {
            if ($fromDate) {
                $query->whereDate('date', '>=', $fromDate);
            }
            if ($toDate) {
                $query->whereDate('date', '<=', $toDate);
            }
        }

        // Get all matching records for the printable report
        $commissions = $query->orderBy('date', 'asc')->orderBy('id', 'asc')->get();

        // Calculate statistics
        $totalWorkAmount = (float) $commissions->sum('amount_of_work');
        $totalCommissionAmount = (float) $commissions->sum('total_amount');
        $totalRecords = $commissions->count();
        $avgCommissionRate = $totalWorkAmount > 0 
            ? round(($totalCommissionAmount / $totalWorkAmount) * 100, 2) 
            : 0.0;

        // Grouped summary per employee
        $employeeSummary = $commissions->groupBy('employee_id')->map(function ($items) {
            $emp = $items->first()->employee;
            $work = (float) $items->sum('amount_of_work');
            $comm = (float) $items->sum('total_amount');
            $rate = $work > 0 ? round(($comm / $work) * 100, 2) : 0.0;
            return [
                'employee' => $emp,
                'count' => $items->count(),
                'total_work' => $work,
                'total_commission' => $comm,
                'avg_rate' => $rate,
            ];
        })->values();

        $selectedEmployee = $employeeId ? Account::with('category')->find($employeeId) : null;
        
        // Day-by-day aggregated breakdown for individual employee mode
        $dailyBreakdown = $selectedEmployee ? $commissions->groupBy(function ($item) {
            return \Carbon\Carbon::parse($item->date)->format('Y-m-d');
        })->map(function ($items, $dateKey) {
            $work = (float) $items->sum('amount_of_work');
            $comm = (float) $items->sum('total_amount');
            $rate = $work > 0 ? round(($comm / $work) * 100, 2) : 0.0;
            return [
                'date' => $dateKey,
                'count' => $items->count(),
                'total_work' => $work,
                'total_commission' => $comm,
                'avg_rate' => $rate,
            ];
        })->values() : collect();

        $appSetting = \App\Models\Setting::first();

        return view('manager.commissions.print', compact(
            'commissions',
            'totalWorkAmount',
            'totalCommissionAmount',
            'totalRecords',
            'avgCommissionRate',
            'employeeSummary',
            'selectedEmployee',
            'dailyBreakdown',
            'search',
            'employeeId',
            'fromDate',
            'toDate',
            'singleDate',
            'appSetting'
        ));
    }
}
