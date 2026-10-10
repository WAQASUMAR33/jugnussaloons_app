@extends('layouts.material')

@section('title', 'Services Booking Reports')

@section('content')
<div class="space-y-6">

    <!-- CRYSTAL REPORTS OFFICIAL DOCUMENT HEADER (PRINT ONLY) -->
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
                <div class="text-[7.5pt] text-slate-500">Service Operations & Audit Report</div>
                <div class="text-[7.5pt] font-semibold text-slate-600 mt-0.5">Doc ID: RPT-SRV-{{ now()->format('YmdHi') }}</div>
            </div>
        </div>

        <!-- Double Divider Rule -->
        <div class="crystal-double-rule"></div>

        <!-- Title Banner -->
        <div class="crystal-title-banner">
            <h2>SERVICES BOOKING & REVENUE AUDIT REPORT</h2>
            <p>
                @if($reportType == 'categorywise')
                    Treatment Category-Wise Revenue Breakdown
                @elseif($reportType == 'detailed')
                    Detailed Client Appointments Register
                @else
                    Date-Wise Service Bookings Breakdown
                @endif
            </p>
        </div>

        <!-- Crystal Reports Parameters Matrix -->
        <table class="crystal-param-grid">
            <tr>
                <td class="label">Date Range:</td>
                <td class="val">{{ $startDate ? \Carbon\Carbon::parse($startDate)->format('M d, Y') : 'Beginning' }} — {{ $endDate ? \Carbon\Carbon::parse($endDate)->format('M d, Y') : 'Present' }}</td>
                <td class="label">Service Category:</td>
                <td class="val">{{ $serviceCategoryId ? ($serviceCategories->firstWhere('id', $serviceCategoryId)->title ?? 'Selected') : 'All Service Categories' }}</td>
            </tr>
            <tr>
                <td class="label">Stylist Scope:</td>
                <td class="val">{{ $employeeId ? ($employees->firstWhere('id', $employeeId)->name ?? 'Selected') : 'All Stylists & Specialists' }}</td>
                <td class="label">Print Date/Time:</td>
                <td class="val">{{ now()->format('d-M-Y h:i:s A') }} | By: {{ Auth::user()?->name ?? 'System' }}</td>
            </tr>
        </table>
    </div>

    <!-- Header & Reports Navigation Tabs (WEB ONLY) -->
    <div class="bg-white border border-slate-200 shadow-sm p-4 sm:p-6 space-y-4 sm:space-y-6 print:hidden">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-indigo-50 text-indigo-600 shrink-0">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 0A3 3 0 104.5 4.5a3 3 0 004.621 4.621zm0 0L12 12m-2.879 2.879a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243z"></path></svg>
                </div>
                <div>
                    <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Business Intelligence & Reports</h1>
                    <p class="text-xs font-semibold text-slate-500 mt-0.5">Comprehensive sales, stock inventory, service bookings, account ledgers, and purchase reports.</p>
                </div>
            </div>

            <button type="button" onclick="window.print()" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-xs transition-colors cursor-pointer w-full sm:w-auto">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                <span>Print Report</span>
            </button>
        </div>

        <!-- Reports Tabs -->
        <div class="flex border-b border-slate-200 overflow-x-auto scrollbar-none gap-2 text-xs font-bold pb-0.5">
            @if(Auth::user()->hasRole('admin'))
            <a href="{{ route('manager.reports.profit') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.profit') ? 'border-emerald-600 text-emerald-700 bg-emerald-50/50 font-extrabold' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>💰 Profit & Loss</span>
            </a>
            <a href="{{ route('manager.reports.day-closing') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.day-closing') ? 'border-teal-600 text-teal-700 bg-teal-50/50 font-extrabold' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>📅 Day Closing</span>
            </a>
            @endif
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

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 print:hidden">
        <div class="bg-white p-3.5 sm:p-4 border border-slate-200 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Bookings</span>
            <span class="text-lg sm:text-xl font-black text-indigo-600 mt-1 block">{{ number_format($totalAppointmentsCount) }}</span>
        </div>
        <div class="bg-white p-3.5 sm:p-4 border border-slate-200 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Gross Revenue</span>
            <span class="text-base sm:text-xl font-black text-slate-900 mt-1 block truncate" title="PKR {{ number_format($totalGrossRevenue, 2) }}">PKR {{ number_format($totalGrossRevenue, 2) }}</span>
        </div>
        <div class="bg-white p-3.5 sm:p-4 border border-slate-200 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Discounts</span>
            <span class="text-base sm:text-xl font-black text-rose-600 mt-1 block truncate" title="PKR {{ number_format($totalDiscount, 2) }}">PKR {{ number_format($totalDiscount, 2) }}</span>
        </div>
        <div class="bg-white p-3.5 sm:p-4 border border-slate-200 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Net Revenue</span>
            <span class="text-base sm:text-xl font-black text-emerald-600 mt-1 block truncate" title="PKR {{ number_format($totalNetRevenue, 2) }}">PKR {{ number_format($totalNetRevenue, 2) }}</span>
        </div>
        <div class="bg-white p-3.5 sm:p-4 border border-slate-200 shadow-sm col-span-2 sm:col-span-1">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Staff Commissions</span>
            <span class="text-base sm:text-xl font-black text-purple-700 mt-1 block truncate" title="PKR {{ number_format($totalCommission, 2) }}">PKR {{ number_format($totalCommission, 2) }}</span>
        </div>
    </div>

    <!-- Filter Form Bar (WEB ONLY) -->
    <div class="bg-white p-4 sm:p-5 border border-slate-200 shadow-sm print:hidden space-y-4">
        <!-- Sub-Report Type Selection Tabs -->
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 pb-3">
            <span class="text-xs font-black text-slate-400 uppercase tracking-wider mr-2 w-full sm:w-auto">Report Selection:</span>
            <a href="{{ request()->fullUrlWithQuery(['report_type' => 'datewise']) }}" 
               class="px-3.5 py-1.5 font-bold text-xs whitespace-nowrap transition-all {{ $reportType == 'datewise' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                📅 Date-Wise Report
            </a>
            <a href="{{ request()->fullUrlWithQuery(['report_type' => 'categorywise']) }}" 
               class="px-3.5 py-1.5 font-bold text-xs whitespace-nowrap transition-all {{ $reportType == 'categorywise' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                🏷️ Category-Wise Report
            </a>
            <a href="{{ request()->fullUrlWithQuery(['report_type' => 'detailed']) }}" 
               class="px-3.5 py-1.5 font-bold text-xs whitespace-nowrap transition-all {{ $reportType == 'detailed' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                📋 Detailed Appointments List
            </a>
        </div>

        <form method="GET" action="{{ route('manager.reports.services') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4 items-end">
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
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Service Category</label>
                <select name="service_category_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600">
                    <option value="">All Service Categories</option>
                    @foreach($serviceCategories as $cat)
                        <option value="{{ $cat->id }}" {{ $serviceCategoryId == $cat->id ? 'selected' : '' }}>
                            {{ $cat->title }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Employee Stylist</label>
                <select name="employee_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600">
                    <option value="">All Stylists</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" {{ $employeeId == $emp->id ? 'selected' : '' }}>
                            {{ $emp->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2 col-span-1 sm:col-span-2 md:col-span-2 lg:col-span-2">
                <button type="submit" class="flex-1 py-2.5 bg-indigo-600 text-white font-bold text-xs hover:bg-indigo-700 transition-colors shadow-xs">
                    Generate Report
                </button>
                <a href="{{ route('manager.reports.services', ['report_type' => $reportType]) }}" class="px-4 py-2.5 bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200 flex items-center justify-center border border-slate-200">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- REPORT BODY CONTENT -->

    @if($reportType == 'datewise')
        <!-- 1. DATE-WISE SERVICE BOOKINGS REPORT -->
        <div class="bg-white border border-slate-200 shadow-sm overflow-hidden p-5 space-y-3">
            <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wider flex items-center gap-2 print:hidden">
                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                Date-Wise Service Bookings Breakdown
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs crystal-grid">
                    <thead>
                        <tr class="bg-slate-100 text-slate-700 font-extrabold uppercase border-b">
                            <th class="py-2.5 px-3 w-12 text-center">Sr #</th>
                            <th class="py-2.5 px-4">Date</th>
                            <th class="py-2.5 px-4 text-right">Total Bookings</th>
                            <th class="py-2.5 px-4 text-right">Gross Bill</th>
                            <th class="py-2.5 px-4 text-right">Discount</th>
                            <th class="py-2.5 px-4 text-right">Net Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-semibold">
                        @forelse($datewiseBreakdown as $db)
                        <tr>
                            <td class="py-2 px-3 text-center font-mono text-[11px] text-slate-500">{{ $loop->iteration }}</td>
                            <td class="py-2 px-4 font-bold text-slate-900">{{ \Carbon\Carbon::parse($db->appointment_date)->format('M d, Y') }}</td>
                            <td class="py-2 px-4 text-right font-bold text-indigo-600">{{ number_format($db->total_count) }}</td>
                            <td class="py-2 px-4 text-right text-slate-700">{{ number_format($db->gross_amount, 2) }}</td>
                            <td class="py-2 px-4 text-right text-rose-600 font-semibold">{{ number_format($db->discount_amount, 2) }}</td>
                            <td class="py-2 px-4 text-right text-emerald-700 font-black">{{ number_format($db->net_amount, 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400">No date-wise booking record found for selected filters.</td>
                        </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="font-black">
                            <td colspan="2" class="py-2 px-3 text-right uppercase">Grand Total:</td>
                            <td class="py-2 px-4 text-right">{{ number_format($totalAppointmentsCount) }}</td>
                            <td class="py-2 px-4 text-right">{{ number_format($totalGrossRevenue, 2) }}</td>
                            <td class="py-2 px-4 text-right">{{ number_format($totalDiscount, 2) }}</td>
                            <td class="py-2 px-4 text-right">{{ number_format($totalNetRevenue, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

    @elseif($reportType == 'categorywise')
        <!-- 2. CATEGORY-WISE SERVICE REVENUE REPORT -->
        <div class="bg-white border border-slate-200 shadow-sm overflow-hidden p-5 space-y-3">
            <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wider flex items-center gap-2 print:hidden">
                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                Category-Wise Service Revenue Breakdown
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs crystal-grid">
                    <thead>
                        <tr class="bg-slate-100 text-slate-700 font-extrabold uppercase border-b">
                            <th class="py-2.5 px-3 w-12 text-center">Sr #</th>
                            <th class="py-2.5 px-4">Service Category</th>
                            <th class="py-2.5 px-4 text-right">Treatments Count</th>
                            <th class="py-2.5 px-4 text-right">Total Net Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-semibold">
                        @forelse($categorywiseBreakdown as $cb)
                        <tr>
                            <td class="py-2 px-3 text-center font-mono text-[11px] text-slate-500">{{ $loop->iteration }}</td>
                            <td class="py-2 px-4 font-bold text-slate-900">{{ $cb->category_title }}</td>
                            <td class="py-2 px-4 text-right font-bold text-indigo-600">{{ number_format($cb->service_count) }}</td>
                            <td class="py-2 px-4 text-right text-emerald-700 font-black">{{ number_format($cb->total_revenue, 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-slate-400">No category-wise service record found for selected filters.</td>
                        </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="font-black">
                            <td colspan="2" class="py-2 px-3 text-right uppercase">Grand Total:</td>
                            <td class="py-2 px-4 text-right">{{ number_format($categorywiseBreakdown->sum('service_count')) }}</td>
                            <td class="py-2 px-4 text-right">{{ number_format($categorywiseBreakdown->sum('total_revenue'), 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

    @else
        <!-- 3. DETAILED APPOINTMENTS LIST REPORT (WITH SERVICES, CATEGORIES & STYLISTS) -->
        <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between print:hidden">
                <h2 class="text-xs font-extrabold text-slate-700 uppercase tracking-wider">Detailed Appointments List</h2>
                <span class="text-xs font-bold text-slate-500">Showing {{ $appointments->firstItem() ?? 0 }} - {{ $appointments->lastItem() ?? 0 }} of {{ $appointments->total() }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs crystal-grid">
                    <thead>
                        <tr class="bg-slate-100 text-slate-700 font-extrabold uppercase border-b tracking-wider">
                            <th class="py-2.5 px-3 w-12 text-center">Sr #</th>
                            <th class="py-2.5 px-3">Booking #</th>
                            <th class="py-2.5 px-3">Date & Time</th>
                            <th class="py-2.5 px-4">Customer Account</th>
                            <th class="py-2.5 px-3">Assigned Stylist</th>
                            <th class="py-2.5 px-4">Treatments & Categories</th>
                            <th class="py-2.5 px-3 text-right">Net Bill</th>
                            <th class="py-2.5 px-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        @forelse($appointments as $apt)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-2 px-3 text-center font-mono text-[11px] text-slate-500">
                                {{ method_exists($appointments, 'firstItem') && $appointments->firstItem() ? $appointments->firstItem() + $loop->index : $loop->iteration }}
                            </td>
                            <td class="py-2 px-3 font-mono font-bold text-indigo-700">#{{ $apt->booking_no }}</td>
                            <td class="py-2 px-3 font-bold text-slate-600">
                                {{ $apt->appointment_date ? $apt->appointment_date->format('d-M-Y') : '—' }}
                                <span class="block text-[9px] text-slate-500 font-normal">{{ $apt->start_time }}</span>
                            </td>
                            <td class="py-2 px-4 font-bold text-slate-900">{{ $apt->customer->name ?? 'Walk-in Customer' }}</td>
                            <td class="py-2 px-3 font-bold text-purple-900">
                                {{ $apt->employee->name ?? 'Unassigned' }}
                            </td>
                            <td class="py-2 px-4">
                                <div class="space-y-0.5 max-w-xs">
                                    @foreach($apt->items as $item)
                                        @if(!$serviceCategoryId || ($item->service && $item->service->service_category_id == $serviceCategoryId))
                                        <div class="text-[11px] leading-tight">
                                            <span class="font-bold text-slate-900">• {{ $item->custom_title ?: ($item->service->title ?? 'Service') }}</span>
                                            @if($item->service && $item->service->category)
                                                <span class="text-[9.5px] text-indigo-700 font-semibold ml-1">
                                                    ({{ $item->service->category->title }})
                                                </span>
                                            @endif
                                            <span class="text-[9.5px] text-slate-500 ml-1">{{ number_format($item->discounted_price, 2) }}</span>
                                        </div>
                                        @endif
                                    @endforeach
                                </div>
                            </td>
                            <td class="py-2 px-3 text-right font-black text-slate-900">{{ number_format($apt->display_net_amount ?? $apt->net_amount, 2) }}</td>
                            <td class="py-2 px-3 text-center uppercase font-bold text-[9.5pt]">
                                @if($apt->status == 'completed')
                                    <span class="text-emerald-700">Completed</span>
                                @elseif($apt->status == 'confirmed')
                                    <span class="text-blue-700">Confirmed</span>
                                @elseif($apt->status == 'pending')
                                    <span class="text-amber-700">Pending</span>
                                @else
                                    <span class="text-rose-700">Cancelled</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-slate-400 font-semibold">
                                No service bookings found for the selected date range and filters.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="font-black">
                            <td colspan="6" class="py-2 px-3 text-right uppercase">Summary Grand Total:</td>
                            <td class="py-2 px-3 text-right">{{ number_format($totalNetRevenue, 2) }}</td>
                            <td class="py-2 px-3 text-center text-[10px] text-slate-500">{{ number_format($totalAppointmentsCount) }} Bookings</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-slate-100 print:hidden">
                {{ $appointments->links() }}
            </div>
        </div>
    @endif

</div>
@endsection
