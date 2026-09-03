@extends('layouts.material')

@section('title', 'Stock Report')

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
                <h2 class="text-xl font-black uppercase text-indigo-900 tracking-tight">STOCK INVENTORY REPORT</h2>
                <div class="text-xs font-semibold text-slate-700 mt-1 space-y-0.5">
                    <p><strong>Stock Status Filter:</strong> {{ $status ? ucfirst($status) : 'All Inventory' }}</p>
                    <p><strong>Search Query:</strong> {{ $search ?: 'None' }}</p>
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
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
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
    <div class="bg-white p-5 border border-slate-200 shadow-sm print:hidden">
        <form method="GET" action="{{ route('manager.reports.stock') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Search Product</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Type product name..." 
                       class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-medium focus:ring-2 focus:ring-indigo-600">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Store / Branch Filter</label>
                <select name="store_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600">
                    <option value="">All Stores (System-Wide)</option>
                    @foreach($stores as $s)
                        <option value="{{ $s->id }}" {{ (string)$storeId === (string)$s->id ? 'selected' : '' }}>
                            🏬 {{ $s->name }} {{ $s->is_default ? '(Default)' : '' }}
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
                @if($search || $storeId || $status)
                <a href="{{ route('manager.reports.stock') }}" class="px-4 py-2.5 bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200 flex items-center justify-center">
                    Reset
                </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Inventory Data Table -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
            <h2 class="text-xs font-extrabold text-slate-700 uppercase tracking-wider">Inventory Stock Breakdown</h2>
            <span class="text-xs font-bold text-slate-500">Showing {{ $products->firstItem() ?? 0 }} - {{ $products->lastItem() ?? 0 }} of {{ $products->total() }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-100 text-slate-500 font-extrabold uppercase border-b tracking-wider">
                        <th class="py-3.5 px-4">ID</th>
                        <th class="py-3.5 px-4">Product Title</th>
                        <th class="py-3.5 px-4">Original Price</th>
                        <th class="py-3.5 px-4">Discount</th>
                        <th class="py-3.5 px-4">Retail Price</th>
                        <th class="py-3.5 px-4">Stock Units</th>
                        <th class="py-3.5 px-4">Inventory Cost Value</th>
                        <th class="py-3.5 px-4">Total Retail Value</th>
                        <th class="py-3.5 px-4 text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($products as $prod)
                    @php
                        $displayStock = $storeId ? $prod->stockInStore($storeId) : $prod->stock;
                    @endphp
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="py-3.5 px-4 font-mono font-bold text-slate-400">#{{ $prod->id }}</td>
                        <td class="py-3.5 px-4 font-bold text-slate-900">
                            <div>{{ $prod->title }}</div>
                            @if(!$storeId && $stores->count() > 1)
                                <div class="flex flex-wrap gap-1 mt-1">
                                    @foreach($stores as $st)
                                        @php $sQty = $prod->stockInStore($st->id); @endphp
                                        <span class="text-[9px] px-1 py-0.2 bg-slate-100 text-slate-600 font-mono border border-slate-200">
                                            {{ $st->code }}: {{ $sQty }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="py-3.5 px-4 font-bold text-slate-700">{{ number_format($prod->price, 2) }}</td>
                        <td class="py-3.5 px-4">
                            @if($prod->discount > 0)
                                <span class="px-2 py-0.5 bg-amber-100 text-amber-800 font-bold text-[10px]">{{ $prod->discount }}% OFF</span>
                            @else
                                <span class="text-slate-400">None</span>
                            @endif
                        </td>
                        <td class="py-3.5 px-4 font-extrabold text-emerald-600">{{ number_format($prod->discounted_price, 2) }}</td>
                        <td class="py-3.5 px-4 font-extrabold {{ $displayStock <= 0 ? 'text-rose-600' : ($displayStock <= 5 ? 'text-amber-700' : 'text-slate-900') }}">
                            {{ $displayStock }} units
                        </td>
                        <td class="py-3.5 px-4 font-bold text-slate-700">{{ number_format($displayStock * $prod->price, 2) }}</td>
                        <td class="py-3.5 px-4 font-black text-indigo-700">{{ number_format($displayStock * $prod->discounted_price, 2) }}</td>
                        <td class="py-3.5 px-4 text-right">
                            @if($displayStock <= 0)
                                <span class="px-2.5 py-1 bg-rose-100 text-rose-800 font-extrabold text-[10px] uppercase">Out of Stock</span>
                            @elseif($displayStock <= 5)
                                <span class="px-2.5 py-1 bg-amber-100 text-amber-800 font-extrabold text-[10px] uppercase">Low Stock</span>
                            @else
                                <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 font-extrabold text-[10px] uppercase">In Stock</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-12 text-center text-slate-400 font-semibold">
                            No products match the selected stock criteria.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-slate-100">
            {{ $products->links() }}
        </div>
    </div>

</div>
@endsection
