<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountCategory;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * Display a listing of accounts.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $categoryId = $request->input('category_id');

        $query = Account::with('category');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone_no1', 'like', "%{$search}%")
                  ->orWhere('phone_no2', 'like', "%{$search}%")
                  ->orWhere('card_no', 'like', "%{$search}%")
                  ->orWhere('father_name', 'like', "%{$search}%");
            });
        }

        if ($categoryId) {
            $query->where('account_category_id', $categoryId);
        }

        $accounts = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();
        $categories = AccountCategory::orderBy('title')->get();

        return view('manager.accounts.index', compact('accounts', 'categories', 'search', 'categoryId'));
    }

    /**
     * Store a newly created account in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_category_id' => ['required', 'exists:account_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'date_of_birth' => ['nullable', 'date'],
            'date_of_anniversary' => ['nullable', 'date'],
            'phone_no1' => ['required', 'string', 'max:50'],
            'phone_no2' => ['nullable', 'string', 'max:50'],
            'card_no' => ['nullable', 'string', 'max:50'],
            'balance' => ['nullable', 'numeric'],
        ]);

        Account::create([
            'account_category_id' => $validated['account_category_id'],
            'name' => $validated['name'],
            'father_name' => $validated['father_name'] ?? null,
            'address' => $validated['address'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'date_of_anniversary' => $validated['date_of_anniversary'] ?? null,
            'phone_no1' => $validated['phone_no1'],
            'phone_no2' => $validated['phone_no2'] ?? null,
            'card_no' => $validated['card_no'] ?? null,
            'balance' => (float) ($validated['balance'] ?? 0.00),
        ]);

        return redirect()->route('manager.accounts.index')
            ->with('success', 'Customer account created successfully!');
    }

    /**
     * Update the specified account in storage.
     */
    public function update(Request $request, Account $account)
    {
        $validated = $request->validate([
            'account_category_id' => ['required', 'exists:account_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'date_of_birth' => ['nullable', 'date'],
            'date_of_anniversary' => ['nullable', 'date'],
            'phone_no1' => ['required', 'string', 'max:50'],
            'phone_no2' => ['nullable', 'string', 'max:50'],
            'card_no' => ['nullable', 'string', 'max:50'],
            'balance' => ['nullable', 'numeric'],
        ]);

        $account->update([
            'account_category_id' => $validated['account_category_id'],
            'name' => $validated['name'],
            'father_name' => $validated['father_name'] ?? null,
            'address' => $validated['address'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'date_of_anniversary' => $validated['date_of_anniversary'] ?? null,
            'phone_no1' => $validated['phone_no1'],
            'phone_no2' => $validated['phone_no2'] ?? null,
            'card_no' => $validated['card_no'] ?? null,
            'balance' => (float) ($validated['balance'] ?? 0.00),
        ]);

        return redirect()->route('manager.accounts.index')
            ->with('success', 'Customer account updated successfully!');
    }

    /**
     * Remove the specified account from storage.
     */
    public function destroy(Account $account)
    {
        $account->delete();

        return redirect()->route('manager.accounts.index')
            ->with('success', 'Customer account deleted successfully!');
    }

    /**
     * Record payment or receiving transaction & generate ledger entry.
     */
    public function recordTransaction(Request $request, Account $account)
    {
        $validated = $request->validate([
            'transaction_type' => ['required', 'in:payment,receiving'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $account) {
            $amount = (float) $validated['amount'];
            $type = $validated['transaction_type'];
            $date = $validated['date'];
            
            // Calculate new balance (Receiving or Payment reduces outstanding account balance)
            $newBalance = round($account->balance - $amount, 2);

            $refNo = $validated['reference_no'] ?: ($type === 'payment' ? 'PAY-' : 'REC-') . date('Ymd-His');
            $desc = $validated['description'] ?: ($type === 'payment' ? "Cash Payment to {$account->name}" : "Payment Received from {$account->name}");

            // 1. Create Ledger Entry
            \App\Models\AccountLedger::create([
                'account_id' => $account->id,
                'date' => $date,
                'type' => $type,
                'reference_no' => $refNo,
                'description' => $desc,
                'debit' => 0.00,
                'credit' => $amount,
                'running_balance' => $newBalance,
            ]);

            // 2. Update Account Balance
            $account->update(['balance' => $newBalance]);
        });

        $label = $validated['transaction_type'] === 'payment' ? 'Payment' : 'Receiving';
        return redirect()->route('manager.accounts.index')
            ->with('success', "{$label} recorded successfully! Ledger entry generated & account balance updated.");
    }
}
