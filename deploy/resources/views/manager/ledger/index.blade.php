@extends('layouts.material')

@section('title', 'Account Ledger Statements')

@section('content')
<div class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="p-2.5 bg-blue-50 text-blue-600">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Account Ledger Statements</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">Audit complete transaction ledgers for supplier purchases, customer sales, payments, and receivings.</p>
            </div>
        </div>

        @if($selectedAccount)
            <div class="p-3 bg-blue-50 border border-blue-200">
                <span class="text-[10px] font-bold text-blue-600 uppercase">Selected Account Balance</span>
                <p class="text-lg font-black text-blue-900">{{ number_format($selectedAccount->balance, 2) }}</p>
            </div>
        @endif
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white p-4 border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <form method="GET" action="{{ route('manager.ledger.index') }}" class="flex-1 flex flex-col sm:flex-row gap-3">
            
            <select name="account_id" class="py-2.5 px-4 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-blue-600 flex-1">
                <option value="">All Accounts Statement</option>
                @foreach($accounts as $acc)
                    <option value="{{ $acc->id }}" {{ $accountId == $acc->id ? 'selected' : '' }}>
                        {{ $acc->name }} ({{ $acc->category->title ?? 'General' }})
                    </option>
                @endforeach
            </select>

            <select name="type" class="py-2.5 px-4 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-blue-600">
                <option value="">All Transaction Types</option>
                <option value="purchase" {{ $type == 'purchase' ? 'selected' : '' }}>Purchase</option>
                <option value="payment" {{ $type == 'payment' ? 'selected' : '' }}>Payment</option>
                <option value="sale" {{ $type == 'sale' ? 'selected' : '' }}>Product Sale</option>
                <option value="receiving" {{ $type == 'receiving' ? 'selected' : '' }}>Payment Receiving</option>
            </select>

            <button type="submit" class="px-5 py-2.5 bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors">
                Filter Ledger
            </button>
            @if($accountId || $type)
                <a href="{{ route('manager.ledger.index') }}" class="px-4 py-2.5 bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200 flex items-center justify-center">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Ledger Table Card -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[11px] font-extrabold uppercase tracking-wider">
                        <th class="py-4 px-6">ID</th>
                        <th class="py-4 px-6">Date</th>
                        <th class="py-4 px-6">Account Name</th>
                        <th class="py-4 px-6">Type</th>
                        <th class="py-4 px-6">Reference #</th>
                        <th class="py-4 px-6">Description</th>
                        <th class="py-4 px-6">Debit (+)</th>
                        <th class="py-4 px-6">Credit (-)</th>
                        <th class="py-4 px-6">Running Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($ledgers as $ledger)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="py-4 px-6 font-bold text-xs text-slate-400">#{{ $ledger->id }}</td>
                        <td class="py-4 px-6 text-xs font-semibold text-slate-600">
                            {{ $ledger->date->format('M d, Y') }}
                        </td>
                        <td class="py-4 px-6">
                            <p class="font-bold text-slate-900 leading-tight">{{ $ledger->account->name ?? 'Unknown' }}</p>
                            <span class="text-[10px] text-slate-400 font-medium">{{ $ledger->account->category->title ?? 'General' }}</span>
                        </td>
                        <td class="py-4 px-6">
                            @if($ledger->type == 'purchase')
                                <span class="px-2.5 py-1 bg-amber-100 text-amber-800 text-[11px] font-bold">Purchase</span>
                            @elseif($ledger->type == 'payment')
                                <span class="px-2.5 py-1 bg-purple-100 text-purple-800 text-[11px] font-bold">Payment</span>
                            @elseif($ledger->type == 'sale')
                                <span class="px-2.5 py-1 bg-indigo-100 text-indigo-800 text-[11px] font-bold">Sale</span>
                            @elseif($ledger->type == 'receiving')
                                <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 text-[11px] font-bold">Receiving</span>
                            @endif
                        </td>
                        <td class="py-4 px-6 font-mono text-xs font-bold text-slate-700">
                            {{ $ledger->reference_no ?: '—' }}
                        </td>
                        <td class="py-4 px-6 text-xs text-slate-600">
                            {{ $ledger->description }}
                        </td>
                        <td class="py-4 px-6 font-bold text-slate-900">
                            {{ $ledger->debit > 0 ? number_format($ledger->debit, 2) : '—' }}
                        </td>
                        <td class="py-4 px-6 font-bold text-emerald-600">
                            {{ $ledger->credit > 0 ? number_format($ledger->credit, 2) : '—' }}
                        </td>
                        <td class="py-4 px-6 font-extrabold text-slate-900">
                            {{ number_format($ledger->running_balance, 2) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-12 text-center text-slate-400">
                            <p class="text-sm font-semibold">No ledger transaction entries found.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-slate-100">
            {{ $ledgers->links() }}
        </div>
    </div>

</div>
@endsection
