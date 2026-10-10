<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountLedger;
use Illuminate\Http\Request;

class AccountLedgerController extends Controller
{
    /**
     * Display account ledger entries.
     */
    public function index(Request $request)
    {
        $accountId = $request->input('account_id');
        $type = $request->input('type');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = AccountLedger::with(['account.category']);

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($startDate) {
            $query->whereDate('date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('date', '<=', $endDate);
        }

        $totalDebit = (clone $query)->sum('debit');
        $totalCredit = (clone $query)->sum('credit');

        $ledgers = $query->orderBy('date', 'desc')->orderBy('id', 'desc')->paginate(20)->withQueryString();
        $accounts = Account::with('category')->orderBy('name')->get();

        $selectedAccount = $accountId ? Account::with('category')->find($accountId) : null;

        return view('manager.ledger.index', compact(
            'ledgers', 
            'accounts', 
            'accountId', 
            'type', 
            'startDate', 
            'endDate', 
            'selectedAccount',
            'totalDebit',
            'totalCredit'
        ));
    }
}
