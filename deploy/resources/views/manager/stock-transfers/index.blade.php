@extends('layouts.material')

@section('title', 'Inter-Store Stock Transfers')

@section('content')
<div x-data="{ 
    createModalOpen: false,
    stores: {{ json_encode($stores) }},
    products: {{ json_encode($products) }},
    sourceStoreId: '{{ $stores->first()->id ?? '' }}',
    destStoreId: '',
    selectedProductId: '',
    quantity: 1,

    get selectedProduct() {
        return this.products.find(p => p.id == this.selectedProductId) || null;
    },

    get availableSourceStock() {
        if (!this.selectedProduct || !this.sourceStoreId) return 0;
        const stockRecord = (this.selectedProduct.store_stocks || []).find(s => s.store_id == this.sourceStoreId);
        return stockRecord ? parseInt(stockRecord.stock) : 0;
    }
}" class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="p-2.5 bg-blue-50 text-blue-600">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Inter-Store Stock Transfers</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">Move product inventory between warehouse, outlets, and branches seamlessly.</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('manager.stores.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs border border-slate-300 transition-colors">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                <span>Manage Stores</span>
            </a>
            <button @click="createModalOpen = true" 
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-sm transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>New Stock Transfer</span>
            </button>
        </div>
    </div>

    <!-- Alert Notifications -->
    @if(session('success'))
    <div class="p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 text-xs font-bold flex items-center justify-between shadow-2xs">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <span>{{ session('success') }}</span>
        </div>
        <button type="button" @click="$el.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900 font-bold">&times;</button>
    </div>
    @endif

    @if(session('error'))
    <div class="p-4 bg-rose-50 border-l-4 border-rose-500 text-rose-800 text-xs font-bold flex items-center justify-between shadow-2xs">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            <span>{{ session('error') }}</span>
        </div>
        <button type="button" @click="$el.parentElement.remove()" class="text-rose-600 hover:text-rose-900 font-bold">&times;</button>
    </div>
    @endif

    <!-- Filter Bar -->
    <div class="bg-white p-5 border border-slate-200 shadow-sm space-y-3">
        <form method="GET" action="{{ route('manager.stock-transfers.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Search</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Transfer #, product title..." 
                       class="w-full text-xs font-semibold p-2 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-600 focus:ring-0">
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Source Store</label>
                <select name="source_store_id" class="w-full text-xs font-semibold p-2 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-600 focus:ring-0">
                    <option value="">All Source Stores</option>
                    @foreach($stores as $s)
                        <option value="{{ $s->id }}" {{ (string)$sourceStoreId === (string)$s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Destination Store</label>
                <select name="destination_store_id" class="w-full text-xs font-semibold p-2 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-600 focus:ring-0">
                    <option value="">All Destination Stores</option>
                    @foreach($stores as $s)
                        <option value="{{ $s->id }}" {{ (string)$destinationStoreId === (string)$s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Date From</label>
                <input type="date" name="from_date" value="{{ $fromDate }}" 
                       class="w-full text-xs font-semibold p-2 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-600 focus:ring-0">
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Date To</label>
                <div class="flex items-center gap-1.5">
                    <input type="date" name="to_date" value="{{ $toDate }}" 
                           class="w-full text-xs font-semibold p-2 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-600 focus:ring-0">
                    <button type="submit" class="px-3.5 py-2 bg-slate-900 text-white font-bold text-xs shrink-0">Filter</button>
                    @if(request()->hasAny(['search', 'source_store_id', 'destination_store_id', 'from_date', 'to_date']))
                        <a href="{{ route('manager.stock-transfers.index') }}" class="px-2.5 py-2 bg-slate-200 text-slate-700 text-xs font-bold shrink-0">✕</a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    <!-- Transfers Table -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <h2 class="text-xs font-black uppercase tracking-wider text-slate-700">Stock Transfer History Log</h2>
            <span class="text-xs font-bold text-slate-500">{{ $transfers->total() }} Transfers Logged ({{ number_format($totalTransferredUnits) }} total units moved)</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-100 border-b border-slate-200 text-slate-600 text-[10px] font-black uppercase tracking-wider">
                        <th class="py-3 px-4">Transfer #</th>
                        <th class="py-3 px-4">Date</th>
                        <th class="py-3 px-4">Product</th>
                        <th class="py-3 px-4">Source Store (From)</th>
                        <th class="py-3 px-4 text-center">Direction</th>
                        <th class="py-3 px-4">Destination Store (To)</th>
                        <th class="py-3 px-4 text-center">Quantity</th>
                        <th class="py-3 px-4">Notes / User</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-800">
                    @forelse($transfers as $trf)
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <td class="py-3.5 px-4 font-mono font-bold text-blue-700">
                            {{ $trf->transfer_no }}
                        </td>

                        <td class="py-3.5 px-4 whitespace-nowrap text-slate-600 font-semibold">
                            {{ \Carbon\Carbon::parse($trf->transfer_date)->format('d M, Y') }}
                        </td>

                        <td class="py-3.5 px-4">
                            <span class="font-extrabold text-slate-900">{{ $trf->product ? $trf->product->title : 'N/A' }}</span>
                        </td>

                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-1.5 font-bold text-rose-700">
                                <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                                <span>{{ $trf->sourceStore ? $trf->sourceStore->name : 'N/A' }}</span>
                            </div>
                        </td>

                        <td class="py-3.5 px-4 text-center text-slate-400">
                            ➔
                        </td>

                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-1.5 font-bold text-emerald-700">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                <span>{{ $trf->destinationStore ? $trf->destinationStore->name : 'N/A' }}</span>
                            </div>
                        </td>

                        <td class="py-3.5 px-4 text-center">
                            <span class="px-2.5 py-1 bg-blue-50 text-blue-800 border border-blue-200 font-mono font-black text-xs">
                                {{ $trf->quantity }} units
                            </span>
                        </td>

                        <td class="py-3.5 px-4 text-slate-500">
                            <div>{{ $trf->notes ?: '—' }}</div>
                            @if($trf->creator)
                                <div class="text-[10px] text-slate-400">by {{ $trf->creator->name }}</div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-10 text-center text-slate-400 font-semibold">
                            No stock transfers recorded yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($transfers->hasPages())
        <div class="p-4 border-t border-slate-200 bg-slate-50">
            {{ $transfers->links() }}
        </div>
        @endif
    </div>

    <!-- NEW STOCK TRANSFER MODAL -->
    <div x-show="createModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-xs" @click="createModalOpen = false"></div>
            <div class="inline-block w-full max-w-lg p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl border border-slate-300 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <div>
                        <h3 class="text-base font-black text-slate-900">Transfer Product Stock</h3>
                        <p class="text-xs text-slate-500">Move inventory from one store location to another.</p>
                    </div>
                    <button type="button" @click="createModalOpen = false" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>

                <form action="{{ route('manager.stock-transfers.store') }}" method="POST" class="space-y-4">
                    @csrf

                    <!-- Source & Destination Stores -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-black uppercase text-slate-700 mb-1">Source Store (From) <span class="text-rose-600">*</span></label>
                            <select name="source_store_id" x-model="sourceStoreId" required 
                                    class="w-full text-xs font-bold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-600 focus:ring-0">
                                <template x-for="s in stores" :key="s.id">
                                    <option :value="s.id" x-text="s.name + (s.is_default ? ' (Default)' : '')"></option>
                                </template>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-black uppercase text-slate-700 mb-1">Destination Store (To) <span class="text-rose-600">*</span></label>
                            <select name="destination_store_id" x-model="destStoreId" required 
                                    class="w-full text-xs font-bold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-600 focus:ring-0">
                                <option value="">-- Choose Destination --</option>
                                <template x-for="s in stores" :key="s.id">
                                    <option :value="s.id" :disabled="s.id == sourceStoreId" x-text="s.name"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <!-- Product Selection -->
                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Select Product <span class="text-rose-600">*</span></label>
                        <select name="product_id" x-model="selectedProductId" required 
                                class="w-full text-xs font-semibold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-600 focus:ring-0">
                            <option value="">-- Choose Product --</option>
                            <template x-for="p in products" :key="p.id">
                                <option :value="p.id" x-text="p.title"></option>
                            </template>
                        </select>

                        <!-- Stock indicator badge -->
                        <div class="mt-1.5 flex items-center justify-between text-xs">
                            <span class="text-slate-500 font-medium">Available in selected source store:</span>
                            <span class="font-mono font-black" :class="availableSourceStock > 0 ? 'text-emerald-700' : 'text-rose-600'" x-text="availableSourceStock + ' units in source store'"></span>
                        </div>
                    </div>

                    <!-- Quantity & Date -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-black uppercase text-slate-700 mb-1">Transfer Quantity <span class="text-rose-600">*</span></label>
                            <input type="number" name="quantity" x-model="quantity" min="1" :max="availableSourceStock" required 
                                   class="w-full text-xs font-mono font-bold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-600 focus:ring-0">
                        </div>

                        <div>
                            <label class="block text-xs font-black uppercase text-slate-700 mb-1">Transfer Date <span class="text-rose-600">*</span></label>
                            <input type="date" name="transfer_date" value="{{ date('Y-m-d') }}" required 
                                   class="w-full text-xs font-semibold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-600 focus:ring-0">
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Transfer Reason / Notes</label>
                        <textarea name="notes" rows="2" placeholder="e.g. Restocking weekend display shelf, branch replenishment..." 
                                  class="w-full text-xs p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-600 focus:ring-0"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                        <button type="button" @click="createModalOpen = false" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-bold">Cancel</button>
                        <button type="submit" 
                                :disabled="availableSourceStock <= 0 || !destStoreId || destStoreId == sourceStoreId"
                                class="px-5 py-2 bg-blue-600 text-white text-xs font-black hover:bg-blue-700 disabled:opacity-50">
                            Confirm & Execute Transfer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
