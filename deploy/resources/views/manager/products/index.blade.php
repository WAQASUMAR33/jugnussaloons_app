@extends('layouts.material')

@section('title', 'Product Inventory Management')

@section('content')
<div x-data="{ 
    createModalOpen: false,
    editModalOpen: false,
    createModalOpen: false,
    editModalOpen: false,
    editProduct: { id: null, title: '', price: 0, discount: 0, discounted_price: 0, stock: 0, low_stock: 5, image: null },
    calculateDiscount(price, discount) {
        if (!price || price <= 0) return 0;
        if (!discount || discount <= 0) return price;
        return (price - (price * (discount / 100))).toFixed(2);
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
                <p class="text-xs font-semibold text-slate-500 mt-0.5">Manage hair care products, styling waxes, shampoos, stock levels, and pricing.</p>
            </div>
        </div>

        <button @click="createModalOpen = true" 
                class="inline-flex items-center gap-2 px-5 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            <span>Add New Product</span>
        </button>
    </div>

    <!-- Short Stock Summary Alert Banner -->
    @if(isset($lowStockCount) && $lowStockCount > 0)
        <div class="p-4 bg-amber-50 border border-amber-300 text-amber-900 text-xs font-bold flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <span>⚠️ Short Stock Warning: <strong>{{ $lowStockCount }}</strong> {{ Str::plural('product', $lowStockCount) }} in inventory are at or below their configured short stock threshold.</span>
            </div>
            <span class="px-2.5 py-1 bg-amber-200/80 text-amber-900 font-extrabold text-[10px] uppercase rounded">Restock Required</span>
        </div>
    @endif

    <!-- Filter & Search Bar -->
    <div class="bg-white p-4 border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <form method="GET" action="{{ route('manager.products.index') }}" class="flex-1 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search product by title..." 
                       class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-emerald-600 focus:bg-white transition-all">
            </div>

            <button type="submit" class="px-5 py-2.5 bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors">
                Search Inventory
            </button>
            @if($search)
                <a href="{{ route('manager.products.index') }}" class="px-4 py-2.5 bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200 flex items-center justify-center">
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
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[11px] font-extrabold uppercase tracking-wider">
                        <th class="py-4 px-6">ID</th>
                        <th class="py-4 px-6">Image</th>
                        <th class="py-4 px-6">Product Title</th>
                        <th class="py-4 px-6">Original Price</th>
                        <th class="py-4 px-6">Discount</th>
                        <th class="py-4 px-6">Final Price</th>
                        <th class="py-4 px-6">Stock Status</th>
                        <th class="py-4 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($products as $product)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="py-4 px-6 font-bold text-xs text-slate-400">#{{ $product->id }}</td>
                        <td class="py-4 px-6">
                            @if($product->image)
                                <img src="{{ asset($product->image) }}" alt="{{ $product->title }}" class="w-12 h-12 object-cover border border-slate-200 shadow-sm">
                            @else
                                <div class="w-12 h-12 bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-400">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                </div>
                            @endif
                        </td>
                        <td class="py-4 px-6">
                            <p class="font-bold text-slate-900 leading-tight">{{ $product->title }}</p>
                        </td>
                        <td class="py-4 px-6 font-bold text-slate-800">
                            {{ number_format($product->price, 2) }}
                        </td>
                        <td class="py-4 px-6">
                            @if($product->discount > 0)
                                <span class="px-2.5 py-1 bg-amber-100 text-amber-800 text-xs font-bold">
                                    {{ $product->discount }}% OFF
                                </span>
                            @else
                                <span class="text-xs text-slate-400 font-medium">None</span>
                            @endif
                        </td>
                        <td class="py-4 px-6 font-extrabold text-emerald-600">
                            {{ number_format($product->discounted_price, 2) }}
                        </td>
                        <td class="py-4 px-6">
                            @if($product->stock <= 0)
                                <span class="px-2.5 py-1 bg-rose-100 text-rose-800 text-xs font-bold flex items-center gap-1 w-fit">
                                    🔴 Out of Stock (0)
                                </span>
                            @elseif($product->stock <= ($product->low_stock ?? 5))
                                <span class="px-2.5 py-1 bg-amber-100 text-amber-900 border border-amber-300 text-xs font-black flex items-center gap-1 w-fit">
                                    ⚠️ Short Stock ({{ $product->stock }} / Limit: {{ $product->low_stock ?? 5 }})
                                </span>
                            @else
                                <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 text-xs font-bold flex items-center gap-1 w-fit">
                                    🟢 In Stock ({{ $product->stock }})
                                </span>
                            @endif
                        </td>
                        <td class="py-4 px-6 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button @click="editProduct = { 
                                            id: {{ $product->id }}, 
                                            title: '{{ addslashes($product->title) }}', 
                                            price: {{ $product->price }}, 
                                            discount: {{ $product->discount }}, 
                                            discounted_price: {{ $product->discounted_price }}, 
                                            stock: {{ $product->stock }}, 
                                            low_stock: {{ $product->low_stock ?? 5 }},
                                            image: '{{ $product->image ? asset($product->image) : '' }}' 
                                        }; editModalOpen = true" 
                                        class="p-2 text-slate-600 hover:text-emerald-600 hover:bg-emerald-50 transition-colors" title="Edit Product">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </button>

                                <form method="POST" action="{{ route('manager.products.destroy', $product) }}" onsubmit="return confirm('Delete product {{ addslashes($product->title) }}?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 text-slate-600 hover:text-red-600 hover:bg-red-50 transition-colors" title="Delete Product">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-12 text-center text-slate-400">
                            <p class="text-sm font-semibold">No products in inventory yet.</p>
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

    <!-- MODAL: CREATE PRODUCT -->
    <div x-show="createModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="createModalOpen = false" class="bg-white max-w-xl w-full p-6 shadow-2xl space-y-6 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    Add Product to Inventory
                </h3>
                <button @click="createModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('manager.products.store') }}" enctype="multipart/form-data" class="space-y-4" x-data="{ newPrice: 0, newDiscount: 0 }">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Product Title</label>
                    <input type="text" name="title" required placeholder="e.g. Premium Matte Styling Clay 100g" 
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-emerald-600">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Price</label>
                        <input type="number" step="0.01" min="0" name="price" x-model.number="newPrice" required placeholder="24.99" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-emerald-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Discount (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="discount" x-model.number="newDiscount" placeholder="15" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-emerald-600">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Discounted Price</label>
                        <input type="number" step="0.01" min="0" name="discounted_price" :value="calculateDiscount(newPrice, newDiscount)" 
                               class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 text-sm font-bold text-emerald-600 focus:ring-2 focus:ring-emerald-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Stock Qty</label>
                        <input type="number" min="0" name="stock" required placeholder="50" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-emerald-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Low Stock Limit</label>
                        <input type="number" min="0" name="low_stock" value="5" required placeholder="5" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-amber-300 text-sm font-bold text-amber-800 focus:ring-2 focus:ring-emerald-600" title="Short stock warning alert threshold">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Product Image (Upload to Server)</label>
                    <input type="file" name="image" accept="image/*" 
                           class="w-full px-4 py-2 bg-slate-50 border border-slate-200 text-xs font-medium focus:ring-2 focus:ring-emerald-600">
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="createModalOpen = false" class="px-4 py-2.5 text-slate-600 font-bold text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 bg-emerald-600 text-white font-bold text-xs shadow-md">
                        Save Product
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: EDIT PRODUCT -->
    <div x-show="editModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="editModalOpen = false" class="bg-white max-w-xl w-full p-6 shadow-2xl space-y-6 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Edit Product Details
                </h3>
                <button @click="editModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form :action="'{{ url('manager/products') }}/' + editProduct.id" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Product Title</label>
                    <input type="text" name="title" x-model="editProduct.title" required 
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-emerald-600">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Price</label>
                        <input type="number" step="0.01" min="0" name="price" x-model.number="editProduct.price" required 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-emerald-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Discount (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="discount" x-model.number="editProduct.discount" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-emerald-600">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Discounted Price</label>
                        <input type="number" step="0.01" min="0" name="discounted_price" :value="calculateDiscount(editProduct.price, editProduct.discount)" 
                               class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 text-sm font-bold text-emerald-600 focus:ring-2 focus:ring-emerald-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Stock Qty</label>
                        <input type="number" min="0" name="stock" x-model.number="editProduct.stock" required 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-emerald-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Low Stock Limit</label>
                        <input type="number" min="0" name="low_stock" x-model.number="editProduct.low_stock" required 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-amber-300 text-sm font-bold text-amber-800 focus:ring-2 focus:ring-emerald-600" title="Short stock warning alert threshold">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Change Image (Upload New Image)</label>
                    <template x-if="editProduct.image">
                        <div class="mb-2 flex items-center gap-3 p-2 bg-slate-50 border border-slate-200">
                            <img :src="editProduct.image" class="w-10 h-10 object-cover border">
                            <span class="text-xs text-slate-500 font-medium">Current Image Saved</span>
                        </div>
                    </template>
                    <input type="file" name="image" accept="image/*" 
                           class="w-full px-4 py-2 bg-slate-50 border border-slate-200 text-xs font-medium focus:ring-2 focus:ring-emerald-600">
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="editModalOpen = false" class="px-4 py-2.5 text-slate-600 font-bold text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 bg-emerald-600 text-white font-bold text-xs shadow-md">
                        Update Product
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
