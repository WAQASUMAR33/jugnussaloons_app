<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    /**
     * Display a listing of expenses with analytics & filters.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $categoryId = $request->input('category_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = Expense::with(['category', 'creator']);

        if ($search) {
            $query->where('exp_title', 'like', "%{$search}%");
        }

        if ($categoryId) {
            $query->where('exp_category_id', $categoryId);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Pagination
        $expenses = (clone $query)->orderBy('created_at', 'desc')->paginate(12)->withQueryString();

        // Calculate Analytics Metrics
        $totalExpensesAmount = (float) (clone $query)->sum('amount');
        $totalExpensesCount = (clone $query)->count();

        // This Month's Total
        $thisMonthTotal = (float) Expense::whereYear('created_at', date('Y'))
            ->whereMonth('created_at', date('m'))
            ->sum('amount');

        // Category-wise Breakdown Analytics
        $categoryBreakdown = Expense::select('exp_category_id', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as count'))
            ->with('category')
            ->groupBy('exp_category_id')
            ->orderBy('total_amount', 'desc')
            ->get();

        $topCategory = $categoryBreakdown->first();

        $categories = ExpenseCategory::orderBy('title')->get();

        return view('manager.expenses.index', compact(
            'expenses',
            'categories',
            'search',
            'categoryId',
            'dateFrom',
            'dateTo',
            'totalExpensesAmount',
            'totalExpensesCount',
            'thisMonthTotal',
            'categoryBreakdown',
            'topCategory'
        ));
    }

    /**
     * Store a newly created expense.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'exp_title' => ['required', 'string', 'max:255'],
            'exp_category_id' => ['required', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:1000'],
            'created_at' => ['nullable', 'date'],
        ]);

        $expenseData = [
            'exp_title' => $validated['exp_title'],
            'exp_category_id' => $validated['exp_category_id'],
            'amount' => (float) $validated['amount'],
            'description' => $validated['description'] ?? null,
            'added_by' => Auth::id(),
        ];

        if (!empty($validated['created_at'])) {
            $expenseData['created_at'] = $validated['created_at'] . ' ' . date('H:i:s');
        }

        Expense::create($expenseData);

        return redirect()->route('manager.expenses.index')
            ->with('success', 'Expense recorded successfully!');
    }

    /**
     * Update the specified expense.
     */
    public function update(Request $request, Expense $expense)
    {
        $validated = $request->validate([
            'exp_title' => ['required', 'string', 'max:255'],
            'exp_category_id' => ['required', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:1000'],
            'created_at' => ['nullable', 'date'],
        ]);

        $expenseData = [
            'exp_title' => $validated['exp_title'],
            'exp_category_id' => $validated['exp_category_id'],
            'amount' => (float) $validated['amount'],
            'description' => $validated['description'] ?? null,
        ];

        if (!empty($validated['created_at'])) {
            $expenseData['created_at'] = $validated['created_at'] . ' ' . date('H:i:s');
        }

        $expense->update($expenseData);

        return redirect()->route('manager.expenses.index')
            ->with('success', 'Expense updated successfully!');
    }

    /**
     * Remove the specified expense.
     */
    public function destroy(Expense $expense)
    {
        $expense->delete();

        return redirect()->route('manager.expenses.index')
            ->with('success', 'Expense deleted successfully!');
    }
}
