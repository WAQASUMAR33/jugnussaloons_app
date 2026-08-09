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

        $query = AccountLedger::with('account');

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        if ($type) {
            $query->where('type', $type);
        }

        $ledgers = $query->orderBy('date', 'desc')->orderBy('id', 'desc')->paginate(15)->withQueryString();
        $accounts = Account::orderBy('name')->get();

        $selectedAccount = $accountId ? Account::find($accountId) : null;

        return view('manager.ledger.index', compact('ledgers', 'accounts', 'accountId', 'type', 'selectedAccount'));
    }
}
