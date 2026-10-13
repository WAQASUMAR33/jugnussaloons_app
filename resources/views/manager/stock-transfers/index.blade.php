@extends('layouts.material')

@section('title', 'Product Category Transfers')

@section('content')
<div x-data="{ 
    createModalOpen: false,
    isSubmitting: false,
    mainCategories: {{ json_encode($mainCategories) }},
    subCategories: {{ json_encode($subCategories) }},
    products: {{ json_encode($products) }},
    
    selectedProductId: '',
    destMainCatId: '',
    destSubCatId: '',
    destProductId: '',
    quantity: 1,

    get selectedProduct() {
        return this.products.find(p => p.id == this.selectedProductId) || null;
    },

    get availableStock() {
        if (!this.selectedProduct) return 0;
        return parseInt(this.selectedProduct.stock) || 0;
    },

    get sourceMainCatTitle() {
        if (!this.selectedProduct || !this.selectedProduct.main_category) return '—';
        return this.selectedProduct.main_category.title;
    },

    get sourceSubCatTitle() {
        if (!this.selectedProduct || !this.selectedProduct.category) return 'Uncategorized';
        return this.selectedProduct.category.title;
    },

    getFilteredSubCategories(mainId) {
        if (!mainId) return [];
        return this.subCategories.filter(sc => sc.product_main_category_id == mainId);
    },

    getDestinationCategoryProducts() {
        if (!this.destMainCatId) return [];
        return this.products.filter(p => p.id != this.selectedProductId && (p.product_main_category_id == this.destMainCatId || (this.destSubCatId && p.product_category_id == this.destSubCatId)));
    }
}" class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="p-2.5 bg-blue-50 text-blue-600">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Product & Category Transfers</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">Transfer product inventory from one main/sub category to another seamlessly.</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('manager.products.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs border border-slate-300 transition-colors">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                <span>Products Inventory</span>
            </a>
            <button @click="createModalOpen = true" 
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-sm transition-all cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>New Category Transfer</span>
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
        <form method="GET" action="{{ route('manager.stock-transfers.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Search</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Transfer #, product title..." 
                       class="w-full text-xs font-semibold p-2 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-600 focus:ring-0">
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Main Category</label>
                <select name="main_category_id" class="w-full text-xs font-semibold p-2 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-600 focus:ring-0">
                    <option value="">All Categories</option>
                    @foreach($mainCategories as $mc)
                        <option value="{{ $mc->id }}" {{ (string)$mainCatId === (string)$mc->id ? 'selected' : '' }}>🏛️ {{ $mc->title }}</option>
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
                    <button type="submit" class="px-3.5 py-2 bg-slate-900 text-white font-bold text-xs shrink-0 cursor-pointer">Filter</button>
                    @if(request()->hasAny(['search', 'main_category_id', 'from_date', 'to_date']))
                        <a href="{{ route('manager.stock-transfers.index') }}" class="px-2.5 py-2 bg-slate-200 text-slate-700 text-xs font-bold shrink-0">✕</a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    <!-- Transfers Table -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <h2 class="text-xs font-black uppercase tracking-wider text-slate-700">Category Transfer History Log</h2>
            <span class="text-xs font-bold text-slate-500">{{ $transfers->total() }} Transfers Logged ({{ number_format($totalTransferredUnits) }} total units moved)</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-100 border-b border-slate-200 text-slate-600 text-[10px] font-black uppercase tracking-wider">
                        <th class="py-3 px-4">Transfer #</th>
                        <th class="py-3 px-4">Date</th>
                        <th class="py-3 px-4">Product</th>
                        <th class="py-3 px-4">Source Category (From)</th>
                        <th class="py-3 px-4 text-center">Direction</th>
                        <th class="py-3 px-4">Destination Category (To)</th>
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

                        <!-- Source Category -->
                        <td class="py-3.5 px-4">
                            <div>
                                <span class="font-bold text-rose-700 text-xs block">
                                    🏛️ {{ $trf->sourceMainCategory ? $trf->sourceMainCategory->title : ($trf->sourceStore ? $trf->sourceStore->name : 'General') }}
                                </span>
                                @if($trf->sourceCategory)
                                    <span class="text-[10px] text-slate-500">🏷️ {{ $trf->sourceCategory->title }}</span>
                                @endif
                            </div>
                        </td>

                        <td class="py-3.5 px-4 text-center text-slate-400">
                            ➔
                        </td>

                        <!-- Destination Category -->
                        <td class="py-3.5 px-4">
                            <div>
                                <span class="font-bold text-emerald-700 text-xs block">
                                    🏛️ {{ $trf->destinationMainCategory ? $trf->destinationMainCategory->title : ($trf->destinationStore ? $trf->destinationStore->name : 'General') }}
                                </span>
                                @if($trf->destinationCategory)
                                    <span class="text-[10px] text-slate-500">🏷️ {{ $trf->destinationCategory->title }}</span>
                                @endif
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
                            No category transfers recorded yet.
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

    <!-- NEW CATEGORY TRANSFER MODAL -->
    <div x-show="createModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-xs" @click="createModalOpen = false"></div>
            <div class="inline-block w-full max-w-lg p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl border border-slate-300 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <div>
                        <h3 class="text-base font-black text-slate-900">Transfer Product Across Categories</h3>
                        <p class="text-xs text-slate-500">Move product inventory between Main Categories & Sub Categories.</p>
                    </div>
                    <button type="button" @click="createModalOpen = false" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>

                <form action="{{ route('manager.stock-transfers.store') }}" method="POST" @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true" class="space-y-4">
                    @csrf

                    <!-- 1. Select Product -->
                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Select Product <span class="text-rose-600">*</span></label>
                        <select name="product_id" x-model="selectedProductId" required 
                                class="w-full text-xs font-semibold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-600 focus:ring-0">
                            <option value="">-- Choose Product to Transfer --</option>
                            <template x-for="p in products" :key="p.id">
                                <option :value="p.id" x-text="p.title + ' (Stock: ' + p.stock + ')'"></option>
                            </template>
                        </select>

                        <!-- Product Current Category Card -->
                        <template x-if="selectedProduct">
                            <div class="mt-2 p-2.5 bg-slate-50 border border-slate-200 text-xs space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500 font-bold">Current Main Category:</span>
                                    <span class="font-black text-indigo-700" x-text="'🏛️ ' + sourceMainCatTitle"></span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500 font-bold">Current Sub Category:</span>
                                    <span class="font-bold text-emerald-700" x-text="'🏷️ ' + sourceSubCatTitle"></span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500 font-bold">Available Stock:</span>
                                    <span class="font-mono font-black" :class="availableStock > 0 ? 'text-emerald-700' : 'text-rose-600'" x-text="availableStock + ' units'"></span>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- 2. Destination Main & Sub Category -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-black uppercase text-slate-700 mb-1">Destination Main Category <span class="text-rose-600">*</span></label>
                            <select name="destination_main_category_id" x-model="destMainCatId" required 
                                    @change="destSubCatId = ''; destProductId = ''"
                                    class="w-full text-xs font-bold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-600 focus:ring-0">
                                <option value="">-- Choose Destination --</option>
                                <template x-for="mc in mainCategories" :key="mc.id">
                                    <option :value="mc.id" x-text="'🏛️ ' + mc.title"></option>
                                </template>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-black uppercase text-slate-700 mb-1">Destination Sub Category</label>
                            <select name="destination_category_id" x-model="destSubCatId"
                                    class="w-full text-xs font-bold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-600 focus:ring-0">
                                <option value="">-- Default / No Sub Category --</option>
                                <template x-for="sc in getFilteredSubCategories(destMainCatId)" :key="sc.id">
                                    <option :value="sc.id" x-text="'🏷️ ' + sc.title"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <!-- 3. Optional: Destination Product if merging units -->
                    <template x-if="destMainCatId && getDestinationCategoryProducts().length > 0">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">
                                Merge into Existing Destination Product <span class="text-slate-400 font-normal">(Optional)</span>
                            </label>
                            <select name="destination_product_id" x-model="destProductId" 
                                    class="w-full text-xs font-semibold p-2 bg-slate-50 border border-slate-300">
                                <option value="">-- None (Move this product itself into the new category) --</option>
                                <template x-for="dp in getDestinationCategoryProducts()" :key="dp.id">
                                    <option :value="dp.id" x-text="dp.title + ' (Current Stock: ' + dp.stock + ')'"></option>
                                </template>
                            </select>
                        </div>
                    </template>

                    <!-- 4. Quantity & Date -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-black uppercase text-slate-700 mb-1">Transfer Units <span class="text-rose-600">*</span></label>
                            <input type="number" name="quantity" x-model="quantity" min="1" :max="availableStock" required 
                                   class="w-full text-xs font-mono font-bold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-600 focus:ring-0">
                        </div>

                        <div>
                            <label class="block text-xs font-black uppercase text-slate-700 mb-1">Transfer Date <span class="text-rose-600">*</span></label>
                            <input type="date" name="transfer_date" value="{{ date('Y-m-d') }}" required 
                                   class="w-full text-xs font-semibold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-600 focus:ring-0">
                        </div>
                    </div>

                    <!-- 5. Notes -->
                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Transfer Notes / Reason</label>
                        <textarea name="notes" rows="2" placeholder="e.g. Reassigned from Salon Use to Retail Display..." 
                                  class="w-full text-xs p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-blue-600 focus:ring-0"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                        <button type="button" @click="createModalOpen = false" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-bold">Cancel</button>
                        <button type="submit" 
                                :disabled="isSubmitting || availableStock <= 0 || !destMainCatId"
                                class="px-5 py-2 bg-blue-600 text-white text-xs font-black hover:bg-blue-700 disabled:opacity-50 flex items-center gap-1.5 cursor-pointer">
                            <template x-if="isSubmitting">
                                <svg class="animate-spin w-3.5 h-3.5 text-white shrink-0" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                            </template>
                            <span x-text="isSubmitting ? 'Transferring...' : 'Execute Category Transfer'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
