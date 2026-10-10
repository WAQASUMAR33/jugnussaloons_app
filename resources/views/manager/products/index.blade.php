@extends('layouts.material')

@section('title', 'Product Inventory Management')

@section('content')
<style>
@keyframes indeterminate-bar {
    0% { left: -35%; right: 100%; }
    60% { left: 100%; right: -90%; }
    100% { left: 100%; right: -90%; }
}
@keyframes indeterminate-short-bar {
    0% { left: -200%; right: 100%; }
    60% { left: 107%; right: -8%; }
    100% { left: 107%; right: -8%; }
}
.progress-bar-loading {
    position: relative;
    overflow: hidden;
}
.progress-bar-loading::before {
    content: '';
    position: absolute;
    background-color: #059669;
    top: 0; left: 0; bottom: 0;
    will-change: left, right;
    animation: indeterminate-bar 2.1s cubic-bezier(0.65, 0.815, 0.735, 0.395) infinite;
}
.progress-bar-loading::after {
    content: '';
    position: absolute;
    background-color: #34d399;
    top: 0; left: 0; bottom: 0;
    will-change: left, right;
    animation: indeterminate-short-bar 2.1s cubic-bezier(0.165, 0.84, 0.44, 1) infinite;
    animation-delay: 1.15s;
}
</style>

<div x-data="{ 
    createModalOpen: {{ $errors->any() ? 'true' : 'false' }},
    editModalOpen: false,
    isSubmitting: false,
    createImagePreview: null,
    createImageName: '',
    createImageSize: '',
    editImagePreview: null,
    editImageName: '',
    stores: {{ json_encode($stores) }},
    newTitle: {{ json_encode(old('title', '')) }},
    newPrice: {{ json_encode(old('price', '')) }},
    newDiscount: {{ json_encode(old('discount', 0)) }},
    newLowStock: {{ json_encode(old('low_stock', 5)) }},
    newStoreStocks: {{ json_encode(old('store_stocks', (object)[])) }},
    editProduct: { id: null, product_category_id: '', title: '', price: 0, discount: 0, discounted_price: 0, stock: 0, low_stock: 5, image: null, store_stocks: {} },
    
    init() {
        if (!this.newStoreStocks || Object.keys(this.newStoreStocks).length === 0) {
            this.resetNewStoreStocks();
        }
    },

    resetNewStoreStocks() {
        this.newStoreStocks = {};
        this.stores.forEach(s => {
            this.newStoreStocks[s.id] = 0;
        });
    },

    openCreate() {
        this.isSubmitting = false;
        this.clearCreateImage();
        @if(!$errors->any())
            this.resetNewStoreStocks();
            this.newTitle = '';
            this.newPrice = '';
            this.newDiscount = 0;
            this.newLowStock = 5;
        @endif
        this.createModalOpen = true;
    },

    handleCreateImage(event) {
        const file = event.target.files[0];
        if (!file) {
            this.clearCreateImage();
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            alert('Image file size exceeds 10MB limit. Please choose a smaller photo.');
            this.clearCreateImage();
            return;
        }
        this.createImageName = file.name;
        this.createImageSize = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
        const reader = new FileReader();
        reader.onload = (e) => {
            this.createImagePreview = e.target.result;
        };
        reader.readAsDataURL(file);
    },

    clearCreateImage() {
        this.createImagePreview = null;
        this.createImageName = '';
        this.createImageSize = '';
        const input = document.getElementById('createProductImageInput');
        if (input) input.value = '';
    },

    handleEditImage(event) {
        const file = event.target.files[0];
        if (!file) {
            this.clearEditImage();
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            alert('Image file size exceeds 10MB limit. Please choose a smaller photo.');
            this.clearEditImage();
            return;
        }
        this.editImageName = file.name;
        const reader = new FileReader();
        reader.onload = (e) => {
            this.editImagePreview = e.target.result;
        };
        reader.readAsDataURL(file);
    },

    clearEditImage() {
        this.editImagePreview = null;
        this.editImageName = '';
        const input = document.getElementById('editProductImageInput');
        if (input) input.value = '';
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
        this.isSubmitting = false;
        this.clearEditImage();
        const stocksMap = {};
        this.stores.forEach(s => {
            const found = (p.store_stocks || []).find(ss => ss.store_id == s.id);
            stocksMap[s.id] = found ? parseInt(found.stock) : 0;
        });

        this.editProduct = {
            id: p.id,
            product_category_id: p.product_category_id || '',
            title: p.title,
            price: p.price,
            discount: p.discount,
            discounted_price: p.discounted_price,
            stock: p.stock,
            low_stock: p.low_stock || 5,
            image: p.image_url || (p.image ? ('/' + p.image.replace(/^\/+/, '')) : ''),
            store_stocks: stocksMap
        };
        this.editModalOpen = true;
    }
}" class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-4 sm:p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="p-2.5 bg-emerald-50 text-emerald-600 shrink-0">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Product Inventory Management</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">Track multi-store inventory levels, retail pricing, and branch stock allocation.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
            <a href="{{ route('manager.product-categories.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs border border-slate-300 transition-colors">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                <span>Categories</span>
            </a>
            <a href="{{ route('manager.stores.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs border border-slate-300 transition-colors">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                <span>Store Locations</span>
            </a>
            <a href="{{ route('manager.stock-transfers.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs border border-slate-300 transition-colors">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                <span>Transfers</span>
            </a>
            <button type="button" 
                    @click="openCreate()" 
                    class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm transition-all cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>Add Product</span>
            </button>
        </div>
    </div>

    <!-- Alert Notifications -->
    @if(session('success'))
    <div class="p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 text-xs font-bold flex items-center justify-between shadow-2xs">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <span>{{ session('success') }}</span>
        </div>
        <button type="button" @click="$el.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900 font-bold">&times;</button>
    </div>
    @endif

    @if(session('error'))
    <div class="p-4 bg-rose-50 border-l-4 border-rose-500 text-rose-800 text-xs font-bold flex items-center justify-between shadow-2xs">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            <span>{{ session('error') }}</span>
        </div>
        <button type="button" @click="$el.parentElement.remove()" class="text-rose-600 hover:text-rose-900 font-bold">&times;</button>
    </div>
    @endif

    <!-- Validation Error Alert Banner -->
    @if($errors->any())
    <div class="p-4 bg-rose-50 border-l-4 border-rose-500 text-rose-800 text-xs shadow-2xs">
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-2 font-black text-rose-900">
                <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <span>Please correct the errors below:</span>
            </div>
            <button type="button" @click="$el.closest('div').remove()" class="text-rose-600 hover:text-rose-900 font-bold">&times;</button>
        </div>
        <ul class="list-disc list-inside space-y-1 text-rose-700 pl-1 font-semibold">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
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

            <!-- Category Filter -->
            <div class="w-full sm:w-56">
                <select name="category_id" class="w-full py-2 px-3 bg-slate-50 border border-slate-200 text-xs font-bold focus:ring-2 focus:ring-emerald-600 focus:bg-white transition-all">
                    <option value="">All Categories (Global)</option>
                    @foreach($categories as $c)
                        <option value="{{ $c->id }}" {{ (string)$selectedCategoryId === (string)$c->id ? 'selected' : '' }}>
                            🏷️ {{ $c->title }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Store Location Filter -->
            <div class="w-full sm:w-56">
                <select name="store_id" class="w-full py-2 px-3 bg-slate-50 border border-slate-200 text-xs font-bold focus:ring-2 focus:ring-emerald-600 focus:bg-white transition-all">
                    <option value="">All Stores & Outlets (Global)</option>
                    @foreach($stores as $s)
                        <option value="{{ $s->id }}" {{ (string)$selectedStoreId === (string)$s->id ? 'selected' : '' }}>
                            🏬 {{ $s->name }} {{ $s->is_default ? '(Default)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="px-5 py-2 bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors shrink-0 cursor-pointer">
                Filter
            </button>
            @if($search || $selectedStoreId || $selectedCategoryId)
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
                        <th class="py-3 px-5">Category</th>
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
                        
                        <!-- Image with fallback -->
                        <td class="py-3.5 px-5">
                            @if($product->image)
                                <img src="{{ asset($product->image) }}" 
                                     alt="{{ $product->title }}" 
                                     class="w-10 h-10 object-cover border border-slate-200 rounded-xs shadow-2xs"
                                     onerror="this.onerror=null; this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');">
                                <div class="hidden w-10 h-10 bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-400" title="Image unavailable">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </div>
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

                        <!-- Category -->
                        <td class="py-3.5 px-5">
                            @if($product->category)
                                <a href="{{ route('manager.products.index', ['category_id' => $product->category->id]) }}" 
                                   class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100 transition-colors"
                                   title="Filter by {{ $product->category->title }}">
                                    <span>🏷️</span>
                                    <span>{{ $product->category->title }}</span>
                                </a>
                            @else
                                <span class="text-slate-400 text-[11px] italic font-medium">Uncategorized</span>
                            @endif
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
                                        class="p-1.5 text-slate-600 hover:text-emerald-600 hover:bg-emerald-50 transition-colors cursor-pointer" 
                                        title="Edit Product & Stocks">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </button>

                                <form method="POST" action="{{ route('manager.products.destroy', $product) }}" onsubmit="return confirm('Delete product {{ addslashes($product->title) }}?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer" title="Delete Product">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-12 text-center text-slate-400">
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
    <template x-teleport="body">
        <div x-show="createModalOpen" 
             @click.self="if(!isSubmitting) createModalOpen = false"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
             x-cloak>
            <div class="bg-white max-w-xl w-full p-6 shadow-2xl space-y-5 max-h-[92vh] overflow-y-auto border border-slate-300 relative">
            
            <!-- Sleek Indeterminate Loading Bar when Submitting -->
            <div x-show="isSubmitting" class="w-full bg-emerald-100 h-1.5 progress-bar-loading -mt-6 -mx-6 mb-3"></div>

            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <div>
                    <h3 class="text-base font-black text-slate-900">Add New Product</h3>
                    <p class="text-xs text-slate-500">Define product details, image, and opening stock across branch locations.</p>
                </div>
                <button type="button" 
                        @click="if(!isSubmitting) createModalOpen = false" 
                        :disabled="isSubmitting"
                        :class="isSubmitting ? 'opacity-40 cursor-not-allowed' : 'text-slate-400 hover:text-slate-600'"
                        class="text-xl font-bold p-1 leading-none cursor-pointer">&times;</button>
            </div>

            <form action="{{ route('manager.products.store') }}" 
                  method="POST" 
                  enctype="multipart/form-data" 
                  @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true;" 
                  class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Product Title <span class="text-rose-600">*</span></label>
                    <input type="text" 
                           name="title" 
                           x-model="newTitle" 
                           required 
                           placeholder="e.g. Keratin Smooth Shampoo 500ml" 
                           class="w-full text-xs font-semibold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                    @error('title')
                        <p class="text-rose-600 text-[11px] font-bold mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Product Category <span class="text-[10px] text-slate-400 font-normal lowercase">(optional)</span></label>
                    <select name="product_category_id" 
                            class="w-full text-xs font-bold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                        <option value="">-- No Category (Uncategorized) --</option>
                        @foreach($categories as $c)
                            <option value="{{ $c->id }}" {{ old('product_category_id') == $c->id ? 'selected' : '' }}>
                                🏷️ {{ $c->title }}
                            </option>
                        @endforeach
                    </select>
                    @error('product_category_id')
                        <p class="text-rose-600 text-[11px] font-bold mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Original Price (PKR) <span class="text-rose-600">*</span></label>
                        <input type="number" 
                               step="0.01" 
                               min="0" 
                               name="price" 
                               x-model="newPrice" 
                               required 
                               placeholder="1500.00" 
                               class="w-full text-xs font-mono font-bold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                        @error('price')
                            <p class="text-rose-600 text-[11px] font-bold mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Discount (%)</label>
                        <input type="number" 
                               step="0.01" 
                               min="0" 
                               max="100" 
                               name="discount" 
                               x-model="newDiscount" 
                               placeholder="10" 
                               class="w-full text-xs font-mono font-bold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                        @error('discount')
                            <p class="text-rose-600 text-[11px] font-bold mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Discounted Selling Price</label>
                        <input type="number" 
                               step="0.01" 
                               min="0" 
                               name="discounted_price" 
                               :value="calculateDiscount(newPrice, newDiscount)" 
                               class="w-full text-xs font-mono font-black text-emerald-700 p-2.5 bg-emerald-50/50 border border-emerald-300 focus:ring-0" 
                               readonly>
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Low Stock Limit</label>
                        <input type="number" 
                               min="0" 
                               name="low_stock" 
                               x-model="newLowStock" 
                               required 
                               class="w-full text-xs font-mono font-bold text-amber-900 p-2.5 bg-amber-50/40 border border-amber-300 focus:ring-0" 
                               title="Short stock alert threshold">
                        @error('low_stock')
                            <p class="text-rose-600 text-[11px] font-bold mt-1">{{ $message }}</p>
                        @enderror
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
                            <span class="text-[10px] text-slate-500 font-bold block">Total Units</span>
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

                <!-- PRODUCT IMAGE UPLOAD WITH LIVE PREVIEW -->
                <div class="space-y-2">
                    <label class="block text-xs font-black uppercase text-slate-700">
                        Product Image <span class="text-[10px] text-slate-400 font-normal lowercase">(optional — jpg, png, webp, max 10MB)</span>
                    </label>

                    <!-- Live Image Preview Card -->
                    <template x-if="createImagePreview">
                        <div class="p-3 bg-emerald-50/70 border border-emerald-200 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <img :src="createImagePreview" class="w-14 h-14 object-cover border border-emerald-300 rounded-xs bg-white shadow-2xs">
                                <div>
                                    <p class="text-xs font-bold text-slate-900 truncate max-w-[200px] sm:max-w-xs" x-text="createImageName"></p>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="text-[10px] font-mono text-emerald-700 font-bold" x-text="createImageSize"></span>
                                        <span class="px-1.5 py-0.2 bg-emerald-100 text-emerald-800 text-[9px] font-bold rounded-xs">Ready</span>
                                    </div>
                                </div>
                            </div>
                            <button type="button" 
                                    @click="clearCreateImage()" 
                                    class="px-2.5 py-1 text-xs font-bold text-rose-600 hover:text-rose-800 hover:bg-rose-50 border border-rose-200 transition-colors cursor-pointer">
                                Remove
                            </button>
                        </div>
                    </template>

                    <div class="relative">
                        <input type="file" 
                               name="image" 
                               id="createProductImageInput" 
                               accept="image/jpeg,image/png,image/jpg,image/gif,image/webp,image/avif" 
                               @change="handleCreateImage($event)"
                               class="w-full text-xs p-2 bg-slate-50 border border-slate-300 focus:border-emerald-600 focus:ring-0 file:mr-3 file:py-1 file:px-3 file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer">
                    </div>
                    @error('image')
                        <p class="text-rose-600 text-[11px] font-bold mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- SUBMIT ACTIONS WITH MULTI-REQUEST PREVENTION & LOADING BAR -->
                <div class="pt-3 flex items-center justify-between border-t border-slate-200">
                    <div class="text-[11px] text-slate-500 font-semibold">
                        <span x-show="isSubmitting" class="text-emerald-700 font-bold inline-flex items-center gap-1.5 animate-pulse">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Creating product & saving stock...
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" 
                                @click="if(!isSubmitting) createModalOpen = false" 
                                :disabled="isSubmitting"
                                :class="isSubmitting ? 'opacity-50 cursor-not-allowed pointer-events-none' : 'hover:bg-slate-200'"
                                class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-bold transition-colors cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" 
                                :disabled="isSubmitting"
                                :class="isSubmitting ? 'opacity-70 cursor-not-allowed pointer-events-none' : 'hover:opacity-90'"
                                style="background-color: #059669; color: #ffffff;"
                                class="inline-flex items-center justify-center gap-2 min-w-[130px] px-5 py-2.5 text-white text-xs font-black shadow-sm transition-all cursor-pointer">
                            <span x-show="!isSubmitting" class="inline-flex items-center gap-1.5 text-white">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                <span class="text-white">Save Product</span>
                            </span>
                            <span x-show="isSubmitting" class="inline-flex items-center gap-2 text-white">
                                <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-white">Saving Product...</span>
                            </span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    </template>

    <!-- MODAL: EDIT PRODUCT WITH MULTI-STORE STOCKS -->
    <template x-teleport="body">
        <div x-show="editModalOpen" 
             @click.self="if(!isSubmitting) editModalOpen = false"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
             x-cloak>
            <div class="bg-white max-w-xl w-full p-6 shadow-2xl space-y-5 max-h-[92vh] overflow-y-auto border border-slate-300 relative">
            
            <!-- Sleek Indeterminate Loading Bar when Submitting -->
            <div x-show="isSubmitting" class="w-full bg-emerald-100 h-1.5 progress-bar-loading -mt-6 -mx-6 mb-3"></div>

            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <div>
                    <h3 class="text-base font-black text-slate-900" x-text="'Edit: ' + editProduct.title"></h3>
                    <p class="text-xs text-slate-500">Update pricing, image, and multi-store inventory balances.</p>
                </div>
                <button type="button" 
                        @click="if(!isSubmitting) editModalOpen = false" 
                        :disabled="isSubmitting"
                        :class="isSubmitting ? 'opacity-40 cursor-not-allowed' : 'text-slate-400 hover:text-slate-600'"
                        class="text-xl font-bold p-1 leading-none cursor-pointer">&times;</button>
            </div>

            <form :action="'/manager/products/' + editProduct.id" 
                  method="POST" 
                  enctype="multipart/form-data" 
                  @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true;" 
                  class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Product Title <span class="text-rose-600">*</span></label>
                    <input type="text" 
                           name="title" 
                           x-model="editProduct.title" 
                           required 
                           class="w-full text-xs font-semibold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                </div>

                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Product Category <span class="text-[10px] text-slate-400 font-normal lowercase">(optional)</span></label>
                    <select name="product_category_id" 
                            x-model="editProduct.product_category_id" 
                            class="w-full text-xs font-bold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                        <option value="">-- No Category (Uncategorized) --</option>
                        @foreach($categories as $c)
                            <option value="{{ $c->id }}">
                                🏷️ {{ $c->title }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Price (PKR) <span class="text-rose-600">*</span></label>
                        <input type="number" 
                               step="0.01" 
                               min="0" 
                               name="price" 
                               x-model.number="editProduct.price" 
                               required 
                               class="w-full text-xs font-mono font-bold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Discount (%)</label>
                        <input type="number" 
                               step="0.01" 
                               min="0" 
                               max="100" 
                               name="discount" 
                               x-model.number="editProduct.discount" 
                               class="w-full text-xs font-mono font-bold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Discounted Selling Price</label>
                        <input type="number" 
                               step="0.01" 
                               min="0" 
                               name="discounted_price" 
                               :value="calculateDiscount(editProduct.price, editProduct.discount)" 
                               class="w-full text-xs font-mono font-black text-emerald-700 p-2.5 bg-emerald-50/50 border border-emerald-300 focus:ring-0" 
                               readonly>
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Low Stock Limit</label>
                        <input type="number" 
                               min="0" 
                               name="low_stock" 
                               x-model.number="editProduct.low_stock" 
                               required 
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

                <!-- PRODUCT IMAGE EDIT WITH CURRENT & REPLACEMENT PREVIEW -->
                <div class="space-y-2">
                    <label class="block text-xs font-black uppercase text-slate-700">
                        Product Image <span class="text-[10px] text-slate-400 font-normal lowercase">(leave empty to retain current photo)</span>
                    </label>

                    <div class="flex flex-wrap items-center gap-3">
                        <template x-if="editProduct.image">
                            <div class="p-2.5 bg-slate-50 border border-slate-200 flex items-center gap-3">
                                <img :src="editProduct.image" class="w-12 h-12 object-cover border border-slate-300 rounded-xs shadow-2xs">
                                <div>
                                    <span class="text-[10px] font-bold text-slate-600 block uppercase">Current Image</span>
                                    <span class="text-[9px] text-slate-400 font-medium">Will remain active</span>
                                </div>
                            </div>
                        </template>

                        <template x-if="editImagePreview">
                            <div class="p-2.5 bg-emerald-50 border border-emerald-300 flex items-center gap-3">
                                <img :src="editImagePreview" class="w-12 h-12 object-cover border border-emerald-400 rounded-xs shadow-2xs bg-white">
                                <div>
                                    <span class="text-[10px] font-bold text-emerald-800 block uppercase">New Replacement</span>
                                    <button type="button" @click="clearEditImage()" class="text-[10px] text-rose-600 font-bold hover:underline cursor-pointer">Cancel New</button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <input type="file" 
                           name="image" 
                           id="editProductImageInput" 
                           accept="image/jpeg,image/png,image/jpg,image/gif,image/webp,image/avif" 
                           @change="handleEditImage($event)"
                           class="w-full text-xs p-2 bg-slate-50 border border-slate-300 focus:border-emerald-600 focus:ring-0 file:mr-3 file:py-1 file:px-3 file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer">
                    @error('image')
                        <p class="text-rose-600 text-[11px] font-bold mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- SUBMIT ACTIONS WITH MULTI-REQUEST PREVENTION & LOADING BAR -->
                <div class="pt-3 flex items-center justify-between border-t border-slate-200">
                    <div class="text-[11px] text-slate-500 font-semibold">
                        <span x-show="isSubmitting" class="text-emerald-700 font-bold inline-flex items-center gap-1.5 animate-pulse">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Updating product & balances...
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" 
                                @click="if(!isSubmitting) editModalOpen = false" 
                                :disabled="isSubmitting"
                                :class="isSubmitting ? 'opacity-50 cursor-not-allowed pointer-events-none' : 'hover:bg-slate-200'"
                                class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-bold transition-colors cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" 
                                :disabled="isSubmitting"
                                :class="isSubmitting ? 'opacity-70 cursor-not-allowed pointer-events-none' : 'hover:opacity-90'"
                                style="background-color: #059669; color: #ffffff;"
                                class="inline-flex items-center justify-center gap-2 min-w-[140px] px-5 py-2.5 text-white text-xs font-black shadow-sm transition-all cursor-pointer">
                            <span x-show="!isSubmitting" class="inline-flex items-center gap-1.5 text-white">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                <span class="text-white">Update Product</span>
                            </span>
                            <span x-show="isSubmitting" class="inline-flex items-center gap-2 text-white">
                                <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-white">Updating Product...</span>
                            </span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    </template>

</div>
@endsection
