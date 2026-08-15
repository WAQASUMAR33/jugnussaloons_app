@extends('layouts.material')

@section('title', 'Purchase Report')

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
                <h2 class="text-xl font-black uppercase text-indigo-900 tracking-tight">
                    SUPPLIER PURCHASE REPORT
                    <span class="block text-xs font-bold text-slate-600 mt-0.5">
                        @if($reportType == 'accountwise')
                            (SUPPLIER ACCOUNT-WISE BREAKDOWN)
                        @elseif($reportType == 'detailed')
                            (DETAILED PURCHASES LIST)
                        @else
                            (DATE-WISE PURCHASES BREAKDOWN)
                        @endif
                    </span>
                </h2>
                <div class="text-xs font-semibold text-slate-700 mt-1 space-y-0.5">
                    <p><strong>Date Range:</strong> {{ $startDate ? \Carbon\Carbon::parse($startDate)->format('M d, Y') : 'Beginning' }} — {{ $endDate ? \Carbon\Carbon::parse($endDate)->format('M d, Y') : 'Present' }}</p>
                    <p><strong>Supplier Filter:</strong> {{ $accountId ? ($suppliers->firstWhere('id', $accountId)->name ?? 'Selected Supplier') : 'All Supplier Accounts' }}</p>
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
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                </div>
                <div>
                    <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Business Intelligence & Reports</h1>
                    <p class="text-xs font-semibold text-slate-500 mt-0.5">Comprehensive sales, stock inventory, service bookings, account ledgers, and purchase reports.</p>
                </div>
            </div>

            <button onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-xs transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                <span>Print Report</span>
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
    <div class="bg-white p-5 border border-slate-200 shadow-sm print:hidden space-y-4">
        <!-- Sub-Report Type Selection Tabs -->
        <div class="flex items-center gap-2 border-b border-slate-200 pb-3">
            <span class="text-xs font-black text-slate-400 uppercase tracking-wider mr-2">Report Selection:</span>
            <a href="{{ request()->fullUrlWithQuery(['report_type' => 'datewise']) }}" 
               class="px-3.5 py-1.5 font-bold text-xs transition-all {{ $reportType == 'datewise' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                📅 Date-Wise Report
            </a>
            <a href="{{ request()->fullUrlWithQuery(['report_type' => 'accountwise']) }}" 
               class="px-3.5 py-1.5 font-bold text-xs transition-all {{ $reportType == 'accountwise' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                🏢 Supplier Account-Wise Report
            </a>
            <a href="{{ request()->fullUrlWithQuery(['report_type' => 'detailed']) }}" 
               class="px-3.5 py-1.5 font-bold text-xs transition-all {{ $reportType == 'detailed' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                📜 Detailed Purchases List
            </a>
        </div>

        <form method="GET" action="{{ route('manager.reports.purchases') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4 items-end">
            <input type="hidden" name="report_type" value="{{ $reportType }}">

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

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Supplier Account</label>
                <select name="account_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600">
                    <option value="">All Supplier Accounts</option>
                    @foreach($suppliers as $sup)
                        <option value="{{ $sup->id }}" {{ $accountId == $sup->id ? 'selected' : '' }}>
                            {{ $sup->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2 col-span-2">
                <button type="submit" class="flex-1 py-2.5 bg-indigo-600 text-white font-bold text-xs hover:bg-indigo-700 transition-colors shadow-xs">
                    Generate Report
                </button>
                <a href="{{ route('manager.reports.purchases') }}" class="px-4 py-2.5 bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200 flex items-center justify-center">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- REPORT BODY CONTENT (SINGLE SELECTED REPORT DISPLAYED & PRINTED) -->

    @if($reportType == 'datewise')
        <!-- 1. DATE-WISE PURCHASE REPORT -->
        <div class="bg-white border border-slate-200 shadow-sm overflow-hidden p-5 space-y-3">
            <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                Date-Wise Purchases Breakdown
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-100 text-slate-600 font-extrabold uppercase border-b">
                            <th class="py-3 px-4">Date</th>
                            <th class="py-3 px-4">Total Orders</th>
                            <th class="py-3 px-4">Total Amount</th>
                            <th class="py-3 px-4">Paid Amount</th>
                            <th class="py-3 px-4 text-right">Balance Due</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-semibold">
                        @forelse($datewiseBreakdown as $db)
                        <tr>
                            <td class="py-3 px-4 font-bold text-slate-900">{{ \Carbon\Carbon::parse($db->purchase_date)->format('M d, Y') }}</td>
                            <td class="py-3 px-4 font-bold text-indigo-600">{{ number_format($db->total_count) }}</td>
                            <td class="py-3 px-4 text-slate-700 font-bold">{{ number_format($db->total_amount, 2) }}</td>
                            <td class="py-3 px-4 text-emerald-600 font-bold">{{ number_format($db->paid_amount, 2) }}</td>
                            <td class="py-3 px-4 text-right text-rose-600 font-bold">{{ number_format($db->balance_due, 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-400">No date-wise purchase record found for selected filters.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    @elseif($reportType == 'accountwise')
        <!-- 2. ACCOUNT-WISE SUPPLIER REPORT -->
        <div class="bg-white border border-slate-200 shadow-sm overflow-hidden p-5 space-y-3">
            <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                Supplier Account-Wise Purchase Breakdown
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-100 text-slate-600 font-extrabold uppercase border-b">
                            <th class="py-3 px-4">Supplier Name</th>
                            <th class="py-3 px-4">Total Orders</th>
                            <th class="py-3 px-4">Total Amount</th>
                            <th class="py-3 px-4">Paid Amount</th>
                            <th class="py-3 px-4 text-right">Payable Due</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-semibold">
                        @forelse($accountwiseBreakdown as $ab)
                        <tr>
                            <td class="py-3 px-4 font-bold text-slate-900">{{ $ab->supplier_name }}</td>
                            <td class="py-3 px-4 font-bold text-indigo-600">{{ number_format($ab->total_orders) }}</td>
                            <td class="py-3 px-4 text-slate-700 font-bold">{{ number_format($ab->total_amount, 2) }}</td>
                            <td class="py-3 px-4 text-emerald-600 font-bold">{{ number_format($ab->paid_amount, 2) }}</td>
                            <td class="py-3 px-4 text-right text-rose-600 font-bold">{{ number_format($ab->balance_due, 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-400">No supplier account purchase record found for selected filters.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    @else
        <!-- 3. DETAILED PURCHASES LIST REPORT -->
        <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                <h2 class="text-xs font-extrabold text-slate-700 uppercase tracking-wider">Detailed Purchases Orders List</h2>
                <span class="text-xs font-bold text-slate-500 print:hidden">Showing {{ $purchases->firstItem() ?? 0 }} - {{ $purchases->lastItem() ?? 0 }} of {{ $purchases->total() }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-100 text-slate-500 font-extrabold uppercase border-b tracking-wider">
                            <th class="py-3.5 px-4">Invoice #</th>
                            <th class="py-3.5 px-4">Purchase Date</th>
                            <th class="py-3.5 px-4">Supplier Account</th>
                            <th class="py-3.5 px-4">Category</th>
                            <th class="py-3.5 px-4">Total Amount</th>
                            <th class="py-3.5 px-4">Paid Amount</th>
                            <th class="py-3.5 px-4 text-right">Payable Balance Due</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        @forelse($purchases as $pur)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-3.5 px-4 font-mono font-bold text-indigo-700">{{ $pur->invoice_no }}</td>
                            <td class="py-3.5 px-4 font-bold text-slate-600">{{ $pur->purchase_date->format('M d, Y') }}</td>
                            <td class="py-3.5 px-4 font-bold text-slate-900">{{ $pur->supplier->name ?? 'Supplier' }}</td>
                            <td class="py-3.5 px-4">
                                <span class="px-2 py-0.5 bg-slate-100 text-slate-700 font-bold text-[10px]">
                                    {{ $pur->supplier->category->title ?? 'Supplier' }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 font-black text-slate-900">{{ number_format($pur->total_amount, 2) }}</td>
                            <td class="py-3.5 px-4 font-bold text-emerald-600">{{ number_format($pur->paid_amount, 2) }}</td>
                            <td class="py-3.5 px-4 text-right font-bold {{ $pur->balance_due > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                                {{ number_format($pur->balance_due, 2) }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400 font-semibold">
                                No purchase orders found for the selected date range and filters.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-slate-100 print:hidden">
                {{ $purchases->links() }}
            </div>
        </div>
    @endif

</div>
@endsection
