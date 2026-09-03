@extends('layouts.material')

@section('title', 'Product POS & Sales Terminal')

@section('content')
<div x-data="{ 
    activeTab: 'pos', // 'pos' or 'history'
    
    // Backend Data Lists
    customersList: {{ json_encode($customers) }},
    productsList: {{ json_encode($products) }},
    storesList: {{ json_encode($stores) }},
    selectedStoreId: '{{ $defaultStore->id ?? ($stores->first()->id ?? '') }}',

    getProductStoreStock(prod) {
        if (!prod) return 0;
        if (prod.store_stocks && Array.isArray(prod.store_stocks)) {
            const found = prod.store_stocks.find(s => s.store_id == this.selectedStoreId);
            if (found) return parseInt(found.stock) || 0;
        }
        return parseInt(prod.stock) || 0;
    },

    onStoreChange() {
        this.items.forEach(it => {
            it.stock = this.getProductStoreStock(it.product_obj);
        });
    },
    
    // Selected Client State (Default: Walk-in Customer)
    selectedCustomerId: '{{ $defaultCustomer->id ?? ($customers->first()->id ?? '') }}',
    selectedCustomerObj: {{ json_encode($defaultCustomer ?? ($customers->first() ?? null)) }},
    customerModalOpen: false,
    customerSearchQuery: '',
    
    // Product Search & Filter State
    activeCategory: 'All Products',
    productSearchQuery: '',
    
    // Invoice Parameters
    invoiceNo: 'INV-{{ date('Ym') }}-{{ str_pad(\App\Models\Sale::count() + 1, 4, '0', STR_PAD_LEFT) }}',
    saleDate: '{{ date('Y-m-d') }}',
    notes: '',
    
    // Cart Items: [{ product_id, product_obj, title, price, quantity, stock }]
    items: [],
    
    // Discount & Payment State
    discountType: 'percentage', // 'percentage' or 'fixed'
    discountPercentage: 0,
    billDiscount: 0,
    paymentMode: 'Cash', // 'Cash', 'Card', 'Bank', 'Other'
    extraAmount: 0,      // Surcharge
    receivedAmount: 0,
    
    // Held Bills State
    heldBillsModalOpen: false,
    heldBills: [],
    
    // View Historical Sale Modal State
    viewModalOpen: false,
    selectedSale: null,

    init() {
        this.recalculateTotals();
        this.loadHeldBills();
    },

    // Client Selection
    selectCustomer(cust) {
        this.selectedCustomerId = cust.id;
        this.selectedCustomerObj = cust;
        this.customerModalOpen = false;
    },
    getFilteredCustomers() {
        if (!this.customerSearchQuery || this.customerSearchQuery.trim() === '') return this.customersList;
        const q = this.customerSearchQuery.toLowerCase();
        return this.customersList.filter(c => 
            c.name.toLowerCase().includes(q) || 
            (c.phone_no1 && c.phone_no1.includes(q))
        );
    },

    // Product Filtering & Images
    getImageUrl(item) {
        if (!item || !item.image) {
            return 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=400&q=80';
        }
        const img = String(item.image).trim();
        if (img.startsWith('http://') || img.startsWith('https://')) {
            return img;
        }
        return '/' + img.replace(/^\/+/, '');
    },
    getFilteredProducts() {
        let list = this.productsList;
        if (this.productSearchQuery && this.productSearchQuery.trim() !== '') {
            const q = this.productSearchQuery.toLowerCase();
            list = list.filter(p => p.title.toLowerCase().includes(q));
        }
        return list;
    },
    
    // Cart Handlers
    isProductSelected(productId) {
        return this.items.some(it => it.product_id == productId);
    },
    getProductCartQuantity(productId) {
        const it = this.items.find(item => item.product_id == productId);
        return it ? (parseInt(it.quantity) || 1) : 0;
    },
    toggleProduct(prod) {
        const item = this.items.find(it => it.product_id == prod.id);
        if (item) {
            item.quantity = (parseInt(item.quantity) || 1) + 1;
        } else {
            this.items.push({
                product_id: prod.id,
                product_obj: prod,
                title: prod.title,
                quantity: 1,
                price: parseFloat(prod.discounted_price || prod.price || 0),
                stock: this.getProductStoreStock(prod)
            });
        }
        this.recalculateDiscount();
    },
    incrementProduct(prod, event) {
        if (event) event.stopPropagation();
        const item = this.items.find(it => it.product_id == prod.id);
        if (item) {
            item.quantity = (parseInt(item.quantity) || 1) + 1;
        } else {
            this.items.push({
                product_id: prod.id,
                product_obj: prod,
                title: prod.title,
                quantity: 1,
                price: parseFloat(prod.discounted_price || prod.price || 0),
                stock: this.getProductStoreStock(prod)
            });
        }
        this.recalculateDiscount();
    },
    decrementProduct(prod, event) {
        if (event) event.stopPropagation();
        const index = this.items.findIndex(it => it.product_id == prod.id);
        if (index > -1) {
            if (this.items[index].quantity > 1) {
                this.items[index].quantity -= 1;
            } else {
                this.items.splice(index, 1);
            }
            this.recalculateDiscount();
        }
    },
    removeItem(index) {
        this.items.splice(index, 1);
        this.recalculateDiscount();
    },

    // Calculations
    getSubtotal() {
        return this.items.reduce((sum, item) => sum + ((parseFloat(item.price) || 0) * (parseInt(item.quantity) || 1)), 0);
    },
    recalculateDiscount() {
        const gross = this.getSubtotal();
        if (this.discountType === 'percentage') {
            const pct = Math.min(100, Math.max(0, parseFloat(this.discountPercentage) || 0));
            this.billDiscount = Math.round(gross * (pct / 100));
        } else {
            const fixed = Math.max(0, parseFloat(this.billDiscount) || 0);
            this.discountPercentage = gross > 0 ? Math.round((fixed / gross) * 100) : 0;
        }
        this.recalculateTotals();
    },
    recalculateTotals() {
        const tot = this.getTotalAmount();
        if (this.receivedAmount === 0 || this.receivedAmount < tot) {
            this.receivedAmount = tot;
        }
    },
    getCalculatedDiscount() {
        const gross = this.getSubtotal();
        if (this.discountType === 'percentage') {
            const pct = Math.min(100, Math.max(0, parseFloat(this.discountPercentage) || 0));
            return Math.round(gross * (pct / 100));
        }
        return Math.max(0, parseFloat(this.billDiscount) || 0);
    },
    getExtraFee() {
        if (this.paymentMode === 'Card' || this.paymentMode === 'Bank') {
            return parseFloat(this.extraAmount || 0);
        }
        return 0;
    },
    getTotalAmount() {
        const total = this.getSubtotal() - this.getCalculatedDiscount() + this.getExtraFee();
        return Math.max(0, Math.round(total));
    },
    getRemainingBalance() {
        const total = this.getTotalAmount();
        const paid = parseFloat(this.receivedAmount || 0);
        return Math.max(0, Math.round(total - paid));
    },
    getChangeReturn() {
        const total = this.getTotalAmount();
        const paid = parseFloat(this.receivedAmount || 0);
        return Math.max(0, Math.round(paid - total));
    },
    clearFullRemainingBalance() {
        this.receivedAmount = this.getTotalAmount();
    },

    // Held Bills Logic
    loadHeldBills() {
        try {
            const saved = localStorage.getItem('pos_held_sales');
            this.heldBills = saved ? JSON.parse(saved) : [];
        } catch(e) {
            this.heldBills = [];
        }
    },
    saveHeldBills() {
        try {
            localStorage.setItem('pos_held_sales', JSON.stringify(this.heldBills));
        } catch(e) {}
    },
    holdCurrentBill() {
        if (this.items.length === 0) {
            alert('Please select at least one product before putting the sale on hold.');
            return;
        }
        const clientName = this.selectedCustomerObj ? this.selectedCustomerObj.name : 'Walk-in Customer';
        const record = {
            id: Date.now(),
            heldAt: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
            invoiceNo: this.invoiceNo,
            selectedCustomerId: this.selectedCustomerId,
            selectedCustomerObj: this.selectedCustomerObj,
            saleDate: this.saleDate,
            notes: this.notes,
            discountType: this.discountType,
            discountPercentage: this.discountPercentage,
            billDiscount: this.billDiscount,
            paymentMode: this.paymentMode,
            extraAmount: this.extraAmount,
            receivedAmount: this.receivedAmount,
            items: JSON.parse(JSON.stringify(this.items)),
            totalAmount: this.getTotalAmount(),
            clientName: clientName
        };

        this.heldBills.unshift(record);
        this.saveHeldBills();
        this.resetToNewBill();
        alert('⏸️ Product sale for ' + clientName + ' has been put on hold.\n\nYou can resume it anytime from the Held Bills button.');
    },
    resumeHeldBill(record) {
        if (this.items.length > 0) {
            if (!confirm('Current in-progress bill will be replaced with the held bill. Do you wish to continue?')) {
                return;
            }
        }
        this.selectedCustomerId = record.selectedCustomerId;
        this.selectedCustomerObj = record.selectedCustomerObj;
        this.invoiceNo = record.invoiceNo || ('INV-{{ date('Ym') }}-{{ str_pad(\App\Models\Sale::count() + 1, 4, '0', STR_PAD_LEFT) }}');
        this.saleDate = record.saleDate || '{{ date('Y-m-d') }}';
        this.notes = record.notes || '';
        this.discountType = record.discountType || 'percentage';
        this.discountPercentage = record.discountPercentage || 0;
        this.billDiscount = record.billDiscount || 0;
        this.paymentMode = record.paymentMode || 'Cash';
        this.extraAmount = record.extraAmount || 0;
        this.receivedAmount = record.receivedAmount || 0;
        this.items = record.items ? JSON.parse(JSON.stringify(record.items)) : [];

        this.heldBills = this.heldBills.filter(h => h.id !== record.id);
        this.saveHeldBills();
        this.heldBillsModalOpen = false;
        this.recalculateDiscount();
        this.activeTab = 'pos';
    },
    discardHeldBill(recordId) {
        if (confirm('Are you sure you want to discard this held sale?')) {
            this.heldBills = this.heldBills.filter(h => h.id !== recordId);
            this.saveHeldBills();
        }
    },

    // Reset Terminal
    resetToNewBill() {
        this.invoiceNo = 'INV-{{ date('Ym') }}-{{ str_pad(\App\Models\Sale::count() + 1, 4, '0', STR_PAD_LEFT) }}';
        this.saleDate = '{{ date('Y-m-d') }}';
        this.items = [];
        this.notes = '';
        this.discountPercentage = 0;
        this.billDiscount = 0;
        this.receivedAmount = 0;
        this.extraAmount = 0;
        this.paymentMode = 'Cash';
        this.selectedCustomerId = '{{ $defaultCustomer->id ?? ($customers->first()->id ?? '') }}';
        this.selectedCustomerObj = {{ json_encode($defaultCustomer ?? ($customers->first() ?? null)) }};
        this.activeTab = 'pos';
    },

    // Submission
    submitForm(isPrint = false) {
        if (this.items.length === 0) {
            alert('Please select at least one product to sell.');
            return;
        }
        if (!this.selectedCustomerId) {
            alert('Please select a customer account.');
            return;
        }

        const form = document.getElementById('posSaleForm');
        if (isPrint) {
            window.print();
        }
        form.submit();
    }
}" class="space-y-6">

    <!-- Top Action Bar & Mode Switcher Tabs -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-none border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold text-slate-400 uppercase tracking-wider">
                <span>Inventory & Store Sales</span>
                <span>&bull;</span>
                <span class="text-indigo-600 font-extrabold">Live POS Terminal</span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight heading-font mt-0.5">Product POS & Checkout</h1>
        </div>

        <!-- Mode Switcher Tabs -->
        <div class="flex items-center gap-2 shrink-0">
            <!-- Held Bills Trigger -->
            <button type="button" @click="heldBillsModalOpen = true" 
                    class="px-3.5 py-2 text-xs rounded-none transition-all flex items-center gap-1.5 border font-bold shadow-2xs"
                    :class="heldBills.length > 0 ? 'bg-amber-50 text-amber-900 border-amber-300 hover:bg-amber-100' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'">
                <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>Held Bills</span>
                <span class="px-1.5 py-0.2 text-[10px] font-black" 
                      :class="heldBills.length > 0 ? 'bg-amber-600 text-white' : 'bg-slate-200 text-slate-700'" 
                      x-text="heldBills.length">0</span>
            </button>

            <div class="inline-flex p-1 bg-slate-100 rounded-none border border-slate-200">
                <button type="button" @click="resetToNewBill()" 
                        :class="activeTab === 'pos' ? 'bg-white text-indigo-700 shadow-sm font-black' : 'text-slate-600 hover:text-slate-900 font-bold'"
                        class="px-4 py-2 text-xs rounded-none transition-all flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    <span>POS Terminal</span>
                </button>
                <button type="button" @click="activeTab = 'history'" 
                        :class="activeTab === 'history' ? 'bg-white text-indigo-700 shadow-sm font-black' : 'text-slate-600 hover:text-slate-900 font-bold'"
                        class="px-4 py-2 text-xs rounded-none transition-all flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    <span>Sales History ({{ $sales->total() }})</span>
                </button>
            </div>
        </div>
    </div>

    <!-- TAB 1: 3-COLUMN POS BILLING TERMINAL (MATCHING APPOINTMENTS LAYOUT) -->
    <div x-show="activeTab === 'pos'" class="space-y-5">
        
        <form id="posSaleForm" method="POST" action="{{ route('manager.sales.store') }}">
            @csrf

            <!-- Hidden Form Payloads -->
            <input type="hidden" name="account_id" :value="selectedCustomerId">
            <input type="hidden" name="store_id" :value="selectedStoreId">
            <input type="hidden" name="sale_date" :value="saleDate">
            <input type="hidden" name="discount" :value="getCalculatedDiscount()">
            <input type="hidden" name="received_amount" :value="receivedAmount || 0">
            <input type="hidden" name="payment_mode" :value="paymentMode">
            <input type="hidden" name="extra_amount" :value="getExtraFee()">
            <input type="hidden" name="notes" :value="notes">

            <!-- Itemized Products Array -->
            <template x-for="(item, idx) in items" :key="idx">
                <div>
                    <input type="hidden" :name="'items['+idx+'][product_id]'" :value="item.product_id">
                    <input type="hidden" :name="'items['+idx+'][quantity]'" :value="item.quantity">
                    <input type="hidden" :name="'items['+idx+'][unit_price]'" :value="item.price">
                </div>
            </template>

            <!-- ACTIVE SELLING STORE / BRANCH PICKER BANNER -->
            <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 border border-slate-700 p-4 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-white mb-5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-indigo-600/30 border border-indigo-400/30 flex items-center justify-center text-xl shrink-0">
                        🏬
                    </div>
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-wider text-indigo-300">Active Selling Store / Branch (Stock Out Source)</span>
                        <div class="flex items-center gap-2 mt-0.5">
                            <h2 class="text-sm font-black text-white" x-text="(storesList.find(s => s.id == selectedStoreId) || {}).name || 'Select Store'"></h2>
                            <span class="px-1.5 py-0.2 bg-indigo-500/30 text-indigo-200 font-mono font-bold text-[10px] border border-indigo-400/30" 
                                  x-text="'[' + ((storesList.find(s => s.id == selectedStoreId) || {}).code || '') + ']'"></span>
                            <template x-if="(storesList.find(s => s.id == selectedStoreId) || {}).is_default">
                                <span class="px-1.5 py-0.2 bg-emerald-500/20 text-emerald-300 text-[9px] font-extrabold border border-emerald-500/30">DEFAULT</span>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2.5">
                    <label class="text-xs font-bold text-slate-300 whitespace-nowrap">Selling From Branch:</label>
                    <select x-model="selectedStoreId" @change="onStoreChange()" 
                            class="px-3 py-2 bg-white text-slate-900 text-xs font-black border border-slate-300 focus:ring-2 focus:ring-indigo-500 shadow-xs cursor-pointer">
                        <template x-for="s in storesList" :key="s.id">
                            <option :value="s.id" x-text="'🏬 ' + s.name + (s.is_default ? ' (Default)' : '')"></option>
                        </template>
                    </select>
                </div>
            </div>

            <!-- 3-Column Modern Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

                <!-- LEFT COLUMN: Client Details & Sale Parameters (Col-Span-3) -->
                <div class="lg:col-span-3 space-y-5">
                    
                    <!-- BOX 1: CLIENT DETAILS -->
                    <div class="bg-white p-4 border border-slate-200 rounded-none space-y-3 shadow-xs">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                            <div class="flex items-center gap-1.5">
                                <span class="w-5 h-5 bg-indigo-600 text-white rounded-none text-xs font-black flex items-center justify-center">1</span>
                                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">Client Details</h3>
                            </div>
                            <button type="button" @click="customerModalOpen = true" class="text-xs font-black text-indigo-600 hover:text-indigo-800 underline">
                                Change
                            </button>
                        </div>

                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-none flex items-center gap-3">
                            <div class="w-10 h-10 bg-indigo-600 text-white font-black flex items-center justify-center text-xs rounded-none shrink-0 shadow-xs">
                                <span x-text="selectedCustomerObj ? selectedCustomerObj.name.substring(0,2).toUpperCase() : 'WA'"></span>
                            </div>
                            <div class="overflow-hidden">
                                <h4 class="text-xs font-extrabold text-slate-900 truncate" x-text="selectedCustomerObj ? selectedCustomerObj.name : 'Walk-in Customer'"></h4>
                                <p class="text-[11px] text-slate-500 font-semibold" x-text="selectedCustomerObj && selectedCustomerObj.phone_no1 ? selectedCustomerObj.phone_no1 : '0300-0000000'"></p>
                            </div>
                        </div>
                    </div>

                    <!-- BOX 2: SALE / INVOICE PARAMETERS -->
                    <div class="bg-white p-4 border border-slate-200 rounded-none space-y-3.5 shadow-xs">
                        <div class="flex items-center gap-1.5 border-b border-slate-100 pb-2.5">
                            <span class="w-5 h-5 bg-indigo-600 text-white rounded-none text-xs font-black flex items-center justify-center">2</span>
                            <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">Sale Parameters</h3>
                        </div>

                        <div class="space-y-3">
                            <!-- Invoice # Display -->
                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 mb-1">Invoice #</label>
                                <input type="text" x-model="invoiceNo" readonly 
                                       class="w-full px-3 py-2 bg-slate-100 border border-slate-200 rounded-none text-xs font-black text-slate-700 font-mono">
                            </div>

                            <!-- Sale Date -->
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 mb-1">Sale Date</label>
                                <input type="date" x-model="saleDate" 
                                       class="w-full px-3 py-2 bg-white border border-slate-300 rounded-none text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500">
                            </div>

                            <!-- Selling Store / Branch Selector -->
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 mb-1">Selling Store / Branch <span class="text-rose-600">*</span></label>
                                <select x-model="selectedStoreId" 
                                        class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-none text-xs font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-500">
                                    <template x-for="s in storesList" :key="s.id">
                                        <option :value="s.id" x-text="'🏬 ' + s.name + (s.is_default ? ' (Default)' : '')"></option>
                                    </template>
                                </select>
                                <span class="text-[9px] text-slate-400 font-medium">Stock is checked & decremented from this location</span>
                            </div>

                            <!-- Notes / Remarks -->
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 mb-1">Notes / Remarks</label>
                                <textarea x-model="notes" rows="2" placeholder="Special sales instructions or remarks..." 
                                          class="w-full px-3 py-2 bg-white border border-slate-300 rounded-none text-xs font-medium text-slate-800 focus:ring-2 focus:ring-indigo-500"></textarea>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- MIDDLE COLUMN: PRODUCT CATALOG GRID (Col-Span-5) -->
                <div class="lg:col-span-5 bg-white p-5 border border-slate-200 rounded-none shadow-xs space-y-4">
                    
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-5 h-5 bg-indigo-600 text-white rounded-none text-xs font-black flex items-center justify-center">3</span>
                            <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">Select Products & Cosmetics</h3>
                        </div>
                        <span class="text-[11px] text-slate-400 font-bold">Click to Add / Adjust Qty</span>
                    </div>

                    <!-- Search Input -->
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </span>
                        <input type="text" x-model="productSearchQuery" placeholder="Search products, cosmetics, hair care..." 
                               class="w-full pl-9 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-none text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <!-- Products Grid Display (3 Columns) -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 pt-1 max-h-[620px] overflow-y-auto pr-1">
                        <template x-for="prod in getFilteredProducts()" :key="prod.id">
                            <div @click="toggleProduct(prod)"
                                 :class="isProductSelected(prod.id) ? 'ring-2 ring-indigo-600 border-indigo-600 bg-indigo-50/40' : 'border-slate-200 hover:border-indigo-400 bg-white'"
                                 class="border rounded-none p-3 cursor-pointer transition-all flex flex-col justify-between group shadow-2xs relative">
                                
                                <div class="space-y-2">
                                    <!-- Image Thumbnail with Aspect Ratio -->
                                    <div class="w-full aspect-[4/3] rounded-none overflow-hidden bg-slate-100 relative">
                                        <img :src="getImageUrl(prod)" 
                                             x-on:error="$el.src = 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=400&q=80'"
                                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" 
                                             :alt="prod.title">

                                        <!-- Top Left High-Contrast Store-Specific Stock Badge -->
                                        <span class="absolute top-2 left-2 px-2 py-0.5 text-[10px] font-black rounded-none shadow-xs"
                                              :class="getProductStoreStock(prod) <= 0 ? 'bg-rose-600 text-white' : (getProductStoreStock(prod) <= 5 ? 'bg-amber-500 text-white' : 'bg-slate-900/85 text-white backdrop-blur-xs')"
                                              x-text="getProductStoreStock(prod) <= 0 ? 'Out of Stock' : ('Stock: ' + getProductStoreStock(prod))"></span>

                                        <!-- Top Right Active Cart Quantity Badge -->
                                        <div x-show="getProductCartQuantity(prod.id) > 0" 
                                             class="absolute top-2 right-2 bg-indigo-600 text-white px-2 py-0.5 text-[10px] font-black rounded-none shadow-md flex items-center gap-1">
                                            <span>✓ Qty:</span>
                                            <span x-text="getProductCartQuantity(prod.id)"></span>
                                        </div>
                                    </div>

                                    <!-- Product Title -->
                                    <h4 class="font-bold text-xs text-slate-900 leading-snug line-clamp-2" x-text="prod.title"></h4>
                                </div>

                                <!-- Bottom Price & Quantity Controls -->
                                <div class="mt-2.5 pt-2 border-t border-slate-100 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] text-slate-400 font-semibold uppercase">Price</span>
                                        <p class="font-black text-xs text-indigo-600">PKR <span x-text="Number(prod.discounted_price || prod.price).toLocaleString()"></span></p>
                                    </div>

                                    <!-- Quick Multi-Quantity Buttons (Appears only when in cart) -->
                                    <div x-show="getProductCartQuantity(prod.id) > 0" class="flex items-center justify-between bg-white border border-indigo-200 p-1 text-xs">
                                        <button type="button" @click.stop="decrementProduct(prod, $event)" 
                                                class="w-6 h-6 flex items-center justify-center bg-slate-100 hover:bg-rose-100 hover:text-rose-700 text-slate-700 font-black text-xs transition-colors">
                                            -
                                        </button>
                                        <span class="font-black text-xs text-indigo-700 px-1" x-text="'Qty: ' + getProductCartQuantity(prod.id)"></span>
                                        <button type="button" @click.stop="incrementProduct(prod, $event)" 
                                                class="w-6 h-6 flex items-center justify-center bg-indigo-50 hover:bg-indigo-600 hover:text-white text-indigo-700 font-black text-xs transition-colors">
                                            +
                                        </button>
                                    </div>
                                </div>

                            </div>
                        </template>
                    </div>

                </div>

                <!-- RIGHT COLUMN: BILL & CLEARANCE SUMMARY (Col-Span-4) -->
                <div class="lg:col-span-4 bg-white p-5 border border-slate-200 rounded-none shadow-xs space-y-4">
                    
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-5 h-5 bg-indigo-600 text-white rounded-none text-xs font-black flex items-center justify-center">4</span>
                            <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">Bill & Clearance Summary</h3>
                        </div>
                        <span class="text-[11px] font-black text-indigo-700 bg-indigo-50 px-2 py-0.5 border border-indigo-200" 
                              x-text="items.length + ' Product(s)'"></span>
                    </div>

                    <!-- Selected Line Items Table -->
                    <div class="border border-slate-200 rounded-none overflow-hidden max-h-56 overflow-y-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-extrabold text-[10px] uppercase">
                                    <th class="py-2 px-2.5">Product Title</th>
                                    <th class="py-2 px-2 text-right">Price</th>
                                    <th class="py-2 px-2 text-center">Qty</th>
                                    <th class="py-2 px-2 text-right">Net Total</th>
                                    <th class="py-2 px-1 text-center">✕</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-[11px] font-bold">
                                <template x-for="(item, index) in items" :key="item.product_id">
                                    <tr class="hover:bg-slate-50/60">
                                        <td class="py-2 px-2.5 font-bold text-slate-900">
                                            <span x-text="item.title"></span>
                                        </td>
                                        <td class="py-2 px-2 text-right text-slate-600">
                                            PKR <span x-text="parseFloat(item.price).toLocaleString()"></span>
                                        </td>
                                        <td class="py-2 px-2 text-center">
                                            <div class="inline-flex items-center border border-slate-300">
                                                <button type="button" @click="decrementProduct(item.product_obj)" 
                                                        class="px-1.5 py-0.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[10px]">-</button>
                                                <input type="number" min="1" x-model.number="item.quantity" @input="recalculateDiscount()" 
                                                       class="w-8 text-center py-0.5 text-xs font-black text-slate-900 border-x border-slate-300 bg-white">
                                                <button type="button" @click="incrementProduct(item.product_obj)" 
                                                        class="px-1.5 py-0.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[10px]">+</button>
                                            </div>
                                        </td>
                                        <td class="py-2 px-2 text-right font-black text-slate-900">
                                            PKR <span x-text="((item.price || 0) * (item.quantity || 1)).toLocaleString()"></span>
                                        </td>
                                        <td class="py-2 px-1 text-center">
                                            <button type="button" @click="removeItem(index)" class="text-rose-500 hover:text-rose-700 font-black text-xs">
                                                &times;
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="items.length === 0">
                                    <tr>
                                        <td colspan="5" class="py-6 text-center text-slate-400 font-semibold">
                                            No products selected. Click items from the catalog.
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- BILL DISCOUNT BOX -->
                    <div class="p-3.5 bg-rose-50/40 border border-rose-200 rounded-none space-y-2.5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                                <label class="text-xs font-black text-slate-900 uppercase tracking-wider">Bill Discount</label>
                            </div>

                            <!-- % vs Flat Switcher -->
                            <div class="inline-flex rounded-none border border-slate-300 p-0.5 bg-white shrink-0">
                                <button type="button" @click="discountType = 'percentage'; recalculateDiscount()"
                                        :class="discountType === 'percentage' ? 'bg-indigo-600 text-white font-black shadow-xs' : 'text-slate-600 font-bold hover:bg-slate-100'"
                                        class="px-2.5 py-0.5 text-[11px] rounded-none transition-all">
                                    % Percent
                                </button>
                                <button type="button" @click="discountType = 'fixed'; recalculateDiscount()"
                                        :class="discountType === 'fixed' ? 'bg-indigo-600 text-white font-black shadow-xs' : 'text-slate-600 font-bold hover:bg-slate-100'"
                                        class="px-2.5 py-0.5 text-[11px] rounded-none transition-all">
                                    PKR Flat
                                </button>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <!-- Custom Discount Input Field -->
                            <div class="relative">
                                <!-- Percentage Input -->
                                <div x-show="discountType === 'percentage'" class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-xs font-black text-slate-400">%</span>
                                    <input type="number" step="any" min="0" max="100" 
                                           x-model.number="discountPercentage" 
                                           @input="recalculateDiscount()"
                                           placeholder="Enter discount percentage (e.g. 10)" 
                                           class="w-full pl-9 pr-4 py-2 bg-white border border-rose-300 text-xs font-black text-slate-900 rounded-none focus:ring-2 focus:ring-rose-500">
                                </div>

                                <!-- Flat PKR Input -->
                                <div x-show="discountType === 'fixed'" class="relative" x-cloak>
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-xs font-black text-slate-400">PKR</span>
                                    <input type="number" step="any" min="0" 
                                           x-model.number="billDiscount" 
                                           @input="recalculateDiscount()"
                                           placeholder="Enter discount amount in PKR" 
                                           class="w-full pl-9 pr-4 py-2 bg-white border border-rose-300 text-xs font-black text-slate-900 rounded-none focus:ring-2 focus:ring-rose-500">
                                </div>
                            </div>

                            <!-- Live Discount Amount Preview -->
                            <div class="flex items-center justify-between text-[11px] font-bold pt-0.5">
                                <span class="text-rose-700">
                                    Deducts: <strong class="font-black text-rose-800">PKR <span x-text="getCalculatedDiscount().toLocaleString()">0</span></strong>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Totals Breakdown -->
                    <div class="space-y-2 pt-1 border-t border-slate-100 text-xs font-bold text-slate-600">
                        <div class="flex items-center justify-between">
                            <span>Subtotal (Gross)</span>
                            <span class="text-slate-900 font-bold">PKR <span x-text="getSubtotal().toLocaleString()">0</span></span>
                        </div>

                        <div class="flex items-center justify-between text-base font-black text-slate-900 pt-1 border-t border-slate-200">
                            <span>Total Net Bill</span>
                            <span class="text-indigo-700 text-xl font-black">PKR <span x-text="getTotalAmount().toLocaleString()">0</span></span>
                        </div>
                    </div>

                    <!-- Payment Mode Selector -->
                    <div class="space-y-1.5 pt-2 border-t border-slate-100">
                        <label class="block text-xs font-black text-slate-700 uppercase tracking-wider">Payment Mode</label>
                        <div class="grid grid-cols-4 gap-2">
                            <!-- Cash -->
                            <button type="button" @click="paymentMode = 'Cash'; extraAmount = 0; recalculateTotals()"
                                    :class="paymentMode === 'Cash' ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-black ring-1 ring-indigo-600' : 'border-slate-200 text-slate-600 font-bold bg-white hover:bg-slate-50'"
                                    class="p-2 border rounded-none text-xs flex flex-col items-center gap-1 transition-all">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                <span>Cash</span>
                            </button>
                            <!-- Card -->
                            <button type="button" @click="paymentMode = 'Card'; recalculateTotals()"
                                    :class="paymentMode === 'Card' ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-black ring-1 ring-indigo-600' : 'border-slate-200 text-slate-600 font-bold bg-white hover:bg-slate-50'"
                                    class="p-2 border rounded-none text-xs flex flex-col items-center gap-1 transition-all">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                                <span>Card</span>
                            </button>
                            <!-- Bank -->
                            <button type="button" @click="paymentMode = 'Bank'; recalculateTotals()"
                                    :class="paymentMode === 'Bank' ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-black ring-1 ring-indigo-600' : 'border-slate-200 text-slate-600 font-bold bg-white hover:bg-slate-50'"
                                    class="p-2 border rounded-none text-xs flex flex-col items-center gap-1 transition-all">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                                <span>Bank</span>
                            </button>
                            <!-- Other -->
                            <button type="button" @click="paymentMode = 'Other'; extraAmount = 0; recalculateTotals()"
                                    :class="paymentMode === 'Other' ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-black ring-1 ring-indigo-600' : 'border-slate-200 text-slate-600 font-bold bg-white hover:bg-slate-50'"
                                    class="p-2 border rounded-none text-xs flex flex-col items-center gap-1 transition-all">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span>Other</span>
                            </button>
                        </div>

                        <!-- Card Surcharge Input Field -->
                        <div x-show="paymentMode === 'Card' || paymentMode === 'Bank'" class="p-2.5 bg-indigo-50/50 border border-indigo-200 space-y-1">
                            <label class="block text-[11px] font-bold text-indigo-900">💳 Processing Surcharge (PKR)</label>
                            <input type="number" step="any" x-model.number="extraAmount" @input="recalculateTotals()" placeholder="0" 
                                   class="w-full px-3 py-1.5 bg-white border border-indigo-300 text-xs font-black text-indigo-900 rounded-none focus:ring-1 focus:ring-indigo-600">
                        </div>
                    </div>

                    <!-- PAYMENT STATUS & CLEARANCE BOX -->
                    <div class="p-3.5 bg-slate-50 border border-slate-200 space-y-3 rounded-none">
                        <div class="flex items-center justify-between text-xs font-bold">
                            <span class="text-slate-800 uppercase tracking-wider text-[11px] font-black flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                <span>Payment & Settlement</span>
                            </span>
                            <button type="button" @click="clearFullRemainingBalance()" class="text-[11px] font-black text-indigo-600 hover:text-indigo-800 underline">
                                ⚡ Clear Full Balance
                            </button>
                        </div>

                        <!-- Payment Input Field -->
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-700">Amount Received (PKR)</label>
                            <input type="number" step="any" x-model.number="receivedAmount" placeholder="0" 
                                   class="w-full px-3.5 py-2.5 bg-white border border-slate-300 text-xs font-black text-slate-900 focus:ring-2 focus:ring-indigo-500 rounded-none">
                        </div>

                        <!-- Live Settlement Results -->
                        <div class="pt-1 space-y-1.5 text-xs font-bold border-t border-slate-200/80">
                            <div class="flex items-center justify-between text-slate-600">
                                <span>Total Paid:</span>
                                <span class="text-slate-900 font-extrabold">PKR <span x-text="Number(receivedAmount || 0).toLocaleString()">0</span></span>
                            </div>

                            <div class="flex items-center justify-between" x-show="getRemainingBalance() > 0">
                                <span class="text-rose-600 font-bold">Remaining Balance Due:</span>
                                <span class="text-sm font-black text-rose-600">PKR <span x-text="getRemainingBalance().toLocaleString()">0</span></span>
                            </div>

                            <div class="flex items-center justify-between" x-show="getChangeReturn() > 0">
                                <span class="text-emerald-700 font-bold">Change Return / Excess:</span>
                                <span class="text-sm font-black text-emerald-700">PKR <span x-text="getChangeReturn().toLocaleString()">0</span></span>
                            </div>

                            <div class="flex items-center justify-between text-emerald-700 pt-0.5" x-show="getRemainingBalance() === 0">
                                <span class="font-bold">Bill Status:</span>
                                <span class="text-xs font-black bg-emerald-100 text-emerald-900 px-2 py-0.5 border border-emerald-300">✓ 100% Cleared & Paid</span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons (Hold, Save & Print, Complete) -->
                    <div class="grid grid-cols-3 gap-2.5 pt-2">
                        <button type="button" @click="holdCurrentBill()" 
                                class="py-3 px-2 bg-amber-50 hover:bg-amber-100 text-amber-900 font-extrabold text-xs rounded-none transition-all flex items-center justify-center gap-1 border border-amber-300 shadow-2xs">
                            <svg class="w-4 h-4 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span>Hold Bill</span>
                        </button>
                        <button type="button" @click="submitForm(true)" 
                                class="py-3 px-2 bg-slate-100 hover:bg-slate-200 text-slate-800 font-extrabold text-xs rounded-none transition-all flex items-center justify-center gap-1 border border-slate-200 shadow-2xs">
                            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                            <span>Save & Print</span>
                        </button>
                        <button type="button" @click="submitForm(false)" 
                                class="py-3 px-2 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs rounded-none transition-all flex items-center justify-center gap-1 shadow-xs">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Complete</span>
                        </button>
                    </div>

                </div>

            </div>
        </form>

    </div>

    <!-- TAB 2: SALES HISTORY & AUDIT LOG -->
    <div x-show="activeTab === 'history'" class="space-y-5" x-cloak>
        
        <!-- Sales Statistics KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white p-5 border border-slate-200 rounded-none shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-extrabold uppercase text-slate-400 tracking-wider">Today's Sales</p>
                    <h3 class="text-2xl font-black text-slate-900 mt-1">PKR {{ number_format($todaySales, 2) }}</h3>
                    <p class="text-[11px] font-semibold text-emerald-600 mt-1">Live Store Revenue</p>
                </div>
                <div class="p-3 bg-emerald-50 text-emerald-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>

            <div class="bg-white p-5 border border-slate-200 rounded-none shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-extrabold uppercase text-slate-400 tracking-wider">This Month Sales</p>
                    <h3 class="text-2xl font-black text-indigo-900 mt-1">PKR {{ number_format($thisMonthSales, 2) }}</h3>
                    <p class="text-[11px] font-semibold text-indigo-600 mt-1">{{ now()->format('F Y') }} Total</p>
                </div>
                <div class="p-3 bg-indigo-50 text-indigo-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                </div>
            </div>

            <div class="bg-white p-5 border border-slate-200 rounded-none shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-extrabold uppercase text-slate-400 tracking-wider">Total Received</p>
                    <h3 class="text-2xl font-black text-emerald-600 mt-1">PKR {{ number_format($totalReceivings, 2) }}</h3>
                    <p class="text-[11px] font-semibold text-slate-500 mt-1">Cleared Payments</p>
                </div>
                <div class="p-3 bg-emerald-50 text-emerald-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
            </div>

            <div class="bg-white p-5 border border-slate-200 rounded-none shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-extrabold uppercase text-slate-400 tracking-wider">Balance Due</p>
                    <h3 class="text-2xl font-black text-rose-600 mt-1">PKR {{ number_format($totalBalanceDue, 2) }}</h3>
                    <p class="text-[11px] font-semibold text-rose-500 mt-1">Pending Receivables</p>
                </div>
                <div class="p-3 bg-rose-50 text-rose-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div class="bg-white p-4 border border-slate-200 rounded-none shadow-xs">
            <form method="GET" action="{{ route('manager.sales.index') }}" class="grid grid-cols-1 sm:grid-cols-5 gap-3">
                <div class="relative flex-1">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </span>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search invoice #, customer name..." 
                           class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-none text-xs font-semibold focus:ring-2 focus:ring-indigo-600">
                </div>

                <div>
                    <select name="store_id" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-none text-xs font-bold focus:ring-2 focus:ring-indigo-600">
                        <option value="">All Stores / Global</option>
                        @foreach($stores as $s)
                            <option value="{{ $s->id }}" {{ (string)$storeId === (string)$s->id ? 'selected' : '' }}>
                                🏬 {{ $s->name }} {{ $s->is_default ? '(Default)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <input type="date" name="start_date" value="{{ $startDate }}" placeholder="From Date"
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-none text-xs font-semibold focus:ring-2 focus:ring-indigo-600">
                </div>

                <div>
                    <input type="date" name="end_date" value="{{ $endDate }}" placeholder="To Date"
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-none text-xs font-semibold focus:ring-2 focus:ring-indigo-600">
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" class="flex-1 py-2.5 bg-indigo-600 text-white font-bold text-xs rounded-none hover:bg-indigo-700 transition-colors shadow-xs">
                        Filter Sales
                    </button>
                    @if($search || $storeId || $startDate || $endDate)
                        <a href="{{ route('manager.sales.index') }}" class="px-4 py-2.5 bg-slate-100 text-slate-600 font-bold text-xs rounded-none hover:bg-slate-200 flex items-center justify-center border border-slate-200">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Sales History Table -->
        <div class="bg-white border border-slate-200 rounded-none shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-400 text-[10px] font-black uppercase tracking-wider">
                            <th class="py-4 px-4">Invoice #</th>
                            <th class="py-4 px-4">Selling Branch</th>
                            <th class="py-4 px-4">Client Name</th>
                            <th class="py-4 px-4">Sale Date</th>
                            <th class="py-4 px-4">Sold Items</th>
                            <th class="py-4 px-4">Net Bill</th>
                            <th class="py-4 px-4">Paid Amount</th>
                            <th class="py-4 px-4">Remaining Balance</th>
                            <th class="py-4 px-4">Mode</th>
                            <th class="py-4 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                        @forelse($sales as $sale)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-4 px-4 font-black text-indigo-600">
                                #{{ $sale->invoice_no }}
                            </td>
                            <td class="py-4 px-4">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-slate-100 text-slate-800 text-[11px] font-bold border border-slate-200">
                                    🏬 {{ $sale->store ? $sale->store->name : ($defaultStore->name ?? 'Main Store') }}
                                </span>
                            </td>
                            <td class="py-4 px-4 font-bold text-slate-900">
                                {{ $sale->customer->name ?? 'Walk-in Customer' }}
                                @if($sale->customer && $sale->customer->phone_no1)
                                    <p class="text-[10px] text-slate-400 font-normal">{{ $sale->customer->phone_no1 }}</p>
                                @endif
                            </td>
                            <td class="py-4 px-4 font-bold text-slate-700">
                                {{ $sale->sale_date ? $sale->sale_date->format('M d, Y') : '—' }}
                            </td>
                            <td class="py-4 px-4">
                                <div class="space-y-0.5">
                                    @foreach($sale->items as $item)
                                        <p class="font-bold text-slate-800 text-[11px] flex items-center gap-1">
                                            <span>• {{ $item->product->title ?? 'Product' }}</span>
                                            <span class="px-1.5 py-0.2 bg-indigo-100 text-indigo-800 text-[10px] font-black rounded-none">x{{ $item->quantity }}</span>
                                        </p>
                                    @endforeach
                                </div>
                            </td>
                            <td class="py-4 px-4 font-black text-slate-900">PKR {{ number_format($sale->total_amount, 2) }}</td>
                            <td class="py-4 px-4 font-bold text-emerald-600">PKR {{ number_format($sale->received_amount, 2) }}</td>
                            <td class="py-4 px-4 font-bold">
                                @if($sale->balance_due > 0)
                                    <span class="px-2 py-0.5 bg-rose-50 text-rose-700 font-black border border-rose-200">
                                        PKR {{ number_format($sale->balance_due, 2) }} Due
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 font-bold border border-emerald-200">
                                        Cleared (PKR 0.00)
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-4">
                                <span class="px-2 py-0.5 text-[10px] font-black uppercase bg-slate-100 text-slate-700 border border-slate-200">
                                    {{ $sale->payment_mode ?? 'Cash' }}
                                </span>
                            </td>
                            <td class="py-4 px-4 text-right">
                                <button type="button" @click="selectedSale = {{ json_encode($sale) }}; viewModalOpen = true" 
                                        class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-[11px] rounded-none border border-slate-300 inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    <span>Inspect Bill</span>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-slate-400 font-semibold">No product sale records found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($sales->hasPages())
                <div class="p-4 border-t border-slate-200 bg-slate-50">
                    {{ $sales->links() }}
                </div>
            @endif
        </div>

    </div>

    <!-- CLIENT SELECTION MODAL -->
    <div x-show="customerModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-xs" @click="customerModalOpen = false"></div>
            <div class="inline-block w-full max-w-lg p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-none border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-extrabold text-slate-900 heading-font">Select Client Account</h3>
                    <button type="button" @click="customerModalOpen = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="relative">
                    <input type="text" x-model="customerSearchQuery" placeholder="Search customer name or phone number..." 
                           class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-none text-xs font-semibold focus:ring-2 focus:ring-indigo-600">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </span>
                </div>

                <div class="max-h-64 overflow-y-auto divide-y divide-slate-100 border border-slate-100">
                    <template x-for="cust in getFilteredCustomers()" :key="cust.id">
                        <div @click="selectCustomer(cust)" 
                             class="p-3 hover:bg-indigo-50 cursor-pointer flex items-center justify-between transition-colors group">
                            <div>
                                <h4 class="text-xs font-bold text-slate-900 group-hover:text-indigo-600" x-text="cust.name"></h4>
                                <p class="text-[10px] text-slate-400" x-text="cust.phone_no1 ? ('Phone: ' + cust.phone_no1) : 'No phone'"></p>
                            </div>
                            <span class="text-xs font-bold text-indigo-600 opacity-0 group-hover:opacity-100 transition-opacity">Select &rarr;</span>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- HELD BILLS MODAL -->
    <div x-show="heldBillsModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-xs" @click="heldBillsModalOpen = false"></div>
            <div class="inline-block w-full max-w-2xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-none border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">⏸️</span>
                        <div>
                            <h3 class="text-base font-extrabold text-slate-900 heading-font">Held Product Sales</h3>
                            <p class="text-[11px] text-slate-500 font-medium">Resume any held sale directly into the checkout terminal</p>
                        </div>
                    </div>
                    <button type="button" @click="heldBillsModalOpen = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Empty State -->
                <template x-if="heldBills.length === 0">
                    <div class="py-12 text-center space-y-2">
                        <div class="w-12 h-12 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto text-xl">⏸️</div>
                        <p class="text-xs font-bold text-slate-700">No product sales currently on hold.</p>
                        <p class="text-[11px] text-slate-400">Click "Hold Bill" in the terminal to park a sale and resume later.</p>
                    </div>
                </template>

                <!-- List of Held Bills -->
                <template x-if="heldBills.length > 0">
                    <div class="space-y-3 max-h-[60vh] overflow-y-auto divide-y divide-slate-100">
                        <template x-for="(held, index) in heldBills" :key="held.id">
                            <div class="p-3.5 bg-slate-50 border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-white transition-all">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs font-black text-indigo-700" x-text="held.invoiceNo"></span>
                                        <span class="text-[10px] font-bold text-slate-400" x-text="'• Held at ' + held.heldAt"></span>
                                        <span class="px-1.5 py-0.2 bg-amber-100 text-amber-900 text-[10px] font-bold border border-amber-300">Held</span>
                                    </div>
                                    <div class="text-xs font-bold text-slate-900">
                                        Client: <span x-text="held.clientName"></span>
                                    </div>
                                    <div class="text-[11px] text-slate-500 font-medium">
                                        <span x-text="(held.items ? held.items.length : 0) + ' product(s)'"></span> &bull;
                                        Total: <strong class="text-slate-900">PKR <span x-text="held.totalAmount ? held.totalAmount.toLocaleString() : '0'"></span></strong>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
                                    <button type="button" @click="resumeHeldBill(held)" 
                                            class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs rounded-none transition-colors flex items-center gap-1.5 shadow-2xs">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        <span>Resume</span>
                                    </button>
                                    <button type="button" @click="discardHeldBill(held.id)" 
                                            class="px-3 py-2 bg-white hover:bg-rose-50 text-rose-700 hover:text-rose-800 font-bold text-xs rounded-none border border-slate-300 hover:border-rose-300 transition-colors">
                                        <span>Discard</span>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <div class="flex items-center justify-end pt-3 border-t border-slate-100">
                    <button type="button" @click="heldBillsModalOpen = false" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold text-xs rounded-none hover:bg-slate-200">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- VIEW SALE DETAILS MODAL -->
    <div x-show="viewModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-xs" @click="viewModalOpen = false"></div>
            <div class="inline-block w-full max-w-xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-none border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <h3 class="text-base font-extrabold text-slate-900 heading-font" x-text="'Invoice #' + (selectedSale ? selectedSale.invoice_no : '')"></h3>
                    <button type="button" @click="viewModalOpen = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <template x-if="selectedSale">
                    <div class="space-y-4 text-xs">
                        <div class="grid grid-cols-3 gap-3 bg-slate-50 p-3 border border-slate-200">
                            <div>
                                <span class="text-slate-400 font-bold block text-[10px]">CUSTOMER:</span>
                                <span class="font-extrabold text-slate-900" x-text="selectedSale.customer ? selectedSale.customer.name : 'Walk-in Customer'"></span>
                            </div>
                            <div>
                                <span class="text-slate-400 font-bold block text-[10px]">SELLING STORE:</span>
                                <span class="font-extrabold text-indigo-700" x-text="selectedSale.store ? selectedSale.store.name : 'Main Store'"></span>
                            </div>
                            <div>
                                <span class="text-slate-400 font-bold block text-[10px]">SALE DATE:</span>
                                <span class="font-bold text-slate-800" x-text="selectedSale.sale_date ? selectedSale.sale_date.split('T')[0] : ''"></span>
                            </div>
                        </div>

                        <!-- Items Table -->
                        <div class="border border-slate-200">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-black text-[10px]">
                                        <th class="py-2 px-3">Item</th>
                                        <th class="py-2 px-3 text-center">Qty</th>
                                        <th class="py-2 px-3 text-right">Price</th>
                                        <th class="py-2 px-3 text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 font-bold">
                                    <template x-for="item in selectedSale.items" :key="item.id">
                                        <tr>
                                            <td class="py-2 px-3 text-slate-900" x-text="item.product ? item.product.title : 'Product'"></td>
                                            <td class="py-2 px-3 text-center" x-text="item.quantity"></td>
                                            <td class="py-2 px-3 text-right text-slate-600" x-text="'PKR ' + Number(item.unit_price).toLocaleString()"></td>
                                            <td class="py-2 px-3 text-right font-black text-slate-900" x-text="'PKR ' + Number(item.subtotal).toLocaleString()"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div class="bg-slate-50 p-3 border border-slate-200 space-y-1.5 text-xs font-bold">
                            <div class="flex items-center justify-between">
                                <span>Total Net Bill:</span>
                                <span class="font-black text-indigo-700">PKR <span x-text="Number(selectedSale.total_amount).toLocaleString()"></span></span>
                            </div>
                            <div class="flex items-center justify-between text-emerald-700">
                                <span>Paid Amount:</span>
                                <span class="font-black">PKR <span x-text="Number(selectedSale.received_amount).toLocaleString()"></span></span>
                            </div>
                            <div class="flex items-center justify-between" :class="selectedSale.balance_due > 0 ? 'text-rose-600' : 'text-slate-600'">
                                <span>Remaining Balance Due:</span>
                                <span class="font-black">PKR <span x-text="Number(selectedSale.balance_due).toLocaleString()"></span></span>
                            </div>
                        </div>
                    </div>
                </template>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="window.print()" class="px-4 py-2 bg-slate-900 text-white font-bold text-xs rounded-none hover:bg-slate-800">
                        Print Receipt
                    </button>
                    <button type="button" @click="viewModalOpen = false" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold text-xs rounded-none hover:bg-slate-200">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
