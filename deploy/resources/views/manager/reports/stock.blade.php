@extends('layouts.material')

@section('title', 'Stock Report')

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
                <div class="text-[7.5pt] text-slate-500">Inventory Valuation & Audit Report</div>
                <div class="text-[7.5pt] font-semibold text-slate-600 mt-0.5">Doc ID: RPT-STK-{{ now()->format('YmdHi') }}</div>
            </div>
        </div>

        <!-- Double Divider Rule -->
        <div class="crystal-double-rule"></div>

        <!-- Title Banner -->
        <div class="crystal-title-banner">
            <h2>STOCK INVENTORY AUDIT & VALUATION REPORT</h2>
            <p>Warehouse & Store Real-Time Stock Balance Statement</p>
        </div>

        <!-- Crystal Reports Parameters Matrix -->
        <table class="crystal-param-grid">
            <tr>
                <td class="label">Main Category:</td>
                <td class="val">{{ $mainCategoryId ? ($mainCategories->firstWhere('id', $mainCategoryId)->title ?? 'Selected Category') : 'All Main Categories' }}</td>
                <td class="label">Sub Category:</td>
                <td class="val">{{ $categoryId ? ($categories->firstWhere('id', $categoryId)->title ?? 'Selected Sub-Category') : 'All Sub-Categories' }}</td>
            </tr>
            <tr>
                <td class="label">Stock Filter:</td>
                <td class="val">{{ $status ? ucfirst($status).' Stock' : 'All Inventory Levels' }}</td>
                <td class="label">Print Date/Time:</td>
                <td class="val">{{ now()->format('d-M-Y h:i:s A') }}</td>
            </tr>
        </table>
    </div>

    <!-- Header & Reports Navigation Tabs (WEB ONLY) -->
    <div class="bg-white border border-slate-200 shadow-sm p-4 sm:p-6 space-y-4 sm:space-y-6 print:hidden">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-indigo-50 text-indigo-600 shrink-0">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
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
            <a href="{{ route('manager.reports.aesthetic-profit') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.aesthetic-profit') ? 'border-rose-600 text-rose-700 bg-rose-50/50 font-extrabold' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>Aesthetic Profit</span>
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
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4 print:hidden">
        <div class="bg-white p-3.5 sm:p-4 border border-slate-200 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Products</span>
            <span class="text-lg sm:text-xl font-black text-indigo-600 mt-1 block">{{ number_format($totalProductsCount) }}</span>
        </div>
        <div class="bg-white p-3.5 sm:p-4 border border-slate-200 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Stock Units</span>
            <span class="text-base sm:text-xl font-black text-slate-900 mt-1 block">{{ number_format($totalStockUnits) }}</span>
        </div>
        <div class="bg-white p-3.5 sm:p-4 border border-slate-200 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Low Stock Alert</span>
            <span class="text-base sm:text-xl font-black text-amber-600 mt-1 block">{{ number_format($lowStockCount) }}</span>
        </div>
        <div class="bg-white p-3.5 sm:p-4 border border-slate-200 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Out of Stock</span>
            <span class="text-base sm:text-xl font-black text-rose-600 mt-1 block">{{ number_format($outOfStockCount) }}</span>
        </div>
        <div class="bg-white p-3.5 sm:p-4 border border-slate-200 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Cost Valuation</span>
            <span class="text-base sm:text-xl font-black text-slate-700 mt-1 block truncate" title="PKR {{ number_format($totalCostValuation, 2) }}">PKR {{ number_format($totalCostValuation, 2) }}</span>
        </div>
        <div class="bg-white p-3.5 sm:p-4 border border-slate-200 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Retail Valuation</span>
            <span class="text-base sm:text-xl font-black text-emerald-600 mt-1 block truncate" title="PKR {{ number_format($totalRetailValuation, 2) }}">PKR {{ number_format($totalRetailValuation, 2) }}</span>
        </div>
    </div>

    <!-- Filter Form Bar (WEB ONLY) -->
    <div class="bg-white p-4 sm:p-5 border border-slate-200 shadow-sm print:hidden">
        <form method="GET" action="{{ route('manager.reports.stock') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4 items-end">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Search Product</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Type product name or code..." 
                       class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-medium focus:ring-2 focus:ring-indigo-600">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Main Category</label>
                <select name="product_main_category_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600">
                    <option value="">All Main Categories</option>
                    @foreach($mainCategories as $mc)
                        <option value="{{ $mc->id }}" {{ (string)($mainCategoryId ?? '') === (string)$mc->id ? 'selected' : '' }}>
                            📁 {{ $mc->title }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Sub Category</label>
                <select name="product_category_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600">
                    <option value="">All Sub-Categories</option>
                    @foreach($categories as $c)
                        <option value="{{ $c->id }}" {{ (string)($categoryId ?? '') === (string)$c->id ? 'selected' : '' }}>
                            🏷️ {{ $c->title }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Stock Status Filter</label>
                <select name="status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600">
                    <option value="">All Inventory Levels</option>
                    <option value="healthy" {{ $status == 'healthy' ? 'selected' : '' }}>Healthy Stock (> 5 units)</option>
                    <option value="low" {{ $status == 'low' ? 'selected' : '' }}>Low Stock Warning (1-5 units)</option>
                    <option value="out" {{ $status == 'out' ? 'selected' : '' }}>Out of Stock (0 units)</option>
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="flex-1 py-2.5 bg-indigo-600 text-white font-bold text-xs hover:bg-indigo-700 transition-colors shadow-xs">
                    Filter Stock
                </button>
                @if($search || $mainCategoryId || $categoryId || $storeId || $status)
                <a href="{{ route('manager.reports.stock') }}" class="px-4 py-2.5 bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200 flex items-center justify-center">
                    Reset
                </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Inventory Data Table -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between print:hidden">
            <h2 class="text-xs font-extrabold text-slate-700 uppercase tracking-wider">Inventory Stock Breakdown</h2>
            <span class="text-xs font-bold text-slate-500">Showing {{ $products->firstItem() ?? 0 }} - {{ $products->lastItem() ?? 0 }} of {{ $products->total() }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs crystal-grid">
                <thead>
                    <tr class="bg-slate-100 text-slate-700 font-extrabold uppercase border-b tracking-wider">
                        <th class="py-2.5 px-3 w-12 text-center">Sr #</th>
                        <th class="py-2.5 px-3 w-16">ID</th>
                        <th class="py-2.5 px-4">Product Title</th>
                        <th class="py-2.5 px-3 text-right">Cost Price</th>
                        <th class="py-2.5 px-3 text-center">Discount</th>
                        <th class="py-2.5 px-3 text-right">Retail Price</th>
                        <th class="py-2.5 px-3 text-right">Stock Units</th>
                        <th class="py-2.5 px-3 text-right">Cost Valuation</th>
                        <th class="py-2.5 px-3 text-right">Retail Valuation</th>
                        <th class="py-2.5 px-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($products as $prod)
                    @php
                        $displayStock = $storeId ? $prod->stockInStore($storeId) : $prod->stock;
                    @endphp
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="py-2 px-3 text-center font-mono text-[11px] text-slate-500">
                            {{ method_exists($products, 'firstItem') && $products->firstItem() ? $products->firstItem() + $loop->index : $loop->iteration }}
                        </td>
                        <td class="py-2 px-3 font-mono font-bold text-slate-500">#{{ $prod->id }}</td>
                        <td class="py-2 px-4 font-bold text-slate-900">
                            <div>{{ $prod->title }}</div>
                            <div class="flex flex-wrap items-center gap-1 mt-0.5">
                                @if($prod->mainCategory)
                                    <span class="text-[9px] px-1.5 py-0.2 bg-indigo-50 text-indigo-700 font-bold border border-indigo-200">
                                        📁 {{ $prod->mainCategory->title }}
                                    </span>
                                @endif
                                @if($prod->category)
                                    <span class="text-[9px] px-1.5 py-0.2 bg-slate-100 text-slate-700 font-bold border border-slate-200">
                                        🏷️ {{ $prod->category->title }}
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="py-2 px-3 text-right font-bold text-slate-700">{{ number_format($prod->price, 2) }}</td>
                        <td class="py-2 px-3 text-center">
                            @if($prod->discount > 0)
                                <span class="font-bold text-amber-800 text-[10px]">{{ $prod->discount }}% OFF</span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="py-2 px-3 text-right font-bold text-emerald-700">{{ number_format($prod->discounted_price, 2) }}</td>
                        <td class="py-2 px-3 text-right font-black {{ $displayStock <= 0 ? 'text-rose-600' : ($displayStock <= 5 ? 'text-amber-700' : 'text-slate-900') }}">
                            {{ number_format($displayStock) }}
                        </td>
                        <td class="py-2 px-3 text-right font-bold text-slate-800">{{ number_format($displayStock * $prod->price, 2) }}</td>
                        <td class="py-2 px-3 text-right font-black text-indigo-700">{{ number_format($displayStock * $prod->discounted_price, 2) }}</td>
                        <td class="py-2 px-3 text-center">
                            @if($displayStock <= 0)
                                <span class="font-bold text-rose-700 text-[9.5pt] uppercase">OUT</span>
                            @elseif($displayStock <= 5)
                                <span class="font-bold text-amber-700 text-[9.5pt] uppercase">LOW</span>
                            @else
                                <span class="font-bold text-emerald-700 text-[9.5pt] uppercase">IN STOCK</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="py-12 text-center text-slate-400 font-semibold">
                            No products match the selected stock criteria.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="font-black">
                        <td colspan="6" class="py-2 px-3 text-right uppercase">Inventory Grand Total:</td>
                        <td class="py-2 px-3 text-right">{{ number_format($totalStockUnits) }}</td>
                        <td class="py-2 px-3 text-right">{{ number_format($totalCostValuation, 2) }}</td>
                        <td class="py-2 px-3 text-right">{{ number_format($totalRetailValuation, 2) }}</td>
                        <td class="py-2 px-3 text-center text-[10px] text-slate-500">{{ number_format($totalProductsCount) }} Items</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-slate-100 print:hidden">
            {{ $products->links() }}
        </div>
    </div>

</div>
@endsection
