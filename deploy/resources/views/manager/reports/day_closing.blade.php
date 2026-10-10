@extends('layouts.material')

@section('title', 'Daily Day Closing & Cash Reconciliation - ' . \Carbon\Carbon::parse($date)->format('d M Y'))

@section('content')
<div class="space-y-6 pb-12" x-data="{
    activeTab: 'statement',
    // Denominations for physical drawer cash counting
    denom: {
        n5000: {{ isset($dayClosing->denominations['5000']) ? (int)$dayClosing->denominations['5000'] : 0 }},
        n1000: {{ isset($dayClosing->denominations['1000']) ? (int)$dayClosing->denominations['1000'] : 0 }},
        n500: {{ isset($dayClosing->denominations['500']) ? (int)$dayClosing->denominations['500'] : 0 }},
        n100: {{ isset($dayClosing->denominations['100']) ? (int)$dayClosing->denominations['100'] : 0 }},
        n50: {{ isset($dayClosing->denominations['50']) ? (int)$dayClosing->denominations['50'] : 0 }},
        n20: {{ isset($dayClosing->denominations['20']) ? (int)$dayClosing->denominations['20'] : 0 }},
        n10: {{ isset($dayClosing->denominations['10']) ? (int)$dayClosing->denominations['10'] : 0 }},
        coins: {{ isset($dayClosing->denominations['coins']) ? (float)$dayClosing->denominations['coins'] : 0 }}
    },
    manualCount: {{ $dayClosing && $dayClosing->cash_actual_counted !== null ? (float)$dayClosing->cash_actual_counted : 0 }},
    useDenom: true,
    expectedCash: {{ (float)$closingCashExpected }},
    get calculatedCash() {
        if (!this.useDenom) return parseFloat(this.manualCount) || 0;
        return (this.denom.n5000 * 5000) +
               (this.denom.n1000 * 1000) +
               (this.denom.n500 * 500) +
               (this.denom.n100 * 100) +
               (this.denom.n50 * 50) +
               (this.denom.n20 * 20) +
               (this.denom.n10 * 10) +
               (parseFloat(this.denom.coins) || 0);
    },
    get difference() {
        return Math.round((this.calculatedCash - this.expectedCash) * 100) / 100;
    },
    addQty(key, count) {
        this.denom[key] = Math.max(0, (parseInt(this.denom[key]) || 0) + count);
    },
    clearDenom() {
        this.denom.n5000 = 0;
        this.denom.n1000 = 0;
        this.denom.n500 = 0;
        this.denom.n100 = 0;
        this.denom.n50 = 0;
        this.denom.n20 = 0;
        this.denom.n10 = 0;
        this.denom.coins = 0;
        this.manualCount = 0;
    },
    matchExpected() {
        this.useDenom = false;
        this.manualCount = this.expectedCash;
    }
}">

    <!-- ========================================================================= -->
    <!-- CRYSTAL REPORTS OFFICIAL DOCUMENT HEADER (PRINT ONLY)                    -->
    <!-- ========================================================================= -->
    <div class="hidden print:block crystal-container mb-4">
        <!-- Top Company Header -->
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
                    @if(isset($appSetting) && ($appSetting->brand_phone1 || $appSetting->brand_phone2))
                        <p class="text-[9px] font-bold text-slate-700 mt-0.5">
                            Tel: {{ implode(' | ', array_filter([$appSetting->brand_phone1, $appSetting->brand_phone2])) }}
                        </p>
                    @endif
                </div>
            </div>
            <div class="text-right text-[8.5pt]">
                <div class="font-bold text-slate-800 uppercase tracking-wide">Enterprise ERP System</div>
                <div class="text-[7.5pt] text-slate-500">Official Daily Cash Register Audit</div>
                <div class="text-[7.5pt] font-semibold text-slate-600 mt-0.5">Doc ID: DC-{{ \Carbon\Carbon::parse($date)->format('Ymd') }}</div>
            </div>
        </div>

        <!-- Double Divider Rule -->
        <div class="crystal-double-rule"></div>

        <!-- Title Banner -->
        <div class="crystal-title-banner">
            <h2>DAILY DAY CLOSING & CASH RECONCILIATION REPORT</h2>
            <p>Official Cash Register Audit | Audit Date: {{ \Carbon\Carbon::parse($date)->format('l, d F Y') }}</p>
        </div>

        <!-- Crystal Reports Parameters Matrix -->
        <table class="crystal-param-grid">
            <tr>
                <td class="label">Audit Date:</td>
                <td class="val">{{ \Carbon\Carbon::parse($date)->format('l, d F Y') }}</td>
                <td class="label">Status:</td>
                <td class="val">
                    @if($dayClosing && $dayClosing->status === 'closed') Day Locked & Closed
                    @elseif($dayClosing && $dayClosing->status === 'verified') Auditor Verified
                    @else Live Register (In-Progress)
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label">Print Date/Time:</td>
                <td class="val">{{ now()->format('d-M-Y h:i:s A') }}</td>
                <td class="label">Generated By:</td>
                <td class="val">{{ Auth::user()->name }} (Operator ID: #{{ Auth::id() }})</td>
            </tr>
        </table>
    </div>

    <!-- ========================================================================= -->
    <!-- 1. SIMPLE HEADER WITH DATE STEPPER (WEB ONLY)                             -->
    <!-- ========================================================================= -->
    <div class="bg-white border border-slate-200 shadow-sm p-4 sm:p-5 space-y-4 print:hidden">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-teal-50 text-teal-600">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <div>
                    <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Day Closing & Cash Reconciliation</h1>
                    <div class="flex flex-wrap items-center gap-2 mt-1">
                        <p class="text-xs font-semibold text-slate-500">Daily Register Audit &mdash; {{ \Carbon\Carbon::parse($date)->format('l, d F Y') }}</p>
                        @if($dayClosing && $dayClosing->status === 'closed')
                            <span class="px-2 py-0.5 rounded text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200">🔒 Day Locked & Closed</span>
                        @elseif($dayClosing && $dayClosing->status === 'verified')
                            <span class="px-2 py-0.5 rounded text-[10px] font-black bg-blue-100 text-blue-800 border border-blue-200">⭐ Auditor Verified</span>
                        @else
                            <span class="px-2 py-0.5 rounded text-[10px] font-black bg-amber-100 text-amber-800 border border-amber-200">⚡ Live Register (In-Progress)</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <!-- Date Stepper -->
                <a href="{{ route('manager.reports.day-closing', ['date' => \Carbon\Carbon::parse($date)->subDay()->toDateString()]) }}"
                   title="Previous Day"
                   class="w-9 h-9 flex items-center justify-center border border-slate-300 hover:border-slate-400 bg-white hover:bg-slate-50 text-slate-600 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
                </a>
                <form action="{{ route('manager.reports.day-closing') }}" method="GET" class="flex items-center">
                    <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()"
                           class="border border-slate-300 text-slate-800 text-xs font-bold px-3 py-2 focus:outline-none focus:border-teal-500 bg-white">
                </form>
                <a href="{{ route('manager.reports.day-closing', ['date' => \Carbon\Carbon::parse($date)->addDay()->toDateString()]) }}"
                   title="Next Day"
                   class="w-9 h-9 flex items-center justify-center border border-slate-300 hover:border-slate-400 bg-white hover:bg-slate-50 text-slate-600 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                </a>
                <a href="{{ route('manager.reports.day-closing', ['date' => now()->toDateString()]) }}"
                   class="px-3 py-2 text-xs font-black border {{ $date === now()->toDateString() ? 'bg-teal-600 text-white border-teal-600' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }} transition-colors">
                    Today
                </a>
                <a href="{{ route('manager.reports.day-closing', ['date' => now()->subDay()->toDateString()]) }}"
                   class="px-3 py-2 text-xs font-black border {{ $date === now()->subDay()->toDateString() ? 'bg-teal-600 text-white border-teal-600' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }} transition-colors">
                    Yesterday
                </a>

                <button type="button" onclick="window.print()"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-xs transition-colors cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    <span>Print Report</span>
                </button>
            </div>
        </div>

        <!-- Reports Tabs -->
        <div class="flex border-b border-slate-200 overflow-x-auto scrollbar-none gap-2 text-xs font-bold pb-0.5">
            <a href="{{ route('manager.reports.day-closing') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.day-closing') ? 'border-teal-600 text-teal-700 bg-teal-50/50 font-extrabold' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>📅 Day Closing</span>
            </a>
            <a href="{{ route('manager.reports.profit') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.profit') ? 'border-emerald-600 text-emerald-700 bg-emerald-50/50 font-extrabold' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>💰 Profit & Loss</span>
            </a>
            <a href="{{ route('manager.reports.sales') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.sales') ? 'border-indigo-600 text-indigo-600 bg-indigo-50/40' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>📊 Sales Report</span>
            </a>
            <a href="{{ route('manager.reports.stock') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.stock') ? 'border-indigo-600 text-indigo-600 bg-indigo-50/40' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>📦 Stock Report</span>
            </a>
            <a href="{{ route('manager.reports.services') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.services') ? 'border-indigo-600 text-indigo-600 bg-indigo-50/40' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>✂️ Services</span>
            </a>
            <a href="{{ route('manager.reports.ledger') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.ledger') ? 'border-indigo-600 text-indigo-600 bg-indigo-50/40' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>📗 Ledger</span>
            </a>
            <a href="{{ route('manager.reports.purchases') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.purchases') ? 'border-indigo-600 text-indigo-600 bg-indigo-50/40' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>🛒 Purchases</span>
            </a>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 3. FINANCIAL COMMAND CARDS (3 Hero Cards + 2 Inflow/Outflow Gauges)        -->
    <!-- ========================================================================= -->
    <div class="space-y-4 print:hidden">
        
        <!-- TOP 3 HERO CARDS -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            
            <!-- CARD 1: CASH IN DRAWER (Physical Cash) -->
            <div class="rounded-3xl p-6 text-white shadow-xl relative overflow-hidden transition-all duration-300 hover:translate-y-[-2px] border border-emerald-500/30 flex flex-col justify-between"
                 style="background: linear-gradient(145deg, #064e3b 0%, #065f46 40%, #022c22 100%);">
                
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-2xl bg-emerald-400/20 text-emerald-300 flex items-center justify-center text-lg shadow-inner">
                            💵
                        </div>
                        <div>
                            <span class="text-xs font-black uppercase tracking-wider text-emerald-200">Cash In Drawer</span>
                            <p class="text-[10px] text-emerald-300/80 font-medium">Physical Register Balance</p>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-400/20 text-emerald-200 border border-emerald-400/30">
                        In Hand
                    </span>
                </div>

                <div class="my-2">
                    <div class="text-xs text-emerald-300 font-bold uppercase tracking-wider">Expected Cash Closing</div>
                    <div class="text-3xl lg:text-4xl font-black tracking-tight text-white heading-font mt-1">
                        Rs. {{ number_format($closingCashExpected, 2) }}
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-emerald-400/20 flex flex-wrap items-center justify-between gap-2 text-xs font-bold text-emerald-100">
                    <div class="flex items-center gap-1.5">
                        <span class="text-emerald-300 text-[10px] uppercase">Opening:</span>
                        <span>Rs. {{ number_format($openingCash, 2) }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 px-2 py-0.5 rounded-lg {{ $netCashFlow >= 0 ? 'bg-emerald-400/20 text-emerald-200' : 'bg-rose-400/20 text-rose-200' }}">
                        <span class="text-[10px] uppercase">Net:</span>
                        <span>{{ $netCashFlow >= 0 ? '+' : '' }}Rs. {{ number_format($netCashFlow, 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- CARD 2: DIGITAL & BANK HOLDINGS -->
            <div class="rounded-3xl p-6 text-white shadow-xl relative overflow-hidden transition-all duration-300 hover:translate-y-[-2px] border border-indigo-500/30 flex flex-col justify-between"
                 style="background: linear-gradient(145deg, #312e81 0%, #3730a3 40%, #1e1b4b 100%);">
                
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-2xl bg-indigo-400/20 text-indigo-300 flex items-center justify-center text-lg shadow-inner">
                            🏦
                        </div>
                        <div>
                            <span class="text-xs font-black uppercase tracking-wider text-indigo-200">Bank & Digital Vault</span>
                            <p class="text-[10px] text-indigo-300/80 font-medium">JazzCash • Easypaisa • Banks</p>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-indigo-400/20 text-indigo-200 border border-indigo-400/30">
                        Digital
                    </span>
                </div>

                <div class="my-2">
                    <div class="text-xs text-indigo-300 font-bold uppercase tracking-wider">Expected Bank Closing</div>
                    <div class="text-3xl lg:text-4xl font-black tracking-tight text-white heading-font mt-1">
                        Rs. {{ number_format($closingBankExpected, 2) }}
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-indigo-400/20 flex flex-wrap items-center justify-between gap-2 text-xs font-bold text-indigo-100">
                    <div class="flex items-center gap-1.5">
                        <span class="text-indigo-300 text-[10px] uppercase">Opening:</span>
                        <span>Rs. {{ number_format($openingBank, 2) }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 px-2 py-0.5 rounded-lg {{ $netBankFlow >= 0 ? 'bg-indigo-400/20 text-indigo-200' : 'bg-rose-400/20 text-rose-200' }}">
                        <span class="text-[10px] uppercase">Net:</span>
                        <span>{{ $netBankFlow >= 0 ? '+' : '' }}Rs. {{ number_format($netBankFlow, 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- CARD 3: GRAND CONSOLIDATED POSITION -->
            <div class="rounded-3xl p-6 text-white shadow-xl relative overflow-hidden transition-all duration-300 hover:translate-y-[-2px] border border-slate-700/80 flex flex-col justify-between"
                 style="background: linear-gradient(145deg, #0f172a 0%, #1e293b 50%, #020617 100%);">
                
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-2xl bg-white/15 text-white flex items-center justify-center text-lg shadow-inner">
                            💎
                        </div>
                        <div>
                            <span class="text-xs font-black uppercase tracking-wider text-slate-200">Total Business Liquidity</span>
                            <p class="text-[10px] text-slate-400 font-medium">Consolidated Cash + Bank</p>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-white/15 text-white border border-white/20">
                        Total Funds
                    </span>
                </div>

                <div class="my-2">
                    <div class="text-xs text-slate-400 font-bold uppercase tracking-wider">Final Closing Position</div>
                    <div class="text-3xl lg:text-4xl font-black tracking-tight text-white heading-font mt-1">
                        Rs. {{ number_format($closingTotalExpected, 2) }}
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-700/80 flex flex-wrap items-center justify-between gap-2 text-xs font-bold text-slate-200">
                    <div class="flex items-center gap-1.5">
                        <span class="text-slate-400 text-[10px] uppercase">Total Opening:</span>
                        <span>Rs. {{ number_format($totalOpening, 2) }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 px-2 py-0.5 rounded-lg {{ $totalNetFlow >= 0 ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
                        <span class="text-[10px] uppercase">Daily Flow:</span>
                        <span>{{ $totalNetFlow >= 0 ? '+' : '' }}Rs. {{ number_format($totalNetFlow, 2) }}</span>
                    </div>
                </div>
            </div>

        </div>

        <!-- SECONDARY STRIP: TODAY'S TOTAL COLLECTIONS VS PAYMENTS -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            
            <!-- INFLOWS BAR -->
            <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-xs flex flex-col justify-between space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-black text-sm">📥</span>
                        <div>
                            <span class="text-xs font-black uppercase tracking-wider text-slate-800">Today's Inflows (Receivings)</span>
                            <p class="text-[10px] text-slate-500 font-medium">Services, Product Sales, Customer Receipts</p>
                        </div>
                    </div>
                    <span class="text-lg font-black text-emerald-600 heading-font">
                        Rs. {{ number_format($totalReceived, 2) }}
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                    <div class="bg-slate-50 rounded-2xl p-3 border border-slate-100">
                        <div class="text-[10px] font-black uppercase tracking-wider text-slate-500 flex items-center justify-between">
                            <span>Cash In</span>
                            <span>💵</span>
                        </div>
                        <div class="text-base font-black text-slate-900 mt-1">
                            Rs. {{ number_format($totalCashReceived, 2) }}
                        </div>
                    </div>
                    <div class="bg-slate-50 rounded-2xl p-3 border border-slate-100">
                        <div class="text-[10px] font-black uppercase tracking-wider text-slate-500 flex items-center justify-between">
                            <span>Bank In</span>
                            <span>🏦</span>
                        </div>
                        <div class="text-base font-black text-indigo-700 mt-1">
                            Rs. {{ number_format($totalBankReceived, 2) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- OUTFLOWS BAR -->
            <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-xs flex flex-col justify-between space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center font-black text-sm">📤</span>
                        <div>
                            <span class="text-xs font-black uppercase tracking-wider text-slate-800">Today's Outflows (Payments)</span>
                            <p class="text-[10px] text-slate-500 font-medium">Expenses, Supplier Orders, Payroll, Advances</p>
                        </div>
                    </div>
                    <span class="text-lg font-black text-rose-600 heading-font">
                        Rs. {{ number_format($totalPaid, 2) }}
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                    <div class="bg-slate-50 rounded-2xl p-3 border border-slate-100">
                        <div class="text-[10px] font-black uppercase tracking-wider text-slate-500 flex items-center justify-between">
                            <span>Cash Out</span>
                            <span>💵</span>
                        </div>
                        <div class="text-base font-black text-slate-900 mt-1">
                            Rs. {{ number_format($totalCashPaid, 2) }}
                        </div>
                    </div>
                    <div class="bg-slate-50 rounded-2xl p-3 border border-slate-100">
                        <div class="text-[10px] font-black uppercase tracking-wider text-slate-500 flex items-center justify-between">
                            <span>Bank Out</span>
                            <span>🏦</span>
                        </div>
                        <div class="text-base font-black text-rose-700 mt-1">
                            Rs. {{ number_format($totalBankPaid, 2) }}
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <!-- ========================================================================= -->
    <!-- 4. NOTIFICATION / STATUS CALLOUT (If day is locked)                        -->
    <!-- ========================================================================= -->
    @if(session('success'))
    <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 flex items-center gap-3 shadow-xs print:hidden">
        <span class="text-2xl">🎉</span>
        <div class="text-xs font-bold">{{ session('success') }}</div>
    </div>
    @endif

    @if($dayClosing && $dayClosing->status !== 'open')
    <div class="p-5 rounded-3xl bg-gradient-to-r from-emerald-50 via-teal-50 to-emerald-50 border border-emerald-200 shadow-xs flex flex-col md:flex-row items-start md:items-center justify-between gap-4 print:hidden">
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-emerald-600 text-white flex items-center justify-center text-xl shrink-0 shadow-md">
                🔒
            </div>
            <div>
                <h2 class="text-sm font-black text-emerald-950 heading-font">
                    Day Officially Closed on {{ $dayClosing->closed_at ? $dayClosing->closed_at->format('M d, Y \a\t h:i A') : $dayClosing->created_at->format('M d, Y') }}
                </h2>
                <p class="text-xs text-emerald-700 mt-0.5">
                    Closed by <strong>{{ $dayClosing->closedBy->name ?? 'Management' }}</strong> • 
                    Counted Cash: <strong>Rs. {{ number_format($dayClosing->cash_actual_counted ?? 0, 2) }}</strong> • 
                    Discrepancy: <strong class="{{ ($dayClosing->cash_difference ?? 0) == 0 ? 'text-emerald-700' : (($dayClosing->cash_difference ?? 0) > 0 ? 'text-blue-700' : 'text-rose-700') }}">
                        {{ ($dayClosing->cash_difference ?? 0) == 0 ? 'Rs. 0.00 (Perfect Match)' : (($dayClosing->cash_difference ?? 0) > 0 ? '+Rs. ' . number_format($dayClosing->cash_difference, 2) . ' (Surplus)' : '-Rs. ' . number_format(abs($dayClosing->cash_difference), 2) . ' (Shortage)') }}
                    </strong>
                </p>
                @if($dayClosing->notes)
                    <p class="text-[11px] text-slate-600 italic mt-1 bg-white/80 px-3 py-1 rounded-lg border border-emerald-100 inline-block">
                        "{{ $dayClosing->notes }}"
                    </p>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3.5 py-1.5 rounded-full text-xs font-black uppercase tracking-wider bg-emerald-200 text-emerald-900 border border-emerald-300">
                Status: {{ ucfirst($dayClosing->status) }}
            </span>
        </div>
    </div>
    @endif

    <!-- ========================================================================= -->
    <!-- 5. LUXURY TAB CONTROLLER & CONTENT                                        -->
    <!-- ========================================================================= -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200/80 overflow-hidden print:hidden">
        
        <!-- Tab Navigation Header -->
        <div class="flex flex-wrap items-center justify-between border-b border-slate-200 px-6 pt-4 bg-slate-50/70">
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" 
                        @click="activeTab = 'statement'"
                        :class="activeTab === 'statement' ? 'border-emerald-600 text-emerald-800 font-black bg-white shadow-xs' : 'border-transparent text-slate-600 hover:text-slate-900 font-bold'"
                        class="px-4 py-3 border-b-2 text-xs transition-all rounded-t-2xl flex items-center gap-2">
                    <span>📊</span> Movement Statement
                </button>
                <button type="button" 
                        @click="activeTab = 'reconciliation'"
                        :class="activeTab === 'reconciliation' ? 'border-emerald-600 text-emerald-800 font-black bg-white shadow-xs' : 'border-transparent text-slate-600 hover:text-slate-900 font-bold'"
                        class="px-4 py-3 border-b-2 text-xs transition-all rounded-t-2xl flex items-center gap-2">
                    <span>🧮</span> Physical Cash Drawer Count & Lock
                    <span class="px-1.5 py-0.2 rounded text-[10px] bg-emerald-100 text-emerald-800 font-black">Denominations</span>
                </button>
                <button type="button" 
                        @click="activeTab = 'receivings'"
                        :class="activeTab === 'receivings' ? 'border-emerald-600 text-emerald-800 font-black bg-white shadow-xs' : 'border-transparent text-slate-600 hover:text-slate-900 font-bold'"
                        class="px-4 py-3 border-b-2 text-xs transition-all rounded-t-2xl flex items-center gap-2">
                    <span>📥</span> Itemized Receivings
                    <span class="px-1.5 py-0.2 rounded text-[10px] bg-emerald-100 text-emerald-800 font-black">{{ $allAppointments->count() + $allSales->count() + $directReceivings->count() }}</span>
                </button>
                <button type="button" 
                        @click="activeTab = 'payments'"
                        :class="activeTab === 'payments' ? 'border-emerald-600 text-emerald-800 font-black bg-white shadow-xs' : 'border-transparent text-slate-600 hover:text-slate-900 font-bold'"
                        class="px-4 py-3 border-b-2 text-xs transition-all rounded-t-2xl flex items-center gap-2">
                    <span>📤</span> Itemized Payments
                    <span class="px-1.5 py-0.2 rounded text-[10px] bg-rose-100 text-rose-800 font-black">{{ $expenses->count() + $purchases->count() + $payrolls->count() + $advances->count() + $directPayments->count() }}</span>
                </button>
                <button type="button" 
                        @click="activeTab = 'bank'"
                        :class="activeTab === 'bank' ? 'border-emerald-600 text-emerald-800 font-black bg-white shadow-xs' : 'border-transparent text-slate-600 hover:text-slate-900 font-bold'"
                        class="px-4 py-3 border-b-2 text-xs transition-all rounded-t-2xl flex items-center gap-2">
                    <span>🏦</span> Bank Accounts
                </button>
            </div>

            <div class="text-xs text-slate-400 font-bold py-2">
                Date: <strong class="text-slate-800">{{ \Carbon\Carbon::parse($date)->format('d M Y') }}</strong>
            </div>
        </div>

        <!-- ===================================================================== -->
        <!-- TAB 1: LUXURY STRUCTURED STATEMENT MATRIX                             -->
        <!-- ===================================================================== -->
        <div x-show="activeTab === 'statement'" class="p-6 space-y-6">
            
            <!-- SECTION 1: OPENING BALANCE CARD -->
            <div class="rounded-3xl p-5 border border-indigo-100 bg-gradient-to-r from-indigo-50/60 via-slate-50/40 to-indigo-50/60 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-xs font-black shadow-xs">1</span>
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-indigo-950">Starting Float / Opening Balances</h3>
                            <p class="text-[11px] text-slate-500 font-medium">Daily opening float position (Fixed to 0.00)</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-xs text-slate-400 font-bold uppercase">Total Opening</span>
                        <div class="text-lg font-black text-indigo-950 heading-font">Rs. {{ number_format($totalOpening, 2) }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                    <div class="bg-white rounded-2xl p-3.5 border border-indigo-100/80 shadow-xs flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-base">💵</span>
                            <span class="text-xs font-bold text-slate-700">Cash Starting Float</span>
                        </div>
                        <span class="text-sm font-black text-slate-900">Rs. {{ number_format($openingCash, 2) }}</span>
                    </div>
                    <div class="bg-white rounded-2xl p-3.5 border border-indigo-100/80 shadow-xs flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-base">🏦</span>
                            <span class="text-xs font-bold text-slate-700">Bank / Digital Starting</span>
                        </div>
                        <span class="text-sm font-black text-indigo-700">Rs. {{ number_format($openingBank, 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: COLLECTIONS & RECEIVINGS (INFLOWS) -->
            <div class="rounded-3xl p-5 border border-emerald-100 bg-gradient-to-r from-emerald-50/40 via-white to-emerald-50/40 space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-xs font-black shadow-xs">2</span>
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-emerald-950">Daily Receivings & Collections (Cash In)</h3>
                            <p class="text-[11px] text-slate-500 font-medium">All customer revenue streams collected today</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-xs text-emerald-700 font-bold uppercase">Total Inflow</span>
                        <div class="text-lg font-black text-emerald-800 heading-font">Rs. {{ number_format($totalReceived, 2) }}</div>
                    </div>
                </div>

                <div class="overflow-x-auto border border-emerald-100/80 rounded-2xl bg-white shadow-xs">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-emerald-50/70 text-emerald-950 text-[10px] uppercase font-black tracking-wider border-b border-emerald-100">
                            <tr>
                                <th class="py-3 px-4">Revenue Stream</th>
                                <th class="py-3 px-4 text-center">Volume / Records</th>
                                <th class="py-3 px-4 text-right">Cash Collected</th>
                                <th class="py-3 px-4 text-right">Bank / Digital</th>
                                <th class="py-3 px-4 text-right font-black">Combined Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700 font-medium">
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-3 px-4 font-bold flex items-center gap-2">
                                    <span class="text-base">✂️</span>
                                    <span>Salon Services & Treatments Rendered</span>
                                </td>
                                <td class="py-3 px-4 text-center font-bold text-slate-500">{{ $allAppointments->count() }} bookings</td>
                                <td class="py-3 px-4 text-right font-bold text-slate-900">Rs. {{ number_format($cashAptTotal, 2) }}</td>
                                <td class="py-3 px-4 text-right font-bold text-indigo-700">Rs. {{ number_format($bankAptTotal, 2) }}</td>
                                <td class="py-3 px-4 text-right font-black text-slate-900 bg-slate-50/50">Rs. {{ number_format($totalAptReceived, 2) }}</td>
                            </tr>
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-3 px-4 font-bold flex items-center gap-2">
                                    <span class="text-base">🛒</span>
                                    <span>Product Retail POS Sales Invoiced</span>
                                </td>
                                <td class="py-3 px-4 text-center font-bold text-slate-500">{{ $allSales->count() }} invoices</td>
                                <td class="py-3 px-4 text-right font-bold text-slate-900">Rs. {{ number_format($cashSalesTotal, 2) }}</td>
                                <td class="py-3 px-4 text-right font-bold text-indigo-700">Rs. {{ number_format($bankSalesTotal, 2) }}</td>
                                <td class="py-3 px-4 text-right font-black text-slate-900 bg-slate-50/50">Rs. {{ number_format($totalSalesReceived, 2) }}</td>
                            </tr>
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-3 px-4 font-bold flex items-center gap-2">
                                    <span class="text-base">📥</span>
                                    <span>Direct Customer Account Receivings</span>
                                </td>
                                <td class="py-3 px-4 text-center font-bold text-slate-500">{{ $directReceivings->count() }} entries</td>
                                <td class="py-3 px-4 text-right font-bold text-slate-900">Rs. {{ number_format($cashDirectReceivingTotal, 2) }}</td>
                                <td class="py-3 px-4 text-right font-bold text-indigo-700">Rs. {{ number_format($bankDirectReceivingTotal, 2) }}</td>
                                <td class="py-3 px-4 text-right font-black text-slate-900 bg-slate-50/50">Rs. {{ number_format($totalDirectReceivings, 2) }}</td>
                            </tr>
                            <tr class="bg-emerald-100/60 text-emerald-950 font-black border-t border-emerald-200">
                                <td colspan="2" class="py-3 px-4 uppercase tracking-wider">Subtotal Daily Receivings</td>
                                <td class="py-3 px-4 text-right">Rs. {{ number_format($totalCashReceived, 2) }}</td>
                                <td class="py-3 px-4 text-right">Rs. {{ number_format($totalBankReceived, 2) }}</td>
                                <td class="py-3 px-4 text-right text-emerald-900 bg-emerald-200/70">Rs. {{ number_format($totalReceived, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SECTION 3: DISBURSEMENTS & PAYMENTS (OUTFLOWS) -->
            <div class="rounded-3xl p-5 border border-rose-100 bg-gradient-to-r from-rose-50/40 via-white to-rose-50/40 space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-xl bg-rose-600 text-white flex items-center justify-center text-xs font-black shadow-xs">3</span>
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-rose-950">Daily Payments & Disbursements (Cash Out)</h3>
                            <p class="text-[11px] text-slate-500 font-medium">Expenses, purchases, salaries, and advances paid today</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-xs text-rose-700 font-bold uppercase">Total Outflow</span>
                        <div class="text-lg font-black text-rose-800 heading-font">Rs. {{ number_format($totalPaid, 2) }}</div>
                    </div>
                </div>

                <div class="overflow-x-auto border border-rose-100/80 rounded-2xl bg-white shadow-xs">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-rose-50/70 text-rose-950 text-[10px] uppercase font-black tracking-wider border-b border-rose-100">
                            <tr>
                                <th class="py-3 px-4">Disbursement Type</th>
                                <th class="py-3 px-4 text-center">Volume / Records</th>
                                <th class="py-3 px-4 text-right">Cash Paid</th>
                                <th class="py-3 px-4 text-right">Bank / Digital</th>
                                <th class="py-3 px-4 text-right font-black">Combined Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700 font-medium">
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-3 px-4 font-bold flex items-center gap-2">
                                    <span class="text-base">🧾</span>
                                    <span>Operational & Utility Expenses Paid</span>
                                </td>
                                <td class="py-3 px-4 text-center font-bold text-slate-500">{{ $expenses->count() }} bills</td>
                                <td class="py-3 px-4 text-right font-bold text-slate-900">Rs. {{ number_format($totalExpensesCash, 2) }}</td>
                                <td class="py-3 px-4 text-right font-bold text-rose-700">Rs. {{ number_format($totalExpensesBank, 2) }}</td>
                                <td class="py-3 px-4 text-right font-black text-slate-900 bg-slate-50/50">Rs. {{ number_format($totalExpenses, 2) }}</td>
                            </tr>
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-3 px-4 font-bold flex items-center gap-2">
                                    <span class="text-base">🚚</span>
                                    <span>Supplier & Product Purchase Payments</span>
                                </td>
                                <td class="py-3 px-4 text-center font-bold text-slate-500">{{ $purchases->count() }} orders</td>
                                <td class="py-3 px-4 text-right font-bold text-slate-900">Rs. {{ number_format($totalPurchasesCash, 2) }}</td>
                                <td class="py-3 px-4 text-right font-bold text-rose-700">Rs. {{ number_format($totalPurchasesBank, 2) }}</td>
                                <td class="py-3 px-4 text-right font-black text-slate-900 bg-slate-50/50">Rs. {{ number_format($totalPurchases, 2) }}</td>
                            </tr>
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-3 px-4 font-bold flex items-center gap-2">
                                    <span class="text-base">👥</span>
                                    <span>Staff Salaries / Monthly Payroll Paid</span>
                                </td>
                                <td class="py-3 px-4 text-center font-bold text-slate-500">{{ $payrolls->count() }} staff</td>
                                <td class="py-3 px-4 text-right font-bold text-slate-900">Rs. {{ number_format($totalPayrollCash, 2) }}</td>
                                <td class="py-3 px-4 text-right font-bold text-rose-700">Rs. {{ number_format($totalPayrollBank, 2) }}</td>
                                <td class="py-3 px-4 text-right font-black text-slate-900 bg-slate-50/50">Rs. {{ number_format($totalPayroll, 2) }}</td>
                            </tr>
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-3 px-4 font-bold flex items-center gap-2">
                                    <span class="text-base">💸</span>
                                    <span>Staff Advance Salaries Disbursed</span>
                                </td>
                                <td class="py-3 px-4 text-center font-bold text-slate-500">{{ $advances->count() }} advances</td>
                                <td class="py-3 px-4 text-right font-bold text-slate-900">Rs. {{ number_format($totalAdvancesCash, 2) }}</td>
                                <td class="py-3 px-4 text-right font-bold text-rose-700">Rs. {{ number_format($totalAdvancesBank, 2) }}</td>
                                <td class="py-3 px-4 text-right font-black text-slate-900 bg-slate-50/50">Rs. {{ number_format($totalAdvances, 2) }}</td>
                            </tr>
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-3 px-4 font-bold flex items-center gap-2">
                                    <span class="text-base">📤</span>
                                    <span>Direct Ledger Vendor / Staff Payments</span>
                                </td>
                                <td class="py-3 px-4 text-center font-bold text-slate-500">{{ $directPayments->count() }} vouchers</td>
                                <td class="py-3 px-4 text-right font-bold text-slate-900">Rs. {{ number_format($totalDirectPaymentsCash, 2) }}</td>
                                <td class="py-3 px-4 text-right font-bold text-rose-700">Rs. {{ number_format($totalDirectPaymentsBank, 2) }}</td>
                                <td class="py-3 px-4 text-right font-black text-slate-900 bg-slate-50/50">Rs. {{ number_format($totalDirectPayments, 2) }}</td>
                            </tr>
                            <tr class="bg-rose-100/60 text-rose-950 font-black border-t border-rose-200">
                                <td colspan="2" class="py-3 px-4 uppercase tracking-wider">Subtotal Daily Payments</td>
                                <td class="py-3 px-4 text-right">Rs. {{ number_format($totalCashPaid, 2) }}</td>
                                <td class="py-3 px-4 text-right">Rs. {{ number_format($totalBankPaid, 2) }}</td>
                                <td class="py-3 px-4 text-right text-rose-900 bg-rose-200/70">Rs. {{ number_format($totalPaid, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SECTION 4: NET DAILY MOVEMENT -->
            <div class="rounded-3xl p-5 border border-amber-200 bg-amber-50/50 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-2.5">
                    <span class="w-7 h-7 rounded-xl bg-amber-500 text-white flex items-center justify-center text-xs font-black shadow-xs">4</span>
                    <div>
                        <h3 class="text-xs font-black uppercase tracking-wider text-amber-950">Net Daily Liquidity Movement (Inflows - Outflows)</h3>
                        <p class="text-[11px] text-slate-500 font-medium">Daily net change in cash drawer and bank vaults</p>
                    </div>
                </div>

                <div class="flex items-center gap-4 text-xs font-black">
                    <div class="bg-white px-3 py-1.5 rounded-xl border border-amber-200">
                        <span class="text-slate-400 font-bold">Net Cash: </span>
                        <span class="{{ $netCashFlow >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                            {{ $netCashFlow >= 0 ? '+' : '' }}Rs. {{ number_format($netCashFlow, 2) }}
                        </span>
                    </div>
                    <div class="bg-white px-3 py-1.5 rounded-xl border border-amber-200">
                        <span class="text-slate-400 font-bold">Net Bank: </span>
                        <span class="{{ $netBankFlow >= 0 ? 'text-indigo-700' : 'text-rose-700' }}">
                            {{ $netBankFlow >= 0 ? '+' : '' }}Rs. {{ number_format($netBankFlow, 2) }}
                        </span>
                    </div>
                    <div class="bg-amber-500 text-white px-3.5 py-1.5 rounded-xl shadow-xs">
                        <span>Total: {{ $totalNetFlow >= 0 ? '+' : '' }}Rs. {{ number_format($totalNetFlow, 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- SECTION 5: FINAL CLOSING POSITION (EMBOSSED TITANIUM CARD) -->
            <div class="rounded-3xl p-6 text-white shadow-2xl relative overflow-hidden border border-slate-700"
                 style="background: radial-gradient(circle at top right, #1e293b 0%, #0f172a 60%, #020617 100%);">
                
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span class="text-xs font-black uppercase tracking-wider text-emerald-300">5. Final Expected Day Closing Position</span>
                        </div>
                        <h2 class="text-2xl font-black text-white heading-font">Settled Register & Vault Balances</h2>
                        <p class="text-xs text-slate-400">Formula: Starting Opening Float + Today's Receivings - Today's Payments</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="bg-white/10 backdrop-blur-md rounded-2xl p-4 border border-white/10">
                            <div class="text-[10px] font-black uppercase tracking-wider text-emerald-300">Expected Cash In Hand</div>
                            <div class="text-xl font-black text-white mt-1">Rs. {{ number_format($closingCashExpected, 2) }}</div>
                            <div class="text-[10px] text-emerald-200/80 mt-1 font-bold">Physical Drawer Float</div>
                        </div>

                        <div class="bg-white/10 backdrop-blur-md rounded-2xl p-4 border border-white/10">
                            <div class="text-[10px] font-black uppercase tracking-wider text-indigo-300">Expected Bank Balance</div>
                            <div class="text-xl font-black text-white mt-1">Rs. {{ number_format($closingBankExpected, 2) }}</div>
                            <div class="text-[10px] text-indigo-200/80 mt-1 font-bold">JazzCash • Easypaisa • Bank</div>
                        </div>

                        <div class="bg-emerald-600/90 backdrop-blur-md rounded-2xl p-4 border border-emerald-400/40 shadow-lg">
                            <div class="text-[10px] font-black uppercase tracking-wider text-emerald-100">Grand Total Closing</div>
                            <div class="text-xl font-black text-white mt-1">Rs. {{ number_format($closingTotalExpected, 2) }}</div>
                            <div class="text-[10px] text-emerald-100/90 mt-1 font-bold">Total Liquid Assets</div>
                        </div>
                    </div>
                </div>

            </div>

        </div>

        <!-- ===================================================================== -->
        <!-- TAB 2: LUXURY CASH DRAWER RECONCILIATION & DENOMINATIONS              -->
        <!-- ===================================================================== -->
        <div x-show="activeTab === 'reconciliation'" class="p-6 space-y-6">
            
            <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                <div>
                    <h3 class="text-base font-black text-slate-900 heading-font flex items-center gap-2">
                        <span>🧮</span> Physical Cash Drawer Reconciliation & Lock
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Count physical currency notes in the cashier drawer to automatically detect surplus or shortage.
                    </p>
                </div>
                
                <div class="flex items-center gap-2">
                    <button type="button" 
                            @click="matchExpected()" 
                            class="px-3 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 rounded-xl text-xs font-black transition-all flex items-center gap-1.5 border border-emerald-200">
                        <span>✨</span> Match Expected Exactly
                    </button>
                    <button type="button" 
                            @click="clearDenom()" 
                            class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-black transition-all flex items-center gap-1.5">
                        <span>🔄</span> Reset Calculator
                    </button>
                </div>
            </div>

            <!-- Form to save/lock day closing -->
            <form action="{{ route('manager.reports.day-closing.save') }}" method="POST" class="space-y-6">
                @csrf
                <input type="hidden" name="closing_date" value="{{ $date }}">
                <input type="hidden" name="opening_cash" value="{{ $openingCash }}">
                <input type="hidden" name="opening_bank" value="{{ $openingBank }}">
                <input type="hidden" name="total_opening" value="{{ $totalOpening }}">
                <input type="hidden" name="cash_received" value="{{ $totalCashReceived }}">
                <input type="hidden" name="bank_received" value="{{ $totalBankReceived }}">
                <input type="hidden" name="total_received" value="{{ $totalReceived }}">
                <input type="hidden" name="cash_paid" value="{{ $totalCashPaid }}">
                <input type="hidden" name="bank_paid" value="{{ $totalBankPaid }}">
                <input type="hidden" name="total_paid" value="{{ $totalPaid }}">
                <input type="hidden" name="net_cash_flow" value="{{ $netCashFlow }}">
                <input type="hidden" name="net_bank_flow" value="{{ $netBankFlow }}">
                <input type="hidden" name="total_net_flow" value="{{ $totalNetFlow }}">
                <input type="hidden" name="closing_cash_expected" value="{{ $closingCashExpected }}">
                <input type="hidden" name="closing_bank_expected" value="{{ $closingBankExpected }}">
                <input type="hidden" name="closing_total_expected" value="{{ $closingTotalExpected }}">
                <input type="hidden" name="cash_actual_counted" :value="calculatedCash">

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    
                    <!-- Left: Denomination Counter Cards -->
                    <div class="lg:col-span-7 space-y-4">
                        
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black uppercase tracking-wider text-slate-600">Currency Bill Counter</span>
                            <div class="flex items-center gap-1.5 bg-slate-100 p-1 rounded-xl text-[11px] font-bold">
                                <button type="button" @click="useDenom = true" :class="useDenom ? 'bg-white font-black text-slate-900 shadow-xs' : 'text-slate-600'" class="px-2.5 py-1 rounded-lg transition-all">By Notes</button>
                                <button type="button" @click="useDenom = false" :class="!useDenom ? 'bg-white font-black text-slate-900 shadow-xs' : 'text-slate-600'" class="px-2.5 py-1 rounded-lg transition-all">Lump Sum</button>
                            </div>
                        </div>

                        <!-- BY NOTES DENOMINATION TILES -->
                        <div x-show="useDenom" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            
                            <!-- 5000 -->
                            <div class="p-3.5 rounded-2xl border border-slate-200/80 bg-slate-50/70 hover:bg-white hover:border-emerald-300 transition-all shadow-xs space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="px-2.5 py-0.5 rounded-lg text-xs font-black bg-emerald-100 text-emerald-800">Rs. 5,000</span>
                                    <span class="text-xs font-extrabold text-slate-900" x-text="'Rs. ' + (denom.n5000 * 5000).toLocaleString()"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="addQty('n5000', -1)" class="w-8 h-8 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 font-black text-sm flex items-center justify-center transition-all">-</button>
                                    <input type="number" min="0" x-model.number="denom.n5000" name="denominations[5000]" class="flex-1 px-2 py-1.5 bg-white border border-slate-300 rounded-xl text-center font-black text-sm focus:ring-2 focus:ring-emerald-500">
                                    <button type="button" @click="addQty('n5000', 1)" class="w-8 h-8 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 font-black text-sm flex items-center justify-center transition-all">+</button>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button type="button" @click="addQty('n5000', 5)" class="px-2 py-0.5 rounded bg-white hover:bg-emerald-50 text-[10px] font-bold text-slate-600 border border-slate-200">+5</button>
                                    <button type="button" @click="addQty('n5000', 10)" class="px-2 py-0.5 rounded bg-white hover:bg-emerald-50 text-[10px] font-bold text-slate-600 border border-slate-200">+10</button>
                                </div>
                            </div>

                            <!-- 1000 -->
                            <div class="p-3.5 rounded-2xl border border-slate-200/80 bg-slate-50/70 hover:bg-white hover:border-emerald-300 transition-all shadow-xs space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="px-2.5 py-0.5 rounded-lg text-xs font-black bg-blue-100 text-blue-800">Rs. 1,000</span>
                                    <span class="text-xs font-extrabold text-slate-900" x-text="'Rs. ' + (denom.n1000 * 1000).toLocaleString()"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="addQty('n1000', -1)" class="w-8 h-8 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 font-black text-sm flex items-center justify-center transition-all">-</button>
                                    <input type="number" min="0" x-model.number="denom.n1000" name="denominations[1000]" class="flex-1 px-2 py-1.5 bg-white border border-slate-300 rounded-xl text-center font-black text-sm focus:ring-2 focus:ring-emerald-500">
                                    <button type="button" @click="addQty('n1000', 1)" class="w-8 h-8 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 font-black text-sm flex items-center justify-center transition-all">+</button>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button type="button" @click="addQty('n1000', 5)" class="px-2 py-0.5 rounded bg-white hover:bg-emerald-50 text-[10px] font-bold text-slate-600 border border-slate-200">+5</button>
                                    <button type="button" @click="addQty('n1000', 10)" class="px-2 py-0.5 rounded bg-white hover:bg-emerald-50 text-[10px] font-bold text-slate-600 border border-slate-200">+10</button>
                                </div>
                            </div>

                            <!-- 500 -->
                            <div class="p-3.5 rounded-2xl border border-slate-200/80 bg-slate-50/70 hover:bg-white hover:border-emerald-300 transition-all shadow-xs space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="px-2.5 py-0.5 rounded-lg text-xs font-black bg-indigo-100 text-indigo-800">Rs. 500</span>
                                    <span class="text-xs font-extrabold text-slate-900" x-text="'Rs. ' + (denom.n500 * 500).toLocaleString()"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="addQty('n500', -1)" class="w-8 h-8 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 font-black text-sm flex items-center justify-center transition-all">-</button>
                                    <input type="number" min="0" x-model.number="denom.n500" name="denominations[500]" class="flex-1 px-2 py-1.5 bg-white border border-slate-300 rounded-xl text-center font-black text-sm focus:ring-2 focus:ring-emerald-500">
                                    <button type="button" @click="addQty('n500', 1)" class="w-8 h-8 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 font-black text-sm flex items-center justify-center transition-all">+</button>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button type="button" @click="addQty('n500', 5)" class="px-2 py-0.5 rounded bg-white hover:bg-emerald-50 text-[10px] font-bold text-slate-600 border border-slate-200">+5</button>
                                    <button type="button" @click="addQty('n500', 10)" class="px-2 py-0.5 rounded bg-white hover:bg-emerald-50 text-[10px] font-bold text-slate-600 border border-slate-200">+10</button>
                                </div>
                            </div>

                            <!-- 100 -->
                            <div class="p-3.5 rounded-2xl border border-slate-200/80 bg-slate-50/70 hover:bg-white hover:border-emerald-300 transition-all shadow-xs space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="px-2.5 py-0.5 rounded-lg text-xs font-black bg-purple-100 text-purple-800">Rs. 100</span>
                                    <span class="text-xs font-extrabold text-slate-900" x-text="'Rs. ' + (denom.n100 * 100).toLocaleString()"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="addQty('n100', -1)" class="w-8 h-8 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 font-black text-sm flex items-center justify-center transition-all">-</button>
                                    <input type="number" min="0" x-model.number="denom.n100" name="denominations[100]" class="flex-1 px-2 py-1.5 bg-white border border-slate-300 rounded-xl text-center font-black text-sm focus:ring-2 focus:ring-emerald-500">
                                    <button type="button" @click="addQty('n100', 1)" class="w-8 h-8 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 font-black text-sm flex items-center justify-center transition-all">+</button>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button type="button" @click="addQty('n100', 5)" class="px-2 py-0.5 rounded bg-white hover:bg-emerald-50 text-[10px] font-bold text-slate-600 border border-slate-200">+5</button>
                                    <button type="button" @click="addQty('n100', 10)" class="px-2 py-0.5 rounded bg-white hover:bg-emerald-50 text-[10px] font-bold text-slate-600 border border-slate-200">+10</button>
                                </div>
                            </div>

                            <!-- 50 -->
                            <div class="p-3.5 rounded-2xl border border-slate-200/80 bg-slate-50/70 hover:bg-white hover:border-emerald-300 transition-all shadow-xs space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="px-2.5 py-0.5 rounded-lg text-xs font-black bg-amber-100 text-amber-800">Rs. 50</span>
                                    <span class="text-xs font-extrabold text-slate-900" x-text="'Rs. ' + (denom.n50 * 50).toLocaleString()"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="addQty('n50', -1)" class="w-8 h-8 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 font-black text-sm flex items-center justify-center transition-all">-</button>
                                    <input type="number" min="0" x-model.number="denom.n50" name="denominations[50]" class="flex-1 px-2 py-1.5 bg-white border border-slate-300 rounded-xl text-center font-black text-sm focus:ring-2 focus:ring-emerald-500">
                                    <button type="button" @click="addQty('n50', 1)" class="w-8 h-8 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 font-black text-sm flex items-center justify-center transition-all">+</button>
                                </div>
                            </div>

                            <!-- 20 -->
                            <div class="p-3.5 rounded-2xl border border-slate-200/80 bg-slate-50/70 hover:bg-white hover:border-emerald-300 transition-all shadow-xs space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="px-2.5 py-0.5 rounded-lg text-xs font-black bg-orange-100 text-orange-800">Rs. 20</span>
                                    <span class="text-xs font-extrabold text-slate-900" x-text="'Rs. ' + (denom.n20 * 20).toLocaleString()"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="addQty('n20', -1)" class="w-8 h-8 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 font-black text-sm flex items-center justify-center transition-all">-</button>
                                    <input type="number" min="0" x-model.number="denom.n20" name="denominations[20]" class="flex-1 px-2 py-1.5 bg-white border border-slate-300 rounded-xl text-center font-black text-sm focus:ring-2 focus:ring-emerald-500">
                                    <button type="button" @click="addQty('n20', 1)" class="w-8 h-8 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 font-black text-sm flex items-center justify-center transition-all">+</button>
                                </div>
                            </div>

                            <!-- 10 -->
                            <div class="p-3.5 rounded-2xl border border-slate-200/80 bg-slate-50/70 hover:bg-white hover:border-emerald-300 transition-all shadow-xs space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="px-2.5 py-0.5 rounded-lg text-xs font-black bg-teal-100 text-teal-800">Rs. 10</span>
                                    <span class="text-xs font-extrabold text-slate-900" x-text="'Rs. ' + (denom.n10 * 10).toLocaleString()"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="addQty('n10', -1)" class="w-8 h-8 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 font-black text-sm flex items-center justify-center transition-all">-</button>
                                    <input type="number" min="0" x-model.number="denom.n10" name="denominations[10]" class="flex-1 px-2 py-1.5 bg-white border border-slate-300 rounded-xl text-center font-black text-sm focus:ring-2 focus:ring-emerald-500">
                                    <button type="button" @click="addQty('n10', 1)" class="w-8 h-8 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 font-black text-sm flex items-center justify-center transition-all">+</button>
                                </div>
                            </div>

                            <!-- Coins / Change -->
                            <div class="p-3.5 rounded-2xl border border-slate-200/80 bg-slate-50/70 hover:bg-white hover:border-emerald-300 transition-all shadow-xs space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="px-2.5 py-0.5 rounded-lg text-xs font-black bg-slate-200 text-slate-800">Coins / Change</span>
                                    <span class="text-xs font-extrabold text-slate-900" x-text="'Rs. ' + (parseFloat(denom.coins) || 0).toLocaleString()"></span>
                                </div>
                                <input type="number" step="any" min="0" x-model.number="denom.coins" name="denominations[coins]" class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-xl text-center font-black text-sm focus:ring-2 focus:ring-emerald-500" placeholder="0.00">
                            </div>

                        </div>

                        <!-- LUMP SUM MODE -->
                        <div x-show="!useDenom" class="p-6 rounded-3xl border border-slate-200 bg-slate-50/70 space-y-3">
                            <label class="block text-xs font-black text-slate-800 uppercase tracking-wider">Total Counted Cash in Hand (PKR):</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 font-bold">Rs.</span>
                                <input type="number" step="0.01" min="0" x-model.number="manualCount" class="w-full pl-12 pr-4 py-3 bg-white border border-slate-300 rounded-2xl font-black text-xl text-slate-900 focus:ring-2 focus:ring-emerald-500">
                            </div>
                            <p class="text-[11px] text-slate-500">Type the lump-sum currency amount counted inside the salon cash drawer.</p>
                        </div>

                    </div>

                    <!-- Right: Reconciliation Dial & Locking Console -->
                    <div class="lg:col-span-5 space-y-4">
                        
                        <div class="p-6 rounded-3xl border border-slate-200/80 bg-gradient-to-br from-slate-50 via-white to-slate-50 shadow-lg space-y-5">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                <span class="text-xs font-black uppercase tracking-wider text-slate-700">Reconciliation Analysis</span>
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-slate-200 text-slate-800">Live Dial</span>
                            </div>

                            <div class="space-y-3 text-xs">
                                <div class="flex items-center justify-between font-bold text-slate-600 pb-2 border-b border-slate-100">
                                    <span>Expected Drawer Cash:</span>
                                    <span class="font-black text-slate-900">Rs. {{ number_format($closingCashExpected, 2) }}</span>
                                </div>
                                <div class="flex items-center justify-between font-bold text-slate-600 pb-2 border-b border-slate-100">
                                    <span>Actual Counted Cash:</span>
                                    <span class="font-black text-base text-emerald-700" x-text="'Rs. ' + calculatedCash.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                </div>
                                <div class="flex items-center justify-between font-bold pb-2 border-b border-slate-100">
                                    <span>Discrepancy (Diff):</span>
                                    <span class="font-black text-base" 
                                          :class="difference === 0 ? 'text-emerald-700' : (difference > 0 ? 'text-blue-700' : 'text-rose-700')"
                                          x-text="(difference > 0 ? '+' : '') + 'Rs. ' + difference.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})">
                                    </span>
                                </div>
                            </div>

                            <!-- Big Status Card Indicator -->
                            <div class="p-4 rounded-2xl flex items-center gap-3 transition-all"
                                 :class="difference === 0 ? 'bg-emerald-500 text-white shadow-md shadow-emerald-500/20' : (difference > 0 ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'bg-rose-600 text-white shadow-md shadow-rose-600/20')">
                                <span class="text-2xl shrink-0" x-text="difference === 0 ? '🎉' : (difference > 0 ? '📈' : '⚠️')"></span>
                                <div class="text-xs">
                                    <div class="font-black uppercase tracking-wider" x-text="difference === 0 ? 'Perfect Match - Balanced' : (difference > 0 ? 'Cash Surplus in Drawer' : 'Cash Shortage Detected')"></div>
                                    <div class="text-[11px] opacity-90 mt-0.5" x-text="difference === 0 ? 'Counted physical notes exactly match system register.' : (difference > 0 ? 'Drawer has extra money above recorded sales.' : 'Drawer is missing physical cash versus recorded sales.')"></div>
                                </div>
                            </div>

                            <!-- Closing Notes -->
                            <div class="space-y-1.5 pt-1">
                                <label class="block text-xs font-black text-slate-700">Closing Handover Notes:</label>
                                <textarea name="notes" rows="3" class="w-full text-xs font-medium border border-slate-300 rounded-2xl p-3 focus:ring-2 focus:ring-emerald-500" placeholder="e.g., Shift A handed cash over to shift manager. Rs. 500 left for opening change float...">{{ $dayClosing->notes ?? '' }}</textarea>
                            </div>

                            <!-- Status Setting -->
                            <div class="space-y-1.5 pt-1">
                                <label class="block text-xs font-black text-slate-700">Lock Status:</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 bg-white cursor-pointer hover:border-emerald-500 transition-all">
                                        <input type="radio" name="status" value="closed" {{ ($dayClosing->status ?? 'closed') === 'closed' ? 'checked' : '' }} class="text-emerald-600 focus:ring-emerald-500">
                                        <span class="text-xs font-black text-slate-800">🔒 Lock & Close</span>
                                    </label>
                                    <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 bg-white cursor-pointer hover:border-blue-500 transition-all">
                                        <input type="radio" name="status" value="verified" {{ ($dayClosing->status ?? '') === 'verified' ? 'checked' : '' }} class="text-blue-600 focus:ring-blue-500">
                                        <span class="text-xs font-black text-slate-800">⭐ Auditor Verified</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit" class="w-full py-3.5 bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 hover:from-emerald-500 hover:to-teal-600 text-white rounded-2xl text-xs font-black shadow-xl shadow-emerald-600/30 transition-all flex items-center justify-center gap-2 cursor-pointer">
                                <span>💾</span>
                                <span>Save & Lock Day Closing</span>
                            </button>

                        </div>

                    </div>

                </div>
            </form>

        </div>

        <!-- ===================================================================== -->
        <!-- TAB 3: ITEMIZED RECEIVINGS (INFLOWS)                                  -->
        <!-- ===================================================================== -->
        <div x-show="activeTab === 'receivings'" class="p-6 space-y-6">
            
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-black text-slate-900 heading-font">All Receivings on {{ \Carbon\Carbon::parse($date)->format('M d, Y') }}</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Individual line items for service appointments, retail product sales, and customer deposits</p>
                </div>
                <div class="text-xs font-black text-emerald-800 bg-emerald-50 px-3.5 py-1.5 rounded-xl border border-emerald-200 shadow-xs">
                    Total Inflow: Rs. {{ number_format($totalReceived, 2) }}
                </div>
            </div>

            <div class="overflow-x-auto border border-slate-200 rounded-2xl">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-100 text-slate-700 uppercase font-bold text-[10px]">
                        <tr>
                            <th class="py-3 px-4">Type</th>
                            <th class="py-3 px-4">Reference #</th>
                            <th class="py-3 px-4">Customer Name</th>
                            <th class="py-3 px-4">Payment Method</th>
                            <th class="py-3 px-4 text-right">Net Bill</th>
                            <th class="py-3 px-4 text-right">Amount Received</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @foreach($allAppointments as $apt)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3 px-4 font-bold text-purple-700 flex items-center gap-1.5">
                                <span>✂️</span> Service Booking
                            </td>
                            <td class="py-3 px-4 font-mono font-bold text-slate-900">{{ $apt->booking_no }}</td>
                            <td class="py-3 px-4 font-bold">{{ $apt->customer->name ?? 'Walk-in Client' }}</td>
                            <td class="py-3 px-4">
                                @php $isBank = in_array(strtolower($apt->payment_mode ?? ''), ['bank', 'card', 'bank transfer', 'online']); @endphp
                                <span class="px-2.5 py-1 rounded-md text-[10px] font-black {{ $isBank ? 'bg-indigo-100 text-indigo-800 border border-indigo-200' : 'bg-emerald-100 text-emerald-800 border border-emerald-200' }}">
                                    {{ $isBank ? '🏦 ' . ($apt->payment_mode ?: 'Bank') : '💵 Cash' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right text-slate-500">Rs. {{ number_format($apt->net_amount, 2) }}</td>
                            <td class="py-3 px-4 text-right font-black text-emerald-700">Rs. {{ number_format($apt->paid_amount, 2) }}</td>
                        </tr>
                        @endforeach

                        @foreach($allSales as $s)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3 px-4 font-bold text-emerald-700 flex items-center gap-1.5">
                                <span>🛒</span> Product POS
                            </td>
                            <td class="py-3 px-4 font-mono font-bold text-slate-900">{{ $s->invoice_no }}</td>
                            <td class="py-3 px-4 font-bold">{{ $s->customer->name ?? 'Customer' }}</td>
                            <td class="py-3 px-4">
                                @php $isBank = in_array(strtolower($s->payment_mode ?? ''), ['bank', 'card', 'bank transfer', 'online']); @endphp
                                <span class="px-2.5 py-1 rounded-md text-[10px] font-black {{ $isBank ? 'bg-indigo-100 text-indigo-800 border border-indigo-200' : 'bg-emerald-100 text-emerald-800 border border-emerald-200' }}">
                                    {{ $isBank ? '🏦 ' . ($s->payment_mode ?: 'Bank') : '💵 Cash' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right text-slate-500">Rs. {{ number_format($s->total_amount - $s->discount, 2) }}</td>
                            <td class="py-3 px-4 text-right font-black text-emerald-700">Rs. {{ number_format($s->received_amount, 2) }}</td>
                        </tr>
                        @endforeach

                        @foreach($directReceivings as $rec)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3 px-4 font-bold text-sky-700 flex items-center gap-1.5">
                                <span>📥</span> Account Receipt
                            </td>
                            <td class="py-3 px-4 font-mono font-bold text-slate-900">{{ $rec->reference_no }}</td>
                            <td class="py-3 px-4 font-bold">{{ $rec->account->name ?? 'Account' }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2.5 py-1 rounded-md text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    💵 Cash
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right text-slate-500">—</td>
                            <td class="py-3 px-4 text-right font-black text-emerald-700">Rs. {{ number_format($rec->credit, 2) }}</td>
                        </tr>
                        @endforeach

                        @if($allAppointments->isEmpty() && $allSales->isEmpty() && $directReceivings->isEmpty())
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400 font-bold">
                                No receivings or customer inflows recorded on this date.
                            </td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>

        </div>

        <!-- ===================================================================== -->
        <!-- TAB 4: ITEMIZED PAYMENTS (OUTFLOWS)                                   -->
        <!-- ===================================================================== -->
        <div x-show="activeTab === 'payments'" class="p-6 space-y-6">
            
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-black text-slate-900 heading-font">All Payments on {{ \Carbon\Carbon::parse($date)->format('M d, Y') }}</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Every operational expense, vendor purchase payment, payroll voucher, and salary advance</p>
                </div>
                <div class="text-xs font-black text-rose-800 bg-rose-50 px-3.5 py-1.5 rounded-xl border border-rose-200 shadow-xs">
                    Total Outflow: Rs. {{ number_format($totalPaid, 2) }}
                </div>
            </div>

            <div class="overflow-x-auto border border-slate-200 rounded-2xl">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-100 text-slate-700 uppercase font-bold text-[10px]">
                        <tr>
                            <th class="py-3 px-4">Type</th>
                            <th class="py-3 px-4">Recipient / Title</th>
                            <th class="py-3 px-4">Description / Notes</th>
                            <th class="py-3 px-4">Payment Method</th>
                            <th class="py-3 px-4 text-right">Amount Paid</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @foreach($expenses as $exp)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3 px-4 font-bold text-amber-700 flex items-center gap-1.5">
                                <span>🧾</span> Expense
                            </td>
                            <td class="py-3 px-4 font-bold text-slate-900">{{ $exp->exp_title }}</td>
                            <td class="py-3 px-4 text-slate-500">{{ $exp->category->title ?? 'General' }} • {{ $exp->description ?: 'No note' }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2.5 py-1 rounded-md text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    💵 Cash
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-black text-rose-700">Rs. {{ number_format($exp->amount, 2) }}</td>
                        </tr>
                        @endforeach

                        @foreach($purchases as $pur)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3 px-4 font-bold text-blue-700 flex items-center gap-1.5">
                                <span>🚚</span> Purchase
                            </td>
                            <td class="py-3 px-4 font-bold text-slate-900">{{ $pur->supplier->name ?? 'Supplier' }}</td>
                            <td class="py-3 px-4 text-slate-500">Bill #{{ $pur->invoice_no }} (Total: Rs. {{ number_format($pur->total_amount, 2) }})</td>
                            <td class="py-3 px-4">
                                <span class="px-2.5 py-1 rounded-md text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    💵 Cash
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-black text-rose-700">Rs. {{ number_format($pur->paid_amount, 2) }}</td>
                        </tr>
                        @endforeach

                        @foreach($payrolls as $pay)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3 px-4 font-bold text-indigo-700 flex items-center gap-1.5">
                                <span>👥</span> Salary
                            </td>
                            <td class="py-3 px-4 font-bold text-slate-900">{{ $pay->employee->name ?? 'Employee' }}</td>
                            <td class="py-3 px-4 text-slate-500">Monthly Payroll for {{ $pay->month_year }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2.5 py-1 rounded-md text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    💵 Cash
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-black text-rose-700">Rs. {{ number_format($pay->net_salary, 2) }}</td>
                        </tr>
                        @endforeach

                        @foreach($advances as $adv)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3 px-4 font-bold text-orange-700 flex items-center gap-1.5">
                                <span>💸</span> Advance
                            </td>
                            <td class="py-3 px-4 font-bold text-slate-900">{{ $adv->employee->name ?? 'Employee' }}</td>
                            <td class="py-3 px-4 text-slate-500">Month: {{ $adv->month_year }} • {{ $adv->reason ?: 'Salary Advance' }}</td>
                            <td class="py-3 px-4">
                                @php $isBank = strtolower($adv->payment_mode ?? '') === 'bank transfer'; @endphp
                                <span class="px-2.5 py-1 rounded-md text-[10px] font-black {{ $isBank ? 'bg-indigo-100 text-indigo-800 border border-indigo-200' : 'bg-emerald-100 text-emerald-800 border border-emerald-200' }}">
                                    {{ $isBank ? '🏦 ' . ($adv->bankAccount->bank_name ?? 'Bank') : '💵 Cash' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-black text-rose-700">Rs. {{ number_format($adv->amount, 2) }}</td>
                        </tr>
                        @endforeach

                        @foreach($directPayments as $dp)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3 px-4 font-bold text-rose-700 flex items-center gap-1.5">
                                <span>📤</span> Ledger Payment
                            </td>
                            <td class="py-3 px-4 font-bold text-slate-900">{{ $dp->account->name ?? 'Account' }}</td>
                            <td class="py-3 px-4 text-slate-500">Ref #{{ $dp->reference_no }} • {{ $dp->description }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2.5 py-1 rounded-md text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    💵 Cash
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-black text-rose-700">Rs. {{ number_format($dp->credit, 2) }}</td>
                        </tr>
                        @endforeach

                        @if($expenses->isEmpty() && $purchases->isEmpty() && $payrolls->isEmpty() && $advances->isEmpty() && $directPayments->isEmpty())
                        <tr>
                            <td colspan="5" class="py-12 text-center text-slate-400 font-bold">
                                No payments or disbursements recorded on this date.
                            </td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>

        </div>

        <!-- ===================================================================== -->
        <!-- TAB 5: BANK & DIGITAL SUMMARY                                         -->
        <!-- ===================================================================== -->
        <div x-show="activeTab === 'bank'" class="p-6 space-y-6">
            
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-black text-slate-900 heading-font">Active Digital & Bank Accounts</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Overview of registered digital payment channels (JazzCash, Easypaisa, Meezan, etc.)</p>
                </div>
                <div class="text-xs font-black text-indigo-800 bg-indigo-50 px-3.5 py-1.5 rounded-xl border border-indigo-200">
                    Net Digital Flow: {{ $netBankFlow >= 0 ? '+' : '' }}Rs. {{ number_format($netBankFlow, 2) }}
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($bankAccounts as $ba)
                <div class="p-5 rounded-3xl border border-slate-200/80 bg-slate-50/70 hover:bg-white hover:border-indigo-300 transition-all shadow-xs space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black text-indigo-900 uppercase tracking-wider">{{ $ba->bank_name }}</span>
                        <span class="px-2 py-0.5 rounded-md text-[9px] font-black bg-emerald-100 text-emerald-800">Active</span>
                    </div>
                    <div>
                        <div class="text-sm font-black text-slate-900">{{ $ba->account_title }}</div>
                        <div class="text-xs text-slate-500 font-mono tracking-wider mt-0.5">{{ $ba->account_no }}</div>
                    </div>
                    @if($ba->iban)
                        <div class="text-[10px] text-slate-400 font-mono">IBAN: {{ $ba->iban }}</div>
                    @endif
                </div>
                @endforeach
            </div>

            <div class="p-6 rounded-3xl bg-gradient-to-r from-indigo-50 via-slate-50 to-indigo-50 border border-indigo-200/80 space-y-4">
                <div class="font-black text-xs text-indigo-950 uppercase tracking-wider">Digital Bank Settlement Summary</div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="p-4 bg-white rounded-2xl border border-indigo-100 shadow-xs">
                        <div class="text-[10px] text-slate-500 uppercase font-black tracking-wider">Digital Opening</div>
                        <div class="text-lg font-black text-indigo-950 mt-1">Rs. {{ number_format($openingBank, 2) }}</div>
                    </div>
                    <div class="p-4 bg-white rounded-2xl border border-indigo-100 shadow-xs">
                        <div class="text-[10px] text-slate-500 uppercase font-black tracking-wider">Digital Collections (In)</div>
                        <div class="text-lg font-black text-emerald-700 mt-1">Rs. {{ number_format($totalBankReceived, 2) }}</div>
                    </div>
                    <div class="p-4 bg-white rounded-2xl border border-indigo-100 shadow-xs">
                        <div class="text-[10px] text-slate-500 uppercase font-black tracking-wider">Expected Bank Closing</div>
                        <div class="text-lg font-black text-indigo-900 mt-1">Rs. {{ number_format($closingBankExpected, 2) }}</div>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <!-- ========================================================================= -->
    <!-- 6. CRYSTAL REPORT OFFICIAL PRINT BODY (Visible strictly on print)         -->
    <!-- ========================================================================= -->
    <div class="hidden print:block space-y-4 text-black bg-white">

        <!-- KPI Statement Summary Table -->
        <div class="space-y-2">
            <h2 class="text-xs font-black uppercase tracking-wider border-b border-black pb-1">I. Cash & Bank Movement Summary</h2>
            <table class="w-full text-xs border-collapse border border-black">
                <thead>
                    <tr class="bg-slate-200">
                        <th class="border border-black p-2 text-left">Category</th>
                        <th class="border border-black p-2 text-right">Cash (PKR)</th>
                        <th class="border border-black p-2 text-right">Bank / Digital (PKR)</th>
                        <th class="border border-black p-2 text-right font-black">Consolidated Total (PKR)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="border border-black p-2 font-bold">1. Opening Balance</td>
                        <td class="border border-black p-2 text-right">{{ number_format($openingCash, 2) }}</td>
                        <td class="border border-black p-2 text-right">{{ number_format($openingBank, 2) }}</td>
                        <td class="border border-black p-2 text-right font-bold">{{ number_format($totalOpening, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="border border-black p-2 font-bold">2. Total Daily Receivings (Inflows)</td>
                        <td class="border border-black p-2 text-right">{{ number_format($totalCashReceived, 2) }}</td>
                        <td class="border border-black p-2 text-right">{{ number_format($totalBankReceived, 2) }}</td>
                        <td class="border border-black p-2 text-right font-bold">{{ number_format($totalReceived, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="border border-black p-2 font-bold">3. Total Daily Payments (Outflows)</td>
                        <td class="border border-black p-2 text-right">{{ number_format($totalCashPaid, 2) }}</td>
                        <td class="border border-black p-2 text-right">{{ number_format($totalBankPaid, 2) }}</td>
                        <td class="border border-black p-2 text-right font-bold">{{ number_format($totalPaid, 2) }}</td>
                    </tr>
                    <tr class="bg-slate-100 font-bold">
                        <td class="border border-black p-2">4. Daily Net Movement</td>
                        <td class="border border-black p-2 text-right">{{ ($netCashFlow >= 0 ? '+' : '') . number_format($netCashFlow, 2) }}</td>
                        <td class="border border-black p-2 text-right">{{ ($netBankFlow >= 0 ? '+' : '') . number_format($netBankFlow, 2) }}</td>
                        <td class="border border-black p-2 text-right font-black">{{ ($totalNetFlow >= 0 ? '+' : '') . number_format($totalNetFlow, 2) }}</td>
                    </tr>
                    <tr class="bg-slate-200 font-black text-sm">
                        <td class="border border-black p-2">5. FINAL CLOSING BALANCE (Expected)</td>
                        <td class="border border-black p-2 text-right">{{ number_format($closingCashExpected, 2) }}</td>
                        <td class="border border-black p-2 text-right">{{ number_format($closingBankExpected, 2) }}</td>
                        <td class="border border-black p-2 text-right font-black">{{ number_format($closingTotalExpected, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Physical Reconciliation Block -->
        <div class="space-y-2 pt-2">
            <h2 class="text-xs font-black uppercase tracking-wider border-b border-black pb-1">II. Physical Drawer Count & Discrepancy Verification</h2>
            <div class="grid grid-cols-3 gap-4 border border-black p-3 text-xs">
                <div>
                    <span class="font-bold">Expected Drawer Cash:</span>
                    <div class="text-sm font-black">Rs. {{ number_format($closingCashExpected, 2) }}</div>
                </div>
                <div>
                    <span class="font-bold">Physical Cash Counted:</span>
                    <div class="text-sm font-black">Rs. {{ number_format($dayClosing->cash_actual_counted ?? $closingCashExpected, 2) }}</div>
                </div>
                <div>
                    <span class="font-bold">Difference (Over/Short):</span>
                    <div class="text-sm font-black">
                        {{ ($dayClosing->cash_difference ?? 0) == 0 ? 'Rs. 0.00 (Balanced)' : (($dayClosing->cash_difference ?? 0) > 0 ? '+Rs. ' . number_format($dayClosing->cash_difference, 2) : '-Rs. ' . number_format(abs($dayClosing->cash_difference), 2)) }}
                    </div>
                </div>
            </div>
            @if($dayClosing && $dayClosing->notes)
            <div class="text-xs italic border border-black p-2 mt-1">
                <strong>Closing Notes:</strong> {{ $dayClosing->notes }}
            </div>
            @endif
        </div>

        <!-- Signature Authorization Block -->
        <div class="grid grid-cols-3 gap-8 pt-12 text-center text-xs border-t border-slate-300 mt-4">
            <div>
                <div class="border-t border-black pt-1 font-bold mt-8">Shift Cashier Signature</div>
                <div class="text-[10px] text-slate-500 mt-0.5">Cash Handover Staff</div>
            </div>
            <div>
                <div class="border-t border-black pt-1 font-bold mt-8">Saloon Manager Signature</div>
                <div class="text-[10px] text-slate-500 mt-0.5">Verification & Approval</div>
            </div>
            <div>
                <div class="border-t border-black pt-1 font-bold mt-8">Owner / Auditor Signature</div>
                <div class="text-[10px] text-slate-500 mt-0.5">Executive Audit</div>
            </div>
        </div>

    </div>

</div>
@endsection
