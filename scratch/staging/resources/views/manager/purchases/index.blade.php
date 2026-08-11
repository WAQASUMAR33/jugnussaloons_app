@extends('layouts.material')

@section('title', 'Purchase Management')

@section('content')
<div x-data="{ 
    createModalOpen: false,
    viewModalOpen: false,
    selectedPurchase: null,
    suppliersList: {{ json_encode($suppliers) }},
    selectedSupplierId: '',
    supplierSearchTerm: '',
    supplierDropdownOpen: false,
    items: [
        { product_id: '', search_term: '', open: false, quantity: 1, unit_price: 0, sale_price: 0, subtotal: 0 }
    ],
    paidAmount: 0,
    
    getFilteredSuppliersList() {
        if (!this.supplierSearchTerm || this.supplierSearchTerm.trim() === '') return this.suppliersList;
        const term = this.supplierSearchTerm.toLowerCase();
        return this.suppliersList.filter(s => 
            s.name.toLowerCase().includes(term) || 
            (s.phone_no1 && s.phone_no1.includes(term))
        );
    },
    
    selectSupplier(supplier) {
        this.selectedSupplierId = supplier.id;
        this.supplierSearchTerm = supplier.name;
        this.supplierDropdownOpen = false;
    },
    addItem() {
        this.items.push({ product_id: '', search_term: '', open: false, quantity: 1, unit_price: 0, sale_price: 0, subtotal: 0 });
    },
    removeItem(index) {
        if (this.items.length > 1) {
            this.items.splice(index, 1);
        }
    },
    selectProduct(index, prod) {
        this.items[index].product_id = prod.id;
        this.items[index].search_term = prod.title;
        this.items[index].unit_price = prod.price;
        this.items[index].sale_price = prod.price;
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
    getTotalAmount() {
        return this.items.reduce((sum, item) => sum + (parseFloat(item.subtotal) || 0), 0).toFixed(2);
    },
    getBalanceDue() {
        const total = parseFloat(this.getTotalAmount()) || 0;
        const paid = parseFloat(this.paidAmount) || 0;
        return (total - paid).toFixed(2);
    }
}" class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="p-2.5 bg-amber-50 text-amber-600">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Purchase Management</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">Record stock purchases from suppliers, create ledger entries, and update accounts balance.</p>
            </div>
        </div>

        <button @click="createModalOpen = true" 
                class="inline-flex items-center gap-2 px-5 py-3 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs shadow-sm transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            <span>Record New Purchase</span>
        </button>
    </div>

    <!-- Search & Filter -->
    <div class="bg-white p-4 border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <form method="GET" action="{{ route('manager.purchases.index') }}" class="flex-1 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by invoice number or supplier name..." 
                       class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-amber-600 focus:bg-white transition-all">
            </div>

            <button type="submit" class="px-5 py-2.5 bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors">
                Search Purchases
            </button>
            @if($search)
                <a href="{{ route('manager.purchases.index') }}" class="px-4 py-2.5 bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200 flex items-center justify-center">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Purchases Table Card -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[11px] font-extrabold uppercase tracking-wider">
                        <th class="py-4 px-6">Invoice #</th>
                        <th class="py-4 px-6">Supplier Account</th>
                        <th class="py-4 px-6">Purchase Date</th>
                        <th class="py-4 px-6">Total Amount</th>
                        <th class="py-4 px-6">Paid Amount</th>
                        <th class="py-4 px-6">Balance Due</th>
                        <th class="py-4 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($purchases as $purchase)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="py-4 px-6 font-mono font-bold text-xs text-amber-700">
                            {{ $purchase->invoice_no }}
                        </td>
                        <td class="py-4 px-6">
                            <p class="font-bold text-slate-900 leading-tight">{{ $purchase->supplier->name ?? 'Unknown Supplier' }}</p>
                            <span class="text-[10px] font-bold text-slate-400">
                                {{ $purchase->supplier->category->title ?? 'Supplier' }}
                            </span>
                        </td>
                        <td class="py-4 px-6 text-xs text-slate-600 font-medium">
                            {{ $purchase->purchase_date->format('M d, Y') }}
                        </td>
                        <td class="py-4 px-6 font-extrabold text-slate-900">
                            {{ number_format($purchase->total_amount, 2) }}
                        </td>
                        <td class="py-4 px-6 font-bold text-emerald-600">
                            {{ number_format($purchase->paid_amount, 2) }}
                        </td>
                        <td class="py-4 px-6 font-bold {{ $purchase->balance_due > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                            {{ number_format($purchase->balance_due, 2) }}
                        </td>
                        <td class="py-4 px-6 text-right">
                            <button @click="selectedPurchase = {{ json_encode($purchase) }}; viewModalOpen = true" 
                                    class="px-3 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-800 font-bold text-xs">
                                View Items ({{ $purchase->items->count() }})
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-12 text-center text-slate-400">
                            <p class="text-sm font-semibold">No purchase records found.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-slate-100">
            {{ $purchases->links() }}
        </div>
    </div>

    <!-- MODAL: RECORD NEW PURCHASE -->
    <div x-show="createModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="createModalOpen = false" class="bg-white max-w-3xl w-full p-6 shadow-2xl space-y-6 max-h-[92vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                    Record Supplier Purchase
                </h3>
                <button @click="createModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('manager.purchases.store') }}" class="space-y-4">
                @csrf
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="relative" @click.outside="supplierDropdownOpen = false">
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Select Supplier Account</label>
                        <input type="hidden" name="account_id" :value="selectedSupplierId" required>
                        
                        <div class="relative">
                            <input type="text" 
                                   x-model="supplierSearchTerm" 
                                   @focus="supplierDropdownOpen = true" 
                                   @input="supplierDropdownOpen = true; selectedSupplierId = ''" 
                                   placeholder="🔍 Search supplier name..." 
                                   required
                                   class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-amber-600">
                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </span>
                        </div>

                        <!-- Filterable Supplier Dropdown List -->
                        <div x-show="supplierDropdownOpen" 
                             class="absolute z-30 left-0 right-0 mt-1 bg-white border border-slate-200 shadow-xl max-h-56 overflow-y-auto divide-y divide-slate-100"
                             x-cloak>
                            <template x-for="sup in getFilteredSuppliersList()" :key="sup.id">
                                <div @click="selectSupplier(sup)" 
                                     class="p-2.5 hover:bg-amber-50 cursor-pointer flex items-center justify-between transition-colors text-xs">
                                    <div>
                                        <p class="font-bold text-slate-900" x-text="sup.name"></p>
                                        <p class="text-[10px] text-slate-400" x-text="'Balance: ' + sup.balance"></p>
                                    </div>
                                    <span class="px-2 py-0.5 bg-slate-100 font-extrabold text-[10px] text-slate-600 rounded" x-text="sup.category ? sup.category.title : 'General'"></span>
                                </div>
                            </template>
                            <div x-show="getFilteredSuppliersList().length === 0" class="p-3 text-center text-xs text-slate-400 font-medium">
                                No matching suppliers found
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Purchase Date</label>
                        <input type="date" name="purchase_date" value="{{ date('Y-m-d') }}" required 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-amber-600">
                    </div>
                </div>

                <!-- Item Lines Header -->
                <div class="pt-2">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase">Purchase Line Items</label>
                        <button type="button" @click="addItem()" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs flex items-center gap-1">
                            + Add Item Line
                        </button>
                    </div>

                    <!-- Column Header Bar for Purchase Line Items -->
                    <div class="hidden sm:flex items-center gap-3 px-3 py-2 bg-slate-100 border border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-600 mb-2">
                        <div class="flex-1">Product Name</div>
                        <div class="w-24">Qty</div>
                        <div class="w-28">Purchase Rate</div>
                        <div class="w-28">Sale Rate</div>
                        <div class="w-28 text-right">Subtotal</div>
                        <div class="w-8 text-center">Action</div>
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
                                               class="w-full px-3 py-2 pr-8 bg-white border border-slate-200 text-xs font-semibold focus:ring-2 focus:ring-amber-600">
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
                                                 class="p-2.5 hover:bg-amber-50 cursor-pointer flex items-center justify-between transition-colors text-xs">
                                                <div>
                                                    <p class="font-bold text-slate-900" x-text="prod.title"></p>
                                                    <p class="text-[10px] text-slate-400">Current Stock: <span class="font-bold text-slate-700" x-text="prod.stock"></span></p>
                                                </div>
                                                <span class="font-extrabold text-amber-700" x-text="prod.price"></span>
                                            </div>
                                        </template>
                                        <div x-show="getFilteredProducts(item.search_term).length === 0" class="p-3 text-center text-xs text-slate-400 font-medium">
                                            No matching products found
                                        </div>
                                    </div>
                                </div>

                                <div class="w-full sm:w-24">
                                    <label class="block text-[10px] font-extrabold uppercase text-slate-500 mb-0.5 sm:hidden">Quantity</label>
                                    <input type="number" min="1" :name="'items['+index+'][quantity]'" x-model.number="item.quantity" @input="calculateSubtotal(index)" required placeholder="Qty" 
                                           class="w-full px-3 py-2 bg-white border border-slate-200 text-xs font-bold focus:ring-2 focus:ring-amber-600">
                                </div>

                                <div class="w-full sm:w-28">
                                    <label class="block text-[10px] font-extrabold uppercase text-slate-500 mb-0.5 sm:hidden">Purchase Price</label>
                                    <input type="number" step="0.01" min="0" :name="'items['+index+'][unit_price]'" x-model.number="item.unit_price" @input="calculateSubtotal(index)" required placeholder="Purchase Cost" 
                                           class="w-full px-3 py-2 bg-white border border-slate-200 text-xs font-bold focus:ring-2 focus:ring-amber-600" title="Purchase cost rate">
                                </div>

                                <div class="w-full sm:w-28">
                                    <label class="block text-[10px] font-extrabold uppercase text-slate-500 mb-0.5 sm:hidden">Sale Rate</label>
                                    <input type="number" step="0.01" min="0" :name="'items['+index+'][sale_price]'" x-model.number="item.sale_price" placeholder="Sale Rate" 
                                           class="w-full px-3 py-2 bg-white border border-emerald-300 text-xs font-bold text-emerald-700 focus:ring-2 focus:ring-emerald-600" title="Updates product retail sale price">
                                </div>

                                <div class="w-full sm:w-28 text-right font-extrabold text-xs text-slate-800">
                                    <span x-text="item.subtotal"></span>
                                </div>

                                <button type="button" @click="removeItem(index)" class="p-2 text-rose-500 hover:bg-rose-50">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Payment Summary Grid -->
                <div class="p-4 bg-amber-50/50 border border-amber-200/80 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Total Bill Amount</label>
                        <div class="text-xl font-black text-slate-900" x-text="getTotalAmount()"></div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Payment Made</label>
                        <input type="number" step="0.01" min="0" name="paid_amount" x-model.number="paidAmount" required placeholder="0.00" 
                               class="w-full px-4 py-2 bg-white border border-slate-300 text-sm font-bold text-emerald-700 focus:ring-2 focus:ring-amber-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Balance Due</label>
                        <div class="text-xl font-black text-rose-700" x-text="getBalanceDue()"></div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Notes / Description (Optional)</label>
                    <textarea name="notes" rows="2" placeholder="Supplier invoice notes, tracking ID..." 
                              class="w-full px-4 py-2 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-amber-600"></textarea>
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="createModalOpen = false" class="px-4 py-2.5 text-slate-600 font-bold text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 bg-amber-600 text-white font-bold text-xs shadow-md">
                        Save Purchase & Update Stock
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: VIEW PURCHASE ITEMS -->
    <div x-show="viewModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="viewModalOpen = false" class="bg-white max-w-xl w-full p-6 shadow-2xl space-y-6" x-if="selectedPurchase">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    Purchase Details (<span x-text="selectedPurchase.invoice_no"></span>)
                </h3>
                <button @click="viewModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="space-y-4">
                <div class="p-3 bg-slate-50 border border-slate-200">
                    <p class="text-xs font-bold text-slate-500 uppercase">Supplier</p>
                    <p class="text-sm font-extrabold text-slate-900" x-text="selectedPurchase.supplier ? selectedPurchase.supplier.name : 'Unknown'"></p>
                </div>

                <div class="border border-slate-200 overflow-hidden">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-100 font-bold text-slate-600 border-b">
                            <tr>
                                <th class="p-2.5">Product</th>
                                <th class="p-2.5">Qty</th>
                                <th class="p-2.5">Price</th>
                                <th class="p-2.5 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <template x-for="item in selectedPurchase.items" :key="item.id">
                                <tr>
                                    <td class="p-2.5 font-bold text-slate-800" x-text="item.product ? item.product.title : 'Product'"></td>
                                    <td class="p-2.5" x-text="item.quantity"></td>
                                    <td class="p-2.5" x-text="item.unit_price"></td>
                                    <td class="p-2.5 text-right font-extrabold text-slate-900" x-text="item.subtotal"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="pt-4 flex justify-end border-t border-slate-100">
                <button type="button" @click="viewModalOpen = false" class="px-5 py-2 bg-slate-900 text-white font-bold text-xs">
                    Close
                </button>
            </div>
        </div>
    </div>

</div>
@endsection
