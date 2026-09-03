@extends('layouts.material')

@section('title', 'Store Locations & Multi-Store Inventory')

@section('content')
<div x-data="{ 
    createModalOpen: false, 
    editModalOpen: false,
    inventoryModalOpen: false,
    selectedStore: null,
    allProducts: {{ json_encode($allProducts) }},
    editStore: { id: null, name: '', code: '', address: '', phone: '', is_active: true, is_default: false },
    storeStocksMap: {},

    openEdit(s) {
        this.editStore = { 
            id: s.id, 
            name: s.name, 
            code: s.code, 
            address: s.address || '', 
            phone: s.phone || '', 
            is_active: s.is_active ? true : false, 
            is_default: s.is_default ? true : false 
        };
        this.editModalOpen = true;
    },

    openManageInventory(s) {
        this.selectedStore = s;
        this.storeStocksMap = {};
        this.allProducts.forEach(p => {
            const found = (s.product_stocks || []).find(ps => ps.product_id == p.id);
            this.storeStocksMap[p.id] = found ? parseInt(found.stock) : 0;
        });
        this.inventoryModalOpen = true;
    },

    setAllStoreStockToZero() {
        if (confirm('Set all product stock quantities to 0 in this modal? Click Save Changes after to persist.')) {
            for (let pId in this.storeStocksMap) {
                this.storeStocksMap[pId] = 0;
            }
        }
    }
}" class="space-y-6">

    <!-- Page Header & Action Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="p-2.5 bg-indigo-50 text-indigo-600">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Store Locations & Inventory Control</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">Manage multi-store outlets, warehouses, stock levels, and zero-stock resets.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('manager.stock-transfers.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs border border-slate-300 transition-colors">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                <span>Stock Transfers</span>
            </a>

            <button @click="createModalOpen = true" 
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>Add Store / Branch</span>
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

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-5 border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Store Outlets</span>
                <h3 class="text-2xl font-black text-slate-900 font-mono mt-0.5">{{ $totalStores }}</h3>
                <p class="text-[10px] text-slate-500 font-medium">Configured salon branches & warehouses</p>
            </div>
            <div class="w-12 h-12 bg-indigo-50 text-indigo-600 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            </div>
        </div>

        <div class="bg-white p-5 border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Stocked Units</span>
                <h3 class="text-2xl font-black text-emerald-700 font-mono mt-0.5">{{ number_format($totalSystemUnits) }}</h3>
                <p class="text-[10px] text-slate-500 font-medium">Sum of all products across all stores</p>
            </div>
            <div class="w-12 h-12 bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
            </div>
        </div>

        <div class="bg-white p-5 border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Inventory Valuation</span>
                <h3 class="text-2xl font-black text-indigo-700 font-mono mt-0.5">PKR {{ number_format($totalRetailValuation, 2) }}</h3>
                <p class="text-[10px] text-slate-500 font-medium">Estimated retail value of inventory</p>
            </div>
            <div class="w-12 h-12 bg-indigo-50 text-indigo-600 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>
    </div>

    <!-- Stores Table Card -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <h2 class="text-xs font-black uppercase tracking-wider text-slate-700">All Registered Stores & Branches</h2>
            <form method="GET" action="{{ route('manager.stores.index') }}" class="flex items-center gap-2">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by name, code, phone..." 
                       class="text-xs font-medium px-3 py-1.5 bg-white border border-slate-300 focus:border-indigo-600 focus:ring-0">
                <button type="submit" class="px-3 py-1.5 bg-slate-900 text-white font-bold text-xs">Search</button>
                @if($search)
                    <a href="{{ route('manager.stores.index') }}" class="px-2.5 py-1.5 bg-slate-200 text-slate-700 text-xs font-bold">Reset</a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-100 border-b border-slate-200 text-slate-600 text-[10px] font-black uppercase tracking-wider">
                        <th class="py-3.5 px-4">Store Name & Code</th>
                        <th class="py-3.5 px-4">Address / Contact</th>
                        <th class="py-3.5 px-4 text-center">Stocked Items</th>
                        <th class="py-3.5 px-4 text-center">Total Units</th>
                        <th class="py-3.5 px-4 text-right">Retail Valuation</th>
                        <th class="py-3.5 px-4 text-center">Branch Status</th>
                        <th class="py-3.5 px-4 text-right">Inventory & Store Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-800">
                    @forelse($stores as $store)
                    <tr class="hover:bg-slate-50/70 transition-colors {{ $store->is_default ? 'bg-indigo-50/20' : '' }}">
                        <td class="py-4 px-4">
                            <div class="flex items-center gap-2">
                                <span class="font-black text-slate-900 text-sm">{{ $store->name }}</span>
                                <span class="px-1.5 py-0.5 bg-slate-200 text-slate-800 font-mono font-bold text-[9px]">
                                    {{ $store->code }}
                                </span>
                                @if($store->is_default)
                                    <span class="px-2 py-0.5 bg-indigo-600 text-white font-extrabold text-[9px] uppercase tracking-wider">
                                        Primary Default
                                    </span>
                                @endif
                            </div>
                        </td>

                        <td class="py-4 px-4 text-slate-600">
                            <div>{{ $store->address ?: 'No address specified' }}</div>
                            @if($store->phone)
                                <div class="text-[10px] font-mono text-slate-400 font-semibold">{{ $store->phone }}</div>
                            @endif
                        </td>

                        <td class="py-4 px-4 text-center font-mono font-bold text-slate-700">
                            {{ $store->total_products_count }} / {{ $allProducts->count() }} Products
                        </td>

                        <td class="py-4 px-4 text-center font-mono font-black text-emerald-700 text-sm">
                            {{ number_format($store->total_units) }}
                            @if($store->low_stock_count > 0)
                                <span class="block text-[9px] font-bold text-amber-600">({{ $store->low_stock_count }} Low)</span>
                            @endif
                        </td>

                        <td class="py-4 px-4 text-right font-mono font-black text-indigo-700">
                            PKR {{ number_format($store->inventory_retail_value, 2) }}
                        </td>

                        <td class="py-4 px-4 text-center">
                            @if($store->is_active)
                                <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 font-black text-[10px]">Active</span>
                            @else
                                <span class="px-2 py-0.5 bg-slate-200 text-slate-600 font-black text-[10px]">Inactive</span>
                            @endif
                        </td>

                        <td class="py-4 px-4 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <!-- Manage Store Inventory Button -->
                                <button type="button" 
                                        @click="openManageInventory({{ json_encode($store) }})" 
                                        class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-[11px] border border-indigo-200 flex items-center gap-1 transition-colors"
                                        title="View & Adjust Product Stock in this Store">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                    <span>Stock</span>
                                </button>

                                <!-- Make Default Button -->
                                @if(!$store->is_default)
                                <form action="{{ route('manager.stores.default', $store->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" 
                                            class="px-2 py-1 bg-slate-100 hover:bg-indigo-50 text-slate-700 hover:text-indigo-700 font-bold text-[10px] border border-slate-200" 
                                            title="Set as Default Store">
                                        Default
                                    </button>
                                </form>
                                @endif

                                <!-- Edit Store Details Button -->
                                <button type="button" 
                                        @click="openEdit({{ json_encode($store) }})" 
                                        class="p-1.5 text-slate-600 hover:text-indigo-600 hover:bg-slate-100 transition-colors" 
                                        title="Edit Store Details">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </button>

                                <!-- Delete Store Button -->
                                @if(!$store->is_default)
                                <form action="{{ route('manager.stores.destroy', $store->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete store {{ $store->name }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors" title="Delete Store">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-slate-400 font-semibold">
                            No store locations found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL: MANAGE / ADJUST STORE INVENTORY -->
    <div x-show="inventoryModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-xs" @click="inventoryModalOpen = false"></div>
            <div class="inline-block w-full max-w-2xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl border border-slate-300 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <div>
                        <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                            <span>📦 Manage Store Inventory:</span>
                            <span class="text-indigo-600" x-text="selectedStore ? selectedStore.name : ''"></span>
                        </h3>
                        <p class="text-xs text-slate-500">View and adjust opening stock balances for all products in this specific store.</p>
                    </div>
                    <button type="button" @click="inventoryModalOpen = false" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>

                <form :action="'/manager/stores/' + (selectedStore ? selectedStore.id : '') + '/inventory'" method="POST" class="space-y-4">
                    @csrf

                    <!-- Quick Toolbar -->
                    <div class="flex items-center justify-between bg-slate-50 p-2.5 border border-slate-200 text-xs">
                        <span class="font-bold text-slate-600">Product List (<span x-text="allProducts.length"></span> items)</span>
                        <button type="button" @click="setAllStoreStockToZero()" class="px-2.5 py-1 bg-amber-100 hover:bg-amber-200 text-amber-900 font-bold text-[10px] transition-colors">
                            Set All in this Store to 0
                        </button>
                    </div>

                    <!-- Products Stock Table -->
                    <div class="border border-slate-200 overflow-hidden max-h-96 overflow-y-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="bg-slate-100 sticky top-0 border-b border-slate-200 text-slate-700 text-[10px] font-black uppercase">
                                <tr>
                                    <th class="py-2.5 px-3">Product Title</th>
                                    <th class="py-2.5 px-3 text-right">Retail Price</th>
                                    <th class="py-2.5 px-3 text-right w-36">Store Stock Qty</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-800">
                                <template x-for="p in allProducts" :key="p.id">
                                    <tr class="hover:bg-slate-50">
                                        <td class="py-2 px-3 font-extrabold text-slate-900" x-text="p.title"></td>
                                        <td class="py-2 px-3 text-right font-mono font-bold text-slate-600" x-text="'PKR ' + (p.discounted_price || p.price)"></td>
                                        <td class="py-2 px-3 text-right">
                                            <input type="number" 
                                                   min="0" 
                                                   :name="'stocks[' + p.id + ']'" 
                                                   x-model.number="storeStocksMap[p.id]" 
                                                   class="w-24 text-xs font-mono font-bold text-right p-1.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-indigo-600 focus:ring-0">
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                        <button type="button" @click="inventoryModalOpen = false" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-bold">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-indigo-600 text-white text-xs font-black hover:bg-indigo-700">Save Stock Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ADD STORE MODAL -->
    <div x-show="createModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-xs" @click="createModalOpen = false"></div>
            <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl border border-slate-300 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <h3 class="text-base font-black text-slate-900">Add New Store Location / Branch</h3>
                    <button type="button" @click="createModalOpen = false" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>

                <form action="{{ route('manager.stores.store') }}" method="POST" class="space-y-3.5">
                    @csrf
                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Store / Branch Name <span class="text-rose-600">*</span></label>
                        <input type="text" name="name" required placeholder="e.g. City Center Branch, Warehouse A" 
                               class="w-full text-xs font-semibold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-indigo-600 focus:ring-0">
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Store Code <span class="text-rose-600">*</span></label>
                        <input type="text" name="code" required placeholder="e.g. CC-01, WH-01" 
                               class="w-full text-xs font-mono font-bold uppercase p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-indigo-600 focus:ring-0">
                        <span class="text-[10px] text-slate-400 font-medium">Short unique identification code</span>
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Address / Location</label>
                        <input type="text" name="address" placeholder="Physical street address or floor" 
                               class="w-full text-xs font-semibold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-indigo-600 focus:ring-0">
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Contact Phone</label>
                        <input type="text" name="phone" placeholder="e.g. +92 300 0000000" 
                               class="w-full text-xs font-semibold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-indigo-600 focus:ring-0">
                    </div>

                    <div class="flex items-center gap-4 pt-1">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" checked class="w-4 h-4 text-indigo-600 rounded-none border-slate-300">
                            <span class="text-xs font-bold text-slate-700">Active Location</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_default" value="1" class="w-4 h-4 text-indigo-600 rounded-none border-slate-300">
                            <span class="text-xs font-bold text-slate-700">Make Default Store</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                        <button type="button" @click="createModalOpen = false" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-bold">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-indigo-600 text-white text-xs font-black hover:bg-indigo-700">Save Store</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- EDIT STORE MODAL -->
    <div x-show="editModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-xs" @click="editModalOpen = false"></div>
            <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl border border-slate-300 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <h3 class="text-base font-black text-slate-900">Edit Store Location</h3>
                    <button type="button" @click="editModalOpen = false" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>

                <form :action="'/manager/stores/' + editStore.id" method="POST" class="space-y-3.5">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Store / Branch Name <span class="text-rose-600">*</span></label>
                        <input type="text" name="name" x-model="editStore.name" required 
                               class="w-full text-xs font-semibold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-indigo-600 focus:ring-0">
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Store Code <span class="text-rose-600">*</span></label>
                        <input type="text" name="code" x-model="editStore.code" required 
                               class="w-full text-xs font-mono font-bold uppercase p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-indigo-600 focus:ring-0">
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Address / Location</label>
                        <input type="text" name="address" x-model="editStore.address" 
                               class="w-full text-xs font-semibold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-indigo-600 focus:ring-0">
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Contact Phone</label>
                        <input type="text" name="phone" x-model="editStore.phone" 
                               class="w-full text-xs font-semibold p-2.5 bg-slate-50 border border-slate-300 focus:bg-white focus:border-indigo-600 focus:ring-0">
                    </div>

                    <div class="flex items-center gap-4 pt-1">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" x-model="editStore.is_active" class="w-4 h-4 text-indigo-600 rounded-none border-slate-300">
                            <span class="text-xs font-bold text-slate-700">Active Location</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_default" value="1" x-model="editStore.is_default" class="w-4 h-4 text-indigo-600 rounded-none border-slate-300">
                            <span class="text-xs font-bold text-slate-700">Default Store</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                        <button type="button" @click="editModalOpen = false" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-bold">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-indigo-600 text-white text-xs font-black hover:bg-indigo-700">Update Store</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
