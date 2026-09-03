<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    /**
     * Display a listing of bank account details.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $query = BankAccount::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('bank_name', 'like', "%{$search}%")
                  ->orWhere('account_title', 'like', "%{$search}%")
                  ->orWhere('account_no', 'like', "%{$search}%")
                  ->orWhere('branch_name', 'like', "%{$search}%")
                  ->orWhere('iban', 'like', "%{$search}%");
            });
        }

        $bankAccounts = $query->orderBy('bankid', 'desc')->paginate(10)->withQueryString();

        return view('manager.settings.bank_accounts', compact('bankAccounts', 'search'));
    }

    /**
     * Store a newly created bank account record in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:255'],
            'account_title' => ['required', 'string', 'max:255'],
            'account_no' => ['required', 'string', 'max:255'],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->has('is_active') ? true : false;

        BankAccount::create($validated);

        return redirect()->route('manager.bank-accounts.index')
            ->with('success', 'Bank Account details created successfully!');
    }

    /**
     * Update the specified bank account record in storage.
     */
    public function update(Request $request, BankAccount $bankAccount)
    {
        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:255'],
            'account_title' => ['required', 'string', 'max:255'],
            'account_no' => ['required', 'string', 'max:255'],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->has('is_active') ? true : false;

        $bankAccount->update($validated);

        return redirect()->route('manager.bank-accounts.index')
            ->with('success', 'Bank Account details updated successfully!');
    }

    /**
     * Remove the specified bank account record from storage.
     */
    public function destroy(BankAccount $bankAccount)
    {
        $bankAccount->delete();

        return redirect()->route('manager.bank-accounts.index')
            ->with('success', 'Bank Account details deleted successfully!');
    }
}
