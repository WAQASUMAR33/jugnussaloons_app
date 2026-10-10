@extends('layouts.material')

@section('title', ($selectedEmployee ? ($selectedEmployee->name . ' - Individual Commission Statement') : 'Staff Commission Statement Report'))

@section('content')
<div class="space-y-4">

    <!-- Top Action & Navigation Bar (WEB ONLY - HIDDEN IN PRINT) -->
    <div class="bg-white border border-slate-200 shadow-sm p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 print:hidden">
        <div class="flex items-center gap-3">
            <a href="{{ route('manager.commissions.index', request()->all()) }}" 
               class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs flex items-center gap-1.5 transition-colors border border-slate-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                <span>Back to Commission List</span>
            </a>
            <div>
                <h1 class="text-base font-black text-slate-900 leading-tight">
                    @if($selectedEmployee)
                        {{ $selectedEmployee->name }} — Individual Commission Statement
                    @else
                        Staff Consolidated Commission Audit Report
                    @endif
                </h1>
                <p class="text-[11px] text-slate-500 font-semibold">
                    Period: 
                    @if(!empty($singleDate))
                        {{ \Carbon\Carbon::parse($singleDate)->format('d-M-Y') }} (Single Day)
                    @elseif($fromDate || $toDate)
                        {{ $fromDate ? \Carbon\Carbon::parse($fromDate)->format('d-M-Y') : 'Beginning' }} to {{ $toDate ? \Carbon\Carbon::parse($toDate)->format('d-M-Y') : 'Today' }}
                    @else
                        All Recorded History
                    @endif
                    @if($selectedEmployee)
                        • {{ $selectedEmployee->category->title ?? 'Staff Member' }}
                        • Phone: {{ $selectedEmployee->phone_no1 ?: 'N/A' }}
                    @endif
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" onclick="window.print()" 
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-sm transition-colors cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                <span>Print Official Report</span>
            </button>
        </div>
    </div>

    <!-- CRYSTAL REPORTS OFFICIAL DOCUMENT (Visible in Web Preview & Print) -->
    <div class="bg-white border border-slate-200 shadow-sm p-6 sm:p-8 crystal-container print:p-0 print:border-none print:shadow-none">
        
        <!-- Top Company Brand Header -->
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
                <div class="font-bold text-slate-800 uppercase tracking-wide">Human Resources & Payroll</div>
                <div class="text-[7.5pt] text-slate-500">
                    @if($selectedEmployee)
                        Individual Employee Commission Voucher
                    @else
                        Official Staff Commission Audit Statement
                    @endif
                </div>
                <div class="text-[7.5pt] font-semibold text-slate-600 mt-0.5 font-mono">
                    Doc Ref: COMM-{{ $selectedEmployee ? ('EMP-' . $selectedEmployee->id . '-') : 'ALL-' }}{{ now()->format('Ymd-His') }}
                </div>
            </div>
        </div>

        <!-- Double Divider Rule -->
        <div class="crystal-double-rule"></div>

        <!-- Title Banner -->
        <div class="crystal-title-banner">
            @if($selectedEmployee)
                <h2>INDIVIDUAL STAFF COMMISSION & EARNINGS STATEMENT</h2>
                <p>Official Performance Payout Statement & Detailed Service Contribution Ledger</p>
            @else
                <h2>STAFF COMMISSION & EARNINGS AUDIT REPORT</h2>
                <p>Official Performance Payout Statement & Revenue Contribution Breakdown</p>
            @endif
        </div>

        @if($selectedEmployee)
        <!-- ========================================================================= -->
        <!-- MODE A: INDIVIDUAL EMPLOYEE PROFILE & PARAMETERS MATRIX                   -->
        <!-- ========================================================================= -->
        <table class="crystal-param-grid mb-4">
            <tr>
                <td class="label">Staff Member:</td>
                <td class="val font-black text-slate-900 text-[10pt]">
                    {{ $selectedEmployee->name }}
                </td>
                <td class="label">Statement Period:</td>
                <td class="val font-bold text-indigo-900">
                    @if(!empty($singleDate))
                        {{ \Carbon\Carbon::parse($singleDate)->format('d-M-Y') }} (Single Day)
                    @elseif($fromDate || $toDate)
                        {{ $fromDate ? \Carbon\Carbon::parse($fromDate)->format('d-M-Y') : 'Beginning' }} to {{ $toDate ? \Carbon\Carbon::parse($toDate)->format('d-M-Y') : 'Today' }}
                    @else
                        All Recorded History
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label">Designation / Role:</td>
                <td class="val font-semibold text-slate-800">
                    {{ $selectedEmployee->category->title ?? 'Salon Personnel' }}
                </td>
                <td class="label">Printed Date & Time:</td>
                <td class="val font-mono">{{ now()->format('d-M-Y h:i A') }}</td>
            </tr>
            <tr>
                <td class="label">Contact Mobile:</td>
                <td class="val font-mono">
                    {{ $selectedEmployee->phone_no1 ?: ($selectedEmployee->phone_no2 ?: '—') }}
                </td>
                <td class="label">Staff ID / Card #:</td>
                <td class="val font-mono font-bold">
                    {{ $selectedEmployee->card_no ?: ('EMP-' . str_pad($selectedEmployee->id, 4, '0', STR_PAD_LEFT)) }}
                </td>
            </tr>
            <tr>
                <td class="label">Audit Status:</td>
                <td class="val font-bold text-emerald-800">
                    ✓ Verified & Approved For Payout
                </td>
                <td class="label">Auditor / Manager:</td>
                <td class="val">
                    {{ Auth::user()->name ?? 'Administrator' }} ({{ Auth::user()->roles->first()->name ?? 'Manager' }})
                </td>
            </tr>
        </table>

        <!-- INDIVIDUAL EMPLOYEE FINANCIAL KPI CARDS -->
        <div class="grid grid-cols-4 gap-2.5 mb-5">
            <div class="border border-slate-300 bg-slate-50/80 p-2 text-center">
                <div class="text-[7pt] font-black uppercase tracking-wider text-slate-500">Work Volume Generated</div>
                <div class="text-sm font-black text-slate-900 font-mono mt-0.5">PKR {{ number_format($totalWorkAmount, 2) }}</div>
                <div class="text-[6.5pt] text-slate-400">Total Customer Billings</div>
            </div>
            <div class="border border-indigo-300 bg-indigo-50/50 p-2 text-center">
                <div class="text-[7pt] font-black uppercase tracking-wider text-indigo-800">Net Commission Payout</div>
                <div class="text-sm font-black text-indigo-900 font-mono mt-0.5">PKR {{ number_format($totalCommissionAmount, 2) }}</div>
                <div class="text-[6.5pt] text-indigo-600 font-bold">Payable to Employee</div>
            </div>
            <div class="border border-slate-300 bg-slate-50/80 p-2 text-center">
                <div class="text-[7pt] font-black uppercase tracking-wider text-slate-500">Effective Commission %</div>
                <div class="text-sm font-black text-emerald-800 font-mono mt-0.5">{{ number_format($avgCommissionRate, 2) }}%</div>
                <div class="text-[6.5pt] text-slate-400">Weighted Average %</div>
            </div>
            <div class="border border-slate-300 bg-slate-50/80 p-2 text-center">
                <div class="text-[7pt] font-black uppercase tracking-wider text-slate-500">Total Jobs Handled</div>
                <div class="text-sm font-black text-slate-800 font-mono mt-0.5">{{ number_format($totalRecords) }}</div>
                <div class="text-[6.5pt] text-slate-400">
                    @if($totalRecords > 0)
                        Avg: PKR {{ number_format($totalCommissionAmount / $totalRecords, 2) }}/job
                    @else
                        0 entries
                    @endif
                </div>
            </div>
        </div>

        @if(isset($dailyBreakdown) && $dailyBreakdown->count() > 1)
        <!-- SUB-SECTION: DAY-BY-DAY SUMMARY MATRIX FOR THIS EMPLOYEE -->
        <div class="mb-5">
            <div class="text-[8.5pt] font-black uppercase tracking-wider text-slate-800 mb-1.5 flex items-center justify-between border-b border-slate-300 pb-1">
                <span>1. Date-Wise Daily Earnings Summary</span>
                <span class="text-[7.5pt] text-slate-500 font-normal">({{ $dailyBreakdown->count() }} active working days)</span>
            </div>
            <table class="crystal-grid">
                <thead>
                    <tr>
                        <th class="text-center w-8">#</th>
                        <th class="text-center w-28">Date</th>
                        <th class="text-center w-20">Day</th>
                        <th class="text-center w-16">Jobs</th>
                        <th class="text-right w-36">Work Volume (PKR)</th>
                        <th class="text-center w-20">Comm %</th>
                        <th class="text-right w-36">Commission (PKR)</th>
                        <th class="text-right w-20">Share %</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dailyBreakdown as $dIndex => $day)
                    @php
                        $dDate = \Carbon\Carbon::parse($day['date']);
                        $share = $totalCommissionAmount > 0 ? round(($day['total_commission'] / $totalCommissionAmount) * 100, 1) : 0;
                    @endphp
                    <tr>
                        <td class="text-center font-mono">{{ $dIndex + 1 }}</td>
                        <td class="text-center font-mono font-bold">{{ $dDate->format('d-M-Y') }}</td>
                        <td class="text-center text-slate-600">{{ $dDate->format('l') }}</td>
                        <td class="text-center font-mono font-bold">{{ $day['count'] }}</td>
                        <td class="text-right font-mono font-semibold">{{ number_format($day['total_work'], 2) }}</td>
                        <td class="text-center font-mono">{{ number_format($day['avg_rate'], 2) }}%</td>
                        <td class="text-right font-mono font-bold text-slate-900">{{ number_format($day['total_commission'], 2) }}</td>
                        <td class="text-right font-mono text-slate-600">{{ $share }}%</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-right font-bold uppercase">Period Daily Totals:</td>
                        <td class="text-center font-mono font-bold">{{ $dailyBreakdown->sum('count') }}</td>
                        <td class="text-right font-mono font-black">PKR {{ number_format($totalWorkAmount, 2) }}</td>
                        <td class="text-center font-mono font-bold">{{ number_format($avgCommissionRate, 2) }}%</td>
                        <td class="text-right font-mono font-black text-slate-900">PKR {{ number_format($totalCommissionAmount, 2) }}</td>
                        <td class="text-right font-mono font-bold">100.0%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @endif

        <!-- SUB-SECTION: COMPLETE ITEMIZED COMMISSION TRANSACTIONS JOURNAL -->
        <div class="mb-5">
            <div class="text-[8.5pt] font-black uppercase tracking-wider text-slate-800 mb-1.5 flex items-center justify-between border-b border-slate-300 pb-1">
                <span>{{ (isset($dailyBreakdown) && $dailyBreakdown->count() > 1) ? '2. Chronological Itemized Commission Ledger' : 'Chronological Itemized Commission Ledger' }}</span>
                <span class="text-[7.5pt] text-slate-500 font-normal">({{ $commissions->count() }} line items logged)</span>
            </div>
            <table class="crystal-grid">
                <thead>
                    <tr>
                        <th class="text-center w-8">#</th>
                        <th class="text-center w-24">Date</th>
                        <th class="text-left">Service Notes / Remarks</th>
                        <th class="text-right w-28">Work Volume</th>
                        <th class="text-center w-16">Comm %</th>
                        <th class="text-right w-28">Commission (PKR)</th>
                        <th class="text-right w-28">Running Total</th>
                    </tr>
                </thead>
                <tbody>
                    @php $runningTotal = 0; @endphp
                    @forelse($commissions as $cIndex => $item)
                    @php
                        $rate = $item->amount_of_work > 0 ? round(($item->total_amount / $item->amount_of_work) * 100, 2) : 0;
                        $runningTotal += (float) $item->total_amount;
                    @endphp
                    <tr>
                        <td class="text-center font-mono text-[7.5pt]">{{ $cIndex + 1 }}</td>
                        <td class="text-center font-mono text-[7.5pt] whitespace-nowrap">
                            {{ \Carbon\Carbon::parse($item->date)->format('d-M-Y') }}
                            <span class="text-[6.5pt] text-slate-400 block">{{ \Carbon\Carbon::parse($item->date)->format('D') }}</span>
                        </td>
                        <td class="text-slate-800 text-[7.5pt]">
                            {{ $item->description ?: 'Standard Salon Service Commission' }}
                        </td>
                        <td class="text-right font-mono font-semibold">{{ number_format($item->amount_of_work, 2) }}</td>
                        <td class="text-center font-mono font-bold">{{ number_format($rate, 2) }}%</td>
                        <td class="text-right font-mono font-black text-slate-900">{{ number_format($item->total_amount, 2) }}</td>
                        <td class="text-right font-mono text-slate-600 font-semibold">{{ number_format($runningTotal, 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-8 text-slate-500 italic">
                            No commission transactions logged for {{ $selectedEmployee->name }} in the selected date range.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-right font-bold uppercase">Statement Net Totals:</td>
                        <td class="text-right font-mono font-black">PKR {{ number_format($totalWorkAmount, 2) }}</td>
                        <td class="text-center font-mono font-bold">{{ number_format($avgCommissionRate, 2) }}%</td>
                        <td class="text-right font-mono font-black text-slate-900">PKR {{ number_format($totalCommissionAmount, 2) }}</td>
                        <td class="text-right font-mono font-black text-slate-900">PKR {{ number_format($totalCommissionAmount, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- INDIVIDUAL EMPLOYEE SIGNATURES & ACKNOWLEDGMENT BAND -->
        <div class="crystal-sign-band">
            <div class="crystal-sign-box">
                <div>PREPARED BY</div>
                <div class="text-[6.5pt] font-normal lowercase text-slate-500 mt-0.5">data operator / accounts clerk</div>
            </div>
            <div class="crystal-sign-box" style="width: 38% !important;">
                <div>EMPLOYEE ACKNOWLEDGMENT</div>
                <div class="font-bold text-slate-900 mt-0.5">{{ $selectedEmployee->name }}</div>
                <div class="text-[6pt] font-normal text-slate-500 mt-0.5">"I verify and confirm receipt of the above commission statement without dispute"</div>
            </div>
            <div class="crystal-sign-box">
                <div>AUTHORIZED BY</div>
                <div class="text-[6.5pt] font-normal lowercase text-slate-500 mt-0.5">branch manager / director</div>
            </div>
        </div>

        @else
        <!-- ========================================================================= -->
        <!-- MODE B: CONSOLIDATED ALL-STAFF STATEMENT & PARAMETERS MATRIX              -->
        <!-- ========================================================================= -->
        <table class="crystal-param-grid mb-4">
            <tr>
                <td class="label">Statement Period:</td>
                <td class="val font-bold text-indigo-900">
                    @if(!empty($singleDate))
                        {{ \Carbon\Carbon::parse($singleDate)->format('d-M-Y') }} (Single Day)
                    @elseif($fromDate || $toDate)
                        {{ $fromDate ? \Carbon\Carbon::parse($fromDate)->format('d-M-Y') : 'Beginning' }} to {{ $toDate ? \Carbon\Carbon::parse($toDate)->format('d-M-Y') : 'Today' }}
                    @else
                        All Recorded History
                    @endif
                </td>
                <td class="label">Printed Date & Time:</td>
                <td class="val font-mono">{{ now()->format('d-M-Y h:i A') }}</td>
            </tr>
            <tr>
                <td class="label">Staff Covered:</td>
                <td class="val font-bold text-slate-800">
                    All Personnel ({{ $employeeSummary->count() }} Staff Members with Commission)
                </td>
                <td class="label">Generated By:</td>
                <td class="val">{{ Auth::user()->name ?? 'System Administrator' }} ({{ Auth::user()->roles->first()->name ?? 'Manager' }})</td>
            </tr>
            <tr>
                <td class="label">Total Commission Logs:</td>
                <td class="val font-mono font-bold">{{ number_format($totalRecords) }} Matched Records</td>
                <td class="label">Audit Status:</td>
                <td class="val font-bold text-emerald-800">Verified & Approved</td>
            </tr>
        </table>

        <!-- Executive Financial KPI Summary -->
        <div class="grid grid-cols-3 gap-3 mb-5">
            <div class="border border-slate-300 bg-slate-50/80 p-2.5 text-center">
                <div class="text-[7.5pt] font-black uppercase tracking-wider text-slate-500">Total Work Volume (Revenue)</div>
                <div class="text-base font-black text-slate-900 font-mono mt-0.5">PKR {{ number_format($totalWorkAmount, 2) }}</div>
                <div class="text-[7pt] text-slate-400">Gross Service/Sales Value Generated</div>
            </div>
            <div class="border border-indigo-300 bg-indigo-50/50 p-2.5 text-center">
                <div class="text-[7.5pt] font-black uppercase tracking-wider text-indigo-800">Total Commission Payable</div>
                <div class="text-base font-black text-indigo-900 font-mono mt-0.5">PKR {{ number_format($totalCommissionAmount, 2) }}</div>
                <div class="text-[7pt] text-indigo-600 font-semibold">Net Commission Earnings Disbursed</div>
            </div>
            <div class="border border-slate-300 bg-slate-50/80 p-2.5 text-center">
                <div class="text-[7.5pt] font-black uppercase tracking-wider text-slate-500">Average Payout Rate</div>
                <div class="text-base font-black text-slate-800 font-mono mt-0.5">{{ number_format($avgCommissionRate, 2) }}%</div>
                <div class="text-[7pt] text-slate-400">Weighted Average Commission %</div>
            </div>
        </div>

        @if($employeeSummary->count() > 0)
        <!-- SECTION 1: EMPLOYEE-WISE CONSOLIDATED SUMMARY -->
        <div class="mb-5">
            <div class="text-[8.5pt] font-black uppercase tracking-wider text-slate-800 mb-1.5 flex items-center justify-between border-b border-slate-300 pb-1">
                <span>1. Staff-Wise Consolidated Performance Summary</span>
                <span class="text-[7.5pt] text-slate-500 font-normal">({{ $employeeSummary->count() }} active earners)</span>
            </div>
            <table class="crystal-grid">
                <thead>
                    <tr>
                        <th class="text-center w-8">#</th>
                        <th class="text-left">Employee / Stylist Name</th>
                        <th class="text-left">Designation</th>
                        <th class="text-center w-16">Jobs</th>
                        <th class="text-right w-32">Total Work (PKR)</th>
                        <th class="text-center w-20">Avg Rate</th>
                        <th class="text-right w-32">Commission (PKR)</th>
                        <th class="text-right w-20">Share %</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($employeeSummary as $index => $empStat)
                    @php
                        $sharePct = $totalCommissionAmount > 0 ? round(($empStat['total_commission'] / $totalCommissionAmount) * 100, 1) : 0;
                    @endphp
                    <tr>
                        <td class="text-center font-mono">{{ $index + 1 }}</td>
                        <td class="font-bold text-slate-900">
                            {{ $empStat['employee']->name ?? 'Unknown' }}
                            @if($empStat['employee'] && $empStat['employee']->phone_no1)
                                <span class="text-[7pt] text-slate-500 font-normal font-mono">({{ $empStat['employee']->phone_no1 }})</span>
                            @endif
                        </td>
                        <td class="text-slate-600">{{ $empStat['employee']->category->title ?? 'Staff' }}</td>
                        <td class="text-center font-mono font-bold">{{ $empStat['count'] }}</td>
                        <td class="text-right font-mono font-semibold">{{ number_format($empStat['total_work'], 2) }}</td>
                        <td class="text-center font-mono">{{ number_format($empStat['avg_rate'], 2) }}%</td>
                        <td class="text-right font-mono font-bold text-slate-900">{{ number_format($empStat['total_commission'], 2) }}</td>
                        <td class="text-right font-mono text-slate-600">{{ $sharePct }}%</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-right font-bold uppercase">Consolidated Summary Totals:</td>
                        <td class="text-center font-mono font-bold">{{ $employeeSummary->sum('count') }}</td>
                        <td class="text-right font-mono font-black">PKR {{ number_format($totalWorkAmount, 2) }}</td>
                        <td class="text-center font-mono font-bold">{{ number_format($avgCommissionRate, 2) }}%</td>
                        <td class="text-right font-mono font-black text-slate-900">PKR {{ number_format($totalCommissionAmount, 2) }}</td>
                        <td class="text-right font-mono font-bold">100.0%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @endif

        <!-- SECTION 2: ITEMIZED COMMISSION JOURNAL / DETAILED LOGS -->
        <div class="mb-5">
            <div class="text-[8.5pt] font-black uppercase tracking-wider text-slate-800 mb-1.5 flex items-center justify-between border-b border-slate-300 pb-1">
                <span>2. Itemized Commission Transaction Journal</span>
                <span class="text-[7.5pt] text-slate-500 font-normal">({{ $commissions->count() }} transaction entries)</span>
            </div>
            <table class="crystal-grid">
                <thead>
                    <tr>
                        <th class="text-center w-8">#</th>
                        <th class="text-center w-24">Date</th>
                        <th class="text-left">Employee Name</th>
                        <th class="text-right w-28">Work Amount</th>
                        <th class="text-center w-16">Comm %</th>
                        <th class="text-right w-28">Commission Paid</th>
                        <th class="text-left">Service Remarks / Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($commissions as $index => $comm)
                    @php
                        $rate = $comm->amount_of_work > 0 ? round(($comm->total_amount / $comm->amount_of_work) * 100, 2) : 0;
                    @endphp
                    <tr>
                        <td class="text-center font-mono text-[7.5pt]">{{ $index + 1 }}</td>
                        <td class="text-center font-mono text-[7.5pt] whitespace-nowrap">{{ \Carbon\Carbon::parse($comm->date)->format('d-M-Y') }}</td>
                        <td class="font-bold text-slate-900">
                            {{ $comm->employee->name ?? 'N/A' }}
                            @if($comm->employee && $comm->employee->phone_no1)
                                <span class="text-[7pt] text-slate-500 font-mono font-normal">({{ $comm->employee->phone_no1 }})</span>
                            @endif
                        </td>
                        <td class="text-right font-mono font-semibold">{{ number_format($comm->amount_of_work, 2) }}</td>
                        <td class="text-center font-mono font-bold">{{ number_format($rate, 2) }}%</td>
                        <td class="text-right font-mono font-black text-slate-900">{{ number_format($comm->total_amount, 2) }}</td>
                        <td class="text-slate-600 text-[7.5pt]">{{ $comm->description ?: '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-6 text-slate-500 italic">
                            No commission records found for the selected filter criteria.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-right font-bold uppercase">Grand Totals:</td>
                        <td class="text-right font-mono font-black">PKR {{ number_format($totalWorkAmount, 2) }}</td>
                        <td class="text-center font-mono font-bold">{{ number_format($avgCommissionRate, 2) }}%</td>
                        <td class="text-right font-mono font-black text-slate-900">PKR {{ number_format($totalCommissionAmount, 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Official Signatures Band -->
        <div class="crystal-sign-band">
            <div class="crystal-sign-box">
                <div>PREPARED BY</div>
                <div class="text-[6.5pt] font-normal lowercase text-slate-500 mt-0.5">payroll clerk / data operator</div>
            </div>
            <div class="crystal-sign-box">
                <div>VERIFIED BY</div>
                <div class="text-[6.5pt] font-normal lowercase text-slate-500 mt-0.5">saloon branch manager</div>
            </div>
            <div class="crystal-sign-box">
                <div>AUTHORIZED BY</div>
                <div class="text-[6.5pt] font-normal lowercase text-slate-500 mt-0.5">managing director / auditor</div>
            </div>
        </div>
        @endif

        <!-- Official System Audit Footer -->
        <div class="crystal-audit-footer">
            <div>
                <strong>{{ $appSetting->brand_name ?? 'Jugnu Saloon' }} Executive Suite</strong> &bull; Official Commission Audit Document &bull; CONFIDENTIAL
            </div>
            <div class="font-mono">
                Printed: {{ now()->format('Y-m-d H:i:s') }} &bull; System Ref: #{{ uniqid() }}
            </div>
        </div>

    </div>

</div>
@endsection
