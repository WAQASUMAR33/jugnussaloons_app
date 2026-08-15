<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\AccountCategory;
use Illuminate\Http\Request;

class AccountCategoryController extends Controller
{
    /**
     * Display a listing of account categories.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $query = AccountCategory::withCount('accounts');

        if ($search) {
            $query->where('title', 'like', "%{$search}%");
        }

        $categories = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        return view('manager.accounts.categories', compact('categories', 'search'));
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255', 'unique:account_categories,title'],
        ]);

        AccountCategory::create([
            'title' => $validated['title'],
        ]);

        return redirect()->route('manager.account-categories.index')
            ->with('success', 'Account category created successfully!');
    }

    /**
     * Update the specified category in storage.
     */
    public function update(Request $request, AccountCategory $accountCategory)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255', 'unique:account_categories,title,'.$accountCategory->id],
        ]);

        $accountCategory->update([
            'title' => $validated['title'],
        ]);

        return redirect()->route('manager.account-categories.index')
            ->with('success', 'Account category updated successfully!');
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(AccountCategory $accountCategory)
    {
        if ($accountCategory->accounts()->count() > 0) {
            return redirect()->route('manager.account-categories.index')
                ->with('error', 'Cannot delete category that contains registered accounts!');
        }

        $accountCategory->delete();

        return redirect()->route('manager.account-categories.index')
            ->with('success', 'Account category deleted successfully!');
    }
}
