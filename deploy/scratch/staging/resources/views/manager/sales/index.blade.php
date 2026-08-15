@extends('layouts.material')

@section('title', 'Product Sales Management')

@section('content')
<div x-data="{ 
    createModalOpen: false,
    viewModalOpen: false,
    selectedSale: null,
    productsList: {{ json_encode($products) }},
    items: [
        { product_id: '', search_term: '', open: false, quantity: 1, unit_price: 0, stock: 0, subtotal: 0 }
    ],
    receivedAmount: 0,
    billDiscount: 0,
    paymentMode: 'Cash',
    extraAmount: 0,
    addItem() {
        this.items.push({ product_id: '', search_term: '', open: false, quantity: 1, unit_price: 0, stock: 0, subtotal: 0 });
    },
    removeItem(index) {
        if (this.items.length > 1) {
            this.items.splice(index, 1);
        }
    },
    selectProduct(index, prod) {
        this.items[index].product_id = prod.id;
        this.items[index].search_term = prod.title;
        this.items[index].unit_price = prod.discounted_price || prod.price;
        this.items[index].stock = prod.stock;
        this.items[index].open = false;
        this.calculateSubtotal(index);
    },
    getFilteredProducts(term) {
        if (!term || term.trim() === '') return this.productsList;
        const search = term.toLowerCase();
        return this.productsList.filter(p => p.title.toLowerCase().includes(search));
    },
    calculateSubtotal(index) {
        const qty = parseFloat(this.items[index].quantity) || 0;
        const price = parseFloat(this.items[index].unit_price) || 0;
        this.items[index].subtotal = (qty * price).toFixed(2);
    },
    getItemSubtotal() {
        return this.items.reduce((sum, item) => sum + (parseFloat(item.subtotal) || 0), 0).toFixed(2);
    },
    getTotalAmount() {
        const subtotal = parseFloat(this.getItemSubtotal()) || 0;
        const discount = parseFloat(this.billDiscount) || 0;
        const extra = (this.paymentMode === 'Bank') ? (parseFloat(this.extraAmount) || 0) : 0;
        return Math.max(0, subtotal - discount + extra).toFixed(2);
    },
    getBalanceDue() {
        const total = parseFloat(this.getTotalAmount()) || 0;
        const received = parseFloat(this.receivedAmount) || 0;
        return (total - received).toFixed(2);
    }
}" class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="p-2.5 bg-emerald-50 text-emerald-600">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Product Sales Management</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">Sell hair products & accessories to customers, record receivings, and update customer balance.</p>
            </div>
        </div>

        <button @click="createModalOpen = true" 
                class="inline-flex items-center gap-2 px-5 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            <span>Record Product Sale</span>
        </button>
    </div>

    <!-- Sales Statistics Overview Widgets -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Today Sales -->
        <div class="bg-white p-5 border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-[11px] font-extrabold uppercase text-slate-400 tracking-wider">Today's Sales</p>
                <h3 class="text-2xl font-black text-slate-900 mt-1">{{ number_format($todaySales, 2) }}</h3>
                <p class="text-[11px] font-semibold text-emerald-600 mt-1">Live Revenue Today</p>
            </div>
            <div class="p-3 bg-emerald-50 text-emerald-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>

        <!-- Monthly Sales -->
        <div class="bg-white p-5 border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-[11px] font-extrabold uppercase text-slate-400 tracking-wider">This Month Sales</p>
                <h3 class="text-2xl font-black text-indigo-900 mt-1">{{ number_format($thisMonthSales, 2) }}</h3>
                <p class="text-[11px] font-semibold text-indigo-600 mt-1">{{ now()->format('F Y') }} Total</p>
            </div>
            <div class="p-3 bg-indigo-50 text-indigo-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </div>
        </div>

        <!-- Total Payments Collected -->
        <div class="bg-white p-5 border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-[11px] font-extrabold uppercase text-slate-400 tracking-wider">Payments Received</p>
                <h3 class="text-2xl font-black text-emerald-700 mt-1">{{ number_format($totalReceivings, 2) }}</h3>
                <p class="text-[11px] font-semibold text-emerald-600 mt-1">Cash & Receivings</p>
            </div>
            <div class="p-3 bg-emerald-50 text-emerald-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>

        <!-- Balance Due Credit -->
        <div class="bg-white p-5 border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-[11px] font-extrabold uppercase text-slate-400 tracking-wider">Credit Balance Due</p>
                <h3 class="text-2xl font-black text-rose-700 mt-1">{{ number_format($totalBalanceDue, 2) }}</h3>
                <p class="text-[11px] font-semibold text-rose-600 mt-1">Pending Customer Receivables</p>
            </div>
            <div class="p-3 bg-rose-50 text-rose-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>
    </div>

    <!-- Date-wise Sales Statistics Breakdown Card -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden" x-data="{ showStatsTable: true }">
        <div class="p-4 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <div class="p-1.5 bg-indigo-600 text-white">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                </div>
                <h3 class="text-sm font-extrabold text-slate-900 uppercase tracking-wider">Date-Wise Sales Statistics Breakdown</h3>
            </div>

            <button @click="showStatsTable = !showStatsTable" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">
                <span x-text="showStatsTable ? 'Hide Date-Wise Breakdown' : 'Show Date-Wise Breakdown'"></span>
            </button>
        </div>

        <div x-show="showStatsTable" class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-100/70 border-b border-slate-200 text-slate-600 text-[11px] font-extrabold uppercase tracking-wider">
                        <th class="py-3 px-6">Sale Date</th>
                        <th class="py-3 px-6">Invoices Count</th>
                        <th class="py-3 px-6">Total Sales Volume</th>
                        <th class="py-3 px-6">Discounts Allowed</th>
                        <th class="py-3 px-6">Payments Collected</th>
                        <th class="py-3 px-6">Remaining Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs font-medium">
                    @forelse($dateWiseStats as $stat)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="py-3.5 px-6 font-bold text-slate-900">
                            {{ \Carbon\Carbon::parse($stat->sale_date)->format('M d, Y') }} 
                            <span class="text-[10px] text-slate-400 font-semibold">({{ \Carbon\Carbon::parse($stat->sale_date)->format('l') }})</span>
                        </td>
                        <td class="py-3.5 px-6">
                            <span class="px-2.5 py-1 bg-indigo-50 text-indigo-700 font-extrabold rounded-none">
                                {{ $stat->invoice_count }} Invoices
                            </span>
                        </td>
                        <td class="py-3.5 px-6 font-black text-slate-900">
                            {{ number_format($stat->total_sales, 2) }}
                        </td>
                        <td class="py-3.5 px-6 font-bold text-amber-700">
                            {{ number_format($stat->total_discount, 2) }}
                        </td>
                        <td class="py-3.5 px-6 font-bold text-emerald-600">
                            {{ number_format($stat->total_received, 2) }}
                        </td>
                        <td class="py-3.5 px-6 font-bold {{ $stat->total_balance_due > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                            {{ number_format($stat->total_balance_due, 2) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-8 text-center text-slate-400">
                            <p class="text-xs font-semibold">No sales statistics recorded for selected date range.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Search & Date Filter Bar -->
    <div class="bg-white p-4 border border-slate-200 shadow-sm">
        <form method="GET" action="{{ route('manager.sales.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <div class="relative flex-1">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by invoice # or customer..." 
                       class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 text-xs font-medium focus:ring-2 focus:ring-emerald-600 focus:bg-white transition-all">
            </div>

            <div>
                <input type="date" name="start_date" value="{{ $startDate }}" placeholder="From Date" 
                       class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-medium focus:ring-2 focus:ring-emerald-600">
            </div>

            <div>
                <input type="date" name="end_date" value="{{ $endDate }}" placeholder="To Date" 
                       class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-medium focus:ring-2 focus:ring-emerald-600">
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 py-2 bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors">
                    Filter Statistics
                </button>
                @if($search || $startDate || $endDate)
                    <a href="{{ route('manager.sales.index') }}" class="px-3 py-2 bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200 flex items-center justify-center">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Sales Table Card -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[11px] font-extrabold uppercase tracking-wider">
                        <th class="py-4 px-6">Invoice #</th>
                        <th class="py-4 px-6">Customer Account</th>
                        <th class="py-4 px-6">Sale Date</th>
                        <th class="py-4 px-6">Payment Mode</th>
                        <th class="py-4 px-6">Total Amount</th>
                        <th class="py-4 px-6">Bill Discount</th>
                        <th class="py-4 px-6">Received Amount</th>
                        <th class="py-4 px-6">Balance Due</th>
                        <th class="py-4 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($sales as $sale)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="py-4 px-6 font-mono font-bold text-xs text-emerald-700">
                            {{ $sale->invoice_no }}
                        </td>
                        <td class="py-4 px-6">
                            <p class="font-bold text-slate-900 leading-tight">{{ $sale->customer->name ?? 'Walk-in Customer' }}</p>
                            <span class="text-[10px] font-bold text-slate-400">
                                {{ $sale->customer->category->title ?? 'Customer' }}
                            </span>
                        </td>
                        <td class="py-4 px-6 text-xs text-slate-600 font-medium">
                            {{ $sale->sale_date->format('M d, Y') }}
                        </td>
                        <td class="py-4 px-6">
                            <span class="px-2 py-0.5 font-extrabold text-[10px] uppercase rounded tracking-wider {{ $sale->payment_mode === 'Bank' ? 'bg-indigo-100 text-indigo-800 border border-indigo-200' : 'bg-emerald-100 text-emerald-800 border border-emerald-200' }}">
                                {{ $sale->payment_mode ?? 'Cash' }}
                            </span>
                            @if($sale->payment_mode === 'Bank' && $sale->extra_amount > 0)
                                <span class="block text-[10px] text-indigo-600 font-bold mt-0.5">+{{ number_format($sale->extra_amount, 2) }} extra</span>
                            @endif
                        </td>
                        <td class="py-4 px-6 font-extrabold text-slate-900">
                            {{ number_format($sale->total_amount, 2) }}
                        </td>
                        <td class="py-4 px-6 font-bold text-amber-700">
                            {{ number_format($sale->discount, 2) }}
                        </td>
                        <td class="py-4 px-6 font-bold text-emerald-600">
                            {{ number_format($sale->received_amount, 2) }}
                        </td>
                        <td class="py-4 px-6 font-bold {{ $sale->balance_due > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                            {{ number_format($sale->balance_due, 2) }}
                        </td>
                        <td class="py-4 px-6 text-right">
                            <button @click="selectedSale = {{ json_encode($sale) }}; viewModalOpen = true" 
                                    class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 font-bold text-xs">
                                View Items ({{ $sale->items->count() }})
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-12 text-center text-slate-400">
                            <p class="text-sm font-semibold">No product sale records found.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-slate-100">
            {{ $sales->links() }}
        </div>
    </div>

    <!-- MODAL: RECORD NEW SALE -->
    <div x-show="createModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="createModalOpen = false" class="bg-white max-w-3xl w-full p-6 shadow-2xl space-y-6 max-h-[92vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    Record Product Sale
                </h3>
                <button @click="createModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('manager.sales.store') }}" class="space-y-4">
                @csrf
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Select Customer Account</label>
                        <select name="account_id" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-emerald-600">
                            <option value="">Select Customer Account</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}">
                                    {{ $customer->name }} (Category: {{ $customer->category->title ?? 'General' }} | Balance: {{ number_format($customer->balance, 2) }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Sale Date</label>
                        <input type="date" name="sale_date" value="{{ date('Y-m-d') }}" required 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-emerald-600">
                    </div>
                </div>

                <!-- Item Lines Header -->
                <div class="pt-2">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase">Product Sale Line Items</label>
                        <button type="button" @click="addItem()" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs flex items-center gap-1">
                            + Add Product Line
                        </button>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(item, index) in items" :key="index">
                            <div class="flex flex-col sm:flex-row items-center gap-3 p-3 bg-slate-50 border border-slate-200">
                                <div class="flex-1 w-full relative" @click.outside="item.open = false">
                                    <input type="hidden" :name="'items['+index+'][product_id]'" :value="item.product_id" required>
                                    
                                    <div class="relative">
                                        <input type="text" 
                                               x-model="item.search_term" 
                                               @focus="item.open = true" 
                                               @input="item.open = true; item.product_id = ''" 
                                               placeholder="🔍 Type product name to filter..." 
                                               required
                                               class="w-full px-3 py-2 pr-8 bg-white border border-slate-200 text-xs font-semibold focus:ring-2 focus:ring-emerald-600">
                                        <span class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-slate-400">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                        </span>
                                    </div>

                                    <!-- Filterable Product List Dropdown -->
                                    <div x-show="item.open" 
                                         class="absolute z-30 left-0 right-0 mt-1 bg-white border border-slate-200 shadow-xl max-h-52 overflow-y-auto divide-y divide-slate-100"
                                         x-cloak>
                                        <template x-for="prod in getFilteredProducts(item.search_term)" :key="prod.id">
                                            <div @click="selectProduct(index, prod)" 
                                                 class="p-2.5 hover:bg-emerald-50 cursor-pointer flex items-center justify-between transition-colors text-xs">
                                                <div>
                                                    <p class="font-bold text-slate-900" x-text="prod.title"></p>
                                                    <p class="text-[10px]" :class="prod.stock <= 0 ? 'text-rose-600 font-bold' : 'text-slate-400'">
                                                        In Stock: <span class="font-bold" :class="prod.stock <= 0 ? 'text-rose-600' : 'text-slate-700'" x-text="prod.stock"></span>
                                                        <span x-show="prod.stock <= 0" class="text-rose-600 font-extrabold ml-1">(Out of Stock)</span>
                                                    </p>
                                                </div>
                                                <span class="font-extrabold text-emerald-600" x-text="prod.discounted_price || prod.price"></span>
                                            </div>
                                        </template>
                                        <div x-show="getFilteredProducts(item.search_term).length === 0" class="p-3 text-center text-xs text-slate-400 font-medium">
                                            No matching products found
                                        </div>
                                    </div>
                                </div>

                                <div class="w-full sm:w-24">
                                    <input type="number" min="1" :name="'items['+index+'][quantity]'" x-model.number="item.quantity" @input="calculateSubtotal(index)" required placeholder="Qty" 
                                           class="w-full px-3 py-2 bg-white border border-slate-200 text-xs font-bold focus:ring-2 focus:ring-emerald-600">
                                </div>

                                <div class="w-full sm:w-32">
                                    <input type="number" step="0.01" min="0" :name="'items['+index+'][unit_price]'" x-model.number="item.unit_price" @input="calculateSubtotal(index)" required placeholder="Unit Price" 
                                           class="w-full px-3 py-2 bg-white border border-slate-200 text-xs font-bold focus:ring-2 focus:ring-emerald-600">
                                </div>

                                <div class="w-full sm:w-32 text-right font-extrabold text-xs text-slate-800">
                                    <span x-text="item.subtotal"></span>
                                </div>

                                <button type="button" @click="removeItem(index)" class="p-2 text-rose-500 hover:bg-rose-50">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>

                            <!-- Low Stock Alert Banner for Item -->
                            <div x-show="item.product_id && item.quantity > item.stock" class="mt-1 px-3 py-1.5 bg-amber-50 border border-amber-200 text-amber-900 text-xs font-bold flex items-center justify-between">
                                <span class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                    <span>⚠️ Low / Out of Stock Alert: Selling <span x-text="item.quantity"></span> unit(s) (Current stock: <span x-text="item.stock"></span>).</span>
                                </span>
                                <span class="text-[10px] bg-amber-200/80 text-amber-900 px-2 py-0.5 font-black uppercase rounded">Sale Allowed</span>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Payment Summary Grid -->
                <div class="p-4 bg-emerald-50/50 border border-emerald-200/80 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Items Subtotal</label>
                        <div class="text-lg font-bold text-slate-700" x-text="getItemSubtotal()"></div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Bill Discount</label>
                        <input type="number" step="0.01" min="0" name="discount" x-model.number="billDiscount" placeholder="0.00" 
                               class="w-full px-3 py-2 bg-white border border-slate-300 text-sm font-bold text-amber-700 focus:ring-2 focus:ring-emerald-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Payment Mode</label>
                        <select name="payment_mode" x-model="paymentMode" class="w-full px-3 py-2 bg-white border border-slate-300 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-emerald-600">
                            <option value="Cash">Cash</option>
                            <option value="Bank">Bank</option>
                        </select>
                    </div>

                    <div x-show="paymentMode === 'Bank'">
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Bank Extra Amount</label>
                        <input type="number" step="0.01" min="0" name="extra_amount" x-model.number="extraAmount" placeholder="0.00" 
                               class="w-full px-3 py-2 bg-white border border-slate-300 text-sm font-bold text-indigo-700 focus:ring-2 focus:ring-emerald-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Net Bill Amount</label>
                        <div class="text-xl font-black text-slate-900" x-text="getTotalAmount()"></div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Payment Received</label>
                        <input type="number" step="0.01" min="0" name="received_amount" x-model.number="receivedAmount" required placeholder="0.00" 
                               class="w-full px-3 py-2 bg-white border border-slate-300 text-sm font-bold text-emerald-700 focus:ring-2 focus:ring-emerald-600">
                    </div>
                </div>

                <div class="p-3 bg-rose-50 border border-rose-200/80 flex items-center justify-between">
                    <span class="text-xs font-bold text-rose-800 uppercase">Balance Due / Remaining Credit</span>
                    <span class="text-xl font-black text-rose-700" x-text="getBalanceDue()"></span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Notes / Remarks (Optional)</label>
                    <textarea name="notes" rows="2" placeholder="Special discounts, gift codes..." 
                              class="w-full px-4 py-2 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-emerald-600"></textarea>
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="createModalOpen = false" class="px-4 py-2.5 text-slate-600 font-bold text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 bg-emerald-600 text-white font-bold text-xs shadow-md">
                        Complete Sale & Update Stock
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: VIEW SALE ITEMS & PRINT SLIP -->
    <div x-show="viewModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="viewModalOpen = false" class="bg-white max-w-xl w-full p-6 shadow-2xl space-y-6" x-if="selectedSale" id="sale-print-slip">
            
            <div class="pos-receipt-80mm-container pos-receipt-font bg-white p-5 max-w-sm mx-auto shadow-md border border-slate-200">
                <!-- Cursive Receipt Header & Store Info -->
                <div class="text-center pb-2">
                    <h1 class="pos-receipt-cursive text-4xl font-bold text-slate-900 leading-tight">Receipt</h1>
                    <h2 class="text-xs font-bold text-slate-900 uppercase tracking-tight mt-0.5">{{ $appSetting->brand_name }}</h2>
                    @if($appSetting->brand_address)
                        <p class="text-[11px] text-slate-700 leading-tight mt-1">Adress: {{ $appSetting->brand_address }}</p>
                    @endif
                    @if($appSetting->brand_phone1 || $appSetting->brand_phone2)
                        <p class="text-[11px] text-slate-700">Tel: {{ implode(' | ', array_filter([$appSetting->brand_phone1, $appSetting->brand_phone2])) }}</p>
                    @endif
                </div>

                <!-- Dashed Line -->
                <div class="border-t border-dashed border-slate-900 my-2"></div>

                <!-- Date & Time / Invoice Info -->
                <div class="flex justify-between text-xs font-bold text-slate-900">
                    <span>Date: <span x-text="selectedSale.created_at"></span></span>
                    <span>Inv: #<span x-text="selectedSale.invoice_no"></span></span>
                </div>

                <!-- Dashed Line -->
                <div class="border-t border-dashed border-slate-900 my-2"></div>

                <!-- Items List -->
                <div class="space-y-1.5 text-xs py-1">
                    <template x-for="item in selectedSale.items" :key="item.id">
                        <div class="flex justify-between items-start">
                            <div class="flex-1 pr-2">
                                <span class="font-bold text-slate-900" x-text="item.product ? item.product.title : 'Product'"></span>
                                <span class="text-[10px] text-slate-600 block" x-text="item.quantity + ' @ ' + item.unit_price"></span>
                            </div>
                            <span class="font-bold text-slate-900" x-text="item.subtotal"></span>
                        </div>
                    </template>
                </div>

                <!-- Dashed Line -->
                <div class="border-t border-dashed border-slate-900 my-2"></div>

                <!-- Main AMOUNT Row -->
                <div class="flex justify-between items-baseline py-1">
                    <span class="text-sm font-bold uppercase tracking-wider text-slate-900">AMOUNT</span>
                    <span class="text-xl font-bold text-slate-900" x-text="(parseFloat(selectedSale.total_amount) - parseFloat(selectedSale.discount || 0)).toFixed(2)"></span>
                </div>

                <!-- Sub-total & Financial Breakdown -->
                <div class="space-y-1 text-xs pt-1.5 border-t border-slate-200">
                    <div class="flex justify-between text-slate-800">
                        <span>Sub-total</span>
                        <span x-text="selectedSale.total_amount"></span>
                    </div>
                    <div class="flex justify-between text-slate-800" x-show="selectedSale.discount > 0">
                        <span>Discount</span>
                        <span x-text="selectedSale.discount"></span>
                    </div>
                    <div class="flex justify-between text-slate-800">
                        <span>Paid</span>
                        <span x-text="selectedSale.received_amount"></span>
                    </div>
                    <div class="flex justify-between font-bold text-slate-900" x-show="selectedSale.balance_due > 0">
                        <span>Balance</span>
                        <span x-text="selectedSale.balance_due"></span>
                    </div>
                </div>

                <!-- Dashed Line -->
                <div class="border-t border-dashed border-slate-900 my-3"></div>

                <!-- Barcode Representation -->
                <div class="text-center space-y-1">
                    <div class="font-mono text-base tracking-tighter text-slate-900 leading-none overflow-hidden select-none">
                        ||| | ||||| || |||||| | |||| ||| ||||||| | |||||| |||||
                    </div>
                    <p class="text-[10px] text-slate-600 font-bold uppercase tracking-wider pt-1">Thank You For Your Visit</p>
                </div>
            </div>

            <div class="pt-4 flex flex-wrap justify-between items-center gap-2 border-t border-slate-100 print:hidden">
                <div class="flex items-center gap-2">
                    <button type="button" @click="print80mmPOSReceipt('sale-print-slip')" 
                            class="px-4 py-2 bg-emerald-600 text-white font-extrabold text-xs shadow-xs hover:bg-emerald-700 flex items-center gap-1.5">
                        <span>🧾 Print 80mm POS Thermal Slip</span>
                    </button>
                    <button type="button" @click="window.print()" 
                            class="px-4 py-2 bg-indigo-600 text-white font-extrabold text-xs shadow-xs hover:bg-indigo-700 flex items-center gap-1.5">
                        <span>📄 Print A4 Slip</span>
                    </button>
                </div>

                <button type="button" @click="viewModalOpen = false" class="px-5 py-2 bg-slate-900 text-white font-bold text-xs">
                    Close
                </button>
            </div>
        </div>
    </div>

</div>
@endsection
