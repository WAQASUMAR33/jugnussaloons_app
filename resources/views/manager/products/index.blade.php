@extends('layouts.material')

@section('title', 'Product Inventory Management')

@section('content')
<div x-data="{ 
    createModalOpen: false,
    editModalOpen: false,
    stores: {{ json_encode($stores) }},
    newPrice: 0,
    newDiscount: 0,
    newStoreStocks: {},
    editProduct: { id: null, title: '', price: 0, discount: 0, discounted_price: 0, stock: 0, low_stock: 5, image: null, store_stocks: {} },
    
    init() {
        this.resetNewStoreStocks();
    },

    resetNewStoreStocks() {
        this.newStoreStocks = {};
        this.stores.forEach(s => {
            this.newStoreStocks[s.id] = 0;
        });
    },

    get newTotalStock() {
        let total = 0;
        for (let sId in this.newStoreStocks) {
            total += parseInt(this.newStoreStocks[sId]) || 0;
        }
        return total;
    },

    get editTotalStock() {
        let total = 0;
        if (!this.editProduct.store_stocks) return 0;
        for (let sId in this.editProduct.store_stocks) {
            total += parseInt(this.editProduct.store_stocks[sId]) || 0;
        }
        return total;
    },

    calculateDiscount(price, discount) {
        if (!price || price <= 0) return 0;
        if (!discount || discount <= 0) return price;
        return (price - (price * (discount / 100))).toFixed(2);
    },

    openEdit(p) {
        const stocksMap = {};
        this.stores.forEach(s => {
            const found = (p.store_stocks || []).find(ss => ss.store_id == s.id);
            stocksMap[s.id] = found ? parseInt(found.stock) : 0;
        });

        this.editProduct = {
            id: p.id,
            title: p.title,
            price: p.price,
            discount: p.discount,
            discounted_price: p.discounted_price,
            stock: p.stock,
            low_stock: p.low_stock || 5,
            image: p.image ? ('/' + p.image.replace(/^\/+/, '')) : '',
            store_stocks: stocksMap
        };
        this.editModalOpen = true;
    }
}" class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="p-2.5 bg-emerald-50 text-emerald-600">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Product Inventory Management</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">Track multi-store inventory levels, retail pricing, and branch stock allocation.</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('manager.stores.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs border border-slate-300 transition-colors">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                <span>Store Locations</span>
            </a>
            <a href="{{ route('manager.stock-transfers.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs border border-slate-300 transition-colors">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                <span>Transfers</span>
            </a>
            <button @click="resetNewStoreStocks(); createModalOpen = true" 
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>Add Product</span>
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

    <!-- Short Stock Summary Alert Banner -->
    @if(isset($lowStockCount) && $lowStockCount > 0)
        <div class="p-4 bg-amber-50 border border-amber-300 text-amber-900 text-xs font-bold flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <span>⚠️ Short Stock Warning: <strong>{{ $lowStockCount }}</strong> {{ Str::plural('product', $lowStockCount) }} in inventory are at or below their configured short stock threshold.</span>
            </div>
            <a href="{{ route('manager.reports.stock', ['status' => 'low']) }}" class="px-2.5 py-1 bg-amber-200/80 text-amber-900 font-extrabold text-[10px] uppercase hover:bg-amber-300 transition-colors">
                View Stock Report
            </a>
        </div>
    @endif

    <!-- Filter & Search Bar with Store Selector -->
    <div class="bg-white p-4 border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <form method="GET" action="{{ route('manager.products.index') }}" class="flex-1 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search product by title..." 
                       class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 text-xs font-semibold focus:ring-2 focus:ring-emerald-600 focus:bg-white transition-all">
            </div>

            <!-- Store Location Filter -->
            <div class="w-full sm:w-64">
                <select name="store_id" class="w-full py-2 px-3 bg-slate-50 border border-slate-200 text-xs font-bold focus:ring-2 focus:ring-emerald-600 focus:bg-white transition-all">
                    <option value="">All Stores & Outlets (Global)</option>
                    @foreach($stores as $s)
                        <option value="{{ $s->id }}" {{ (string)$selectedStoreId === (string)$s->id ? 'selected' : '' }}>
                            🏬 {{ $s->name }} {{ $s->is_default ? '(Default)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="px-5 py-2 bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors shrink-0">
                Filter
            </button>
            @if($search || $selectedStoreId)
                <a href="{{ route('manager.products.index') }}" class="px-3.5 py-2 bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200 flex items-center justify-center shrink-0">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Products Table Card -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[10px] font-extrabold uppercase tracking-wider">
                        <th class="py-3 px-5">ID</th>
                        <th class="py-3 px-5">Image</th>
                        <th class="py-3 px-5">Product Title</th>
                        <th class="py-3 px-5 text-right">Price</th>
                        <th class="py-3 px-5 text-center">Discount</th>
                        <th class="py-3 px-5 text-right">Selling Price</th>
                        <th class="py-3 px-5">Store Stock Breakdown</th>
                        <th class="py-3 px-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @forelse($products as $product)
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <td class="py-3.5 px-5 font-mono font-bold text-slate-400">#{{ $product->id }}</td>
                        
                        <!-- Image -->
                        <td class="py-3.5 px-5">
                            @if($product->image)
                                <img src="{{ asset($product->image) }}" alt="{{ $product->title }}" class="w-10 h-10 object-cover border border-slate-200">
                            @else
                                <div class="w-10 h-10 bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-400">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                </div>
                            @endif
                        </td>

                        <!-- Title -->
                        <td class="py-3.5 px-5">
                            <p class="font-extrabold text-slate-900 leading-tight">{{ $product->title }}</p>
                        </td>

                        <!-- Price -->
                        <td class="py-3.5 px-5 text-right font-mono font-bold text-slate-700">
                            {{ number_format($product->price, 2) }}
                        </td>

                        <!-- Discount -->
                        <td class="py-3.5 px-5 text-center">
                            @if($product->discount > 0)
                                <span class="px-2 py-0.5 bg-amber-100 text-amber-800 text-[10px] font-bold">
                                    {{ $product->discount }}% OFF
                                </span>
                            @else
                                <span class="text-[10px] text-slate-400 font-medium">—</span>
                            @endif
                        </td>

                        <!-- Discounted Price -->
                        <td class="py-3.5 px-5 text-right font-mono font-black text-emerald-700">
                            {{ number_format($product->discounted_price, 2) }}
                        </td>

                        <!-- Multi-Store Stock Status Breakdown -->
                        <td class="py-3.5 px-5">
                            <div class="space-y-1.5">
                                <!-- Aggregate Total Badge -->
                                <div class="flex items-center gap-2">
                                    @if($product->stock <= 0)
                                        <span class="px-2 py-0.5 bg-rose-100 text-rose-800 text-[10px] font-black">
                                            🔴 Out of Stock (0)
                                        </span>
                                    @elseif($product->stock <= ($product->low_stock ?? 5))
                                        <span class="px-2 py-0.5 bg-amber-100 text-amber-900 border border-amber-300 text-[10px] font-black">
                                            ⚠️ Low Stock Total ({{ $product->stock }})
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 text-[10px] font-black">
                                            🟢 In Stock ({{ $product->stock }} total)
                                        </span>
                                    @endif
                                </div>

                                <!-- Multi-Store Individual Pills -->
                                <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
                                    @foreach($stores as $s)
                                        @php
                                            $stk = $product->storeStocks->where('store_id', $s->id)->first();
                                            $qty = $stk ? $stk->stock : 0;
                                        @endphp
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-slate-100 text-slate-700 text-[9px] font-bold border border-slate-200"
                                              title="{{ $s->name }}: {{ $qty }} units">
                                            <span class="text-slate-400 font-normal">{{ $s->code }}:</span>
                                            <span class="font-mono {{ $qty <= 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ $qty }}</span>
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </td>

                        <!-- Actions -->
                        <td class="py-3.5 px-5 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <button type="button" 
                                        @click="openEdit({{ json_encode($product) }})" 
                                        class="p-1.5 text-slate-600 hover:text-emerald-600 hover:bg-emerald-50 transition-colors" 
                                        title="Edit Product & Stocks">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </button>

                                <form method="POST" action="{{ route('manager.products.destroy', $product) }}" onsubmit="return confirm('Delete product {{ addslashes($product->title) }}?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors" title="Delete Product">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-12 text-center text-slate-400">
                            <p class="text-xs font-semibold">No products found matching query.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50">
            {{ $products->links() }}
        </div>
    </div>

    <!-- MODAL: CREATE PRODUCT WITH MULTI-STORE STOCK ALLOCATION -->
    <div x-show="createModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs"
         x-cloak>
        <div @click.outside="createModalOpen = false" class="bg-white max-w-xl w-full p-6 shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto border border-slate-300">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <div>
                    <h3 class="text-base font-black text-slate-900">Add New Product</h3>
                    <p class="text-xs text-slate-500">Define product details and allocate opening stock across store branches.</p>
                </div>
                <button type="button" @click="createModalOpen = false" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <form action="{{ route('manager.products.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Product Title <span class="text-rose-600">*</span></label>
                    <input type="text" name="title" required placeholder="e.g. Keratin Smooth Shampoo 500ml" 
                           class="w-full text-xs font-semibold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Original Price (PKR) <span class="text-rose-600">*</span></label>
                        <input type="number" step="0.01" min="0" name="price" x-model.number="newPrice" required placeholder="1500.00" 
                               class="w-full text-xs font-mono font-bold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Discount (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="discount" x-model.number="newDiscount" placeholder="10" 
                               class="w-full text-xs font-mono font-bold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Discounted Selling Price</label>
                        <input type="number" step="0.01" min="0" name="discounted_price" :value="calculateDiscount(newPrice, newDiscount)" 
                               class="w-full text-xs font-mono font-black text-emerald-700 p-2.5 bg-emerald-50/50 border border-emerald-300 focus:ring-0" readonly>
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Low Stock Threshold Limit</label>
                        <input type="number" min="0" name="low_stock" value="5" required 
                               class="w-full text-xs font-mono font-bold text-amber-900 p-2.5 bg-amber-50/40 border border-amber-300 focus:ring-0" title="Short stock alert threshold">
                    </div>
                </div>

                <!-- MULTI-STORE STOCK ALLOCATION BOX -->
                <div class="p-4 bg-slate-50 border border-slate-200 space-y-2.5">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                        <div>
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-800">Multi-Store Opening Stock Allocation</h4>
                            <p class="text-[10px] text-slate-500">Specify initial units stocked in each branch / store outlet.</p>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] text-slate-500 font-bold block">Total Stock Units</span>
                            <span class="text-sm font-mono font-black text-emerald-700" x-text="newTotalStock"></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-1">
                        <template x-for="s in stores" :key="s.id">
                            <div class="flex items-center justify-between p-2 bg-white border border-slate-200">
                                <div>
                                    <span class="text-xs font-bold text-slate-800 block" x-text="s.name"></span>
                                    <span class="text-[10px] font-mono text-slate-400" x-text="s.code + (s.is_default ? ' • Default' : '')"></span>
                                </div>
                                <div class="w-24">
                                    <input type="number" 
                                           min="0" 
                                           :name="'store_stocks[' + s.id + ']'" 
                                           x-model.number="newStoreStocks[s.id]" 
                                           placeholder="0" 
                                           class="w-full text-xs font-mono font-bold text-right p-1.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Product Image</label>
                    <input type="file" name="image" accept="image/*" 
                           class="w-full text-xs p-2 bg-slate-50 border border-slate-300 focus:border-emerald-600 focus:ring-0">
                </div>

                <div class="pt-3 flex justify-end gap-2 border-t border-slate-200">
                    <button type="button" @click="createModalOpen = false" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-bold">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-emerald-600 text-white text-xs font-black hover:bg-emerald-700">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: EDIT PRODUCT WITH MULTI-STORE STOCKS -->
    <div x-show="editModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs"
         x-cloak>
        <div @click.outside="editModalOpen = false" class="bg-white max-w-xl w-full p-6 shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto border border-slate-300">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <div>
                    <h3 class="text-base font-black text-slate-900" x-text="'Edit: ' + editProduct.title"></h3>
                    <p class="text-xs text-slate-500">Update pricing and multi-store inventory balances.</p>
                </div>
                <button type="button" @click="editModalOpen = false" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <form :action="'/manager/products/' + editProduct.id" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Product Title <span class="text-rose-600">*</span></label>
                    <input type="text" name="title" x-model="editProduct.title" required 
                           class="w-full text-xs font-semibold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Price (PKR) <span class="text-rose-600">*</span></label>
                        <input type="number" step="0.01" min="0" name="price" x-model.number="editProduct.price" required 
                               class="w-full text-xs font-mono font-bold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Discount (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="discount" x-model.number="editProduct.discount" 
                               class="w-full text-xs font-mono font-bold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Discounted Selling Price</label>
                        <input type="number" step="0.01" min="0" name="discounted_price" :value="calculateDiscount(editProduct.price, editProduct.discount)" 
                               class="w-full text-xs font-mono font-black text-emerald-700 p-2.5 bg-emerald-50/50 border border-emerald-300 focus:ring-0" readonly>
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Low Stock Limit</label>
                        <input type="number" min="0" name="low_stock" x-model.number="editProduct.low_stock" required 
                               class="w-full text-xs font-mono font-bold text-amber-900 p-2.5 bg-amber-50/40 border border-amber-300 focus:ring-0">
                    </div>
                </div>

                <!-- MULTI-STORE STOCK ALLOCATION EDIT BOX -->
                <div class="p-4 bg-slate-50 border border-slate-200 space-y-2.5">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                        <div>
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-800">Branch Stock Balances</h4>
                            <p class="text-[10px] text-slate-500">Edit stock counts across stores directly.</p>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] text-slate-500 font-bold block">Aggregated Total</span>
                            <span class="text-sm font-mono font-black text-emerald-700" x-text="editTotalStock"></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-1">
                        <template x-for="s in stores" :key="s.id">
                            <div class="flex items-center justify-between p-2 bg-white border border-slate-200">
                                <div>
                                    <span class="text-xs font-bold text-slate-800 block" x-text="s.name"></span>
                                    <span class="text-[10px] font-mono text-slate-400" x-text="s.code + (s.is_default ? ' • Default' : '')"></span>
                                </div>
                                <div class="w-24">
                                    <input type="number" 
                                           min="0" 
                                           :name="'store_stocks[' + s.id + ']'" 
                                           x-model.number="editProduct.store_stocks[s.id]" 
                                           class="w-full text-xs font-mono font-bold text-right p-1.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Product Image</label>
                    <template x-if="editProduct.image">
                        <div class="mb-2 flex items-center gap-3 p-2 bg-slate-50 border border-slate-200">
                            <img :src="editProduct.image" class="w-10 h-10 object-cover border">
                            <span class="text-xs text-slate-500 font-medium">Current Image</span>
                        </div>
                    </template>
                    <input type="file" name="image" accept="image/*" 
                           class="w-full text-xs p-2 bg-slate-50 border border-slate-300 focus:border-emerald-600 focus:ring-0">
                </div>

                <div class="pt-3 flex justify-end gap-2 border-t border-slate-200">
                    <button type="button" @click="editModalOpen = false" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-bold">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-emerald-600 text-white text-xs font-black hover:bg-emerald-700">Update Product</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
