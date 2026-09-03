@extends('layouts.material')

@section('title', 'Store-Wise Stock Inventory')

@section('content')
<div class="space-y-6">

    <!-- Page Header & Action Bar -->
    <div class="bg-white p-6 border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="p-2.5 bg-indigo-50 text-indigo-600">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Store-Wise Stock Inventory Ledger</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">Filter product stocks store-wise across branches and inspect specific product allocations.</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('manager.stock-transfers.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs border border-slate-300 transition-colors">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                <span>Stock Transfers</span>
            </a>
            <a href="{{ route('manager.stores.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                <span>Manage Stores</span>
            </a>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="bg-white p-5 border border-slate-200 shadow-sm">
        <form method="GET" action="{{ route('manager.store-stocks.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5 items-end">
            
            <!-- Store / Branch Selector -->
            <div>
                <label class="block text-[11px] font-black text-slate-700 uppercase mb-1">Store / Branch Outlet</label>
                <select name="store_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 text-xs font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-500">
                    <option value="">🏬 All Stores (Combined Matrix)</option>
                    @foreach($stores as $s)
                        <option value="{{ $s->id }}" {{ (string)$storeId === (string)$s->id ? 'selected' : '' }}>
                            🏬 {{ $s->name }} ({{ $s->code }}) {{ $s->is_default ? '— Default' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Specific Product Selector -->
            <div>
                <label class="block text-[11px] font-black text-slate-700 uppercase mb-1">Specific Product</label>
                <select name="product_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 text-xs font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-500">
                    <option value="">📦 All Products Catalog</option>
                    @foreach($allProducts as $p)
                        <option value="{{ $p->id }}" {{ (string)$productId === (string)$p->id ? 'selected' : '' }}>
                            #{{ $p->id }} - {{ $p->title }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Stock Level Status Filter -->
            <div>
                <label class="block text-[11px] font-black text-slate-700 uppercase mb-1">Stock Level Status</label>
                <select name="status" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 text-xs font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Stock Levels</option>
                    <option value="healthy" {{ $status === 'healthy' ? 'selected' : '' }}>Healthy Stock (> 5 units)</option>
                    <option value="low" {{ $status === 'low' ? 'selected' : '' }}>Low Stock Warning (1-5 units)</option>
                    <option value="out" {{ $status === 'out' ? 'selected' : '' }}>Out of Stock (0 units)</option>
                </select>
            </div>

            <!-- Keyword Search -->
            <div>
                <label class="block text-[11px] font-black text-slate-700 uppercase mb-1">Search Keyword</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search product title..." 
                       class="w-full px-3 py-2 bg-slate-50 border border-slate-300 text-xs font-medium text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-500">
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 py-2 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs shadow-xs transition-colors">
                    Filter Stock
                </button>
                @if($search || $storeId || $productId || $status)
                    <a href="{{ route('manager.store-stocks.index') }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs border border-slate-300 flex items-center justify-center">
                        Reset
                    </a>
                @endif
            </div>

        </form>
    </div>

    <!-- KPI Summary Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Filtered Scope</span>
                <h3 class="text-base font-black text-slate-900 mt-1 truncate max-w-[180px]">
                    {{ $selectedStore ? $selectedStore->name : 'All Stores (Combined)' }}
                </h3>
                <p class="text-[10px] text-slate-500 font-medium">
                    {{ $selectedProduct ? "Product: {$selectedProduct->title}" : "{$totalFilteredProducts} Products Listed" }}
                </p>
            </div>
            <div class="w-10 h-10 bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            </div>
        </div>

        <div class="bg-white p-5 border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Available Units</span>
                <h3 class="text-2xl font-black text-emerald-700 font-mono mt-1">{{ number_format($totalUnits) }}</h3>
                <p class="text-[10px] text-slate-500 font-medium">Units in selected scope</p>
            </div>
            <div class="w-10 h-10 bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
            </div>
        </div>

        <div class="bg-white p-5 border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Retail Valuation</span>
                <h3 class="text-2xl font-black text-indigo-700 font-mono mt-1">PKR {{ number_format($totalRetailValuation, 2) }}</h3>
                <p class="text-[10px] text-slate-500 font-medium">Potential sales revenue</p>
            </div>
            <div class="w-10 h-10 bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>

        <div class="bg-white p-5 border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Inventory Cost Value</span>
                <h3 class="text-2xl font-black text-slate-700 font-mono mt-1">PKR {{ number_format($totalCostValuation, 2) }}</h3>
                <p class="text-[10px] text-slate-500 font-medium">Purchase cost investment</p>
            </div>
            <div class="w-10 h-10 bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
            </div>
        </div>
    </div>

    <!-- Stock Ledger Table -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <h2 class="text-xs font-black uppercase tracking-wider text-slate-700 flex items-center gap-2">
                <span>📦 Store-Wise Inventory Matrix</span>
                @if($selectedStore)
                    <span class="px-2 py-0.5 bg-indigo-100 text-indigo-800 text-[10px] font-bold">Filtered: {{ $selectedStore->name }}</span>
                @else
                    <span class="px-2 py-0.5 bg-slate-200 text-slate-700 text-[10px] font-bold">All {{ $stores->count() }} Stores View</span>
                @endif
            </h2>
            <span class="text-xs font-bold text-slate-500">
                Showing {{ $products->firstItem() ?? 0 }} - {{ $products->lastItem() ?? 0 }} of {{ $products->total() }} Products
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-100 border-b border-slate-200 text-slate-600 text-[10px] font-black uppercase tracking-wider">
                        <th class="py-3.5 px-4">Product Info</th>
                        <th class="py-3.5 px-3 text-right">Retail Price</th>
                        @if(!$storeId)
                            <!-- Dynamic Store Columns in All-Stores Matrix -->
                            @foreach($stores as $st)
                                <th class="py-3.5 px-3 text-center bg-slate-50/80 border-x border-slate-200">
                                    <div class="font-black text-slate-800">{{ $st->name }}</div>
                                    <span class="text-[9px] font-mono text-slate-400">[{{ $st->code }}]</span>
                                </th>
                            @endforeach
                            <th class="py-3.5 px-3 text-center bg-indigo-50/50">Total Stock</th>
                        @else
                            <!-- Single Store Specific View -->
                            <th class="py-3.5 px-4 text-center bg-indigo-50/40">
                                <div class="font-black text-indigo-900">{{ $selectedStore->name }} Stock</div>
                                <span class="text-[9px] font-mono text-indigo-500">[{{ $selectedStore->code }}]</span>
                            </th>
                            <th class="py-3.5 px-3 text-right">Store Cost Value</th>
                            <th class="py-3.5 px-3 text-right">Store Retail Value</th>
                        @endif
                        <th class="py-3.5 px-4 text-right">Stock Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-800">
                    @forelse($products as $prod)
                    @php
                        $storeStockVal = $storeId ? $prod->stockInStore($storeId) : $prod->stock;
                    @endphp
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        
                        <!-- Product Title & Image -->
                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 bg-slate-100 border border-slate-200 shrink-0 overflow-hidden flex items-center justify-center">
                                    @if($prod->image)
                                        <img src="{{ asset($prod->image) }}" class="w-full h-full object-cover" onerror="this.src='https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=100&q=80'">
                                    @else
                                        <span class="text-xs">🧴</span>
                                    @endif
                                </div>
                                <div>
                                    <h4 class="font-extrabold text-slate-900 leading-snug">{{ $prod->title }}</h4>
                                    <span class="font-mono text-[10px] text-slate-400">SKU: PROD-{{ str_pad($prod->id, 4, '0', STR_PAD_LEFT) }}</span>
                                </div>
                            </div>
                        </td>

                        <!-- Retail Price -->
                        <td class="py-3.5 px-3 text-right font-mono font-bold text-slate-800">
                            <div>PKR {{ number_format($prod->discounted_price ?? $prod->price, 2) }}</div>
                            @if($prod->discount > 0)
                                <span class="text-[9px] text-amber-700 font-extrabold">{{ $prod->discount }}% OFF</span>
                            @endif
                        </td>

                        @if(!$storeId)
                            <!-- Matrix View: Stock count per store column -->
                            @foreach($stores as $st)
                                @php
                                    $qty = $prod->stockInStore($st->id);
                                @endphp
                                <td class="py-3.5 px-3 text-center border-x border-slate-100 font-mono">
                                    <span class="inline-block px-2 py-0.5 text-xs font-black {{ $qty <= 0 ? 'bg-rose-50 text-rose-700 border border-rose-200' : ($qty <= 5 ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-slate-100 text-slate-900 border border-slate-300') }}">
                                        {{ $qty }}
                                    </span>
                                </td>
                            @endforeach

                            <!-- Total Aggregate Stock -->
                            <td class="py-3.5 px-3 text-center font-mono font-black text-sm bg-indigo-50/30 text-indigo-900">
                                {{ $prod->stock }}
                            </td>
                        @else
                            <!-- Single Store Stock -->
                            <td class="py-3.5 px-4 text-center font-mono font-black text-sm bg-indigo-50/20">
                                <span class="inline-block px-2.5 py-1 text-xs font-black {{ $storeStockVal <= 0 ? 'bg-rose-100 text-rose-800' : ($storeStockVal <= 5 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800') }}">
                                    {{ $storeStockVal }} units
                                </span>
                            </td>

                            <!-- Store Cost Valuation -->
                            <td class="py-3.5 px-3 text-right font-mono font-bold text-slate-700">
                                PKR {{ number_format($storeStockVal * $prod->price, 2) }}
                            </td>

                            <!-- Store Retail Valuation -->
                            <td class="py-3.5 px-3 text-right font-mono font-black text-indigo-700">
                                PKR {{ number_format($storeStockVal * ($prod->discounted_price ?? $prod->price), 2) }}
                            </td>
                        @endif

                        <!-- Stock Status Badge -->
                        <td class="py-3.5 px-4 text-right">
                            @if($storeStockVal <= 0)
                                <span class="px-2 py-0.5 bg-rose-100 text-rose-800 font-black text-[9px] uppercase">
                                    Out of Stock
                                </span>
                            @elseif($storeStockVal <= 5)
                                <span class="px-2 py-0.5 bg-amber-100 text-amber-800 font-black text-[9px] uppercase">
                                    Low Stock ({{ $storeStockVal }})
                                </span>
                            @else
                                <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 font-black text-[9px] uppercase">
                                    In Stock
                                </span>
                            @endif
                        </td>

                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ !$storeId ? ($stores->count() + 4) : 6 }}" class="py-12 text-center text-slate-400 font-semibold">
                            No products found matching the selected store and stock criteria.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="p-4 border-t border-slate-200">
            {{ $products->links() }}
        </div>
    </div>

</div>
@endsection
