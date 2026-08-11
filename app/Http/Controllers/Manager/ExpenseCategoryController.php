<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    /**
     * Display listing of expense categories.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $query = ExpenseCategory::withCount('expenses');

        if ($search) {
            $query->where('title', 'like', "%{$search}%");
        }

        $categories = $query->orderBy('title')->paginate(10)->withQueryString();

        return view('manager.expense_categories.index', compact('categories', 'search'));
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255', 'unique:expense_categories,title'],
        ]);

        ExpenseCategory::create($validated);

        return redirect()->back()
            ->with('success', 'Expense category created successfully!');
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, ExpenseCategory $expenseCategory)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255', 'unique:expense_categories,title,' . $expenseCategory->id],
        ]);

        $expenseCategory->update($validated);

        return redirect()->back()
            ->with('success', 'Expense category updated successfully!');
    }

    /**
     * Remove the specified category.
     */
    public function destroy(ExpenseCategory $expenseCategory)
    {
        $expenseCategory->delete();

        return redirect()->back()
            ->with('success', 'Expense category deleted successfully!');
    }
}
