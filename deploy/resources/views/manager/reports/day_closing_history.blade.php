@extends('layouts.material')

@section('title', 'Day Closing History & Audit Trail')

@section('content')
<div class="space-y-6 pb-12" x-data="{
    detailModalOpen: false,
    selectedRecord: null,
    openDetail(rec) {
        this.selectedRecord = rec;
        this.detailModalOpen = true;
    }
}">

    <!-- ========================================================================= -->
    <!-- CRYSTAL REPORTS OFFICIAL DOCUMENT HEADER (PRINT ONLY)                    -->
    <!-- ========================================================================= -->
    <div class="hidden print:block crystal-container mb-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                @if(isset($appSetting) && $appSetting->brand_logo)
                    <img src="{{ asset($appSetting->brand_logo) }}" class="h-12 w-auto object-contain">
                @endif
                <div>
                    <h1 class="text-xl font-black uppercase text-slate-900 tracking-tight">{{ $appSetting->brand_name ?? 'Jugnu Saloon' }}</h1>
                    @if(isset($appSetting) && $appSetting->brand_slogan)
                        <p class="text-[10px] font-bold text-slate-600 italic leading-none">{{ $appSetting->brand_slogan }}</p>
                    @endif
                    @if(isset($appSetting) && $appSetting->brand_address)
                        <p class="text-[9px] text-slate-500 mt-0.5 leading-tight">{{ $appSetting->brand_address }}</p>
                    @endif
                </div>
            </div>
            <div class="text-right text-[8.5pt]">
                <div class="font-bold text-slate-800 uppercase tracking-wide">Enterprise ERP System</div>
                <div class="text-[7.5pt] text-slate-500">Official Day Closing History Audit Log</div>
                <div class="text-[7.5pt] font-semibold text-slate-600 mt-0.5">Printed: {{ now()->format('d-M-Y h:i A') }}</div>
            </div>
        </div>

        <div class="crystal-double-rule"></div>

        <div class="crystal-title-banner">
            <h2>DAY CLOSING AUDIT TRAIL & PREVIOUS REGISTERS REPORT</h2>
            <p>
                Period: 
                @if($startDate && $endDate)
                    {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} to {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}
                @elseif($startDate)
                    From {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }}
                @else
                    All Recorded History
                @endif
            </p>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 1. TOP HEADER & NAVIGATION SWITCHER                                       -->
    <!-- ========================================================================= -->
    <div class="bg-white border border-slate-200 shadow-xs p-4 sm:p-5 space-y-4 print:hidden">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-teal-50 text-teal-600 rounded-xl">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Day Closing History</h1>
                    <div class="flex flex-wrap items-center gap-2 mt-1">
                        <p class="text-xs font-semibold text-slate-500">
                            Comprehensive historical cash register settlements & drawer audits
                        </p>
                        <span class="px-2 py-0.5 rounded text-[10px] font-black bg-teal-100 text-teal-800 border border-teal-200">
                            {{ $metrics['total_days'] }} Registers Audited
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <a href="{{ route('manager.reports.day-closing') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs rounded-xl shadow-xs transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <span>Today's Live Day Closing</span>
                </a>

                <button type="button" onclick="window.print()"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl shadow-xs transition-colors cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    <span>Print Audit Log</span>
                </button>
            </div>
        </div>

        <!-- Reports Tabs Switcher -->
        <div class="flex border-b border-slate-200 overflow-x-auto scrollbar-none gap-2 text-xs font-bold pb-0.5">
            <a href="{{ route('manager.reports.day-closing') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.day-closing') && !request()->routeIs('manager.reports.day-closing.history') ? 'border-teal-600 text-teal-700 bg-teal-50/50 font-extrabold' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>Daily Day Closing</span>
            </a>
            <a href="{{ route('manager.reports.day-closing.history') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.day-closing.history') ? 'border-teal-600 text-teal-700 bg-teal-50/50 font-extrabold' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>Closing History</span>
            </a>
            <a href="{{ route('manager.reports.profit') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.profit') ? 'border-emerald-600 text-emerald-700 bg-emerald-50/50 font-extrabold' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>Profit & Loss</span>
            </a>
            <a href="{{ route('manager.reports.aesthetic-profit') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.aesthetic-profit') ? 'border-rose-600 text-rose-700 bg-rose-50/50 font-extrabold' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>Aesthetic Profit</span>
            </a>
            <a href="{{ route('manager.reports.sales') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.sales') ? 'border-indigo-600 text-indigo-600 bg-indigo-50/40' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>Sales Report</span>
            </a>
            <a href="{{ route('manager.reports.stock') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.stock') ? 'border-indigo-600 text-indigo-600 bg-indigo-50/40' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>Stock Report</span>
            </a>
            <a href="{{ route('manager.reports.services') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.services') ? 'border-indigo-600 text-indigo-600 bg-indigo-50/40' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>Services</span>
            </a>
            <a href="{{ route('manager.reports.ledger') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.ledger') ? 'border-indigo-600 text-indigo-600 bg-indigo-50/40' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>Ledger</span>
            </a>
            <a href="{{ route('manager.reports.purchases') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.purchases') ? 'border-indigo-600 text-indigo-600 bg-indigo-50/40' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>Purchases</span>
            </a>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 2. AUDIT METRICS DASHBOARD (5 KPI COMMAND TILES)                          -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 print:hidden">
        
        <!-- CARD 1: AUDITED DAYS -->
        <div class="bg-white rounded-2xl p-4 border border-slate-200/90 shadow-2xs space-y-2">
            <div class="flex items-center justify-between text-slate-500">
                <span class="text-[10px] font-black uppercase tracking-wider">Settled Days</span>
                <span class="p-1.5 bg-slate-100 rounded-lg text-slate-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </span>
            </div>
            <div class="text-2xl font-black text-slate-900 heading-font">
                {{ $metrics['total_days'] }} <span class="text-xs font-bold text-slate-500">days</span>
            </div>
            <div class="flex items-center gap-2 text-[10.5px] font-bold">
                <span class="text-emerald-700">{{ $metrics['closed_days'] }} Locked</span>
                <span class="text-slate-300">•</span>
                <span class="text-amber-700">{{ $metrics['open_days'] }} Live</span>
            </div>
        </div>

        <!-- CARD 2: TOTAL INFLOWS -->
        <div class="bg-white rounded-2xl p-4 border border-slate-200/90 shadow-2xs space-y-2">
            <div class="flex items-center justify-between text-slate-500">
                <span class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Total Collections In</span>
                <span class="p-1.5 bg-emerald-50 rounded-lg text-emerald-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path></svg>
                </span>
            </div>
            <div class="text-xl font-black text-emerald-700 heading-font">
                Rs. {{ number_format($metrics['total_received'], 2) }}
            </div>
            <div class="text-[10.5px] text-slate-500 font-medium">
                Cash: <strong>Rs. {{ number_format($metrics['cash_received'], 2) }}</strong> • Bank: <strong>Rs. {{ number_format($metrics['bank_received'], 2) }}</strong>
            </div>
        </div>

        <!-- CARD 3: TOTAL OUTFLOWS -->
        <div class="bg-white rounded-2xl p-4 border border-slate-200/90 shadow-2xs space-y-2">
            <div class="flex items-center justify-between text-slate-500">
                <span class="text-[10px] font-black uppercase tracking-wider text-rose-800">Total Payments Out</span>
                <span class="p-1.5 bg-rose-50 rounded-lg text-rose-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"></path></svg>
                </span>
            </div>
            <div class="text-xl font-black text-rose-700 heading-font">
                Rs. {{ number_format($metrics['total_paid'], 2) }}
            </div>
            <div class="text-[10.5px] text-slate-500 font-medium">
                Cash: <strong>Rs. {{ number_format($metrics['cash_paid'], 2) }}</strong> • Bank: <strong>Rs. {{ number_format($metrics['bank_paid'], 2) }}</strong>
            </div>
        </div>

        <!-- CARD 4: NET MOVEMENT -->
        <div class="bg-white rounded-2xl p-4 border border-slate-200/90 shadow-2xs space-y-2">
            <div class="flex items-center justify-between text-slate-500">
                <span class="text-[10px] font-black uppercase tracking-wider text-indigo-900">Net Liquidity Flow</span>
                <span class="p-1.5 bg-indigo-50 rounded-lg text-indigo-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                </span>
            </div>
            <div class="text-xl font-black heading-font {{ $metrics['net_flow'] >= 0 ? 'text-indigo-900' : 'text-rose-700' }}">
                {{ $metrics['net_flow'] >= 0 ? '+' : '' }}Rs. {{ number_format($metrics['net_flow'], 2) }}
            </div>
            <div class="text-[10.5px] font-bold text-slate-500">
                Opening Float Sum: <strong>Rs. {{ number_format($metrics['total_opening'], 2) }}</strong>
            </div>
        </div>

        <!-- CARD 5: DRAWER RECONCILIATION & DISCREPANCIES -->
        <div class="bg-white rounded-2xl p-4 border border-slate-200/90 shadow-2xs space-y-2">
            <div class="flex items-center justify-between text-slate-500">
                <span class="text-[10px] font-black uppercase tracking-wider text-amber-900">Drawer Discrepancy</span>
                <span class="p-1.5 bg-amber-50 rounded-lg text-amber-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </span>
            </div>
            <div class="text-xl font-black heading-font {{ $metrics['total_shortage'] > 0 ? 'text-rose-700' : ($metrics['total_surplus'] > 0 ? 'text-blue-700' : 'text-emerald-700') }}">
                @if($metrics['total_shortage'] > 0)
                    -Rs. {{ number_format($metrics['total_shortage'], 2) }}
                @elseif($metrics['total_surplus'] > 0)
                    +Rs. {{ number_format($metrics['total_surplus'], 2) }}
                @else
                    Rs. 0.00
                @endif
            </div>
            <div class="text-[10.5px] font-bold flex items-center gap-1.5">
                <span class="text-emerald-700">{{ $metrics['balanced_days'] }} Balanced</span>
                @if($metrics['shortage_days'] > 0)
                    <span class="text-slate-300">•</span>
                    <span class="text-rose-700">{{ $metrics['shortage_days'] }} Short</span>
                @endif
                @if($metrics['surplus_days'] > 0)
                    <span class="text-slate-300">•</span>
                    <span class="text-blue-700">{{ $metrics['surplus_days'] }} Surplus</span>
                @endif
            </div>
        </div>

    </div>

    <!-- ========================================================================= -->
    <!-- 3. ADVANCED AUDIT FILTER BAR                                              -->
    <!-- ========================================================================= -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs p-4 sm:p-5 space-y-4 print:hidden">
        
        <!-- Quick Preset Buttons -->
        <div class="flex flex-wrap items-center gap-1.5 pb-2 border-b border-slate-100">
            <span class="text-xs font-black uppercase tracking-wider text-slate-400 mr-2">Quick Presets:</span>
            <a href="{{ route('manager.reports.day-closing.history', ['preset' => 'today']) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ $preset === 'today' ? 'bg-teal-600 text-white font-black shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                Today
            </a>
            <a href="{{ route('manager.reports.day-closing.history', ['preset' => 'last_7_days']) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ $preset === 'last_7_days' ? 'bg-teal-600 text-white font-black shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                Last 7 Days
            </a>
            <a href="{{ route('manager.reports.day-closing.history', ['preset' => 'this_month']) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ $preset === 'this_month' ? 'bg-teal-600 text-white font-black shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                This Month
            </a>
            <a href="{{ route('manager.reports.day-closing.history', ['preset' => 'last_month']) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ $preset === 'last_month' ? 'bg-teal-600 text-white font-black shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                Last Month
            </a>
            <a href="{{ route('manager.reports.day-closing.history', ['preset' => 'this_year']) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ $preset === 'this_year' ? 'bg-teal-600 text-white font-black shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                This Year
            </a>
            <a href="{{ route('manager.reports.day-closing.history', ['preset' => 'all']) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ $preset === 'all' ? 'bg-teal-600 text-white font-black shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                All Records
            </a>
        </div>

        <!-- Filter Form Fields -->
        <form action="{{ route('manager.reports.day-closing.history') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
            <input type="hidden" name="preset" value="custom">

            <!-- From Date -->
            <div>
                <label class="block text-[11px] font-black uppercase tracking-wider text-slate-600 mb-1">From Date</label>
                <input type="date" name="start_date" value="{{ $startDate }}"
                       class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-teal-500">
            </div>

            <!-- To Date -->
            <div>
                <label class="block text-[11px] font-black uppercase tracking-wider text-slate-600 mb-1">To Date</label>
                <input type="date" name="end_date" value="{{ $endDate }}"
                       class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-teal-500">
            </div>

            <!-- Status Filter -->
            <div>
                <label class="block text-[11px] font-black uppercase tracking-wider text-slate-600 mb-1">Register Status</label>
                <select name="status" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-teal-500">
                    <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All Statuses</option>
                    <option value="closed" {{ $status === 'closed' ? 'selected' : '' }}>Locked & Closed</option>
                    <option value="verified" {{ $status === 'verified' ? 'selected' : '' }}>Auditor Verified</option>
                    <option value="open" {{ $status === 'open' ? 'selected' : '' }}>Live In-Progress</option>
                </select>
            </div>

            <!-- Discrepancy Filter -->
            <div>
                <label class="block text-[11px] font-black uppercase tracking-wider text-slate-600 mb-1">Drawer Accuracy</label>
                <select name="discrepancy" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-teal-500">
                    <option value="all" {{ $discrepancy === 'all' ? 'selected' : '' }}>All Registers</option>
                    <option value="balanced" {{ $discrepancy === 'balanced' ? 'selected' : '' }}>Balanced (No Diff)</option>
                    <option value="shortage" {{ $discrepancy === 'shortage' ? 'selected' : '' }}>Shortages Only</option>
                    <option value="surplus" {{ $discrepancy === 'surplus' ? 'selected' : '' }}>Surplus Only</option>
                    <option value="has_diff" {{ $discrepancy === 'has_diff' ? 'selected' : '' }}>Any Discrepancy</option>
                </select>
            </div>

            <!-- Search & Actions -->
            <div class="flex items-center gap-2">
                <div class="flex-1">
                    <label class="block text-[11px] font-black uppercase tracking-wider text-slate-600 mb-1">Keyword</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Staff or notes..."
                           class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-teal-500">
                </div>
                <div class="shrink-0 flex items-center gap-1 mt-5">
                    <button type="submit" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs cursor-pointer">
                        Apply
                    </button>
                    <a href="{{ route('manager.reports.day-closing.history') }}" class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl transition-all" title="Reset">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    </a>
                </div>
            </div>
        </form>

    </div>

    <!-- ========================================================================= -->
    <!-- 4. HISTORICAL CLOSINGS AUDIT TABLE                                        -->
    <!-- ========================================================================= -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        
        <div class="p-4 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-black text-slate-900 heading-font">Day Closing Historical Ledger</h3>
                <p class="text-xs text-slate-500">Detailed line-by-line register closing summaries</p>
            </div>
            <div class="text-xs text-slate-500 font-bold">
                Showing {{ $closings->firstItem() ?? 0 }} to {{ $closings->lastItem() ?? 0 }} of {{ $closings->total() }} records
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-100/80 border-b border-slate-200 text-slate-600 font-black uppercase text-[10px] tracking-wider whitespace-nowrap">
                        <th class="py-3 px-3.5">Audit Date</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3 text-right">Opening Float</th>
                        <th class="py-3 px-3 text-right text-emerald-800">Collections (In)</th>
                        <th class="py-3 px-3 text-right text-rose-800">Payments (Out)</th>
                        <th class="py-3 px-3 text-right text-indigo-900">Net Flow</th>
                        <th class="py-3 px-3 text-right">Expected Drawer</th>
                        <th class="py-3 px-3 text-right text-slate-900">Counted Cash</th>
                        <th class="py-3 px-3 text-right">Discrepancy</th>
                        <th class="py-3 px-3 text-right text-teal-800">Tomorrow Float</th>
                        <th class="py-3 px-3">Closed By / Time</th>
                        <th class="py-3 px-3 text-center print:hidden">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($closings as $c)
                    <tr class="hover:bg-slate-50/70 transition-colors whitespace-nowrap">
                        
                        <!-- Date -->
                        <td class="py-3 px-3.5 font-bold text-slate-900">
                            <div class="flex items-center gap-2">
                                <span class="font-black text-slate-950">{{ \Carbon\Carbon::parse($c->closing_date)->format('d M Y') }}</span>
                                <span class="text-[10px] text-slate-400 font-semibold">({{ \Carbon\Carbon::parse($c->closing_date)->format('D') }})</span>
                            </div>
                            @if($c->closing_date->toDateString() === now()->toDateString())
                                <span class="px-1.5 py-0.2 rounded text-[9px] font-black bg-teal-100 text-teal-800 uppercase">Today</span>
                            @elseif($c->closing_date->toDateString() === now()->subDay()->toDateString())
                                <span class="px-1.5 py-0.2 rounded text-[9px] font-black bg-slate-100 text-slate-700 uppercase">Yesterday</span>
                            @endif
                        </td>

                        <!-- Status Badge -->
                        <td class="py-3 px-3">
                            @if($c->status === 'closed')
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    Locked
                                </span>
                            @elseif($c->status === 'verified')
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-blue-100 text-blue-800 border border-blue-200">
                                    Verified
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-800 border border-amber-200">
                                    Live Open
                                </span>
                            @endif
                        </td>

                        <!-- Opening Float -->
                        <td class="py-3 px-3 text-right">
                            <span class="font-bold text-slate-900">Rs. {{ number_format($c->total_opening, 2) }}</span>
                            <div class="text-[9.5px] text-slate-400">
                                C: {{ number_format($c->opening_cash, 0) }} | B: {{ number_format($c->opening_bank, 0) }}
                            </div>
                        </td>

                        <!-- Collections In -->
                        <td class="py-3 px-3 text-right font-bold text-emerald-700">
                            <span>Rs. {{ number_format($c->total_received, 2) }}</span>
                            <div class="text-[9.5px] text-emerald-600/80">
                                C: {{ number_format($c->cash_received, 0) }} | B: {{ number_format($c->bank_received, 0) }}
                            </div>
                        </td>

                        <!-- Payments Out -->
                        <td class="py-3 px-3 text-right font-bold text-rose-700">
                            <span>Rs. {{ number_format($c->total_paid, 2) }}</span>
                            <div class="text-[9.5px] text-rose-600/80">
                                C: {{ number_format($c->cash_paid, 0) }} | B: {{ number_format($c->bank_paid, 0) }}
                            </div>
                        </td>

                        <!-- Net Flow -->
                        <td class="py-3 px-3 text-right font-black {{ $c->total_net_flow >= 0 ? 'text-indigo-900' : 'text-rose-700' }}">
                            {{ $c->total_net_flow >= 0 ? '+' : '' }}Rs. {{ number_format($c->total_net_flow, 2) }}
                        </td>

                        <!-- Expected Drawer Cash -->
                        <td class="py-3 px-3 text-right font-bold text-slate-800">
                            Rs. {{ number_format($c->closing_cash_expected, 2) }}
                        </td>

                        <!-- Actual Counted Cash -->
                        <td class="py-3 px-3 text-right font-black text-slate-950">
                            @if($c->cash_actual_counted !== null)
                                Rs. {{ number_format($c->cash_actual_counted, 2) }}
                            @else
                                <span class="text-slate-400 font-normal italic">Uncounted</span>
                            @endif
                        </td>

                        <!-- Discrepancy -->
                        <td class="py-3 px-3 text-right font-black">
                            @if($c->cash_difference === null)
                                <span class="text-slate-400 text-[10px] font-normal">-</span>
                            @elseif($c->cash_difference == 0)
                                <span class="px-2 py-0.5 rounded text-[10px] bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    Rs. 0.00 Balanced
                                </span>
                            @elseif($c->cash_difference < 0)
                                <span class="px-2 py-0.5 rounded text-[10px] bg-rose-100 text-rose-800 border border-rose-200">
                                    -Rs. {{ number_format(abs($c->cash_difference), 2) }} Short
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] bg-blue-100 text-blue-800 border border-blue-200">
                                    +Rs. {{ number_format($c->cash_difference, 2) }} Over
                                </span>
                            @endif
                        </td>

                        <!-- Next Day Float -->
                        <td class="py-3 px-3 text-right">
                            @if($c->next_day_opening_cash !== null)
                                <span class="font-bold text-teal-800">Rs. {{ number_format($c->next_day_opening_cash, 2) }}</span>
                            @else
                                <span class="text-slate-400 text-[10px] font-normal">Not Set</span>
                            @endif
                        </td>

                        <!-- Closed By / Time -->
                        <td class="py-3 px-3 text-[11px]">
                            @if($c->closedBy)
                                <div class="font-bold text-slate-800">{{ $c->closedBy->name }}</div>
                            @else
                                <span class="text-slate-400 italic">Auto / System</span>
                            @endif
                            @if($c->closed_at)
                                <div class="text-[9.5px] text-slate-400">{{ $c->closed_at->format('h:i A') }}</div>
                            @endif
                        </td>

                        <!-- Action Buttons -->
                        <td class="py-3 px-3 text-center print:hidden">
                            <div class="flex items-center justify-center gap-1.5">
                                <a href="{{ route('manager.reports.day-closing', ['date' => $c->closing_date->toDateString()]) }}"
                                   title="Open Full Day Audit"
                                   class="p-1.5 rounded-lg bg-teal-50 hover:bg-teal-100 text-teal-700 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                </a>

                                <button type="button" 
                                        @click="openDetail({{ json_encode($c) }})"
                                        title="Quick Inspection Modal"
                                        class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </button>
                            </div>
                        </td>

                    </tr>
                    @empty
                    <tr>
                        <td colspan="12" class="py-12 text-center text-slate-400 font-bold">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <span class="text-3xl">📜</span>
                                <p class="text-sm font-black text-slate-700">No Day Closing records found matching this criteria.</p>
                                <p class="text-xs text-slate-400">Try adjusting your date range or filter presets.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($closings->hasPages())
        <div class="p-4 bg-slate-50 border-t border-slate-200 print:hidden">
            {{ $closings->links() }}
        </div>
        @endif

    </div>

    <!-- ========================================================================= -->
    <!-- 5. QUICK RECORD DETAIL MODAL (WEB ONLY)                                   -->
    <!-- ========================================================================= -->
    <div x-show="detailModalOpen" 
         x-cloak 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs print:hidden"
         @keydown.escape.window="detailModalOpen = false">
        
        <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-5 max-h-[90vh] overflow-y-auto"
             @click.away="detailModalOpen = false">
            
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center text-lg">
                        📜
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-900 heading-font">Day Closing Inspection</h3>
                        <p class="text-[10px] text-slate-500 font-medium" x-text="'Audit Date: ' + (selectedRecord ? selectedRecord.closing_date : '')"></p>
                    </div>
                </div>
                <button type="button" @click="detailModalOpen = false" class="text-slate-400 hover:text-slate-600 font-black text-lg p-1 cursor-pointer">
                    ✕
                </button>
            </div>

            <template x-if="selectedRecord">
                <div class="space-y-4 text-xs">
                    
                    <!-- Top Status & Handover Strip -->
                    <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Lock Status</span>
                            <div class="font-black text-slate-900 capitalize" x-text="selectedRecord.status"></div>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Audit Discrepancy</span>
                            <div class="font-black"
                                 :class="(parseFloat(selectedRecord.cash_difference) || 0) === 0 ? 'text-emerald-700' : ((parseFloat(selectedRecord.cash_difference) || 0) < 0 ? 'text-rose-700' : 'text-blue-700')"
                                 x-text="(parseFloat(selectedRecord.cash_difference) || 0) === 0 ? 'Rs. 0.00 Balanced' : ((parseFloat(selectedRecord.cash_difference) || 0) < 0 ? '-Rs. ' + Math.abs(selectedRecord.cash_difference).toLocaleString() + ' (Shortage)' : '+Rs. ' + selectedRecord.cash_difference.toLocaleString() + ' (Surplus)')">
                            </div>
                        </div>
                    </div>

                    <!-- Flow Grid -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3 rounded-xl bg-emerald-50/60 border border-emerald-100">
                            <span class="text-[10px] font-black text-emerald-800 uppercase block">Total Inflows (In)</span>
                            <span class="text-base font-black text-emerald-900" x-text="'Rs. ' + (parseFloat(selectedRecord.total_received) || 0).toLocaleString()"></span>
                            <div class="text-[10px] text-emerald-700 mt-1">
                                Cash: Rs. <span x-text="(parseFloat(selectedRecord.cash_received) || 0).toLocaleString()"></span><br>
                                Bank: Rs. <span x-text="(parseFloat(selectedRecord.bank_received) || 0).toLocaleString()"></span>
                            </div>
                        </div>
                        <div class="p-3 rounded-xl bg-rose-50/60 border border-rose-100">
                            <span class="text-[10px] font-black text-rose-800 uppercase block">Total Payments (Out)</span>
                            <span class="text-base font-black text-rose-900" x-text="'Rs. ' + (parseFloat(selectedRecord.total_paid) || 0).toLocaleString()"></span>
                            <div class="text-[10px] text-rose-700 mt-1">
                                Cash: Rs. <span x-text="(parseFloat(selectedRecord.cash_paid) || 0).toLocaleString()"></span><br>
                                Bank: Rs. <span x-text="(parseFloat(selectedRecord.bank_paid) || 0).toLocaleString()"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Drawer Position -->
                    <div class="p-3.5 rounded-2xl border border-slate-200 bg-white space-y-2">
                        <div class="flex items-center justify-between pb-1 border-b border-slate-100">
                            <span class="text-slate-500 font-bold">Expected Drawer Cash:</span>
                            <span class="font-black text-slate-900" x-text="'Rs. ' + (parseFloat(selectedRecord.closing_cash_expected) || 0).toLocaleString(undefined, {minimumFractionDigits: 2})"></span>
                        </div>
                        <div class="flex items-center justify-between pb-1 border-b border-slate-100">
                            <span class="text-slate-500 font-bold">Physical Counted Cash:</span>
                            <span class="font-black text-slate-900" x-text="'Rs. ' + (parseFloat(selectedRecord.cash_actual_counted) || 0).toLocaleString(undefined, {minimumFractionDigits: 2})"></span>
                        </div>
                        <div class="flex items-center justify-between pb-1 border-b border-slate-100">
                            <span class="text-slate-500 font-bold">Designated Tomorrow Float:</span>
                            <span class="font-black text-teal-800" x-text="selectedRecord.next_day_opening_cash !== null ? 'Rs. ' + parseFloat(selectedRecord.next_day_opening_cash).toLocaleString(undefined, {minimumFractionDigits: 2}) : 'Not Set'"></span>
                        </div>
                    </div>

                    <!-- Denominations Breakdown (If present) -->
                    <template x-if="selectedRecord.denominations">
                        <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-2">
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-500 block">Denominations Counted:</span>
                            <div class="grid grid-cols-4 gap-1.5 text-center text-[10px]">
                                <div class="bg-white p-1.5 rounded-lg border border-slate-200 font-bold">
                                    <span class="text-slate-400 block">5000:</span>
                                    <span class="font-black" x-text="selectedRecord.denominations['5000'] || 0"></span>
                                </div>
                                <div class="bg-white p-1.5 rounded-lg border border-slate-200 font-bold">
                                    <span class="text-slate-400 block">1000:</span>
                                    <span class="font-black" x-text="selectedRecord.denominations['1000'] || 0"></span>
                                </div>
                                <div class="bg-white p-1.5 rounded-lg border border-slate-200 font-bold">
                                    <span class="text-slate-400 block">500:</span>
                                    <span class="font-black" x-text="selectedRecord.denominations['500'] || 0"></span>
                                </div>
                                <div class="bg-white p-1.5 rounded-lg border border-slate-200 font-bold">
                                    <span class="text-slate-400 block">100:</span>
                                    <span class="font-black" x-text="selectedRecord.denominations['100'] || 0"></span>
                                </div>
                                <div class="bg-white p-1.5 rounded-lg border border-slate-200 font-bold">
                                    <span class="text-slate-400 block">50:</span>
                                    <span class="font-black" x-text="selectedRecord.denominations['50'] || 0"></span>
                                </div>
                                <div class="bg-white p-1.5 rounded-lg border border-slate-200 font-bold">
                                    <span class="text-slate-400 block">20:</span>
                                    <span class="font-black" x-text="selectedRecord.denominations['20'] || 0"></span>
                                </div>
                                <div class="bg-white p-1.5 rounded-lg border border-slate-200 font-bold">
                                    <span class="text-slate-400 block">10:</span>
                                    <span class="font-black" x-text="selectedRecord.denominations['10'] || 0"></span>
                                </div>
                                <div class="bg-white p-1.5 rounded-lg border border-slate-200 font-bold">
                                    <span class="text-slate-400 block">Coins:</span>
                                    <span class="font-black" x-text="selectedRecord.denominations['coins'] || 0"></span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Handover Notes -->
                    <template x-if="selectedRecord.notes">
                        <div class="p-3 rounded-2xl bg-amber-50/60 border border-amber-200 text-amber-950">
                            <span class="text-[10px] font-black uppercase tracking-wider text-amber-800 block">Closing Notes:</span>
                            <p class="mt-1 italic" x-text="selectedRecord.notes"></p>
                        </div>
                    </template>

                    <!-- Footer Link -->
                    <div class="pt-2 flex items-center justify-between border-t border-slate-100">
                        <button type="button" @click="detailModalOpen = false"
                                class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors cursor-pointer">
                            Close
                        </button>
                        <a :href="'{{ url('manager/reports/day-closing') }}?date=' + selectedRecord.closing_date.substring(0, 10)"
                           class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs rounded-xl shadow-xs transition-colors flex items-center gap-1.5">
                            <span>Open Full Day Report</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </a>
                    </div>

                </div>
            </template>
        </div>
    </div>

</div>
@endsection
