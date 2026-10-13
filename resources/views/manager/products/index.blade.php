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
    
    mainCategories: {{ json_encode($mainCategories) }},
    subCategories: {{ json_encode($categories) }},

    newMainCategoryId: {{ json_encode(old('product_main_category_id', $selectedMainCategoryId ?? ($mainCategories->first()->id ?? ''))) }},
    newCategoryId: {{ json_encode(old('product_category_id', '')) }},
    newTitle: {{ json_encode(old('title', '')) }},
    newPrice: {{ json_encode(old('price', '')) }},
    newDiscount: {{ json_encode(old('discount', 0)) }},
    newStock: {{ json_encode(old('stock', 0)) }},
    newLowStock: {{ json_encode(old('low_stock', 5)) }},

    editProduct: { 
        id: null, 
        product_main_category_id: '', 
        product_category_id: '', 
        title: '', 
        price: 0, 
        discount: 0, 
        discounted_price: 0, 
        stock: 0, 
        low_stock: 5, 
        image: null 
    },

    getSubCategoriesFor(mainCatId) {
        if (!mainCatId) return [];
        return this.subCategories.filter(sc => sc.product_main_category_id == mainCatId);
    },

    openCreate() {
        this.isSubmitting = false;
        this.clearCreateImage();
        @if(!$errors->any())
            this.newMainCategoryId = '{{ $mainCategories->first()->id ?? '' }}';
            this.newCategoryId = '';
            this.newTitle = '';
            this.newPrice = '';
            this.newDiscount = 0;
            this.newStock = 0;
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

    calculateDiscount(price, discount) {
        let p = parseFloat(price) || 0;
        let d = parseFloat(discount) || 0;
        if (d > 0 && d <= 100) {
            return (p - (p * (d / 100))).toFixed(2);
        }
        return p.toFixed(2);
    },

    openEdit(product) {
        this.isSubmitting = false;
        this.clearEditImage();
        this.editProduct = {
            id: product.id,
            product_main_category_id: product.product_main_category_id || (product.category ? product.category.product_main_category_id : ''),
            product_category_id: product.product_category_id || '',
            title: product.title,
            price: product.price,
            discount: product.discount || 0,
            discounted_price: product.discounted_price,
            stock: product.stock,
            low_stock: product.low_stock || 5,
            image: product.image_url || (product.image ? ('/' + product.image.replace(/^\/+/, '')) : null)
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
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Products & Cosmetics Inventory</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">Manage products categorized by dynamic Main Categories and linked Sub Categories with realtime stock tracking.</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('manager.product-categories.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs border border-slate-300 transition-colors">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                <span>Manage Categories</span>
            </a>
            <button @click="openCreate()" 
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm transition-all cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>Add New Product</span>
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

    <!-- Filter & Search Bar with Dynamic Main & Sub Category Selectors -->
    <div class="bg-white p-4 border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <form method="GET" action="{{ route('manager.products.index') }}" class="flex-1 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search product by title..." 
                       class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 text-xs font-semibold focus:ring-2 focus:ring-emerald-600 focus:bg-white transition-all">
            </div>

            <!-- Main Category Filter -->
            <div class="w-full sm:w-56">
                <select name="main_category_id" class="w-full py-2 px-3 bg-slate-50 border border-slate-200 text-xs font-bold focus:ring-2 focus:ring-emerald-600 focus:bg-white transition-all">
                    <option value="">All Main Categories</option>
                    @foreach($mainCategories as $mc)
                        <option value="{{ $mc->id }}" {{ (string)$selectedMainCategoryId === (string)$mc->id ? 'selected' : '' }}>
                            🏛️ {{ $mc->title }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Sub Category Filter -->
            <div class="w-full sm:w-56">
                <select name="category_id" class="w-full py-2 px-3 bg-slate-50 border border-slate-200 text-xs font-bold focus:ring-2 focus:ring-emerald-600 focus:bg-white transition-all">
                    <option value="">All Sub Categories</option>
                    @foreach($categories as $c)
                        @if(!$selectedMainCategoryId || $c->product_main_category_id == $selectedMainCategoryId)
                            <option value="{{ $c->id }}" {{ (string)$selectedCategoryId === (string)$c->id ? 'selected' : '' }}>
                                🏷️ {{ $c->title }}
                            </option>
                        @endif
                    @endforeach
                </select>
            </div>

            <button type="submit" class="px-5 py-2 bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors shrink-0 cursor-pointer">
                Filter
            </button>
            @if($search || $selectedMainCategoryId || $selectedCategoryId)
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
                        <th class="py-3 px-5">Main Category</th>
                        <th class="py-3 px-5">Sub Category</th>
                        <th class="py-3 px-5 text-right">Price</th>
                        <th class="py-3 px-5 text-center">Discount</th>
                        <th class="py-3 px-5 text-right">Selling Price</th>
                        <th class="py-3 px-5 text-center">Current Stock</th>
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

                        <!-- Main Category -->
                        <td class="py-3.5 px-5">
                            @if($product->mainCategory)
                                <a href="{{ route('manager.products.index', ['main_category_id' => $product->mainCategory->id]) }}" 
                                   class="inline-flex items-center gap-1 px-2.5 py-0.5 text-[10px] font-black uppercase bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 transition-colors">
                                    <span>🏛️</span>
                                    <span>{{ $product->mainCategory->title }}</span>
                                </a>
                            @else
                                <span class="text-slate-400 text-[11px] italic font-medium">—</span>
                            @endif
                        </td>

                        <!-- Sub Category -->
                        <td class="py-3.5 px-5">
                            @if($product->category)
                                <a href="{{ route('manager.products.index', ['category_id' => $product->category->id]) }}" 
                                   class="inline-flex items-center gap-1 px-2.5 py-0.5 text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100 transition-colors">
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

                        <!-- Direct Stock Status -->
                        <td class="py-3.5 px-5 text-center">
                            @if($product->stock <= 0)
                                <span class="px-2.5 py-1 bg-rose-100 text-rose-800 border border-rose-300 font-mono font-black text-xs">
                                    Out of Stock (0)
                                </span>
                            @elseif($product->stock <= ($product->low_stock ?? 5))
                                <span class="px-2.5 py-1 bg-amber-100 text-amber-900 border border-amber-300 font-mono font-black text-xs">
                                    Low: {{ $product->stock }} units
                                </span>
                            @else
                                <span class="px-2.5 py-1 bg-emerald-50 text-emerald-800 border border-emerald-300 font-mono font-black text-xs">
                                    {{ $product->stock }} units
                                </span>
                            @endif
                        </td>

                        <!-- Actions -->
                        <td class="py-3.5 px-5 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" 
                                        @click="openEdit({{ json_encode($product) }})" 
                                        class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors cursor-pointer">
                                    Edit
                                </button>

                                <form action="{{ route('manager.products.destroy', $product) }}" 
                                      method="POST" 
                                      onsubmit="return confirm('Delete product {{ addslashes($product->title) }}? This cannot be undone.');" 
                                      class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" 
                                            class="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-xs transition-colors cursor-pointer">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="py-12 text-center text-slate-400">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                <span class="font-bold text-xs">No products found matching the criteria.</span>
                                @if($search || $selectedMainCategoryId || $selectedCategoryId)
                                    <a href="{{ route('manager.products.index') }}" class="text-xs text-emerald-600 font-bold hover:underline">
                                        Clear all filters
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
        <div class="p-4 border-t border-slate-200 bg-slate-50">
            {{ $products->links() }}
        </div>
        @endif
    </div>

    <!-- ============================================================= -->
    <!-- CREATE PRODUCT MODAL (WITH DYNAMIC MAIN & SUB CATEGORY LOGIC) -->
    <!-- ============================================================= -->
    <div x-show="createModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-xs" @click="if(!isSubmitting) createModalOpen = false"></div>

            <div class="inline-block w-full max-w-xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl border border-slate-300 space-y-4">
                
                <div x-show="isSubmitting" class="h-1.5 w-full bg-slate-100 progress-bar-loading -mt-6 -mx-6 mb-3"></div>

                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <div>
                        <h3 class="text-base font-black text-slate-900">Add New Inventory Product</h3>
                        <p class="text-xs text-slate-500">Configure product catalog details and initial stock level.</p>
                    </div>
                    <button type="button" @click="if(!isSubmitting) createModalOpen = false" :disabled="isSubmitting" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <form action="{{ route('manager.products.store') }}" 
                      method="POST" 
                      enctype="multipart/form-data" 
                      @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true"
                      class="space-y-4">
                    @csrf

                    <!-- 1. DYNAMIC MAIN CATEGORY SELECTOR -->
                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">
                            Main Category <span class="text-rose-600">*</span>
                        </label>
                        <select name="product_main_category_id" 
                                x-model="newMainCategoryId" 
                                @change="newCategoryId = ''" 
                                required 
                                class="w-full text-xs font-bold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                            <option value="">-- Select Main Category --</option>
                            <template x-for="mc in mainCategories" :key="mc.id">
                                <option :value="mc.id" x-text="'🏛️ ' + mc.title"></option>
                            </template>
                        </select>
                        <span class="text-[10px] text-slate-400 font-medium">Top division (e.g. Retail Products, Salon Use, Cosmetics)</span>
                    </div>

                    <!-- 2. DYNAMIC SUB CATEGORY SELECTOR (SHOWN ONLY FOR SELECTED MAIN CATEGORY) -->
                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">
                            Sub Category <span class="text-[10px] text-slate-400 font-normal lowercase">(filtered for chosen Main Category)</span>
                        </label>
                        <select name="product_category_id" 
                                x-model="newCategoryId" 
                                class="w-full text-xs font-bold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                            <option value="">-- No Sub Category / General --</option>
                            <template x-for="sc in getSubCategoriesFor(newMainCategoryId)" :key="sc.id">
                                <option :value="sc.id" x-text="'🏷️ ' + sc.title"></option>
                            </template>
                        </select>
                        <div x-show="!newMainCategoryId" class="text-[10px] text-amber-600 font-semibold mt-0.5">
                            Please select a Main Category first to view its sub categories.
                        </div>
                    </div>

                    <!-- 3. TITLE -->
                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Product Title <span class="text-rose-600">*</span></label>
                        <input type="text" 
                               name="title" 
                               x-model="newTitle" 
                               required 
                               placeholder="e.g. Keratin Smooth Shampoo 500ml" 
                               class="w-full text-xs font-semibold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                    </div>

                    <!-- 4. PRICING -->
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
                        </div>

                        <div>
                            <label class="block text-xs font-black uppercase text-slate-700 mb-1">Discount (%)</label>
                            <input type="number" 
                                   step="0.01" 
                                   min="0" 
                                   max="100" 
                                   name="discount" 
                                   x-model="newDiscount" 
                                   placeholder="0" 
                                   class="w-full text-xs font-mono font-bold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-black uppercase text-slate-700 mb-1">Selling Price</label>
                            <input type="number" 
                                   step="0.01" 
                                   name="discounted_price" 
                                   :value="calculateDiscount(newPrice, newDiscount)" 
                                   class="w-full text-xs font-mono font-black text-emerald-700 p-2.5 bg-emerald-50/50 border border-emerald-300 focus:ring-0" 
                                   readonly>
                        </div>

                        <div>
                            <label class="block text-xs font-black uppercase text-slate-700 mb-1">Opening Stock <span class="text-rose-600">*</span></label>
                            <input type="number" 
                                   min="0" 
                                   name="stock" 
                                   x-model="newStock" 
                                   required 
                                   class="w-full text-xs font-mono font-bold text-slate-900 p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0" 
                                   placeholder="0">
                        </div>

                        <div>
                            <label class="block text-xs font-black uppercase text-slate-700 mb-1">Low Stock Limit</label>
                            <input type="number" 
                                   min="0" 
                                   name="low_stock" 
                                   x-model="newLowStock" 
                                   required 
                                   class="w-full text-xs font-mono font-bold text-amber-900 p-2.5 bg-amber-50/40 border border-amber-300 focus:ring-0">
                        </div>
                    </div>

                    <!-- 5. IMAGE UPLOAD -->
                    <div class="space-y-2">
                        <label class="block text-xs font-black uppercase text-slate-700">
                            Product Image <span class="text-[10px] text-slate-400 font-normal lowercase">(optional — max 10MB)</span>
                        </label>

                        <template x-if="createImagePreview">
                            <div class="p-3 bg-emerald-50/70 border border-emerald-200 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <img :src="createImagePreview" class="w-14 h-14 object-cover border border-emerald-300 rounded-xs bg-white shadow-2xs">
                                    <div>
                                        <p class="text-xs font-bold text-slate-900 truncate max-w-[200px]" x-text="createImageName"></p>
                                        <span class="text-[10px] font-mono text-emerald-700 font-bold" x-text="createImageSize"></span>
                                    </div>
                                </div>
                                <button type="button" @click="clearCreateImage()" class="px-2 py-1 text-xs font-bold text-rose-600 hover:bg-rose-50 border border-rose-200">
                                    Remove
                                </button>
                            </div>
                        </template>

                        <input type="file" 
                               name="image" 
                               id="createProductImageInput" 
                               accept="image/*" 
                               @change="handleCreateImage($event)"
                               class="w-full text-xs p-2 bg-slate-50 border border-slate-300 file:mr-3 file:py-1 file:px-3 file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700">
                    </div>

                    <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-200">
                        <button type="button" 
                                @click="createModalOpen = false" 
                                :disabled="isSubmitting"
                                class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                :disabled="isSubmitting"
                                class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black">
                            <span x-text="isSubmitting ? 'Saving Product...' : 'Save Product'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ============================================================= -->
    <!-- EDIT PRODUCT MODAL (WITH DYNAMIC MAIN & SUB CATEGORY LOGIC)   -->
    <!-- ============================================================= -->
    <div x-show="editModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-xs" @click="if(!isSubmitting) editModalOpen = false"></div>

            <div class="inline-block w-full max-w-xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl border border-slate-300 space-y-4">
                
                <div x-show="isSubmitting" class="h-1.5 w-full bg-slate-100 progress-bar-loading -mt-6 -mx-6 mb-3"></div>

                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <div>
                        <h3 class="text-base font-black text-slate-900">Edit Inventory Product</h3>
                        <p class="text-xs text-slate-500">Update category taxonomy, prices, and stock units.</p>
                    </div>
                    <button type="button" @click="if(!isSubmitting) editModalOpen = false" :disabled="isSubmitting" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <form :action="'{{ url('manager/products') }}/' + editProduct.id" 
                      method="POST" 
                      enctype="multipart/form-data" 
                      @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true"
                      class="space-y-4">
                    @csrf
                    @method('PUT')

                    <!-- 1. MAIN CATEGORY -->
                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">
                            Main Category <span class="text-rose-600">*</span>
                        </label>
                        <select name="product_main_category_id" 
                                x-model="editProduct.product_main_category_id" 
                                required 
                                class="w-full text-xs font-bold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                            <option value="">-- Select Main Category --</option>
                            <template x-for="mc in mainCategories" :key="mc.id">
                                <option :value="mc.id" x-text="'🏛️ ' + mc.title"></option>
                            </template>
                        </select>
                    </div>

                    <!-- 2. SUB CATEGORY (FILTERED) -->
                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">
                            Sub Category
                        </label>
                        <select name="product_category_id" 
                                x-model="editProduct.product_category_id" 
                                class="w-full text-xs font-bold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                            <option value="">-- No Sub Category / General --</option>
                            <template x-for="sc in getSubCategoriesFor(editProduct.product_main_category_id)" :key="sc.id">
                                <option :value="sc.id" x-text="'🏷️ ' + sc.title"></option>
                            </template>
                        </select>
                    </div>

                    <!-- 3. TITLE -->
                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Product Title <span class="text-rose-600">*</span></label>
                        <input type="text" 
                               name="title" 
                               x-model="editProduct.title" 
                               required 
                               class="w-full text-xs font-semibold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                    </div>

                    <!-- 4. PRICING -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-black uppercase text-slate-700 mb-1">Original Price (PKR) <span class="text-rose-600">*</span></label>
                            <input type="number" 
                                   step="0.01" 
                                   min="0" 
                                   name="price" 
                                   x-model="editProduct.price" 
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
                                   x-model="editProduct.discount" 
                                   class="w-full text-xs font-mono font-bold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-black uppercase text-slate-700 mb-1">Selling Price</label>
                            <input type="number" 
                                   step="0.01" 
                                   name="discounted_price" 
                                   :value="calculateDiscount(editProduct.price, editProduct.discount)" 
                                   class="w-full text-xs font-mono font-black text-emerald-700 p-2.5 bg-emerald-50/50 border border-emerald-300 focus:ring-0" 
                                   readonly>
                        </div>

                        <div>
                            <label class="block text-xs font-black uppercase text-slate-700 mb-1">Stock Units <span class="text-rose-600">*</span></label>
                            <input type="number" 
                                   min="0" 
                                   name="stock" 
                                   x-model="editProduct.stock" 
                                   required 
                                   class="w-full text-xs font-mono font-bold text-slate-900 p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-emerald-600 focus:ring-0">
                        </div>

                        <div>
                            <label class="block text-xs font-black uppercase text-slate-700 mb-1">Low Stock Limit</label>
                            <input type="number" 
                                   min="0" 
                                   name="low_stock" 
                                   x-model="editProduct.low_stock" 
                                   required 
                                   class="w-full text-xs font-mono font-bold text-amber-900 p-2.5 bg-amber-50/40 border border-amber-300 focus:ring-0">
                        </div>
                    </div>

                    <!-- 5. IMAGE -->
                    <div class="space-y-2">
                        <label class="block text-xs font-black uppercase text-slate-700">
                            Change Image <span class="text-[10px] text-slate-400 font-normal lowercase">(optional — max 10MB)</span>
                        </label>

                        <div class="flex items-center gap-3">
                            <template x-if="editImagePreview">
                                <img :src="editImagePreview" class="w-12 h-12 object-cover border">
                            </template>
                            <template x-if="!editImagePreview && editProduct.image">
                                <img :src="editProduct.image" class="w-12 h-12 object-cover border">
                            </template>
                            <input type="file" 
                                   name="image" 
                                   id="editProductImageInput" 
                                   accept="image/*" 
                                   @change="handleEditImage($event)"
                                   class="w-full text-xs p-2 bg-slate-50 border border-slate-300 file:mr-3 file:py-1 file:px-3 file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700">
                        </div>
                    </div>

                    <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-200">
                        <button type="button" 
                                @click="editModalOpen = false" 
                                :disabled="isSubmitting"
                                class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                :disabled="isSubmitting"
                                class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black">
                            <span x-text="isSubmitting ? 'Updating Product...' : 'Update Product'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
