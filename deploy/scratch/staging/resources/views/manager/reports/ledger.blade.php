@extends('layouts.material')

@section('title', 'Ledger Report (Account Wise)')

@section('content')
<div class="space-y-6">

    <!-- PRINT-ONLY DEDICATED STORE REPORT HEADER -->
    <div class="hidden print:block pb-6 mb-6 border-b-2 border-slate-900">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                @if($appSetting->brand_logo)
                    <img src="{{ asset($appSetting->brand_logo) }}" class="h-14 w-auto object-contain">
                @endif
                <div>
                    <h1 class="text-2xl font-black text-slate-900 uppercase tracking-tight">{{ $appSetting->brand_name }}</h1>
                    @if($appSetting->brand_slogan)
                        <p class="text-xs font-bold text-slate-600 italic">{{ $appSetting->brand_slogan }}</p>
                    @endif
                    @if($appSetting->brand_address)
                        <p class="text-[11px] text-slate-500 mt-0.5 leading-tight">{{ $appSetting->brand_address }}</p>
                    @endif
                    @if($appSetting->brand_phone1 || $appSetting->brand_phone2)
                        <p class="text-[11px] font-bold text-slate-700 mt-0.5">
                            📞 {{ implode(' | ', array_filter([$appSetting->brand_phone1, $appSetting->brand_phone2])) }}
                        </p>
                    @endif
                </div>
            </div>

            <div class="text-right border-l-2 border-slate-300 pl-6">
                <h2 class="text-xl font-black uppercase text-indigo-900 tracking-tight">ACCOUNT LEDGER STATEMENT</h2>
                <div class="text-xs font-semibold text-slate-700 mt-1 space-y-0.5">
                    <p><strong>Selected Account:</strong> {{ $selectedAccount ? $selectedAccount->name : 'Select Account' }}</p>
                    <p><strong>Date Range:</strong> {{ $startDate ? \Carbon\Carbon::parse($startDate)->format('M d, Y') : 'Beginning' }} — {{ $endDate ? \Carbon\Carbon::parse($endDate)->format('M d, Y') : 'Present' }}</p>
                    <p class="text-[10px] text-slate-500 pt-1">Printed: {{ now()->format('M d, Y — h:i A') }} | By: {{ Auth::user()->name }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Header & Reports Navigation Tabs (WEB ONLY) -->
    <div class="bg-white border border-slate-200 shadow-sm p-6 space-y-6 print:hidden">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-indigo-50 text-indigo-600">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <div>
                    <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Business Intelligence & Reports</h1>
                    <p class="text-xs font-semibold text-slate-500 mt-0.5">Comprehensive sales, stock inventory, service bookings, account ledgers, and purchase reports.</p>
                </div>
            </div>

            <button onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-xs transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                <span>Print Ledger</span>
            </button>
        </div>

        <!-- Reports Tabs -->
        <div class="flex border-b border-slate-200 overflow-x-auto gap-2 text-xs font-bold">
            <a href="{{ route('manager.reports.sales') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.sales') ? 'border-indigo-600 text-indigo-600 bg-indigo-50/40' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>📊 Sales Report</span>
            </a>
            <a href="{{ route('manager.reports.stock') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.stock') ? 'border-indigo-600 text-indigo-600 bg-indigo-50/40' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>📦 Stock Report</span>
            </a>
            <a href="{{ route('manager.reports.services') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.services') ? 'border-indigo-600 text-indigo-600 bg-indigo-50/40' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>💈 Service Booking Reports</span>
            </a>
            <a href="{{ route('manager.reports.ledger') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.ledger') ? 'border-indigo-600 text-indigo-600 bg-indigo-50/40' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>📗 Ledger Report</span>
            </a>
            <a href="{{ route('manager.reports.purchases') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.purchases') ? 'border-indigo-600 text-indigo-600 bg-indigo-50/40' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>🛒 Purchase Report</span>
            </a>
        </div>
    </div>

    <!-- Filter Form Bar (WEB ONLY) -->
    <div class="bg-white p-5 border border-slate-200 shadow-sm print:hidden">
        <form method="GET" action="{{ route('manager.reports.ledger') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 items-end">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Select Account</label>
                <select name="account_id" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600">
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}" {{ $selectedAccount && $selectedAccount->id == $acc->id ? 'selected' : '' }}>
                            {{ $acc->name }} (Category: {{ $acc->category->title ?? 'General' }} | Balance: {{ number_format($acc->balance, 2) }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">From Date</label>
                <input type="date" name="start_date" value="{{ $startDate }}" 
                       class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">To Date</label>
                <input type="date" name="end_date" value="{{ $endDate }}" 
                       class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600">
            </div>

            <div class="flex gap-2">
                <button type="submit" class="flex-1 py-2.5 bg-indigo-600 text-white font-bold text-xs hover:bg-indigo-700 transition-colors shadow-xs">
                    Fetch Account Ledger
                </button>
                <a href="{{ route('manager.reports.ledger') }}" class="px-4 py-2.5 bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200 flex items-center justify-center">
                    Reset
                </a>
            </div>
        </form>
    </div>

    @if($selectedAccount)


    <!-- Account Ledger Table -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
            <h2 class="text-xs font-extrabold text-slate-700 uppercase tracking-wider">Statement for {{ $selectedAccount->name }}</h2>
            <span class="text-xs font-bold text-slate-500">Date: {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} to {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-100 text-slate-500 font-extrabold uppercase border-b tracking-wider">
                        <th class="py-3.5 px-4">Date</th>
                        <th class="py-3.5 px-4">Transaction Type</th>
                        <th class="py-3.5 px-4">Reference #</th>
                        <th class="py-3.5 px-4">Description</th>
                        <th class="py-3.5 px-4">Debit (+)</th>
                        <th class="py-3.5 px-4">Credit (-)</th>
                        <th class="py-3.5 px-4 text-right">Running Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    <!-- Opening Balance Row -->
                    <tr class="bg-slate-50/80 font-bold">
                        <td class="py-3 px-4 text-slate-500">{{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }}</td>
                        <td class="py-3 px-4 uppercase text-[10px] text-slate-600">Opening Balance</td>
                        <td class="py-3 px-4 text-slate-400">—</td>
                        <td class="py-3 px-4 text-slate-500">Opening balance prior to {{ $startDate }}</td>
                        <td class="py-3 px-4 text-slate-400">—</td>
                        <td class="py-3 px-4 text-slate-400">—</td>
                        <td class="py-3 px-4 text-right font-black text-slate-800">{{ number_format($openingBalance, 2) }}</td>
                    </tr>

                    @forelse($ledgers as $entry)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="py-3.5 px-4 font-bold text-slate-700">{{ $entry->date->format('M d, Y') }}</td>
                        <td class="py-3.5 px-4">
                            @if($entry->type == 'purchase')
                                <span class="px-2 py-0.5 bg-amber-100 text-amber-800 font-bold text-[10px] uppercase">Purchase</span>
                            @elseif($entry->type == 'payment')
                                <span class="px-2 py-0.5 bg-indigo-100 text-indigo-800 font-bold text-[10px] uppercase">Payment</span>
                            @elseif($entry->type == 'sale')
                                <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 font-bold text-[10px] uppercase">Sale</span>
                            @else
                                <span class="px-2 py-0.5 bg-purple-100 text-purple-800 font-bold text-[10px] uppercase">Receiving</span>
                            @endif
                        </td>
                        <td class="py-3.5 px-4 font-mono font-bold text-indigo-700">{{ $entry->reference_no ?: '—' }}</td>
                        <td class="py-3.5 px-4 font-medium text-slate-600">{{ $entry->description ?: 'N/A' }}</td>
                        <td class="py-3.5 px-4 font-bold text-indigo-600">{{ number_format($entry->debit, 2) }}</td>
                        <td class="py-3.5 px-4 font-bold text-emerald-600">{{ number_format($entry->credit, 2) }}</td>
                        <td class="py-3.5 px-4 text-right font-black text-slate-900">{{ number_format($entry->running_balance, 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-12 text-center text-slate-400 font-semibold">
                            No ledger transactions recorded for {{ $selectedAccount->name }} in this date range.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(is_object($ledgers) && method_exists($ledgers, 'links'))
        <div class="px-6 py-4 border-t border-slate-100">
            {{ $ledgers->links() }}
        </div>
        @endif
    </div>
    @endif

</div>
@endsection
