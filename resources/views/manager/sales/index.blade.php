@extends('layouts.material')

@section('title', 'Product POS & Sales Terminal')

@section('content')
@php
    $brandSettings = \App\Models\Setting::getSettings();
@endphp
<style>
    /* Remove number spinner arrows so quantity text is fully visible */
    input[type=number]::-webkit-inner-spin-button,
    input[type=number]::-webkit-outer-spin-button {
        -webkit-appearance: none !important;
        margin: 0 !important;
    }
    input[type=number] {
        -moz-appearance: textfield !important;
    }

    @page {
        size: auto;
        margin: 0;
    }

    /* POS 80mm Thermal Receipt Print Rules */
    @media print {
        aside, nav, header, footer, .sidebar, #posSaleForm, [x-show="activeTab === 'history'"], .print\:hidden {
            display: none !important;
        }
        body, html {
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
            overflow: visible !important;
            height: auto !important;
        }
        body.pos-receipt-active * {
            visibility: hidden !important;
        }
        body.pos-receipt-active #posReceiptPrintMount,
        body.pos-receipt-active #posReceiptPrintMount *,
        body.pos-receipt-active .pos-receipt-80mm-container,
        body.pos-receipt-active .pos-receipt-80mm-container * {
            visibility: visible !important;
        }
        body.pos-receipt-active #posReceiptPrintMount {
            display: block !important;
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            margin-left: 0 !important;
            padding: 0 1.5mm !important;
            background: #ffffff !important;
            z-index: 999999 !important;
            box-sizing: border-box !important;
        }
    }
</style>
<script>
function posSaleTerminal() {
    return {
        activeTab: 'pos',
        brandSettings: {
            name: @json($brandSettings->brand_name ?? 'Jugnu Saloon'),
            logo: @json($brandSettings->brand_logo ? asset($brandSettings->brand_logo) : ''),
            slogan: @json($brandSettings->brand_slogan ?? ''),
            address: @json($brandSettings->brand_address ?? ''),
            phone1: @json($brandSettings->brand_phone1 ?? ''),
            phone2: @json($brandSettings->brand_phone2 ?? ''),
            posReceiptNote: @json($brandSettings->pos_receipt_note ?? '')
        }, // 'pos' or 'history'
    
    // Backend Data Lists
    customersList: @json($customers),
    productsList: @json($products),
    productCategoriesList: @json($productCategories ?? []),
    storesList: @json($stores),
    salesList: @json($sales->items()),
    selectedStoreId: '{{ $defaultStore->id ?? ($stores->first()->id ?? '') }}',

    isIssueStockStore() {
        const store = (this.storesList || []).find(s => s.id == this.selectedStoreId);
        if (!store) return false;
        const name = (store.name || '').toUpperCase().trim();
        const code = (store.code || '').toUpperCase().trim();
        return name === 'ISSUE STOCK' || code === 'ISSUE STOCK';
    },

    getProductStoreStock(prod) {
        if (!prod) return 0;
        if (prod.store_stocks && Array.isArray(prod.store_stocks)) {
            const found = prod.store_stocks.find(s => s.store_id == this.selectedStoreId);
            if (found) return parseInt(found.stock) || 0;
        }
        return parseInt(prod.stock) || 0;
    },

    onStoreChange() {
        const isIssue = this.isIssueStockStore();
        this.items.forEach(it => {
            it.stock = this.getProductStoreStock(it.product_obj);
            it.price = this.getProductSalePrice(it.product_obj);
        });
        if (isIssue) {
            this.billDiscount = 0;
            this.discountPercentage = 0;
            this.items.forEach(it => {
                it.discount = 0;
            });
        }
        this.recalculateDiscount();
        this.recalculateTotals();
    },
    
    // Selected Client State (Default: Walk-in Customer)
    selectedCustomerId: '{{ $defaultCustomer->id ?? ($customers->first()->id ?? '') }}',
    selectedCustomerObj: @json($defaultCustomer ?? ($customers->first() ?? null)),
    customerModalOpen: false,
    customerSearchQuery: '',
    
    // Product Search & Filter State
    activeCategory: 'All Products',
    activeCategoryId: null,
    productSearchQuery: '',
    
    // Invoice Parameters
    invoiceNo: 'INV-{{ date('Ym') }}-{{ str_pad(\App\Models\Sale::count() + 1, 4, '0', STR_PAD_LEFT) }}',
    saleDate: '{{ date('Y-m-d') }}',
    notes: '',
    
    // Bill Editing & Settlement State
    editingSaleId: null,
    previouslyPaidAmount: 0,
    additionalPayment: 0,

    // Cart Items: [{ product_id, product_obj, title, price, quantity, stock }]
    items: [],
    
    // User & Discount Permission State
    isAdminUser: {{ auth()->user()->hasRole('admin') ? 'true' : 'false' }},
    hasDiscountPermission: {{ (auth()->user()->hasRole('admin') || auth()->user()->canGiveDiscount()) ? 'true' : 'false' }},
    adminAuthModalOpen: false,
    adminPassword: '',
    adminAuthorized: false,

    // Discount & Payment State
    discountType: 'percentage', // 'percentage' or 'fixed'
    discountPercentage: 0,
    billDiscount: 0,
    paymentMode: 'Cash', // 'Cash', 'Card', 'Bank', 'Other', 'Split'
    splitPayments: { Cash: 0, Card: 0, Bank: 0, Other: 0 },
    extraPercentage: 0,  // Card / Bank Processing Surcharge (%)
    extraAmount: 0,      // Calculated Surcharge (PKR)
    receivedAmount: '',      // Empty by default so user enters explicitly,
    
    // Held Bills State
    heldBillsModalOpen: false,
    heldBills: [],
    
    // View Historical Sale Modal State
    viewModalOpen: false,
    selectedSale: null,

    // 80mm Thermal Receipt State
    receiptModalOpen: false,
    receiptData: null,

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

    // Effective Sales Price Calculation (0 if ISSUE STOCK, otherwise Discounted Price)
    getProductSalePrice(prod) {
        if (!prod) return 0;

        // If current store is "ISSUE STOCK", set sale rate to 0
        if (this.isIssueStockStore()) {
            return 0;
        }

        const price = parseFloat(prod.price) || 0;
        const discountPct = parseFloat(prod.discount) || 0;
        const discountedPrice = parseFloat(prod.discounted_price) || 0;

        if (prod.sale_price !== undefined && parseFloat(prod.sale_price) > 0) {
            return parseFloat(prod.sale_price);
        }
        if (discountedPrice > 0 && discountedPrice < price) {
            return discountedPrice;
        }
        if (discountPct > 0 && discountPct <= 100) {
            return Math.round((price - (price * (discountPct / 100))) * 100) / 100;
        }
        if (discountedPrice > 0) {
            return discountedPrice;
        }
        return price;
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
        if (this.activeCategoryId) {
            list = list.filter(p => p.product_category_id == this.activeCategoryId);
        }
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
                price: this.getProductSalePrice(prod),
                stock: this.getProductStoreStock(prod),
                discount: 0,
                discount_type: 'fixed'
            });
        }
        this.recalculateDiscount();
    },
    incrementProduct(prod, event) {
        if (event) event.stopPropagation();
        if (!prod) return;
        const pId = prod.id || prod.product_id;
        if (!pId) return;
        const item = this.items.find(it => it.product_id == pId);
        if (item) {
            item.quantity = (parseInt(item.quantity) || 0) + 1;
        } else {
            const origProd = prod.id ? prod : (prod.product_obj || this.productsList.find(p => p.id == pId));
            if (origProd) {
                this.items.push({
                    product_id: origProd.id,
                    product_obj: origProd,
                    title: origProd.title,
                    quantity: 1,
                    price: this.getProductSalePrice(origProd),
                    stock: this.getProductStoreStock(origProd),
                    discount: 0,
                    discount_type: 'fixed'
                });
            }
        }
        this.recalculateDiscount();
    },
    decrementProduct(prod, event) {
        if (event) event.stopPropagation();
        if (!prod) return;
        const pId = prod.id || prod.product_id;
        if (!pId) return;
        const index = this.items.findIndex(it => it.product_id == pId);
        if (index > -1) {
            if ((parseInt(this.items[index].quantity) || 1) > 1) {
                this.items[index].quantity = (parseInt(this.items[index].quantity) || 1) - 1;
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

    // Single Product Discount Helpers
    getItemDiscountAmount(item) {
        if (!item || this.isIssueStockStore()) return 0;
        const price = parseFloat(item.price) || 0;
        const discVal = parseFloat(item.discount) || 0;
        if (discVal <= 0 || price <= 0) return 0;
        if (item.discount_type === 'percentage') {
            const pct = Math.min(100, Math.max(0, discVal));
            return Math.round(price * (pct / 100) * 100) / 100;
        }
        return Math.min(price, Math.max(0, discVal));
    },
    getItemNetUnitPrice(item) {
        if (!item) return 0;
        const price = parseFloat(item.price) || 0;
        return Math.max(0, Math.round((price - this.getItemDiscountAmount(item)) * 100) / 100);
    },
    getItemLineSubtotal(item) {
        if (!item) return 0;
        const qty = parseInt(item.quantity) || 1;
        return Math.max(0, Math.round(this.getItemNetUnitPrice(item) * qty * 100) / 100);
    },
    getRawSubtotal() {
        return this.items.reduce((sum, item) => sum + ((parseFloat(item.price) || 0) * (parseInt(item.quantity) || 1)), 0);
    },
    getItemDiscountsTotal() {
        if (this.isIssueStockStore()) return 0;
        return this.items.reduce((sum, item) => sum + (this.getItemDiscountAmount(item) * (parseInt(item.quantity) || 1)), 0);
    },

    // Calculations
    getSubtotal() {
        return this.items.reduce((sum, item) => sum + this.getItemLineSubtotal(item), 0);
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
        // Do NOT auto-populate receivedAmount: user will enter payment explicitly
    },
    getCalculatedDiscount() {
        if (this.isIssueStockStore()) {
            return 0;
        }
        const gross = this.getSubtotal();
        if (this.discountType === 'percentage') {
            const pct = Math.min(100, Math.max(0, parseFloat(this.discountPercentage) || 0));
            return Math.round(gross * (pct / 100));
        }
        return Math.max(0, parseFloat(this.billDiscount) || 0);
    },
    getExtraFee() {
        if (this.paymentMode === 'Card' || this.paymentMode === 'Bank') {
            const netBeforeExtra = Math.max(0, this.getSubtotal() - this.getCalculatedDiscount());
            const pct = Math.max(0, parseFloat(this.extraPercentage || 0));
            return Math.round(((netBeforeExtra * pct) / 100) * 100) / 100;
        }
        if (this.paymentMode === 'Split') {
            const cardBankSplit = (parseFloat(this.splitPayments.Card) || 0) + (parseFloat(this.splitPayments.Bank) || 0);
            const pct = Math.max(0, parseFloat(this.extraPercentage || 0));
            return Math.round(((cardBankSplit * pct) / 100) * 100) / 100;
        }
        return 0;
    },
    getSplitTotal() {
        return Math.round(
            (parseFloat(this.splitPayments.Cash) || 0) +
            (parseFloat(this.splitPayments.Card) || 0) +
            (parseFloat(this.splitPayments.Bank) || 0) +
            (parseFloat(this.splitPayments.Other) || 0)
        );
    },
    getSplitRemainder() {
        const netTotal = this.getTotalAmount();
        return Math.round(netTotal - this.getSplitTotal());
    },
    fillSplitRemainder(mode) {
        let otherSum = 0;
        for (const m in this.splitPayments) {
            if (m !== mode) {
                otherSum += (parseFloat(this.splitPayments[m]) || 0);
            }
        }
        const netTotal = this.getTotalAmount();
        this.splitPayments[mode] = Math.max(0, Math.round(netTotal - otherSum));
        this.recalculateTotals();
    },
    presetSplit(mode1, mode2) {
        const total = this.getTotalAmount();
        const half = Math.round(total / 2);
        const otherHalf = total - half;
        this.splitPayments = { Cash: 0, Card: 0, Bank: 0, Other: 0 };
        this.splitPayments[mode1] = half;
        this.splitPayments[mode2] = otherHalf;
        this.recalculateTotals();
    },
    clearSplitPayments() {
        this.splitPayments = { Cash: 0, Card: 0, Bank: 0, Other: 0 };
        this.recalculateTotals();
    },
    getTotalAmount() {
        const total = this.getSubtotal() - this.getCalculatedDiscount() + this.getExtraFee();
        return Math.max(0, Math.round(total));
    },

    // Clearance and Payment Calculations
    getAlreadyPaid() {
        return parseFloat(this.previouslyPaidAmount || 0);
    },
    getInitialPendingBeforePay() {
        const total = this.getTotalAmount();
        const alreadyPaid = this.getAlreadyPaid();
        return Math.max(0, Math.round(total - alreadyPaid));
    },
    getTotalPaidAmount() {
        if (this.paymentMode === 'Split') {
            return this.getSplitTotal();
        }
        if (this.editingSaleId) {
            const alreadyPaid = this.getAlreadyPaid();
            const addPay = parseFloat(this.additionalPayment || 0);
            return Math.round(alreadyPaid + addPay);
        }
        return Math.round(parseFloat(this.receivedAmount || 0));
    },
    getRemainingBalance() {
        const total = this.getTotalAmount();
        const totalPaid = this.getTotalPaidAmount();
        return Math.max(0, Math.round(total - totalPaid));
    },
    getChangeReturn() {
        const total = this.getTotalAmount();
        const totalPaid = this.getTotalPaidAmount();
        return Math.max(0, Math.round(totalPaid - total));
    },
    clearFullRemainingBalance() {
        if (this.paymentMode === 'Split') {
            this.fillSplitRemainder('Cash');
            return;
        }
        if (this.editingSaleId) {
            this.additionalPayment = this.getInitialPendingBeforePay();
        } else {
            this.receivedAmount = this.getTotalAmount();
        }
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
            splitPayments: JSON.parse(JSON.stringify(this.splitPayments)),
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
        this.splitPayments = record.splitPayments ? JSON.parse(JSON.stringify(record.splitPayments)) : { Cash: 0, Card: 0, Bank: 0, Other: 0 };
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

    // Helper to find a sale by its ID from current page items
    getSaleById(id) {
        return this.salesList.find(s => s.id == id);
    },

    // Inspect historical sale in modal
    inspectSale(saleOrId) {
        const sale = (typeof saleOrId === 'object' && saleOrId !== null) ? saleOrId : this.getSaleById(saleOrId);
        if (sale) {
            this.selectedSale = sale;
            this.viewModalOpen = true;
        }
    },

    // Load Existing Sale for Editing & Remaining Balance Settlement
    loadSaleForEdit(saleOrId) {
        const sale = (typeof saleOrId === 'object' && saleOrId !== null) ? saleOrId : this.getSaleById(saleOrId);
        if (!sale) return;

        this.editingSaleId = sale.id;
        this.invoiceNo = sale.invoice_no;
        this.saleDate = sale.sale_date ? (typeof sale.sale_date === 'string' ? sale.sale_date.split('T')[0].split(' ')[0] : sale.sale_date) : '{{ date('Y-m-d') }}';
        this.selectedStoreId = sale.store_id || '{{ $defaultStore->id ?? ($stores->first()->id ?? '') }}';
        this.selectedCustomerId = sale.account_id;
        this.selectedCustomerObj = sale.customer || this.customersList.find(c => c.id == sale.account_id) || null;
        this.notes = sale.notes || '';
        this.paymentMode = sale.payment_mode || 'Cash';
        if (sale.payment_mode === 'Split' && sale.split_payments) {
            const sp = (typeof sale.split_payments === 'string') ? JSON.parse(sale.split_payments) : sale.split_payments;
            this.splitPayments = {
                Cash: parseFloat(sp.Cash) || 0,
                Card: parseFloat(sp.Card) || 0,
                Bank: parseFloat(sp.Bank) || 0,
                Other: parseFloat(sp.Other) || 0
            };
        } else {
            this.splitPayments = { Cash: 0, Card: 0, Bank: 0, Other: 0 };
        }
        
        const netBeforeExtra = Math.max(0, (parseFloat(sale.total_amount) || 0) - (parseFloat(sale.discount) || 0));
        const extraAmt = parseFloat(sale.extra_amount) || 0;
        if (extraAmt > 0 && netBeforeExtra > 0) {
            this.extraPercentage = Math.round((extraAmt / netBeforeExtra) * 100 * 10) / 10;
        } else {
            this.extraPercentage = 0;
        }

        this.previouslyPaidAmount = parseFloat(sale.received_amount) || 0;
        this.additionalPayment = 0;
        this.receivedAmount = parseFloat(sale.received_amount) || 0;

        this.items = [];
        if (sale.items && sale.items.length > 0) {
            sale.items.forEach(it => {
                const prod = this.productsList.find(p => p.id == it.product_id) || it.product || { id: it.product_id, title: it.product ? it.product.title : 'Product' };
                this.items.push({
                    product_id: it.product_id,
                    product_obj: prod,
                    title: prod.title || (it.product ? it.product.title : 'Product'),
                    quantity: parseInt(it.quantity) || 1,
                    price: this.isIssueStockStore() ? 0 : (parseFloat(it.unit_price) || this.getProductSalePrice(prod)),
                    stock: this.getProductStoreStock(prod),
                    discount: parseFloat(it.discount) || 0,
                    discount_type: it.discount_type || 'fixed'
                });
            });
        }

        this.billDiscount = parseFloat(sale.discount) || 0;
        const sub = this.getSubtotal();
        if (sub > 0 && this.billDiscount > 0) {
            this.discountPercentage = Math.round((this.billDiscount / sub) * 100);
            this.discountType = 'fixed';
        } else {
            this.discountType = 'percentage';
            this.discountPercentage = 0;
        }

        this.activeTab = 'pos';
        this.viewModalOpen = false;
    },

    // Reset Terminal
    resetToNewBill() {
        this.editingSaleId = null;
        this.previouslyPaidAmount = 0;
        this.additionalPayment = 0;
        this.invoiceNo = 'INV-{{ date('Ym') }}-{{ str_pad(\App\Models\Sale::count() + 1, 4, '0', STR_PAD_LEFT) }}';
        this.saleDate = '{{ date('Y-m-d') }}';
        this.items = [];
        this.notes = '';
        this.discountPercentage = 0;
        this.billDiscount = 0;
        this.receivedAmount = '';
        this.extraPercentage = 0;
        this.extraAmount = 0;
        this.paymentMode = 'Cash';
        this.splitPayments = { Cash: 0, Card: 0, Bank: 0, Other: 0 };
        this.selectedCustomerId = '{{ $defaultCustomer->id ?? ($customers->first()->id ?? '') }}';
        this.selectedCustomerObj = @json($defaultCustomer ?? ($customers->first() ?? null));
        this.activeTab = 'pos';
    },

    // Generate and Prepare 80mm Receipt Data for either an existing sale or the active cart
    generateReceiptData(saleOrId = null) {
        if (saleOrId) {
            const sale = (typeof saleOrId === 'object' && saleOrId !== null) ? saleOrId : this.getSaleById(saleOrId);
            const storeObj = sale.store || (this.storesList ? this.storesList.find(s => s.id == sale.store_id) : null);
            const isSaleIssue = storeObj && (((storeObj.name || '').toUpperCase().trim() === 'ISSUE STOCK') || ((storeObj.code || '').toUpperCase().trim() === 'ISSUE STOCK'));

            const receiptItems = (sale.items || []).map(it => {
                const prod = (it.product) || (this.productsList ? this.productsList.find(p => p.id == it.product_id) : null);
                const qty = parseInt(it.quantity) || 1;
                const unitPrice = isSaleIssue ? 0 : (parseFloat(it.unit_price) || 0);
                const origPrice = isSaleIssue ? 0 : (prod ? (parseFloat(prod.price) || unitPrice) : unitPrice);
                const discAmt = isSaleIssue ? 0 : (Math.max(0, origPrice - unitPrice) * qty);
                const subtotal = isSaleIssue ? 0 : ((it.subtotal && parseFloat(it.subtotal) > 0) ? parseFloat(it.subtotal) : (unitPrice * qty));
                return {
                    title: prod ? prod.title : (it.title || 'Product'),
                    quantity: qty,
                    price: origPrice > 0 ? origPrice : unitPrice,
                    unitPrice: unitPrice,
                    discount: discAmt,
                    discountText: discAmt > 0 ? `PKR ${discAmt.toLocaleString()} OFF` : '',
                    total: subtotal
                };
            });

            const itemDiscountsTotal = receiptItems.reduce((acc, it) => acc + (it.discount || 0), 0);
            let rawSubtotal = receiptItems.reduce((acc, it) => acc + (it.price * it.quantity), 0);
            const billDisc = parseFloat(sale.discount) || 0;
            const totalDisc = itemDiscountsTotal + billDisc;

            // If overall bill discount exists but item discounts are 0, allocate across items so DISC column shows the discount
            if (itemDiscountsTotal === 0 && billDisc > 0 && receiptItems.length > 0) {
                let remainingDisc = billDisc;
                receiptItems.forEach((it, idx) => {
                    if (idx === receiptItems.length - 1) {
                        it.discount = remainingDisc;
                    } else {
                        const itemBaseTotal = it.price * it.quantity;
                        const itemShare = rawSubtotal > 0 ? Math.round((itemBaseTotal / rawSubtotal) * billDisc) : 0;
                        it.discount = itemShare;
                        remainingDisc -= itemShare;
                    }
                    if (it.discount > 0) {
                        it.discountText = `PKR ${Number(it.discount).toLocaleString()} OFF`;
                        it.total = Math.max(0, (it.price * it.quantity) - it.discount);
                    }
                });
            }

            const extra = parseFloat(sale.extra_amount) || 0;
            const net = (sale.total_amount !== undefined) ? parseFloat(sale.total_amount) : (rawSubtotal - totalDisc + extra);
            const received = parseFloat(sale.received_amount) || 0;
            const due = (sale.balance_due !== undefined) ? parseFloat(sale.balance_due) : Math.max(0, net - received);
            const change = Math.max(0, received - net);

            let bDate = '{{ date('Y-m-d') }}';
            let bTime = '{{ date('h:i A') }}';
            if (sale.sale_date) {
                bDate = typeof sale.sale_date === 'string' ? sale.sale_date.split('T')[0].split(' ')[0] : sale.sale_date;
            }
            if (sale.created_at) {
                const d = new Date(sale.created_at);
                if (!isNaN(d)) {
                    bTime = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                }
            }

            const custObj = sale.customer || (this.customersList ? this.customersList.find(c => c.id == sale.account_id) : null);

            return {
                billNo: sale.invoice_no || ('#' + sale.id),
                billDate: bDate,
                billTime: bTime,
                storeName: storeObj ? storeObj.name : '{{ $defaultStore->name ?? 'Main Store' }}',
                customerName: custObj ? custObj.name : 'Walk-in Client',
                customerPhone: custObj ? (custObj.phone_no1 || '') : '',
                items: receiptItems,
                subtotal: rawSubtotal > 0 ? rawSubtotal : net + totalDisc - extra,
                productDiscount: itemDiscountsTotal,
                billDiscount: billDisc,
                discount: totalDisc,
                discountPercentage: (totalDisc > 0 && rawSubtotal > 0) ? Math.round((totalDisc / rawSubtotal) * 100) : 0,
                extraAmount: extra,
                extraPercentage: (extra > 0 && (rawSubtotal - totalDisc) > 0) ? Math.round((extra / (rawSubtotal - totalDisc)) * 100) : 0,
                netAmount: net,
                paidAmount: received,
                balanceDue: due,
                changeReturn: change,
                paymentMode: sale.payment_mode || 'Cash',
                splitPayments: sale.split_payments ? (typeof sale.split_payments === 'string' ? JSON.parse(sale.split_payments) : sale.split_payments) : null,
                notes: sale.notes || '',
                generatedBy: '{{ addslashes(auth()->user()->name ?? 'Staff') }}'
            };
        } else {
            if (this.items.length === 0) {
                alert('Please select at least one product before generating a receipt.');
                return null;
            }

            const isCartIssue = this.isIssueStockStore();
            const cartItems = this.items.map(it => {
                const qty = parseInt(it.quantity) || 1;
                const unitPrice = isCartIssue ? 0 : (parseFloat(it.price) || 0);
                const unitDisc = isCartIssue ? 0 : this.getItemDiscountAmount(it);
                const discAmt = unitDisc * qty;
                const netPrice = isCartIssue ? 0 : Math.max(0, unitPrice - unitDisc);
                const lineSubtotal = isCartIssue ? 0 : (netPrice * qty);
                return {
                    title: it.title || (it.product_obj ? it.product_obj.title : 'Product'),
                    quantity: qty,
                    price: unitPrice,
                    unitPrice: netPrice,
                    discount: discAmt,
                    discountText: discAmt > 0 ? `PKR ${discAmt.toLocaleString()} OFF` : '',
                    total: lineSubtotal
                };
            });

            const itemDiscountsTotal = cartItems.reduce((acc, it) => acc + (it.discount || 0), 0);
            let rawSubtotal = cartItems.reduce((acc, it) => acc + (it.price * it.quantity), 0);
            const billDisc = this.getCalculatedDiscount();
            const totalDisc = itemDiscountsTotal + billDisc;

            // If overall bill discount exists but item discounts are 0, allocate across items so DISC column shows the discount
            if (itemDiscountsTotal === 0 && billDisc > 0 && cartItems.length > 0) {
                let remainingDisc = billDisc;
                cartItems.forEach((it, idx) => {
                    if (idx === cartItems.length - 1) {
                        it.discount = remainingDisc;
                    } else {
                        const itemBaseTotal = it.price * it.quantity;
                        const itemShare = rawSubtotal > 0 ? Math.round((itemBaseTotal / rawSubtotal) * billDisc) : 0;
                        it.discount = itemShare;
                        remainingDisc -= itemShare;
                    }
                    if (it.discount > 0) {
                        it.discountText = `PKR ${Number(it.discount).toLocaleString()} OFF`;
                        it.total = Math.max(0, (it.price * it.quantity) - it.discount);
                    }
                });
            }

            const storeObj = this.storesList ? this.storesList.find(s => s.id == this.selectedStoreId) : null;

            return {
                billNo: this.editingSaleId ? ('#EDIT-' + this.editingSaleId) : this.invoiceNo,
                billDate: this.saleDate,
                billTime: '{{ date('h:i A') }}',
                storeName: storeObj ? storeObj.name : '{{ $defaultStore->name ?? 'Main Store' }}',
                customerName: this.selectedCustomerObj ? this.selectedCustomerObj.name : 'Walk-in Client',
                customerPhone: this.selectedCustomerObj ? (this.selectedCustomerObj.phone_no1 || '') : '',
                items: cartItems,
                subtotal: rawSubtotal,
                productDiscount: itemDiscountsTotal,
                billDiscount: billDisc,
                discount: totalDisc,
                discountPercentage: this.discountPercentage || ((totalDisc > 0 && rawSubtotal > 0) ? Math.round((totalDisc / rawSubtotal) * 100) : 0),
                extraAmount: this.getExtraFee(),
                extraPercentage: parseFloat(this.extraPercentage || 0),
                netAmount: this.getTotalAmount(),
                paidAmount: this.getTotalPaidAmount(),
                balanceDue: this.getRemainingBalance(),
                changeReturn: this.getChangeReturn(),
                paymentMode: this.paymentMode,
                splitPayments: this.paymentMode === 'Split' ? JSON.parse(JSON.stringify(this.splitPayments)) : null,
                notes: this.notes,
                generatedBy: '{{ addslashes(auth()->user()->name ?? 'Staff') }}'
            };
        }
    },

    openReceiptModal(saleOrId = null) {
        const data = this.generateReceiptData(saleOrId);
        if (data) {
            this.receiptData = data;
            this.receiptModalOpen = true;
        }
    },

    buildReceiptPrintDocument(data) {
        return (typeof window.buildProductReceiptDocument === 'function') 
            ? window.buildProductReceiptDocument(data, this.brandSettings) 
            : '';
    },

    printReceiptHtml(data, callback = null) {
        if (!data) return;

        let iframe = document.getElementById('pos80mmReceiptIframe');
        if (!iframe) {
            iframe = document.createElement('iframe');
            iframe.id = 'pos80mmReceiptIframe';
            iframe.style.position = 'fixed';
            iframe.style.right = '0';
            iframe.style.bottom = '0';
            iframe.style.width = '0';
            iframe.style.height = '0';
            iframe.style.border = '0';
            iframe.style.visibility = 'hidden';
            document.body.appendChild(iframe);
        }

        const doc = iframe.contentWindow.document;
        doc.open();
        doc.write(this.buildReceiptPrintDocument(data));
        doc.close();

        const triggerPrint = () => {
            try {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
            } catch (err) {
                console.warn('Iframe print fallback to window.print():', err);
                document.body.classList.add('pos-receipt-active');
                window.print();
                setTimeout(() => {
                    document.body.classList.remove('pos-receipt-active');
                }, 1000);
            }
            if (callback && typeof callback === 'function') {
                setTimeout(callback, 800);
            }
        };

        const imgEl = doc.querySelector('img');
        if (imgEl && !imgEl.complete) {
            imgEl.onload = triggerPrint;
            imgEl.onerror = triggerPrint;
            setTimeout(triggerPrint, 800);
        } else {
            setTimeout(triggerPrint, 350);
        }
    },

    print80mmReceiptDirect(saleOrId = null) {
        let data = null;
        if (saleOrId) {
            data = this.generateReceiptData(saleOrId);
            if (data) this.receiptData = data;
        } else if (this.receiptData) {
            data = this.receiptData;
        } else {
            data = this.generateReceiptData(null);
            if (data) this.receiptData = data;
        }

        if (!data) return;
        this.printReceiptHtml(data);
    },

    authorizeAdmin(shouldSubmit = false) {
        if (!this.adminPassword || this.adminPassword.trim() === '') {
            alert('Please enter the Admin Password.');
            return;
        }
        this.adminAuthorized = true;
        this.adminAuthModalOpen = false;
        if (shouldSubmit) {
            this.submitForm(false);
        }
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

        if (this.paymentMode === 'Split') {
            const splitTotal = this.getSplitTotal();
            const totalAmt = this.getTotalAmount();
            if (splitTotal <= 0) {
                alert('Please allocate the split payment amounts before completing the sale.');
                return;
            }
            if (Math.abs(splitTotal - totalAmt) > 0.01) {
                if (!confirm(`Total split allocation (PKR ${splitTotal.toLocaleString()}) does not match total bill (PKR ${totalAmt.toLocaleString()}). Do you wish to continue with a partial payment / remaining balance?`)) {
                    return;
                }
            }
        }

        // Check discount restrictions
        const calcDiscount = this.getCalculatedDiscount();
        if (calcDiscount > 0 && !this.isAdminUser && !this.adminAuthorized) {
            if (!this.hasDiscountPermission && (!this.adminPassword || this.adminPassword.trim() === '')) {
                this.adminAuthModalOpen = true;
                return;
            }
            if (this.hasDiscountPermission && this.discountPercentage > 10 && (!this.adminPassword || this.adminPassword.trim() === '')) {
                this.adminAuthModalOpen = true;
                return;
            }
        }

        const form = document.getElementById('posSaleForm');
        if (isPrint) {
            const data = this.generateReceiptData();
            if (!data) return;
            this.receiptData = data;
            this.printReceiptHtml(data, () => {
                form.submit();
            });
            return;
        }
        form.submit();
    }
    };
}
</script>
<div x-data="posSaleTerminal()" class="space-y-6">

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
        <div class="flex flex-wrap items-center gap-2">
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

            <div class="inline-flex flex-wrap sm:flex-nowrap p-1 bg-slate-100 rounded-none border border-slate-200">
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
        
        <form id="posSaleForm" method="POST" :action="editingSaleId ? ('{{ url('manager/sales') }}/' + editingSaleId) : '{{ route('manager.sales.store') }}'">
            @csrf
            <template x-if="editingSaleId">
                <input type="hidden" name="_method" value="PUT">
            </template>

            <!-- Hidden Form Payloads -->
            <input type="hidden" name="account_id" :value="selectedCustomerId">
            <input type="hidden" name="store_id" :value="selectedStoreId">
            <input type="hidden" name="sale_date" :value="saleDate">
            <input type="hidden" name="discount" :value="getCalculatedDiscount()">
            <input type="hidden" name="received_amount" :value="editingSaleId ? getTotalPaidAmount() : (receivedAmount || 0)">
            <input type="hidden" name="additional_payment" :value="editingSaleId ? (additionalPayment || 0) : 0">
            <input type="hidden" name="payment_mode" :value="paymentMode">
            <input type="hidden" name="split_payments" :value="paymentMode === 'Split' ? JSON.stringify(splitPayments) : ''">
            <input type="hidden" name="extra_amount" :value="getExtraFee()">
            <input type="hidden" name="notes" :value="notes">
            <input type="hidden" name="admin_password" :value="adminPassword">

            <!-- Itemized Products Array -->
            <template x-for="(item, idx) in items" :key="idx">
                <div>
                    <input type="hidden" :name="'items['+idx+'][product_id]'" :value="item.product_id">
                    <input type="hidden" :name="'items['+idx+'][quantity]'" :value="item.quantity">
                    <input type="hidden" :name="'items['+idx+'][unit_price]'" :value="item.price">
                    <input type="hidden" :name="'items['+idx+'][discount]'" :value="item.discount || 0">
                    <input type="hidden" :name="'items['+idx+'][discount_type]'" :value="item.discount_type || 'fixed'">
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
                            <template x-if="isIssueStockStore()">
                                <span class="px-2 py-0.5 bg-amber-400 text-slate-950 text-[10px] font-black uppercase tracking-wider flex items-center gap-1 shadow-sm">
                                    ⚡ ISSUE STOCK: Zero Price & Rate (PKR 0) &bull; Zero Discount &bull; Direct Stock Out
                                </span>
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

            <!-- EDITING SALE NOTICE BANNER -->
            <template x-if="editingSaleId">
                <div class="p-4 bg-amber-50 border-l-4 border-amber-500 border border-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs mb-5">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-amber-500 text-white flex items-center justify-center font-black text-sm shrink-0 shadow-xs">
                            ✏️
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-black text-amber-950 uppercase tracking-wide">Editing Sale Invoice</span>
                                <span class="px-2 py-0.5 bg-amber-200 text-amber-950 font-mono font-black text-xs" x-text="'#' + invoiceNo"></span>
                                <span class="px-2 py-0.5 bg-indigo-100 text-indigo-900 text-[10px] font-black uppercase">Edit Mode Active</span>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 text-xs text-amber-900 font-bold mt-1">
                                <span>Total Net Bill: <strong class="text-slate-900">PKR <span x-text="getTotalAmount().toLocaleString()"></span></strong></span>
                                <span>&bull;</span>
                                <span>Already Paid: <strong class="text-emerald-700 font-black">PKR <span x-text="Number(previouslyPaidAmount).toLocaleString()"></span></strong></span>
                                <span>&bull;</span>
                                <span :class="getInitialPendingBeforePay() > 0 ? 'text-rose-700 font-black' : 'text-emerald-700 font-bold'">
                                    Remaining Balance: PKR <span x-text="Number(getInitialPendingBeforePay()).toLocaleString()"></span>
                                </span>
                            </div>
                        </div>
                    </div>
                    <button type="button" @click="resetToNewBill()" 
                            class="px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-xs transition-colors shrink-0 flex items-center gap-1.5 cursor-pointer shadow-xs">
                        <span>&times;</span>
                        <span>Cancel Edit / New Bill</span>
                    </button>
                </div>
            </template>

            <!-- 3-Column Modern Grid (Responsive: 1 col on mobile, 2 col on tablet/laptop, 3 col on large desktop) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 xl:grid-cols-12 gap-5 items-start">

                <!-- LEFT COLUMN: Client Details & Sale Parameters (Col-Span-3 on XL, Col-Span-4 on LG, 1 col on mobile) -->
                <div class="col-span-1 lg:col-span-4 xl:col-span-3 space-y-5">
                    
                    <!-- BOX 1: CLIENT DETAILS (Hidden in Issue Stock mode) -->
                    <div x-show="!isIssueStockStore()" class="bg-white p-4 border border-slate-200 rounded-none space-y-3 shadow-xs">
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

                    <!-- BOX 1b: ISSUE STOCK DIRECT STOCK OUT NOTICE (Shown only in Issue Stock mode) -->
                    <div x-show="isIssueStockStore()" class="p-4 bg-amber-500/10 border-2 border-dashed border-amber-400 space-y-2 text-amber-950">
                        <div class="flex items-center gap-2">
                            <span class="text-base">⚡</span>
                            <span class="text-xs font-black uppercase tracking-wider text-amber-900">Direct Stock Out</span>
                        </div>
                        <p class="text-[11px] font-semibold text-amber-800 leading-snug">
                            Store: <strong class="font-black text-amber-950">ISSUE STOCK</strong>. Items are stocked out with purchase price 0 and sale price 0. Client details, discounts, and payments are bypassed.
                        </p>
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

                <!-- MIDDLE COLUMN: PRODUCT CATALOG GRID (Col-Span-5 on XL, Col-Span-8 on LG, 1 col on mobile) -->
                <div class="col-span-1 lg:col-span-8 xl:col-span-5 bg-white p-4 sm:p-5 border border-slate-200 rounded-none shadow-xs space-y-4">
                    
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

                    <!-- Category Pills Filter -->
                    <template x-if="productCategoriesList && productCategoriesList.length > 0">
                        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs">
                            <button type="button" 
                                    @click="activeCategoryId = null"
                                    :class="activeCategoryId === null ? 'bg-slate-900 text-white font-extrabold shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold'"
                                    class="px-3 py-1.5 rounded-none whitespace-nowrap text-[11px] transition-all cursor-pointer">
                                All Products
                            </button>
                            <template x-for="cat in productCategoriesList" :key="cat.id">
                                <button type="button" 
                                        @click="activeCategoryId = (activeCategoryId === cat.id ? null : cat.id)"
                                        :class="activeCategoryId === cat.id ? 'bg-indigo-600 text-white font-extrabold shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold'"
                                        class="px-3 py-1.5 rounded-none whitespace-nowrap text-[11px] transition-all flex items-center gap-1 cursor-pointer">
                                    <span>🏷️</span>
                                    <span x-text="cat.title"></span>
                                </button>
                            </template>
                        </div>
                    </template>

                    <!-- Products Grid Display (3 Columns) -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 pt-1 max-h-[620px] overflow-y-auto pr-1">
                        <template x-for="prod in getFilteredProducts()" :key="prod.id">
                            <div @click="toggleProduct(prod)"
                                 :class="isProductSelected(prod.id) ? 'ring-2 ring-indigo-600 border-indigo-600 bg-indigo-50/40' : 'border-slate-200 hover:border-indigo-400 bg-white'"
                                 class="border rounded-none p-3 cursor-pointer transition-all flex flex-col justify-between group shadow-2xs relative">
                                
                                <div class="space-y-2">
                                    <!-- Image Thumbnail with Aspect Ratio, Lazy Loading & Shimmer -->
                                    <div class="w-full aspect-[4/3] rounded-none overflow-hidden bg-slate-100 relative group/img"
                                         x-data="{ imgLoaded: false, imgError: false }">
                                        <!-- Shimmer Skeleton Placeholder -->
                                        <div x-show="!imgLoaded && !imgError" 
                                             class="absolute inset-0 bg-gradient-to-r from-slate-100 via-slate-200 to-slate-100 animate-pulse flex items-center justify-center">
                                            <svg class="w-6 h-6 text-slate-300 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                            </svg>
                                        </div>

                                        <img :src="getImageUrl(prod)" 
                                             loading="lazy"
                                             decoding="async"
                                             @load="imgLoaded = true"
                                             x-on:error="imgError = true; imgLoaded = true; $el.src = 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=500&q=80'"
                                             :class="imgLoaded ? 'opacity-100' : 'opacity-0'"
                                             class="w-full h-full object-cover group-hover:scale-105 transition-all duration-500 ease-out" 
                                             :alt="prod.title">

                                        <!-- Top Left High-Contrast Store-Specific Stock Badge -->
                                        <span class="absolute top-2 left-2 px-2 py-0.5 text-[10px] font-black rounded-none shadow-xs"
                                              :class="getProductStoreStock(prod) <= 0 ? 'bg-rose-600 text-white' : (getProductStoreStock(prod) <= 5 ? 'bg-amber-500 text-white' : 'bg-slate-900/85 text-white backdrop-blur-xs')"
                                              x-text="getProductStoreStock(prod) <= 0 ? 'Out of Stock' : ('Stock: ' + getProductStoreStock(prod))"></span>

                                        <!-- Top Right Discount % Badge (if discounted and not in issue stock mode) -->
                                        <template x-if="!isIssueStockStore() && (parseFloat(prod.discount) > 0 || (getProductSalePrice(prod) < (parseFloat(prod.price) || 0)))">
                                            <div class="absolute top-2 right-2 bg-rose-600 text-white px-2 py-0.5 text-[9px] font-black rounded-none shadow-md ring-1 ring-white/50">
                                                <span x-text="'-' + (parseFloat(prod.discount) > 0 ? prod.discount : Math.round((((parseFloat(prod.price)||0) - getProductSalePrice(prod)) / (parseFloat(prod.price)||1)) * 100)) + '% OFF'"></span>
                                            </div>
                                        </template>

                                        <!-- Active Cart Quantity Badge -->
                                        <div x-show="getProductCartQuantity(prod.id) > 0" 
                                             class="absolute bottom-2 right-2 bg-indigo-600 text-white px-2 py-0.5 text-[10px] font-black rounded-none shadow-md flex items-center gap-1 ring-1 ring-white/50">
                                            <span>✓ Qty:</span>
                                            <span x-text="getProductCartQuantity(prod.id)"></span>
                                        </div>
                                    </div>

                                    <!-- Product Title -->
                                    <h4 class="font-bold text-xs text-slate-900 leading-snug line-clamp-2" x-text="prod.title"></h4>
                                </div>

                                <!-- Bottom Price & Quantity Controls -->
                                <div class="mt-2.5 pt-2 border-t border-slate-100 space-y-2">
                                    <div x-show="!isIssueStockStore()" class="flex items-center justify-between">
                                        <span class="text-[10px] font-bold uppercase text-slate-400">Sale Price</span>
                                        <div class="text-right">
                                            <template x-if="parseFloat(prod.discount) > 0 || (getProductSalePrice(prod) < (parseFloat(prod.price) || 0))">
                                                <span class="line-through text-slate-400 text-[10px] font-bold block -mb-0.5 font-mono">
                                                    PKR <span x-text="Number(prod.price).toLocaleString()"></span>
                                                </span>
                                            </template>
                                            <p class="font-black text-xs font-mono text-emerald-700">
                                                PKR <span x-text="Number(getProductSalePrice(prod)).toLocaleString()"></span>
                                            </p>
                                        </div>
                                    </div>
                                    <div x-show="isIssueStockStore()" class="flex items-center justify-between">
                                        <span class="text-[10px] font-bold uppercase text-amber-700">Issue Rate</span>
                                        <p class="font-black text-xs font-mono text-amber-800">
                                            PKR 0
                                        </p>
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

                <!-- RIGHT COLUMN: BILL & CLEARANCE SUMMARY (Col-Span-4 on XL, Col-Span-12 on LG, 1 col on mobile) -->
                <div class="col-span-1 lg:col-span-12 xl:col-span-4 bg-white p-4 sm:p-5 border border-slate-200 rounded-none shadow-xs space-y-4">
                    
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-5 h-5 bg-indigo-600 text-white rounded-none text-xs font-black flex items-center justify-center">4</span>
                            <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">Bill & Clearance Summary</h3>
                        </div>
                        <span class="text-[11px] font-black text-indigo-700 bg-indigo-50 px-2 py-0.5 border border-indigo-200" 
                              x-text="items.length + ' Product(s)'"></span>
                    </div>

                    <!-- 4.1 Selected Line Items Table (with Sequence #) -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-xs font-bold text-slate-700">
                            <span class="text-[11px] font-black uppercase text-slate-500 tracking-wider flex items-center gap-1.5">
                                <span class="px-1.5 py-0.2 bg-slate-200 text-slate-800 text-[10px] font-black">4.1</span>
                                <span>Selected Items & Quantities</span>
                            </span>
                        </div>
                        <div class="border border-slate-200 rounded-none overflow-hidden max-h-56 overflow-y-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-extrabold text-[10px] uppercase">
                                        <th class="py-2 px-1 text-center w-7 text-slate-400 font-mono">#</th>
                                        <th class="py-2 px-2">Product Title</th>
                                        <th x-show="!isIssueStockStore()" class="py-2 px-1 text-right">Price</th>
                                        <th class="py-2 px-1 text-center w-24">Qty</th>
                                        <th x-show="!isIssueStockStore()" class="py-2 px-1 text-center w-28">Discount</th>
                                        <th x-show="!isIssueStockStore()" class="py-2 px-2 text-right">Net Total</th>
                                        <th class="py-2 px-1 text-center w-6">✕</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-[11px] font-bold">
                                    <template x-for="(item, index) in items" :key="item.product_id">
                                        <tr class="hover:bg-slate-50/60">
                                            <td class="py-2 px-1 text-center font-mono font-bold text-slate-400 text-[10px]" x-text="index + 1"></td>
                                            <td class="py-2 px-2 font-bold text-slate-900 leading-tight">
                                                <div x-text="item.title"></div>
                                                <template x-if="getItemDiscountAmount(item) > 0">
                                                    <div class="text-[9px] text-emerald-600 font-black">
                                                        -PKR <span x-text="getItemDiscountAmount(item).toLocaleString()"></span>/unit off
                                                    </div>
                                                </template>
                                            </td>
                                            <td x-show="!isIssueStockStore()" class="py-2 px-1 text-right text-slate-600 font-mono whitespace-nowrap">
                                                <template x-if="getItemDiscountAmount(item) > 0">
                                                    <div>
                                                        <span class="line-through text-slate-400 text-[9px]">PKR <span x-text="parseFloat(item.price).toLocaleString()"></span></span>
                                                        <div class="text-indigo-600 font-black text-[11px]">PKR <span x-text="getItemNetUnitPrice(item).toLocaleString()"></span></div>
                                                    </div>
                                                </template>
                                                <template x-if="!getItemDiscountAmount(item) || getItemDiscountAmount(item) === 0">
                                                    <span>PKR <span x-text="parseFloat(item.price).toLocaleString()"></span></span>
                                                </template>
                                            </td>
                                            <td class="py-2 px-1 text-center">
                                                <div class="inline-flex items-center border border-slate-300 bg-white shadow-2xs">
                                                    <button type="button" @click="decrementProduct(item)" 
                                                            class="w-5 h-6 flex items-center justify-center bg-slate-100 hover:bg-rose-100 hover:text-rose-700 text-slate-800 font-black text-xs transition-colors cursor-pointer select-none">
                                                        -
                                                    </button>
                                                    <input type="text" inputmode="numeric" pattern="[0-9]*"
                                                           :value="item.quantity" 
                                                           @input="item.quantity = Math.max(1, parseInt($event.target.value) || 1); recalculateDiscount()"
                                                           @change="item.quantity = Math.max(1, parseInt($event.target.value) || 1); recalculateDiscount()"
                                                           style="width: 28px; height: 24px; text-align: center; font-size: 11px; font-weight: 900; color: #0f172a !important; background-color: #ffffff !important; border-left: 1px solid #cbd5e1; border-right: 1px solid #cbd5e1; border-top: none; border-bottom: none; outline: none; padding: 0;"
                                                           class="text-center font-black text-slate-900 bg-white">
                                                    <button type="button" @click="incrementProduct(item)" 
                                                            class="w-5 h-6 flex items-center justify-center bg-slate-100 hover:bg-indigo-100 hover:text-indigo-700 text-slate-800 font-black text-xs transition-colors cursor-pointer select-none">
                                                        +
                                                    </button>
                                                </div>
                                            </td>
                                            <!-- Single Product Discount Column -->
                                            <td x-show="!isIssueStockStore()" class="py-2 px-1 text-center">
                                                <div class="inline-flex items-center border border-slate-300 bg-white rounded-none shadow-2xs overflow-hidden">
                                                    <input type="number" step="any" min="0" :placeholder="item.discount_type === 'percentage' ? '0%' : 'PKR'"
                                                           x-model.number="item.discount" @input="recalculateDiscount()"
                                                           class="w-14 h-6 text-center text-[10px] font-black text-slate-900 bg-transparent border-0 focus:ring-0 p-0">
                                                    <button type="button" @click="item.discount_type = (item.discount_type === 'percentage' ? 'fixed' : 'percentage'); recalculateDiscount()"
                                                            :title="item.discount_type === 'percentage' ? 'Switch to Flat PKR' : 'Switch to % Percentage'"
                                                            :class="item.discount_type === 'percentage' ? 'bg-amber-100 text-amber-900 font-black' : 'bg-slate-100 text-slate-700 font-black'"
                                                            class="h-6 px-1 text-[9px] border-l border-slate-300 hover:bg-indigo-50 transition-colors uppercase cursor-pointer">
                                                        <span x-text="item.discount_type === 'percentage' ? '%' : 'PKR'"></span>
                                                    </button>
                                                </div>
                                            </td>
                                            <td x-show="!isIssueStockStore()" class="py-2 px-2 text-right font-black text-slate-900 font-mono whitespace-nowrap">
                                                PKR <span x-text="getItemLineSubtotal(item).toLocaleString()"></span>
                                            </td>
                                            <td class="py-2 px-1 text-center">
                                                <button type="button" @click="removeItem(index)" class="text-rose-500 hover:text-rose-700 font-black text-xs cursor-pointer">
                                                    &times;
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                    <template x-if="items.length === 0">
                                        <tr>
                                            <td :colspan="isIssueStockStore() ? 4 : 7" class="py-6 text-center text-slate-400 font-semibold">
                                                No products selected. Click items from the catalog.
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 4.2 BILL DISCOUNT BOX (Hidden in Issue Stock mode) -->
                    <div x-show="!isIssueStockStore()" class="p-3.5 bg-rose-50/40 border border-rose-200 rounded-none space-y-2.5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <span class="px-1.5 py-0.2 bg-rose-200 text-rose-800 text-[10px] font-black">4.2</span>
                                <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                                <label class="text-xs font-black text-slate-900 uppercase tracking-wider">Bill Discount</label>
                                <template x-if="!hasDiscountPermission && !adminAuthorized && !isAdminUser">
                                    <span class="px-1.5 py-0.2 bg-rose-100 text-rose-800 text-[10px] font-black border border-rose-200">
                                        🔒 Admin Required
                                    </span>
                                </template>
                                <template x-if="adminAuthorized">
                                    <span class="px-1.5 py-0.2 bg-emerald-100 text-emerald-800 text-[10px] font-black border border-emerald-200">
                                        ✓ Admin Authorized
                                    </span>
                                </template>
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

                        <!-- Standard Discount Inputs -->
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

                            <template x-if="!hasDiscountPermission && !adminAuthorized && !isAdminUser">
                                <div class="mt-1 flex items-center justify-between bg-amber-50 border border-amber-200 p-2 text-[11px] text-amber-900">
                                    <span>⚠️ Discount not permitted for your account.</span>
                                    <button type="button" @click="adminAuthModalOpen = true" class="px-2 py-0.5 bg-amber-600 hover:bg-amber-700 text-white font-black text-[10px]">
                                        Enter Admin Password
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Totals Breakdown (Hidden in Issue Stock mode) -->
                    <div x-show="!isIssueStockStore()" class="space-y-2 pt-1 border-t border-slate-100 text-xs font-bold text-slate-600">
                        <div class="flex items-center justify-between">
                            <span>Subtotal (Gross)</span>
                            <span class="text-slate-900 font-bold">PKR <span x-text="getRawSubtotal().toLocaleString()">0</span></span>
                        </div>

                        <div class="flex items-center justify-between" x-show="!isIssueStockStore() && getItemDiscountsTotal() > 0">
                            <div class="flex items-center gap-1.5 text-rose-600">
                                <span>Product Discounts</span>
                                <span class="px-1.5 py-0.2 bg-rose-100 text-rose-800 text-[10px] font-black">Item Level</span>
                            </div>
                            <span class="text-rose-700 font-black">- PKR <span x-text="getItemDiscountsTotal().toLocaleString()">0</span></span>
                        </div>

                        <div class="flex items-center justify-between" x-show="!isIssueStockStore() && getCalculatedDiscount() > 0">
                            <span class="text-rose-600">Bill Discount</span>
                            <span class="text-rose-700 font-black">- PKR <span x-text="getCalculatedDiscount().toLocaleString()">0</span></span>
                        </div>

                        <!-- Card / Bank Surcharge Fee Line -->
                        <div class="flex items-center justify-between" x-show="!isIssueStockStore() && getExtraFee() > 0">
                            <div class="flex items-center gap-1.5">
                                <span class="text-indigo-700 font-bold">Card Surcharge Fee:</span>
                                <span class="px-1.5 py-0.5 bg-indigo-100 text-indigo-800 font-black text-[10px] rounded-none border border-indigo-200" x-text="(parseFloat(extraPercentage) || 0) + '%'">0%</span>
                            </div>
                            <span class="text-indigo-700 font-black">+ PKR <span x-text="getExtraFee().toLocaleString()">0</span></span>
                        </div>

                        <div class="flex items-center justify-between text-base font-black text-slate-900 pt-1 border-t border-slate-200">
                            <span x-text="isIssueStockStore() ? 'Total Stock Out Value (PKR 0)' : 'Total Net Bill'"></span>
                            <span :class="isIssueStockStore() ? 'text-amber-700' : 'text-indigo-700'" class="text-xl font-black">PKR <span x-text="getTotalAmount().toLocaleString()">0</span></span>
                        </div>
                    </div>

                    <!-- 4.3 Payment Mode Selector (Hidden in Issue Stock mode) -->
                    <div x-show="!isIssueStockStore()" class="space-y-1.5 pt-2 border-t border-slate-100">
                        <div class="flex items-center gap-1.5">
                            <span class="px-1.5 py-0.2 bg-indigo-200 text-indigo-800 text-[10px] font-black">4.3</span>
                            <label class="block text-xs font-black text-slate-700 uppercase tracking-wider">Payment Mode</label>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                            <!-- Cash -->
                            <button type="button" @click="paymentMode = 'Cash'; extraPercentage = 0; recalculateTotals()"
                                    :class="paymentMode === 'Cash' ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-black ring-1 ring-indigo-600' : 'border-slate-200 text-slate-600 font-bold bg-white hover:bg-slate-50'"
                                    class="p-2 border rounded-none text-xs flex flex-col items-center gap-1 transition-all cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                <span>Cash</span>
                            </button>
                            <!-- Card -->
                            <button type="button" @click="paymentMode = 'Card'; recalculateTotals()"
                                    :class="paymentMode === 'Card' ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-black ring-1 ring-indigo-600' : 'border-slate-200 text-slate-600 font-bold bg-white hover:bg-slate-50'"
                                    class="p-2 border rounded-none text-xs flex flex-col items-center gap-1 transition-all cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                                <span>Card</span>
                            </button>
                            <!-- Bank -->
                            <button type="button" @click="paymentMode = 'Bank'; recalculateTotals()"
                                    :class="paymentMode === 'Bank' ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-black ring-1 ring-indigo-600' : 'border-slate-200 text-slate-600 font-bold bg-white hover:bg-slate-50'"
                                    class="p-2 border rounded-none text-xs flex flex-col items-center gap-1 transition-all cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                                <span>Bank</span>
                            </button>
                            <!-- Other -->
                            <button type="button" @click="paymentMode = 'Other'; extraPercentage = 0; recalculateTotals()"
                                    :class="paymentMode === 'Other' ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-black ring-1 ring-indigo-600' : 'border-slate-200 text-slate-600 font-bold bg-white hover:bg-slate-50'"
                                    class="p-2 border rounded-none text-xs flex flex-col items-center gap-1 transition-all cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span>Other</span>
                            </button>
                            <!-- Split -->
                            <button type="button" @click="paymentMode = 'Split'; if (getSplitTotal() === 0) fillSplitRemainder('Cash'); recalculateTotals()"
                                    :class="paymentMode === 'Split' ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-black ring-2 ring-indigo-600' : 'border-slate-200 text-slate-600 font-bold bg-white hover:bg-slate-50'"
                                    class="p-2 border rounded-none text-xs flex flex-col items-center gap-1 transition-all col-span-2 sm:col-span-1 cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                                <span class="flex items-center gap-1">
                                    <span>Split</span>
                                    <span class="text-[9px] font-black uppercase text-indigo-600 bg-indigo-100 px-1 py-0.2 rounded-xs">Multi</span>
                                </span>
                            </button>
                        </div>

                        <!-- SPLIT PAYMENT BREAKDOWN PANEL -->
                        <div x-show="paymentMode === 'Split'" x-cloak class="p-3.5 bg-gradient-to-br from-indigo-50/80 via-white to-slate-50 border border-indigo-200 space-y-3 shadow-xs">
                            <div class="flex flex-wrap items-center justify-between gap-1.5 pb-2 border-b border-indigo-100">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-indigo-600 animate-pulse"></span>
                                    <h4 class="text-xs font-black text-indigo-950 uppercase tracking-wider">Split Payment Breakdown</h4>
                                </div>
                                <div class="flex items-center gap-1 text-[10px]">
                                    <button type="button" @click="presetSplit('Cash', 'Card')" class="px-2 py-0.5 font-bold bg-white border border-indigo-200 text-indigo-700 hover:bg-indigo-100 transition-colors cursor-pointer">50% Cash / Card</button>
                                    <button type="button" @click="presetSplit('Cash', 'Bank')" class="px-2 py-0.5 font-bold bg-white border border-indigo-200 text-indigo-700 hover:bg-indigo-100 transition-colors cursor-pointer">50% Cash / Bank</button>
                                    <button type="button" @click="clearSplitPayments()" class="px-2 py-0.5 font-bold bg-white border border-slate-200 text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer">Reset</button>
                                </div>
                            </div>

                            <p class="text-[11px] text-slate-500">Allocate the bill across multiple payment methods. Click <strong class="text-indigo-700 font-bold">⚡ Fill Remainder</strong> to auto-assign the remaining balance.</p>

                            <!-- Split Allocation Inputs Grid -->
                            <div class="space-y-2 text-xs">
                                <!-- Cash Row -->
                                <div class="flex items-center gap-2 p-2 bg-white border border-slate-200">
                                    <div class="w-20 shrink-0 font-bold text-slate-700 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                        <span>Cash</span>
                                    </div>
                                    <div class="relative flex-1">
                                        <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-[10px] font-black text-slate-400">PKR</span>
                                        <input type="number" step="any" min="0" x-model.number="splitPayments.Cash" @input="recalculateTotals()" placeholder="0" 
                                               class="w-full pl-9 pr-2.5 py-1.5 bg-slate-50 border border-slate-200 text-xs font-black text-slate-900 rounded-none focus:bg-white focus:ring-1 focus:ring-indigo-600">
                                    </div>
                                    <button type="button" @click="fillSplitRemainder('Cash')" title="Assign remaining balance to Cash"
                                            class="px-2 py-1.5 text-[10px] font-black bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100 transition-colors shrink-0 cursor-pointer">
                                        ⚡ Fill Remainder
                                    </button>
                                </div>

                                <!-- Card Row -->
                                <div class="flex items-center gap-2 p-2 bg-white border border-slate-200">
                                    <div class="w-20 shrink-0 font-bold text-slate-700 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                        <span>Card</span>
                                    </div>
                                    <div class="relative flex-1">
                                        <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-[10px] font-black text-slate-400">PKR</span>
                                        <input type="number" step="any" min="0" x-model.number="splitPayments.Card" @input="recalculateTotals()" placeholder="0" 
                                               class="w-full pl-9 pr-2.5 py-1.5 bg-slate-50 border border-slate-200 text-xs font-black text-slate-900 rounded-none focus:bg-white focus:ring-1 focus:ring-indigo-600">
                                    </div>
                                    <button type="button" @click="fillSplitRemainder('Card')" title="Assign remaining balance to Card"
                                            class="px-2 py-1.5 text-[10px] font-black bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100 transition-colors shrink-0 cursor-pointer">
                                        ⚡ Fill Remainder
                                    </button>
                                </div>

                                <!-- Bank Row -->
                                <div class="flex items-center gap-2 p-2 bg-white border border-slate-200">
                                    <div class="w-20 shrink-0 font-bold text-slate-700 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                        <span>Bank</span>
                                    </div>
                                    <div class="relative flex-1">
                                        <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-[10px] font-black text-slate-400">PKR</span>
                                        <input type="number" step="any" min="0" x-model.number="splitPayments.Bank" @input="recalculateTotals()" placeholder="0" 
                                               class="w-full pl-9 pr-2.5 py-1.5 bg-slate-50 border border-slate-200 text-xs font-black text-slate-900 rounded-none focus:bg-white focus:ring-1 focus:ring-indigo-600">
                                    </div>
                                    <button type="button" @click="fillSplitRemainder('Bank')" title="Assign remaining balance to Bank"
                                            class="px-2 py-1.5 text-[10px] font-black bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100 transition-colors shrink-0 cursor-pointer">
                                        ⚡ Fill Remainder
                                    </button>
                                </div>

                                <!-- Other Row -->
                                <div class="flex items-center gap-2 p-2 bg-white border border-slate-200">
                                    <div class="w-20 shrink-0 font-bold text-slate-700 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        <span>Other</span>
                                    </div>
                                    <div class="relative flex-1">
                                        <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-[10px] font-black text-slate-400">PKR</span>
                                        <input type="number" step="any" min="0" x-model.number="splitPayments.Other" @input="recalculateTotals()" placeholder="0" 
                                               class="w-full pl-9 pr-2.5 py-1.5 bg-slate-50 border border-slate-200 text-xs font-black text-slate-900 rounded-none focus:bg-white focus:ring-1 focus:ring-indigo-600">
                                    </div>
                                    <button type="button" @click="fillSplitRemainder('Other')" title="Assign remaining balance to Other"
                                            class="px-2 py-1.5 text-[10px] font-black bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100 transition-colors shrink-0 cursor-pointer">
                                        ⚡ Fill Remainder
                                    </button>
                                </div>
                            </div>

                            <!-- Allocation Status Summary -->
                            <div class="p-2.5 bg-white border border-indigo-100 space-y-1.5 text-xs">
                                <div class="flex items-center justify-between font-bold text-slate-700">
                                    <span>Total Split Allocated:</span>
                                    <span class="font-black text-slate-900">PKR <span x-text="getSplitTotal().toLocaleString()">0</span></span>
                                </div>
                                <div class="flex items-center justify-between text-[11px] pt-1 border-t border-slate-100">
                                    <template x-if="getSplitRemainder() === 0">
                                        <span class="inline-flex items-center gap-1 font-black text-emerald-700">
                                            <span>✓</span>
                                            <span>Matches bill exactly (PKR 0 remaining)</span>
                                        </span>
                                    </template>
                                    <template x-if="getSplitRemainder() > 0">
                                        <span class="inline-flex items-center gap-1 font-bold text-amber-700">
                                            <span>⚠️</span>
                                            <span>PKR <span x-text="getSplitRemainder().toLocaleString()"></span> remaining to allocate</span>
                                        </span>
                                    </template>
                                    <template x-if="getSplitRemainder() < 0">
                                        <span class="inline-flex items-center gap-1 font-bold text-rose-700">
                                            <span>⚠️</span>
                                            <span>Allocated exceeds bill by PKR <span x-text="Math.abs(getSplitRemainder()).toLocaleString()"></span></span>
                                        </span>
                                    </template>
                                    <span class="text-slate-400 font-medium">Target: PKR <span x-text="getTotalAmount().toLocaleString()"></span></span>
                                </div>
                            </div>
                        </div>

                        <!-- Card / Bank Percentage Surcharge Input Field & Quick Chips -->
                        <div x-show="paymentMode === 'Card' || paymentMode === 'Bank' || paymentMode === 'Split'" class="p-3 bg-indigo-50/70 border border-indigo-200 space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="block text-[11px] font-bold text-indigo-900 flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 text-indigo-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                    <span>Card / Bank Processing Charges (%)</span>
                                </label>
                                <div class="flex items-center gap-1">
                                    <button type="button" @click="extraPercentage = 1.5; recalculateTotals()" class="px-1.5 py-0.5 text-[10px] font-bold bg-white border border-indigo-200 text-indigo-700 hover:bg-indigo-100 transition-colors cursor-pointer">1.5%</button>
                                    <button type="button" @click="extraPercentage = 2; recalculateTotals()" class="px-1.5 py-0.5 text-[10px] font-bold bg-white border border-indigo-200 text-indigo-700 hover:bg-indigo-100 transition-colors cursor-pointer">2%</button>
                                    <button type="button" @click="extraPercentage = 2.5; recalculateTotals()" class="px-1.5 py-0.5 text-[10px] font-bold bg-white border border-indigo-200 text-indigo-700 hover:bg-indigo-100 transition-colors cursor-pointer">2.5%</button>
                                    <button type="button" @click="extraPercentage = 3; recalculateTotals()" class="px-1.5 py-0.5 text-[10px] font-bold bg-white border border-indigo-200 text-indigo-700 hover:bg-indigo-100 transition-colors cursor-pointer">3%</button>
                                    <button type="button" @click="extraPercentage = 0; recalculateTotals()" class="px-1.5 py-0.5 text-[10px] font-bold bg-white border border-slate-200 text-slate-500 hover:bg-rose-50 hover:text-rose-600 transition-colors cursor-pointer">0%</button>
                                </div>
                            </div>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-xs font-black text-indigo-500">%</span>
                                <input type="number" step="any" min="0" max="100" x-model.number="extraPercentage" @input="recalculateTotals()" placeholder="Enter card processing % (e.g. 2)" 
                                       class="w-full pl-8 pr-3 py-1.5 bg-white border border-indigo-300 text-xs font-black text-indigo-900 rounded-none focus:ring-1 focus:ring-indigo-600">
                            </div>
                            <div class="flex items-center justify-between text-[11px] font-bold text-indigo-900 pt-0.5" x-show="getExtraFee() > 0">
                                <span>Card Fee Added:</span>
                                <span class="font-black text-indigo-700">+ PKR <span x-text="getExtraFee().toLocaleString()">0</span> <span class="text-[10px] font-semibold text-indigo-500" x-text="'(' + extraPercentage + '%)'"></span></span>
                            </div>
                        </div>
                    </div>

                    <!-- 4.4 PAYMENT STATUS & CLEARANCE BOX (Hidden in Issue Stock mode) -->
                    <div x-show="!isIssueStockStore()" class="p-3.5 bg-slate-50 border border-slate-200 space-y-3 rounded-none">
                        <div class="flex items-center justify-between text-xs font-bold">
                            <span class="text-slate-800 uppercase tracking-wider text-[11px] font-black flex items-center gap-1.5">
                                <span class="px-1.5 py-0.2 bg-slate-200 text-slate-800 text-[10px] font-black">4.4</span>
                                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                <span x-text="editingSaleId ? 'Bill Payment & Settlement' : 'Payment & Settlement'"></span>
                            </span>
                            <button type="button" @click="clearFullRemainingBalance()" class="text-[11px] font-black text-indigo-600 hover:text-indigo-800 underline cursor-pointer">
                                ⚡ Clear Full Balance
                            </button>
                        </div>

                        <!-- If Editing Existing Bill: Display Detailed Breakdown of Bill Total, Discount, Paid & Remaining -->
                        <template x-if="editingSaleId">
                            <div class="space-y-2">
                                <div class="p-3 bg-indigo-50/60 border border-indigo-200 rounded-none space-y-1.5 text-xs">
                                    <div class="flex items-center justify-between font-bold">
                                        <span class="text-slate-600">Total Net Bill:</span>
                                        <span class="text-indigo-900 font-black text-sm">PKR <span x-text="getTotalAmount().toLocaleString()">0</span></span>
                                    </div>
                                    <div class="flex items-center justify-between font-bold" x-show="getCalculatedDiscount() > 0">
                                        <span class="text-slate-600">Discount Given:</span>
                                        <span class="text-rose-700 font-black">- PKR <span x-text="getCalculatedDiscount().toLocaleString()">0</span></span>
                                    </div>
                                    <div class="flex items-center justify-between font-bold border-t border-indigo-100 pt-1">
                                        <span class="text-slate-600">Already Paid Amount:</span>
                                        <span class="text-emerald-700 font-black bg-emerald-50 px-2 py-0.5 border border-emerald-200">
                                            PKR <span x-text="getAlreadyPaid().toLocaleString()">0</span>
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between font-bold border-t border-indigo-100 pt-1">
                                        <span class="text-slate-600">Remaining Balance Due:</span>
                                        <span class="px-2 py-0.5 border font-black"
                                              :class="getInitialPendingBeforePay() > 0 ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'"
                                              x-text="getInitialPendingBeforePay() > 0 ? ('PKR ' + getInitialPendingBeforePay().toLocaleString() + ' Due') : 'Cleared (PKR 0.00)'">
                                        </span>
                                    </div>
                                </div>

                                <!-- Additional Payment Input for Edited Bill -->
                                <div class="space-y-1">
                                    <label class="block text-xs font-bold text-slate-700 flex items-center justify-between">
                                        <span>Collect Additional Payment (PKR)</span>
                                        <span x-show="getInitialPendingBeforePay() === 0" class="text-[10px] text-emerald-700 font-bold bg-emerald-50 px-1.5 py-0.2 border border-emerald-200">🔒 Bill Already Fully Paid</span>
                                    </label>
                                    <input type="number" step="any" min="0" 
                                           x-model.number="additionalPayment" 
                                           :disabled="getInitialPendingBeforePay() === 0"
                                           :placeholder="getInitialPendingBeforePay() === 0 ? '0 (Fully Paid)' : 'Enter amount to collect now...'" 
                                           :class="getInitialPendingBeforePay() === 0 ? 'bg-slate-100 text-slate-400 border-slate-200 cursor-not-allowed' : 'bg-white text-slate-900 border-slate-300 focus:ring-2 focus:ring-indigo-500'"
                                           class="w-full px-3.5 py-2.5 text-xs font-black rounded-none border">
                                </div>
                            </div>
                        </template>

                        <!-- If New Sale: Normal Amount Received Input (Hidden in Split mode) -->
                        <template x-if="!editingSaleId && paymentMode !== 'Split'">
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-700">Amount Received (PKR)</label>
                                <input type="number" step="any" x-model.number="receivedAmount" placeholder="Enter amount received..." 
                                       class="w-full px-3.5 py-2.5 bg-white border border-slate-300 text-xs font-black text-slate-900 focus:ring-2 focus:ring-indigo-500 rounded-none">
                            </div>
                        </template>

                        <!-- If Split Mode: Informational summary in Section 4.4 -->
                        <template x-if="!editingSaleId && paymentMode === 'Split'">
                            <div class="p-2.5 bg-indigo-50/70 border border-indigo-200 text-xs text-indigo-900 flex items-center justify-between font-bold">
                                <span>Split Payment Active:</span>
                                <span class="font-black text-indigo-700">PKR <span x-text="getSplitTotal().toLocaleString()"></span> Allocated</span>
                            </div>
                        </template>

                        <!-- Live Settlement Results -->
                        <div class="pt-1 space-y-1.5 text-xs font-bold border-t border-slate-200/80">
                            <div class="flex items-center justify-between text-slate-600">
                                <span>Total Paid:</span>
                                <span class="text-slate-900 font-extrabold">PKR <span x-text="Number(getTotalPaidAmount()).toLocaleString()">0</span></span>
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

                    <!-- 4.5 Action Buttons -->
                    <div class="space-y-1.5 pt-2 border-t border-slate-100">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-black uppercase text-slate-500 tracking-wider flex items-center gap-1.5">
                                <span class="px-1.5 py-0.2 bg-slate-200 text-slate-800 text-[10px] font-black">4.5</span>
                                <span x-text="isIssueStockStore() ? 'Execute Stock Out' : 'Finalize Bill'"></span>
                            </span>
                            <button type="button" @click="openReceiptModal()" class="text-[11px] font-black text-indigo-600 hover:text-indigo-800 underline flex items-center gap-1 cursor-pointer">
                                <span>🖨️ Preview 80mm</span>
                            </button>
                        </div>

                        <!-- Regular Buttons (Normal Sales Mode) -->
                        <div x-show="!isIssueStockStore()" class="grid grid-cols-3 gap-2.5">
                            <button type="button" @click="holdCurrentBill()" 
                                    class="py-3 px-2 bg-amber-50 hover:bg-amber-100 text-amber-900 font-extrabold text-xs rounded-none transition-all flex items-center justify-center gap-1 border border-amber-300 shadow-2xs">
                                <svg class="w-4 h-4 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span>Hold Bill</span>
                            </button>
                            <button type="button" @click="submitForm(true)" 
                                    class="py-3 px-2 bg-slate-800 hover:bg-slate-900 text-white font-extrabold text-xs rounded-none transition-all flex items-center justify-center gap-1 border border-slate-900 shadow-2xs cursor-pointer">
                                <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                <span>Save & Print 80mm</span>
                            </button>
                            <button type="button" @click="submitForm(false)" 
                                    class="py-3 px-2 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs rounded-none transition-all flex items-center justify-center gap-1 shadow-xs cursor-pointer">
                                <svg class="w-4 h-4 fill-none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                <span x-text="editingSaleId ? 'Update & Save Payment' : 'Complete'"></span>
                            </button>
                        </div>

                        <!-- Direct Stock Out Action Buttons (ISSUE STOCK Mode) -->
                        <div x-show="isIssueStockStore()" class="grid grid-cols-3 gap-2.5">
                            <button type="button" @click="submitForm(false)" 
                                    class="col-span-2 py-3.5 px-4 bg-amber-400 hover:bg-amber-500 text-slate-950 font-black text-xs rounded-none transition-all flex items-center justify-center gap-2 shadow-sm cursor-pointer border border-amber-500">
                                <span class="text-sm">⚡</span>
                                <span>Confirm Stock Out (Issue Stock)</span>
                            </button>
                            <button type="button" @click="submitForm(true)" 
                                    class="py-3.5 px-3 bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-xs rounded-none transition-all flex items-center justify-center gap-1.5 shadow-sm cursor-pointer">
                                <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                <span>Print Slip</span>
                            </button>
                        </div>

                </div>

            </div>
            </div>
        </form>

    </div>

    <!-- TAB 2: SALES HISTORY & AUDIT LOG -->
    <div x-show="activeTab === 'history'" class="space-y-5">
        
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
            <form method="GET" action="{{ route('manager.sales.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
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
                            <th class="py-4 px-3 text-center w-12 text-slate-400 font-mono">#</th>
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
                            <td class="py-4 px-3 text-center font-mono font-bold text-slate-400 text-xs">
                                {{ $loop->iteration }}
                            </td>
                            <td class="py-4 px-4 font-black text-indigo-600 font-mono">
                                <button type="button" @click="inspectSale({{ $sale->id }})" class="font-black text-indigo-600 hover:text-indigo-800 font-mono hover:underline cursor-pointer">
                                    #{{ $sale->invoice_no }}
                                </button>
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
                                    @foreach($sale->items as $idx => $item)
                                        <p class="font-bold text-slate-800 text-[11px] flex items-center gap-1">
                                            <span class="text-slate-400 font-mono text-[10px]">{{ $idx + 1 }}.</span>
                                            <span>{{ $item->product->title ?? 'Product' }}</span>
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
                                @if(strtolower($sale->payment_mode ?? '') === 'split')
                                    <span class="px-2 py-0.5 text-[10px] font-black uppercase bg-indigo-100 text-indigo-800 border border-indigo-200">
                                        SPLIT
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 text-[10px] font-black uppercase bg-slate-100 text-slate-700 border border-slate-200">
                                        {{ $sale->payment_mode ?? 'Cash' }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button" @click="print80mmReceiptDirect({{ $sale->id }})" 
                                            title="Print 80mm Receipt"
                                            class="px-2.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-[11px] rounded-none inline-flex items-center gap-1 cursor-pointer">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                        <span>Print</span>
                                    </button>
                                    <button type="button" @click="loadSaleForEdit({{ $sale->id }})" 
                                            class="px-2.5 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-black text-[11px] rounded-none border border-indigo-200 inline-flex items-center gap-1 cursor-pointer">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        <span>Edit / Pay</span>
                                    </button>
                                    <button type="button" @click="inspectSale({{ $sale->id }})" 
                                            class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-[11px] rounded-none border border-slate-300 inline-flex items-center gap-1 cursor-pointer">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                        <span>Inspect</span>
                                    </button>
                                </div>
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
                                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-black text-[10px] uppercase">
                                        <th class="py-2 px-2.5 text-center w-8 text-slate-400 font-mono">#</th>
                                        <th class="py-2 px-3">Item</th>
                                        <th class="py-2 px-3 text-center">Qty</th>
                                        <th class="py-2 px-3 text-right">Price</th>
                                        <th class="py-2 px-3 text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 font-bold">
                                    <template x-for="(item, idx) in selectedSale.items" :key="item.id">
                                        <tr>
                                            <td class="py-2 px-2.5 text-center font-mono font-bold text-slate-400 text-[10px]" x-text="idx + 1"></td>
                                            <td class="py-2 px-3 text-slate-900 leading-tight">
                                                <div x-text="item.product ? item.product.title : 'Product'"></div>
                                                <template x-if="parseFloat(item.discount || 0) > 0">
                                                    <div class="text-[9px] text-emerald-600 font-bold">-PKR <span x-text="Number(item.discount).toLocaleString()"></span> discount</div>
                                                </template>
                                            </td>
                                            <td class="py-2 px-3 text-center font-black text-slate-800" x-text="item.quantity"></td>
                                            <td class="py-2 px-3 text-right text-slate-600 font-mono" x-text="'PKR ' + Number(item.unit_price).toLocaleString()"></td>
                                            <td class="py-2 px-3 text-right font-black text-slate-900 font-mono" x-text="'PKR ' + Number(item.subtotal).toLocaleString()"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div class="bg-slate-50 p-3.5 border border-slate-200 space-y-2 text-xs font-bold">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-600">Total Net Bill:</span>
                                <span class="font-black text-indigo-700 text-sm">PKR <span x-text="Number(selectedSale.total_amount).toLocaleString()"></span></span>
                            </div>
                            <div class="flex items-center justify-between text-rose-700" x-show="parseFloat(selectedSale.discount) > 0">
                                <span>Discount Given:</span>
                                <span class="font-black">- PKR <span x-text="Number(selectedSale.discount).toLocaleString()"></span></span>
                            </div>
                            <div class="flex items-center justify-between text-slate-700">
                                <span>Payment Mode:</span>
                                <span class="font-black uppercase" x-text="selectedSale.payment_mode || 'Cash'"></span>
                            </div>
                            <template x-if="selectedSale.payment_mode === 'Split' && selectedSale.split_payments">
                                <div class="p-2 bg-indigo-50/70 border border-indigo-200 space-y-1 text-[11px]">
                                    <div class="font-black text-indigo-900 uppercase text-[10px]">Split Payment Breakdown:</div>
                                    <div class="grid grid-cols-2 gap-1 text-slate-700">
                                        <template x-if="parseFloat((typeof selectedSale.split_payments === 'string' ? JSON.parse(selectedSale.split_payments) : selectedSale.split_payments).Cash || 0) > 0">
                                            <div>Cash: <strong>PKR <span x-text="Number((typeof selectedSale.split_payments === 'string' ? JSON.parse(selectedSale.split_payments) : selectedSale.split_payments).Cash).toLocaleString()"></span></strong></div>
                                        </template>
                                        <template x-if="parseFloat((typeof selectedSale.split_payments === 'string' ? JSON.parse(selectedSale.split_payments) : selectedSale.split_payments).Card || 0) > 0">
                                            <div>Card: <strong>PKR <span x-text="Number((typeof selectedSale.split_payments === 'string' ? JSON.parse(selectedSale.split_payments) : selectedSale.split_payments).Card).toLocaleString()"></span></strong></div>
                                        </template>
                                        <template x-if="parseFloat((typeof selectedSale.split_payments === 'string' ? JSON.parse(selectedSale.split_payments) : selectedSale.split_payments).Bank || 0) > 0">
                                            <div>Bank: <strong>PKR <span x-text="Number((typeof selectedSale.split_payments === 'string' ? JSON.parse(selectedSale.split_payments) : selectedSale.split_payments).Bank).toLocaleString()"></span></strong></div>
                                        </template>
                                        <template x-if="parseFloat((typeof selectedSale.split_payments === 'string' ? JSON.parse(selectedSale.split_payments) : selectedSale.split_payments).Other || 0) > 0">
                                            <div>Other: <strong>PKR <span x-text="Number((typeof selectedSale.split_payments === 'string' ? JSON.parse(selectedSale.split_payments) : selectedSale.split_payments).Other).toLocaleString()"></span></strong></div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <div class="flex items-center justify-between text-emerald-700">
                                <span>Paid Amount:</span>
                                <span class="font-black">PKR <span x-text="Number(selectedSale.received_amount).toLocaleString()"></span></span>
                            </div>
                            <div class="flex items-center justify-between border-t border-slate-200/80 pt-1.5" :class="parseFloat(selectedSale.balance_due) > 0 ? 'text-rose-600' : 'text-slate-600'">
                                <span>Remaining Balance Due:</span>
                                <span class="font-black text-sm" x-text="'PKR ' + Number(selectedSale.balance_due).toLocaleString()"></span>
                            </div>
                        </div>

                        <!-- Quick Pay Remaining Section inside Inspect Modal -->
                        <template x-if="selectedSale && parseFloat(selectedSale.balance_due) > 0">
                            <div class="p-3.5 bg-rose-50/70 border border-rose-200 rounded-none space-y-2.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-black text-rose-900 uppercase tracking-wide flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        <span>⚡ Pay Remaining Balance</span>
                                    </span>
                                    <span class="text-xs font-black text-rose-700 font-mono">
                                        Due: PKR <span x-text="Number(selectedSale.balance_due).toLocaleString()"></span>
                                    </span>
                                </div>
                                <form :action="'{{ url('manager/sales') }}/' + selectedSale.id + '/pay-remaining'" method="POST" class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                    @csrf
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Amount to Pay</label>
                                        <input type="number" step="any" name="payment_amount" :value="selectedSale.balance_due" :max="selectedSale.balance_due" min="0.01" required
                                               class="w-full px-2.5 py-1.5 bg-white border border-rose-300 text-xs font-black text-slate-900 rounded-none">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Payment Mode</label>
                                        <select name="payment_mode" class="w-full px-2 py-1.5 bg-white border border-rose-300 text-xs font-bold text-slate-800 rounded-none">
                                            <option value="Cash">Cash</option>
                                            <option value="Card">Card</option>
                                            <option value="Bank">Bank Transfer</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="flex items-end">
                                        <button type="submit" class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs rounded-none transition-colors shadow-xs cursor-pointer">
                                            ✓ Pay Now
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </template>
                    </div>
                </template>

                <div class="flex items-center justify-between gap-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="loadSaleForEdit(selectedSale)" 
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs rounded-none transition-colors flex items-center gap-1.5 cursor-pointer shadow-xs">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        <span>Open in POS to Edit & Pay</span>
                    </button>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="openReceiptModal(selectedSale)" class="px-3.5 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-xs rounded-none border border-indigo-200 cursor-pointer flex items-center gap-1.5">
                            <span>🔍 Thermal Preview</span>
                        </button>
                        <button type="button" @click="print80mmReceiptDirect(selectedSale)" class="px-4 py-2 bg-slate-900 text-white font-bold text-xs rounded-none hover:bg-slate-800 cursor-pointer flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                            <span>Print 80mm Receipt</span>
                        </button>
                        <button type="button" @click="viewModalOpen = false" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold text-xs rounded-none hover:bg-slate-200 cursor-pointer">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 80MM THERMAL RECEIPT PREVIEW & PRINT MODAL -->
    @php
        $brandSettings = \App\Models\Setting::getSettings();
    @endphp
    <div x-show="receiptModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-xs" @click="receiptModalOpen = false"></div>
            <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-xl border border-slate-300 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">🖨️</span>
                        <h3 class="text-sm font-extrabold text-slate-900 heading-font">POS 80mm Thermal Receipt</h3>
                    </div>
                    <button type="button" @click="receiptModalOpen = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Receipt Paper Simulation Container for Screen Preview -->
                <div class="bg-slate-100 p-4 border border-slate-200 shadow-inner max-h-[70vh] overflow-y-auto flex justify-center">
                    <div class="bg-white p-4 border border-slate-300 shadow-sm w-full max-w-[80mm] text-black text-xs leading-normal space-y-2.5" style="font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif;">
                        @include('manager.sales._receipt_content')
                    </div>
                <!-- First-time Print Setup Tip -->
                <div class="bg-amber-50 border border-amber-200 p-2.5 text-[11px] text-amber-900 leading-snug flex items-start gap-2">
                    <span class="text-sm shrink-0">💡</span>
                    <span><strong>To remove paper margins:</strong> In the print preview, click <strong>More settings</strong>, set <strong>Margins</strong> to <strong>None</strong>, and uncheck <strong>Headers &amp; footers</strong>. Chrome will remember this setting.</span>
                </div>

                <!-- Modal Actions -->
                <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-200">
                    <button type="button" @click="receiptModalOpen = false" 
                            class="px-4 py-2 bg-slate-100 text-slate-700 font-bold text-xs rounded-none hover:bg-slate-200 cursor-pointer">
                        Close
                    </button>
                    <button type="button" @click="print80mmReceiptDirect(null)" 
                            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs rounded-none transition-colors flex items-center gap-1.5 shadow-sm cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        <span>Print 80mm Receipt</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- DEDICATED 80MM THERMAL RECEIPT PRINT CONTAINER (RENDERED CLEANLY ON PRINT) -->
        <!-- ADMIN AUTHORIZATION MODAL (FOR DISCOUNT RESTRICTIONS) -->
    <div x-show="adminAuthModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-xs" @click="adminAuthModalOpen = false"></div>
            <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-none border-2 border-rose-500 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2 text-rose-600 font-extrabold text-sm">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <span>Admin Authorization Required</span>
                    </div>
                    <button type="button" @click="adminAuthModalOpen = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="space-y-2 text-xs text-slate-600">
                    <template x-if="!hasDiscountPermission">
                        <p class="font-bold text-slate-800">
                            Your employee account is <span class="text-rose-600 font-black">not permitted</span> to apply discounts. Applying a discount of <span class="text-rose-600 font-black text-sm" x-text="(discountPercentage || 0) + '% (PKR ' + getCalculatedDiscount().toLocaleString() + ')'"></span> requires Administrator authorization.
                        </p>
                    </template>
                    <template x-if="hasDiscountPermission">
                        <p class="font-bold text-slate-800">
                            This sale includes a discount of <span class="text-rose-600 font-black text-sm" x-text="discountPercentage + '%'"></span> which exceeds the allowed 10% limit.
                        </p>
                    </template>
                    <p>
                        Please enter the <strong>Administrator Password</strong> to authorize and complete this sale.
                    </p>
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-bold text-slate-700">Admin Password</label>
                    <input type="password" x-model="adminPassword" placeholder="Enter admin password..." 
                           @keydown.enter.prevent="authorizeAdmin(true)"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-none text-xs font-bold text-slate-900 focus:ring-2 focus:ring-rose-500">
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                    <button type="button" @click="adminAuthModalOpen = false" class="px-4 py-2 bg-slate-100 text-slate-600 font-bold text-xs rounded-none hover:bg-slate-200 border border-slate-200">
                        Cancel
                    </button>
                    <button type="button" @click="authorizeAdmin(true)" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white font-black text-xs rounded-none shadow-xs">
                        Authorize & Charge
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="posReceiptPrintMount" class="hidden print:block">
        <div id="pos80mmReceipt" class="pos-receipt-80mm-container bg-white p-0 text-black text-xs leading-normal space-y-2.5" style="font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif;">
            @include('manager.sales._receipt_content')
        </div>
    </div>

</div>

<script>
window.buildProductReceiptDocument = function(data, brand) {
    if (!data) return '';
    brand = brand || {};
    const items = data.items || [];
    let itemsRows = '';
    items.forEach(it => {
        const price = Number(it.price || 0).toLocaleString();
        const total = Number(it.total || 0).toLocaleString();
        const discVal = Number(it.discount || 0);
        itemsRows += `
            <tr style="border-bottom: 1px solid #000;">
                <td style="padding: 3px 0; text-align: left; font-weight: 400; word-break: break-word; color: #000; font-size: 11.5px; line-height: 1.3;">
                    ${it.title || 'Product'}
                    ${it.discountText ? `<div style="font-size: 9.5px; font-weight: 400; color: #000;">${it.discountText}</div>` : ''}
                </td>
                <td style="padding: 3px 0; text-align: center; font-weight: 400; color: #000; font-size: 11.5px;">${it.quantity || 1}</td>
                <td style="padding: 3px 0; text-align: right; font-weight: 400; color: #000; font-size: 11.5px;">${price}</td>
                <td style="padding: 3px 0; text-align: right; font-weight: 400; color: #000; font-size: 11.5px;">${discVal > 0 ? ('-' + discVal.toLocaleString()) : '0'}</td>
                <td style="padding: 3px 0; text-align: right; font-weight: 500; color: #000; font-size: 11.5px;">${total}</td>
            </tr>
        `;
    });

    let discountRows = '';
    const totalDiscountAmount = (data.discount > 0) ? data.discount : ((data.productDiscount || 0) + (data.billDiscount || 0));
    if (data.productDiscount > 0 && data.billDiscount > 0) {
        discountRows = `
            <div style="display: flex; justify-content: space-between; margin-bottom: 2px; color: #000; font-weight: 400; font-size: 11.5px;">
                <span>Product Discount (Items):</span>
                <span style="font-weight: 500;">- PKR ${Number(data.productDiscount).toLocaleString()}</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 2px; color: #000; font-weight: 400; font-size: 11.5px;">
                <span>Bill Discount:</span>
                <span style="font-weight: 500;">- PKR ${Number(data.billDiscount).toLocaleString()}</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 2px; color: #000; font-weight: 500; font-size: 11.5px;">
                <span>Total Product Discount:</span>
                <span style="font-weight: 500;">- PKR ${Number(totalDiscountAmount).toLocaleString()}</span>
            </div>
        `;
    } else if (data.productDiscount > 0) {
        discountRows = `
            <div style="display: flex; justify-content: space-between; margin-bottom: 2px; color: #000; font-weight: 400; font-size: 11.5px;">
                <span>Product Discount:</span>
                <span style="font-weight: 500;">- PKR ${Number(data.productDiscount).toLocaleString()}</span>
            </div>
        `;
    } else if (totalDiscountAmount > 0) {
        discountRows = `
            <div style="display: flex; justify-content: space-between; margin-bottom: 2px; color: #000; font-weight: 400; font-size: 11.5px;">
                <span>Product Discount:</span>
                <span style="font-weight: 500;">- PKR ${Number(totalDiscountAmount).toLocaleString()}</span>
            </div>
        `;
    }

    const extraRow = data.extraAmount > 0 ? `
        <div style="display: flex; justify-content: space-between; margin-bottom: 2px; font-weight: 400; font-size: 11.5px; color: #000;">
            <span>Card / Bank Fee ${data.extraPercentage > 0 ? '(' + data.extraPercentage + '%)' : ''}:</span>
            <span style="font-weight: 500;">+ PKR ${Number(data.extraAmount).toLocaleString()}</span>
        </div>
    ` : '';

    const balanceRow = data.balanceDue > 0 ? `
        <div style="display: flex; justify-content: space-between; margin-bottom: 2px; color: #000; font-weight: 600; font-size: 12px;">
            <span>Balance Due:</span>
            <span>PKR ${Number(data.balanceDue).toLocaleString()} (DUE)</span>
        </div>
    ` : `
        <div style="display: flex; justify-content: space-between; margin-bottom: 2px; color: #000; font-weight: 500; font-size: 12px;">
            <span>Bill Status:</span>
            <span>PAID IN FULL (CLEARED)</span>
        </div>
    `;

    const changeRow = data.changeReturn > 0 ? `
        <div style="display: flex; justify-content: space-between; margin-bottom: 2px; color: #000; font-weight: 400; font-size: 11.5px;">
            <span>Change Return:</span>
            <span style="font-weight: 500;">PKR ${Number(data.changeReturn).toLocaleString()}</span>
        </div>
    ` : '';

    const notesSection = data.notes ? `
        <div style="border-top: 1px solid #000; margin-top: 5px; padding-top: 4px; font-size: 11px; color: #000; font-weight: 400; line-height: 1.35;">
            <strong style="color: #000; font-weight: 600;">Notes:</strong> ${data.notes}
        </div>
    ` : '';

    const logoHtml = (brand && brand.logo) ? `
        <div style="text-align: center; margin-bottom: 4px;">
            <img src="${brand.logo}" alt="Logo" style="max-height: 80px; max-width: 200px; height: auto; width: auto; object-fit: contain; margin: 0 auto; display: inline-block;">
        </div>
    ` : '';

    const slogan = brand.slogan ? `<div style="font-size: 11px; text-transform: uppercase; color: #000; font-weight: 400; margin-top: 2px;">${brand.slogan}</div>` : '';
    const address = brand.address ? `<div style="font-size: 11px; color: #000; font-weight: 400; margin-top: 2px; line-height: 1.3;">${brand.address}</div>` : '';
    const phone = (brand.phone1 || brand.phone2) ? `<div style="font-size: 11.5px; font-weight: 400; color: #000; margin-top: 2px;">Tel: ${brand.phone1 || ''} ${brand.phone2 ? '/ ' .concat(brand.phone2) : ''}</div>` : '';

    return `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>&nbsp;</title>
    <style>
        @page {
            margin: 0;
            size: auto;
        }
        @media print {
            html, body {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
            }
            .receipt-wrapper {
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                margin-left: 0 !important;
                padding: 0 1.5mm !important;
                box-sizing: border-box !important;
            }
        }
        html, body {
            width: 100%;
            margin: 0;
            padding: 0;
            background: #fff;
            color: #000;
            font-family: Arial, "Segoe UI", Roboto, "Helvetica Neue", Helvetica, sans-serif;
            font-size: 11.5px;
            font-weight: 400;
            line-height: 1.35;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: geometricPrecision;
            box-sizing: border-box;
        }
        .receipt-wrapper {
            width: 100%;
            max-width: 100%;
            margin: 0;
            margin-left: 0;
            padding: 0 1.5mm;
            box-sizing: border-box;
            font-family: Arial, "Segoe UI", Roboto, "Helvetica Neue", Helvetica, sans-serif;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>
    <div class="receipt-wrapper">
        <div style="text-align: center; margin-bottom: 5px;">
            ${logoHtml}
            <div style="font-size: 15px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; color: #000; line-height: 1.2;">${brand.name || 'JUGNU SALOON'}</div>
            ${slogan}
            ${address}
            ${phone}
        </div>

        <div style="border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 2.5px 0; margin: 4px 0; text-align: center; font-weight: 600; font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.3px; color: #000;">
            PRODUCT SALES BILL / CASH RECEIPT
        </div>

        <div style="font-size: 11.5px; margin-bottom: 5px; color: #000;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                <span style="font-weight: 400; color: #000;">Invoice #:</span>
                <span style="font-weight: 500; color: #000;">${data.billNo || '—'}</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                <span style="font-weight: 400; color: #000;">Date & Time:</span>
                <span style="font-weight: 400; color: #000;">${data.billDate || ''} ${data.billTime || ''}</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                <span style="font-weight: 400; color: #000;">Branch / Store:</span>
                <span style="font-weight: 500; color: #000;">${data.storeName || 'Main Store'}</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                <span style="font-weight: 400; color: #000;">Client:</span>
                <span style="font-weight: 500; color: #000;">${data.customerName || 'Walk-in Client'}</span>
            </div>
            ${data.customerPhone ? `
            <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                <span style="font-weight: 400; color: #000;">Phone:</span>
                <span style="font-weight: 400; color: #000;">${data.customerPhone}</span>
            </div>` : ''}
        </div>

        <div style="border-top: 1px solid #000; padding-top: 3px; margin-bottom: 5px;">
            <table style="width: 100%; border-collapse: collapse; font-size: 11.5px;">
                <thead>
                    <tr style="border-bottom: 1px solid #000;">
                        <th style="padding: 2.5px 0; text-align: left; font-size: 11px; font-weight: 600; color: #000;">ITEM / PRODUCT</th>
                        <th style="padding: 2.5px 0; text-align: center; width: 28px; font-size: 11px; font-weight: 600; color: #000;">QTY</th>
                        <th style="padding: 2.5px 0; text-align: right; width: 48px; font-size: 11px; font-weight: 600; color: #000;">PRICE</th>
                        <th style="padding: 2.5px 0; text-align: right; width: 44px; font-size: 11px; font-weight: 600; color: #000;">DISC</th>
                        <th style="padding: 2.5px 0; text-align: right; width: 52px; font-size: 11px; font-weight: 600; color: #000;">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsRows}
                </tbody>
            </table>
        </div>

        <div style="border-top: 1px solid #000; padding-top: 3px; font-size: 11.5px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                <span style="font-weight: 400; color: #000;">Subtotal (Gross):</span>
                <span style="font-weight: 500; color: #000;">PKR ${Number(data.subtotal || 0).toLocaleString()}</span>
            </div>
            ${discountRows}
            ${extraRow}

            <div style="display: flex; justify-content: space-between; font-size: 13.5px; font-weight: 700; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 3.5px 0; margin: 5px 0; color: #000;">
                <span>TOTAL NET BILL:</span>
                <span>PKR ${Number(data.netAmount || 0).toLocaleString()}</span>
            </div>

            <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                <span style="font-weight: 400; color: #000;">Payment Mode:</span>
                <span style="font-weight: 500; text-transform: uppercase; color: #000;">${data.paymentMode || 'Cash'}</span>
            </div>

            ${(data.paymentMode === 'Split' && data.splitPayments) ? `
            <div style="margin: 2px 0 3px 0; padding: 2px 4px; background: #f8fafc; border: 1px dashed #64748b; font-size: 10.5px;">
                <div style="font-weight: 600; text-transform: uppercase; margin-bottom: 1.5px; font-size: 10px;">Split Breakdown:</div>
                ${parseFloat(data.splitPayments.Cash || 0) > 0 ? `<div style="display: flex; justify-content: space-between;"><span>• Cash:</span><span style="font-weight: 500;">PKR ${Number(data.splitPayments.Cash).toLocaleString()}</span></div>` : ''}
                ${parseFloat(data.splitPayments.Card || 0) > 0 ? `<div style="display: flex; justify-content: space-between;"><span>• Card:</span><span style="font-weight: 500;">PKR ${Number(data.splitPayments.Card).toLocaleString()}</span></div>` : ''}
                ${parseFloat(data.splitPayments.Bank || 0) > 0 ? `<div style="display: flex; justify-content: space-between;"><span>• Bank Transfer:</span><span style="font-weight: 500;">PKR ${Number(data.splitPayments.Bank).toLocaleString()}</span></div>` : ''}
                ${parseFloat(data.splitPayments.Other || 0) > 0 ? `<div style="display: flex; justify-content: space-between;"><span>• Other:</span><span style="font-weight: 500;">PKR ${Number(data.splitPayments.Other).toLocaleString()}</span></div>` : ''}
            </div>
            ` : ''}

            <div style="display: flex; justify-content: space-between; margin-bottom: 2px; font-weight: 400; color: #000;">
                <span>Paid Amount:</span>
                <span style="font-weight: 500;">PKR ${Number(data.paidAmount || 0).toLocaleString()}</span>
            </div>

            ${balanceRow}
            ${changeRow}
        </div>

        ${notesSection}

        <div style="border-top: 1px solid #000; margin-top: 6px; padding-top: 5px; text-align: center; font-size: 11px; color: #000;">
            <div style="font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; font-size: 11.5px;">*** THANK YOU FOR SHOPPING ***</div>
            <div style="font-size: 11px; color: #000; font-weight: 400; margin-top: 3px; white-space: pre-line; line-height: 1.35;">${brand.posReceiptNote ? brand.posReceiptNote : 'Please inspect your products before leaving.'}</div>
            <div style="font-size: 11px; color: #000; font-weight: 400; margin-top: 4px;">Bill Generated By: <strong style="color: #000; font-weight: 600;">${data.generatedBy || '{{ addslashes(auth()->user()->name ?? 'Staff') }}'}</strong></div>
            <div style="font-size: 10px; color: #000; font-weight: 400; margin-top: 3px;">Powered by RAPIDTECHPRO &bull; ${new Date().getFullYear()}</div>
        </div>
    </div>
</body>
</html>`;
};
</script>

@endsection
