@extends('layouts.material')

@section('title', !empty($editingAppointment) ? ('Edit Booking #' . $editingAppointment->booking_no) : 'New Service Bill & POS Checkout')

@section('content')
@php
    $brandSettings = \App\Models\Setting::getSettings();
@endphp
<style>
    /* POS 80mm Thermal Receipt Print Rules */
    @page {
        size: auto;
        margin: 0;
    }
    @media print {
        /* Completely collapse dashboard navigation and app frames so they do not take layout space */
        aside, nav, header, footer, .sidebar, #posBookingForm, [x-show="activeTab === 'history'"], .print\:hidden {
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
            display: block !important;
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
function appointmentTerminal() {
    return {
    activeTab: '{{ request('tab', (request()->hasAny(['order_type', 'search', 'employee_id', 'ranking', 'status', 'date', 'page']) ? 'history' : 'pos')) }}',
    brandSettings: {
        name: @json($brandSettings->brand_name ?? 'Jugnu Saloon'),
        logo: @json($brandSettings->brand_logo ? asset($brandSettings->brand_logo) : ''),
        slogan: @json($brandSettings->brand_slogan ?? ''),
        address: @json($brandSettings->brand_address ?? ''),
        phone1: @json($brandSettings->brand_phone1 ?? ''),
        phone2: @json($brandSettings->brand_phone2 ?? ''),
        appointmentReceiptNote: @json($brandSettings->appointment_receipt_note ?? '')
    },
    proofModalOpen: false,
    proofModalData: {
        appointmentId: null,
        url: '',
        bookingNo: '',
        clientName: '',
        amount: '',
        date: '',
        orderType: 'Online',
        imgLoading: true,
        imgError: false,
        uploading: false,
        uploadSuccess: false,
        errorMessage: '',
        showReplaceForm: false
    },
    openProofModal(appointmentId, url, bookingNo, clientName, amount, date, orderType = 'Online') {
        this.proofModalData = {
            appointmentId: appointmentId,
            url: url || '',
            bookingNo: bookingNo,
            clientName: clientName,
            amount: amount,
            date: date,
            orderType: orderType,
            imgLoading: !!url,
            imgError: !url,
            uploading: false,
            uploadSuccess: false,
            errorMessage: '',
            showReplaceForm: !url
        };
        this.proofModalOpen = true;
    },
    uploadSlip() {
        if (typeof window.uploadAppointmentReceiptSlip === 'function') {
            window.uploadAppointmentReceiptSlip(this);
        }
    },
    customerModalOpen: false,
    adminAuthModalOpen: false,
    isAdminUser: {{ auth()->user()->hasRole('admin') ? 'true' : 'false' }},
    hasDiscountPermission: {{ (auth()->user()->hasRole('admin') || auth()->user()->canGiveDiscount()) ? 'true' : 'false' }},
    canApplyDiscount: true,
    adminPassword: '',
    adminAuthorized: false,
    adminAuthReason: '',
    adminAuthError: '',
    isVerifyingAdmin: false,
    pendingCartAction: null,
    originalConfirmedBooking: false,
    originalConfirmedServices: {},

    selectedAppointment: null,
    editingAppointmentId: null,
    editingBookingNo: null,
    bookingStatus: 'confirmed',
    orderType: 'On Site', // 'On Site' (POS/In-store) or 'Online' (Website/App)

    // Backend Lists
    customersList: @json($customers),
    employeesList: @json($employees),
    saloonServices: @json($saloonServices),
    serviceCategories: @json($serviceCategories),
    bankAccountsList: @json($bankAccounts ?? []),
    appointmentsList: [],

    getAppointmentById(id) {
        if (!id) return null;
        return (this.appointmentsList || []).find(a => a.id == id) || null;
    },

    // Selected Client State (Default: Walk-in Customer)
    selectedCustomerId: '{{ $defaultCustomer->id ?? ($customers->first()->id ?? '') }}',
    selectedCustomerObj: @json($defaultCustomer ?? ($customers->first() ?? null)),
    customerSearchQuery: '',

    // Assigned Employee & Ranking State
    selectedEmployeeId: '',
    ranking: 0,
    rankingNotes: '',

    // Quick Rating Modal State
    rateModalOpen: false,
    ratingApt: null,
    modalEmployeeId: '',
    modalRanking: 5,
    modalRankingNotes: '',

    getRankingLabel(rank) {
        switch(parseInt(rank)) {
            case 5: return 'Top Excellent (5/5)';
            case 4: return 'Very Good (4/5)';
            case 3: return 'Good Service (3/5)';
            case 2: return 'Fair Performance (2/5)';
            case 1: return 'Needs Improvement (1/5)';
            default: return 'Click a star to rank';
        }
    },

    openRateModal(apt) {
        this.ratingApt = apt;
        this.modalEmployeeId = apt.employee_id || '';
        this.modalRanking = parseInt(apt.ranking) || 5;
        this.modalRankingNotes = apt.ranking_notes || '';
        this.rateModalOpen = true;
    },

    // Service Filter State
    activeCategory: 'All Services',
    serviceSearchQuery: '',

    // Bill Details State
    billNo: 'BILL-{{ date('Y') }}-{{ str_pad(\App\Models\Appointment::count() + 1, 4, '0', STR_PAD_LEFT) }}',
    billDate: '{{ date('Y-m-d') }}',
    notes: '',

    // Billing Line Items
    items: [],

    // Add-Ons Bar State
    newAddonTitle: '',
    newAddonPrice: '',
    newAddonQty: 1,

    getAddOnServiceObj() {
        let srv = (this.saloonServices || []).find(s => s.id == 137);
        if (!srv) {
            srv = (this.saloonServices || []).find(s => {
                const t = (s.title || '').toLowerCase();
                return t === 'add on' || t === 'ad on' || t === 'addon';
            });
        }
        if (!srv) {
            srv = { id: 137, title: 'Add On', price: 0, category: null };
        }
        return srv;
    },

    addCustomAddon(presetTitle = null, presetPrice = null) {
        const title = (presetTitle !== null ? presetTitle : (this.newAddonTitle || '')).trim();
        const rawPrice = (presetPrice !== null ? presetPrice : this.newAddonPrice);
        const price = Math.max(0, parseFloat(rawPrice) || 0);
        const qty = Math.max(1, parseInt(this.newAddonQty) || 1);

        if (!title && price <= 0) {
            alert('Please enter an Add-On title or price.');
            return;
        }

        const finalTitle = title || 'Custom Add-On';
        const srv = this.getAddOnServiceObj();

        this.items.push({
            service_id: srv.id || 137,
            service_obj: srv,
            custom_title: finalTitle,
            quantity: qty,
            price: price,
            discount: 0,
            discount_type: 'percentage',
            is_addon: true
        });

        // Reset inputs
        this.newAddonTitle = '';
        this.newAddonPrice = '';
        this.newAddonQty = 1;

        this.recalculateDiscount();
    },

    getAddOnsTotal() {
        return this.items.filter(it => this.isAddOn(it)).reduce((sum, it) => sum + this.getItemLineTotal(it), 0);
    },

    // Calculations State
    discountType: 'percentage', // 'percentage' or 'fixed'
    discountPercentage: 0,
    billDiscount: 0,
    taxPercentage: 0,
    paymentMode: 'Cash', // 'Cash', 'Card', 'Bank', 'Other', 'Split'
    bankAccountId: '{{ $editingAppointment->bank_account_id ?? ($bankAccounts->first()->bankid ?? '') }}',
    splitPayments: { Cash: 0, Card: 0, Bank: 0, Other: 0 },
    extraPercentage: 0,  // Card / Bank Surcharge Fee (%)

    getSelectedBankAccount() {
        if (!this.bankAccountId) return null;
        return (this.bankAccountsList || []).find(b => b.bankid == this.bankAccountId) || null;
    },
    
    // Payment & Clearance State
    previouslyPaidAmount: 0, // For existing booking
    additionalPayment: 0,    // New payment being collected now
    receivedAmount: '',      // For new bill or direct override (empty by default)

    // Hold Bills Feature State
    heldBillsModalOpen: false,
    heldBills: [],

    // Receipt Modal & 80mm Thermal Print State
    receiptModalOpen: false,
    receiptData: null,

    // Submission Guard State
    isSubmitting: false,

    getCustomerCardType() {
        if (!this.selectedCustomerObj) return 'No Card';
        const ct = (this.selectedCustomerObj.card_type || '').trim();
        return ct || 'No Card';
    },
    getCardFixedDiscountPercent() {
        const ct = this.getCustomerCardType().toLowerCase();
        if (ct.includes('silver')) return 5;
        if (ct.includes('gold')) return 15;
        if (ct.includes('platinum')) return 20;
        return null;
    },
    isItemDiscounted(it) {
        if (!it) return false;
        if (it.discount && parseFloat(it.discount) > 0) return true;
        const srv = it.service_obj || (this.saloonServices ? this.saloonServices.find(s => s.id == (it.service_id || it.saloon_service_id)) : null);
        if (srv) {
            const originalPrice = parseFloat(srv.price) || 0;
            const discountPct = parseFloat(srv.discount) || 0;
            const discPrice = parseFloat(srv.discounted_price) || 0;
            const unitPrice = parseFloat(it.price) || 0;
            if (discountPct > 0) return true;
            if (discPrice > 0 && discPrice < originalPrice) return true;
            if (originalPrice > 0 && unitPrice < originalPrice) return true;
        }
        return false;
    },
    getItemDiscountAmount(item) {
        const p = parseFloat(item.price) || 0;
        const d = parseFloat(item.discount) || 0;
        if (d <= 0 || p <= 0) return 0;
        if (item.discount_type === 'fixed') {
            return Math.min(p, d);
        }
        const pct = Math.min(100, Math.max(0, d));
        return Math.round(p * (pct / 100));
    },
    getItemNetUnitPrice(item) {
        const p = parseFloat(item.price) || 0;
        return Math.max(0, p - this.getItemDiscountAmount(item));
    },
    getItemLineTotal(item) {
        return Math.round(this.getItemNetUnitPrice(item) * (parseInt(item.quantity) || 1));
    },
    getRawSubtotal() {
        return this.items.reduce((sum, item) => sum + ((parseFloat(item.price) || 0) * (parseInt(item.quantity) || 1)), 0);
    },
    getItemDiscountsTotal() {
        return this.items.reduce((sum, item) => sum + (this.getItemDiscountAmount(item) * (parseInt(item.quantity) || 1)), 0);
    },
    hasDiscountedItem() {
        return this.items.some(it => this.isItemDiscounted(it));
    },
    getDiscountedItems() {
        return this.items.filter(it => this.isItemDiscounted(it));
    },
    // Returns true if item is eligible for bill-level discount:
    // Must NOT be individually discounted AND must NOT be an Add-On service.
    isBillDiscountEligible(it) {
        return !this.isItemDiscounted(it) && !this.isAddOn(it);
    },
    hasNonDiscountedItem() {
        return this.items.some(it => this.isBillDiscountEligible(it));
    },
    getNonDiscountedSubtotal() {
        // Excludes: already-discounted items AND Add-On services — neither get bill discount
        return this.items.filter(it => this.isBillDiscountEligible(it)).reduce((sum, item) => sum + this.getItemLineTotal(item), 0);
    },
    getDiscountedSubtotal() {
        return this.items.filter(it => this.isItemDiscounted(it)).reduce((sum, item) => sum + this.getItemLineTotal(item), 0);
    },
    isCardDiscountEnforced() {
        if (!this.hasNonDiscountedItem()) return false;
        return this.getCardFixedDiscountPercent() !== null;
    },
    syncCardDiscount() {
        if (!this.hasNonDiscountedItem()) {
            this.discountPercentage = 0;
            this.billDiscount = 0;
            return;
        }
        if (this.isCardDiscountEnforced()) {
            const fixedPct = this.getCardFixedDiscountPercent();
            this.discountType = 'percentage';
            this.discountPercentage = fixedPct;
            const eligibleBase = this.getNonDiscountedSubtotal();
            this.billDiscount = Math.round(eligibleBase * (fixedPct / 100));
        }
    },

    init() {
        this.syncCardDiscount();
        this.recalculateTotals();
        this.loadHeldBills();
        @if(!empty($editingAppointment))
            this.loadAppointmentForEdit(@json($editingAppointment));
        @endif
    },

    // Methods for Selecting Client
    selectCustomer(cust) {
        this.selectedCustomerId = cust.id;
        this.selectedCustomerObj = cust;
        this.customerModalOpen = false;
        if (this.isCardDiscountEnforced()) {
            this.syncCardDiscount();
        }
        this.recalculateDiscount();
    },
    getFilteredCustomers() {
        if (!this.customerSearchQuery || this.customerSearchQuery.trim() === '') return this.customersList;
        const q = this.customerSearchQuery.toLowerCase();
        return this.customersList.filter(c => 
            c.name.toLowerCase().includes(q) || 
            (c.phone_no1 && c.phone_no1.includes(q))
        );
    },

    // Methods for Filtering Services
    getFilteredServices() {
        let list = this.saloonServices;
        if (this.activeCategory !== 'All Services') {
            list = list.filter(s => s.category && s.category.title && s.category.title.toLowerCase().includes(this.activeCategory.toLowerCase()));
        }
        if (this.serviceSearchQuery && this.serviceSearchQuery.trim() !== '') {
            const q = this.serviceSearchQuery.toLowerCase();
            list = list.filter(s => s.title.toLowerCase().includes(q) || (s.description && s.description.toLowerCase().includes(q)));
        }
        return list;
    },

    getImageUrl(item) {
        if (!item || !item.image) {
            return 'https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=400&q=80';
        }
        const img = String(item.image).trim();
        if (img.startsWith('http://') || img.startsWith('https://')) {
            return img;
        }
        return '/' + img.replace(/^\/+/, '');
    },

    // Ad-Ons (Add-Ons) Detection
    isAddOn(item) {
        if (!item) return false;
        if (item.is_addon === true) return true;
        if (item.service_id == 137) return true;
        if (item.custom_title && (
            item.custom_title.toLowerCase().includes('ad on') || 
            item.custom_title.toLowerCase().includes('add on') || 
            item.custom_title.toLowerCase().includes('adon')
        )) return true;
        if (!item.service_obj) return false;
        const catTitle = (item.service_obj.category && item.service_obj.category.title) ? item.service_obj.category.title.toLowerCase() : '';
        const srvTitle = (item.service_obj.title) ? item.service_obj.title.toLowerCase() : '';
        return catTitle.includes('ad on') || catTitle.includes('add on') || catTitle.includes('adon') || srvTitle.includes('ad on') || srvTitle.includes('add on') || srvTitle.includes('custom');
    },

    // Service Cart Handlers & Multi-Quantity Support
    isServiceSelected(serviceId) {
        return this.items.some(item => item.service_id == serviceId);
    },
    getServiceCartQuantity(serviceId) {
        const matching = this.items.filter(it => it.service_id == serviceId);
        return matching.reduce((sum, it) => sum + (parseInt(it.quantity) || 1), 0);
    },
    incrementService(srv, event) {
        if (event) event.stopPropagation();
        const isGenericAddon = (srv.id == 137) || (srv.title && (srv.title.toLowerCase().includes('add on') || srv.title.toLowerCase().includes('ad on')));
        if (isGenericAddon) {
            this.addCustomAddon('Add On', 0);
            return;
        }
        const item = this.items.find(it => it.service_id == srv.id && !it.is_addon);
        if (item) {
            item.quantity = (parseInt(item.quantity) || 1) + 1;
        } else {
            const basePrice = parseFloat(srv.price || srv.discounted_price || 0);
            let initDiscount = 0;
            let initDiscountType = 'percentage';
            if (srv.discount && parseFloat(srv.discount) > 0) {
                initDiscount = parseFloat(srv.discount);
                initDiscountType = 'percentage';
            } else if (srv.discounted_price && parseFloat(srv.discounted_price) > 0 && parseFloat(srv.price || 0) > parseFloat(srv.discounted_price)) {
                initDiscount = Math.round(((parseFloat(srv.price) - parseFloat(srv.discounted_price)) / parseFloat(srv.price)) * 100);
                initDiscountType = 'percentage';
            }

            this.items.push({
                service_id: srv.id,
                service_obj: srv,
                custom_title: srv.title,
                quantity: 1,
                price: basePrice,
                discount: initDiscount,
                discount_type: initDiscountType
            });
        }
        this.recalculateDiscount();
    },
    canFullyModifyCart() {
        return !this.editingAppointmentId || !this.originalConfirmedBooking || this.isAdminUser || this.adminAuthorized;
    },
    getOriginalConfirmedQty(serviceId) {
        if (!this.originalConfirmedBooking) return 0;
        return parseInt(this.originalConfirmedServices[serviceId] || 0);
    },
    canDecreaseItemQty(item) {
        if (this.canFullyModifyCart()) return true;
        const origQty = this.getOriginalConfirmedQty(item.service_id);
        return (parseInt(item.quantity) || 1) > origQty;
    },
    canRemoveItem(item) {
        if (this.canFullyModifyCart()) return true;
        const origQty = this.getOriginalConfirmedQty(item.service_id);
        return origQty === 0;
    },
    promptAdminCartUnlock(callback = null) {
        this.adminAuthReason = 'decrease_service';
        this.adminAuthError = '';
        this.adminPassword = '';
        this.pendingCartAction = callback;
        this.adminAuthModalOpen = true;
    },
    decrementCartItem(item, idx) {
        if (!this.canDecreaseItemQty(item)) {
            this.promptAdminCartUnlock(() => {
                this.decrementCartItem(item, idx);
            });
            return;
        }
        if (parseInt(item.quantity) > 1) {
            item.quantity = parseInt(item.quantity) - 1;
            this.recalculateDiscount();
        } else {
            this.removeItem(idx);
        }
    },
    handleQuantityInput(item) {
        const minAllowed = this.canFullyModifyCart() ? 1 : Math.max(1, this.getOriginalConfirmedQty(item.service_id));
        const val = parseInt(item.quantity);
        if (isNaN(val) || val < minAllowed) {
            if (!this.canFullyModifyCart() && this.getOriginalConfirmedQty(item.service_id) > 0) {
                this.promptAdminCartUnlock(() => {
                    if (!item.quantity || parseInt(item.quantity) < 1) item.quantity = 1;
                    this.recalculateDiscount();
                });
            }
            item.quantity = minAllowed;
        }
        this.recalculateDiscount();
    },
    decrementService(srv, event) {
        if (event) event.stopPropagation();
        let index = -1;
        for (let i = this.items.length - 1; i >= 0; i--) {
            if (this.items[i].service_id == srv.id) {
                index = i;
                break;
            }
        }
        if (index > -1) {
            this.decrementCartItem(this.items[index], index);
        }
    },
    toggleService(srv) {
        this.incrementService(srv);
    },
    removeItem(index) {
        const item = this.items[index];
        if (item && !this.canRemoveItem(item)) {
            this.promptAdminCartUnlock(() => {
                this.removeItem(index);
            });
            return;
        }
        this.items.splice(index, 1);
        this.recalculateDiscount();
    },

    // Calculations logic
    getSubtotal() {
        return this.items.reduce((sum, item) => sum + this.getItemLineTotal(item), 0);
    },
    recalculateDiscount() {
        const eligibleBase = this.getNonDiscountedSubtotal();
        if (eligibleBase <= 0) {
            this.discountPercentage = 0;
            this.billDiscount = 0;
            this.recalculateTotals();
            return;
        }

        if (this.isCardDiscountEnforced()) {
            const fixedPct = this.getCardFixedDiscountPercent();
            this.discountType = 'percentage';
            this.discountPercentage = fixedPct;
            this.billDiscount = Math.round(eligibleBase * (fixedPct / 100));
            this.recalculateTotals();
            return;
        }

        if (this.discountType === 'percentage') {
            const pct = Math.min(100, Math.max(0, parseFloat(this.discountPercentage) || 0));
            this.billDiscount = Math.round(eligibleBase * (pct / 100));
        } else {
            const fixed = Math.min(eligibleBase, Math.max(0, parseFloat(this.billDiscount) || 0));
            this.billDiscount = fixed;
            this.discountPercentage = eligibleBase > 0 ? Math.round((fixed / eligibleBase) * 100) : 0;
        }
        this.recalculateTotals();
    },
    applyDiscountPercent(pct) {
        if (!this.hasNonDiscountedItem() || this.isCardDiscountEnforced()) return;
        this.discountType = 'percentage';
        this.discountPercentage = pct;
        this.recalculateDiscount();
    },
    clearDiscount() {
        if (!this.hasNonDiscountedItem() || this.isCardDiscountEnforced()) return;
        this.discountPercentage = 0;
        this.billDiscount = 0;
        this.recalculateDiscount();
    },
    recalculateTotals() {
        const tot = this.getTotalAmount();
        // Do NOT auto-populate receivedAmount: user will enter payment explicitly
    },
    getCalculatedDiscount() {
        const eligibleBase = this.getNonDiscountedSubtotal();
        if (eligibleBase <= 0) return 0;
        if (this.discountType === 'percentage') {
            const pct = Math.min(100, Math.max(0, parseFloat(this.discountPercentage) || 0));
            return Math.round(eligibleBase * (pct / 100));
        }
        return Math.min(eligibleBase, Math.max(0, parseFloat(this.billDiscount) || 0));
    },
    getExtraFee() {
        if (this.paymentMode === 'Card' || this.paymentMode === 'Bank') {
            const netBeforeExtra = Math.max(0, this.getSubtotal() - this.getCalculatedDiscount());
            const pct = Math.max(0, parseFloat(this.extraPercentage || 0));
            return Math.round((netBeforeExtra * pct) / 100);
        }
        if (this.paymentMode === 'Split') {
            const cardBankSplit = (parseFloat(this.splitPayments.Card) || 0) + (parseFloat(this.splitPayments.Bank) || 0);
            const pct = Math.max(0, parseFloat(this.extraPercentage || 0));
            return Math.round((cardBankSplit * pct) / 100);
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
    getTaxAmount() {
        const taxable = Math.max(0, this.getSubtotal() - this.getCalculatedDiscount());
        return Math.round(taxable * (parseFloat(this.taxPercentage || 0) / 100));
    },
    getTotalAmount() {
        const total = this.getSubtotal() - this.getCalculatedDiscount() + this.getTaxAmount() + this.getExtraFee();
        return Math.max(0, Math.round(total));
    },

    // Clearance and Payment Calculations
    getAlreadyPaid() {
        return parseFloat(this.previouslyPaidAmount || 0);
    },
    getPreviouslyPaid() {
        return this.getAlreadyPaid();
    },
    getInitialPendingBeforePay() {
        const total = this.getTotalAmount();
        const alreadyPaid = this.getAlreadyPaid();
        return Math.max(0, Math.round(total - alreadyPaid));
    },
    isAlreadyFullyCleared() {
        if (this.editingAppointmentId) {
            return this.getInitialPendingBeforePay() === 0;
        }
        return false;
    },
    getTotalPaidAmount() {
        if (this.paymentMode === 'Split') {
            return this.getSplitTotal();
        }
        if (this.editingAppointmentId) {
            const alreadyPaid = this.getAlreadyPaid();
            const addPay = parseFloat(this.additionalPayment || 0);
            return Math.round(alreadyPaid + addPay);
        }
        return Math.round(parseFloat(this.receivedAmount || 0));
    },
    getStillPendingAmount() {
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
        if (this.editingAppointmentId) {
            this.additionalPayment = this.getInitialPendingBeforePay();
        } else {
            this.receivedAmount = this.getTotalAmount();
        }
    },
    // Load Existing Booking into Billing Terminal for Editing
    loadAppointmentForEdit(apt) {
        this.editingAppointmentId = apt.id;
        this.editingBookingNo = apt.booking_no;
        this.orderType = apt.order_type || 'On Site';
        this.bookingStatus = apt.status || 'confirmed';
        this.selectedCustomerId = apt.account_id;
        this.selectedCustomerObj = apt.customer || this.customersList.find(c => c.id == apt.account_id) || null;
        this.selectedEmployeeId = apt.employee_id || '';
        this.ranking = parseInt(apt.ranking) || 0;
        this.rankingNotes = apt.ranking_notes || '';
        this.billNo = apt.booking_no;
        this.billDate = apt.appointment_date ? (typeof apt.appointment_date === 'string' ? apt.appointment_date.split('T')[0] : apt.appointment_date) : '{{ date('Y-m-d') }}';
        this.notes = apt.notes || '';
        if (this.isCardDiscountEnforced()) {
            this.syncCardDiscount();
        } else {
            this.discountType = apt.discount_type || 'percentage';
            this.discountPercentage = parseFloat(apt.discount_percentage) || 0;
            this.billDiscount = parseFloat(apt.discount) || 0;
        }
        this.paymentMode = apt.payment_mode || 'Cash';
        this.bankAccountId = apt.bank_account_id ? String(apt.bank_account_id) : (this.bankAccountsList.length > 0 ? String(this.bankAccountsList[0].bankid) : '');
        if (apt.payment_mode === 'Split' && apt.split_payments) {
            const sp = (typeof apt.split_payments === 'string') ? JSON.parse(apt.split_payments) : apt.split_payments;
            this.splitPayments = {
                Cash: parseFloat(sp.Cash) || 0,
                Card: parseFloat(sp.Card) || 0,
                Bank: parseFloat(sp.Bank) || 0,
                Other: parseFloat(sp.Other) || 0,
            };
        } else {
            const prevPaid = parseFloat(apt.paid_amount) || 0;
            this.splitPayments = {
                Cash: (apt.payment_mode === 'Cash' || !apt.payment_mode) ? prevPaid : 0,
                Card: apt.payment_mode === 'Card' ? prevPaid : 0,
                Bank: apt.payment_mode === 'Bank' ? prevPaid : 0,
                Other: apt.payment_mode === 'Other' ? prevPaid : 0,
            };
        }
        const netBeforeExtra = Math.max(0, (parseFloat(apt.total_amount) || 0) - (parseFloat(apt.discount) || 0));
        const extraAmt = parseFloat(apt.extra_amount) || 0;
        if (extraAmt > 0 && netBeforeExtra > 0) {
            this.extraPercentage = Math.round((extraAmt / netBeforeExtra) * 100 * 100) / 100;
        } else {
            this.extraPercentage = 0;
        }
        
        // Track previous payments
        this.previouslyPaidAmount = parseFloat(apt.paid_amount) || 0;
        this.additionalPayment = 0;
        this.receivedAmount = parseFloat(apt.paid_amount) || 0;

        // Load all items belonging to this booking
        this.items = [];
        this.originalConfirmedBooking = (apt.status === 'confirmed');
        this.originalConfirmedServices = {};

        if (apt.items && apt.items.length > 0) {
            apt.items.forEach(it => {
                const srv = this.saloonServices.find(s => s.id == it.saloon_service_id) || it.service;
                const qty = parseInt(it.quantity) || 1;
                const basePrice = (it.price && parseFloat(it.price) > 0)
                    ? parseFloat(it.price)
                    : (srv ? parseFloat(srv.price || 0) : 0);
                const totalDiscAmt = parseFloat(it.discount) || 0;
                const unitDiscAmt = qty > 0 ? (totalDiscAmt / qty) : totalDiscAmt;

                const isAddonItem = (it.saloon_service_id == 137) || (srv && (srv.id == 137 || (srv.title && (srv.title.toLowerCase().includes('add on') || srv.title.toLowerCase().includes('ad on'))))) || (it.custom_title && (it.custom_title.toLowerCase().includes('add on') || it.custom_title.toLowerCase().includes('ad on')));

                this.items.push({
                    service_id: it.saloon_service_id,
                    service_obj: srv,
                    custom_title: it.custom_title || (srv ? srv.title : 'Service'),
                    quantity: qty,
                    price: basePrice > 0 ? basePrice : (parseFloat(it.discounted_price || 0) / qty),
                    discount: unitDiscAmt > 0 ? Math.round(unitDiscAmt) : 0,
                    discount_type: 'fixed',
                    is_addon: isAddonItem
                });

                if (this.originalConfirmedBooking) {
                    this.originalConfirmedServices[it.saloon_service_id] = (this.originalConfirmedServices[it.saloon_service_id] || 0) + qty;
                }
            });
        }

        this.recalculateDiscount();
        this.activeTab = 'pos';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    // Reset Terminal to Fresh New Bill
    resetToNewBill() {
        this.editingAppointmentId = null;
        this.editingBookingNo = null;
        this.originalConfirmedBooking = false;
        this.originalConfirmedServices = {};
        this.adminAuthReason = '';
        this.adminAuthError = '';
        this.isVerifyingAdmin = false;
        this.pendingCartAction = null;
        this.orderType = 'On Site';
        this.bookingStatus = 'confirmed';
        this.billNo = 'BILL-{{ date('Y') }}-{{ str_pad(\App\Models\Appointment::count() + 1, 4, '0', STR_PAD_LEFT) }}';
        this.billDate = '{{ date('Y-m-d') }}';
        this.items = [];
        this.notes = '';
        this.discountPercentage = 0;
        this.billDiscount = 0;
        this.previouslyPaidAmount = 0;
        this.additionalPayment = 0;
        this.receivedAmount = '';
        this.extraPercentage = 0;
        this.paymentMode = 'Cash';
        this.bankAccountId = this.bankAccountsList.length > 0 ? String(this.bankAccountsList[0].bankid) : '';
        this.splitPayments = { Cash: 0, Card: 0, Bank: 0, Other: 0 };
        this.adminPassword = '';
        this.adminAuthorized = false;
        this.selectedCustomerId = '{{ $defaultCustomer->id ?? ($customers->first()->id ?? '') }}';
        this.selectedCustomerObj = @json($defaultCustomer ?? ($customers->first() ?? null));
        this.selectedEmployeeId = '';
        this.ranking = 0;
        this.rankingNotes = '';
        this.discountPercentage = 0;
        this.billDiscount = 0;
        this.syncCardDiscount();
        this.activeTab = 'pos';
    },

    // Held Bills Operations
    loadHeldBills() {
        try {
            const saved = localStorage.getItem('pos_held_appointments');
            this.heldBills = saved ? JSON.parse(saved) : [];
        } catch(e) {
            this.heldBills = [];
        }
    },
    saveHeldBills() {
        try {
            localStorage.setItem('pos_held_appointments', JSON.stringify(this.heldBills));
        } catch(e) {}
    },
    holdCurrentBill() {
        if (this.items.length === 0) {
            alert('Please add at least one treatment / service to the bill before putting it on hold.');
            return;
        }
        const clientName = this.selectedCustomerObj ? this.selectedCustomerObj.name : 'Walk-in Client';
        const record = {
            id: Date.now(),
            heldAt: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
            billNo: this.billNo,
            orderType: this.orderType,
            bookingStatus: this.bookingStatus,
            selectedCustomerId: this.selectedCustomerId,
            selectedCustomerObj: this.selectedCustomerObj,
            billDate: this.billDate,
            notes: this.notes,
            discountType: this.discountType,
            discountPercentage: this.discountPercentage,
            billDiscount: this.billDiscount,
            paymentMode: this.paymentMode,
            bankAccountId: this.bankAccountId,
            splitPayments: JSON.parse(JSON.stringify(this.splitPayments)),
            extraPercentage: this.extraPercentage,
            receivedAmount: this.receivedAmount,
            additionalPayment: this.additionalPayment,
            previouslyPaidAmount: this.previouslyPaidAmount,
            editingAppointmentId: this.editingAppointmentId,
            editingBookingNo: this.editingBookingNo,
            originalConfirmedBooking: this.originalConfirmedBooking,
            originalConfirmedServices: JSON.parse(JSON.stringify(this.originalConfirmedServices)),
            items: JSON.parse(JSON.stringify(this.items)),
            totalAmount: this.getTotalAmount(),
            clientName: clientName
        };

        this.heldBills.unshift(record);
        this.saveHeldBills();
        this.resetToNewBill();
        alert('⏸️ Appointment for ' + clientName + ' has been put on hold.\n\nYou can resume it anytime from the Held Bills button.');
    },
    resumeHeldBill(record) {
        if (this.items.length > 0) {
            if (!confirm('Current in-progress bill will be replaced with the held bill. Do you wish to continue?')) {
                return;
            }
        }
        this.editingAppointmentId = record.editingAppointmentId || null;
        this.editingBookingNo = record.editingBookingNo || null;
        this.originalConfirmedBooking = record.originalConfirmedBooking || false;
        this.originalConfirmedServices = record.originalConfirmedServices || {};
        this.orderType = record.orderType || 'On Site';
        this.bookingStatus = record.bookingStatus || 'confirmed';
        this.selectedCustomerId = record.selectedCustomerId;
        this.selectedCustomerObj = record.selectedCustomerObj;
        this.billNo = record.billNo || ('BILL-{{ date('Y') }}-{{ str_pad(\App\Models\Appointment::count() + 1, 4, '0', STR_PAD_LEFT) }}');
        this.billDate = record.billDate || '{{ date('Y-m-d') }}';
        this.notes = record.notes || '';
        if (this.isCardDiscountEnforced()) {
            this.syncCardDiscount();
        } else {
            this.discountType = record.discountType || 'percentage';
            this.discountPercentage = record.discountPercentage || 0;
            this.billDiscount = record.billDiscount || 0;
        }
        this.paymentMode = record.paymentMode || 'Cash';
        this.bankAccountId = record.bankAccountId || (this.bankAccountsList.length > 0 ? String(this.bankAccountsList[0].bankid) : '');
        this.splitPayments = record.splitPayments ? JSON.parse(JSON.stringify(record.splitPayments)) : { Cash: 0, Card: 0, Bank: 0, Other: 0 };
        this.extraPercentage = record.extraPercentage !== undefined ? record.extraPercentage : (record.extraAmount || 0);
        this.receivedAmount = record.receivedAmount || 0;
        this.additionalPayment = record.additionalPayment || 0;
        this.previouslyPaidAmount = record.previouslyPaidAmount || 0;
        this.items = record.items ? JSON.parse(JSON.stringify(record.items)) : [];

        // Remove this record from held list
        this.heldBills = this.heldBills.filter(h => h.id !== record.id);
        this.saveHeldBills();
        this.heldBillsModalOpen = false;
        this.recalculateDiscount();
        this.activeTab = 'pos';
    },
    discardHeldBill(recordId) {
        if (confirm('Are you sure you want to discard this held bill?')) {
            this.heldBills = this.heldBills.filter(h => h.id !== recordId);
            this.saveHeldBills();
        }
    },

    // Generate and Prepare 80mm Receipt Data
    generateReceiptData(apt = null) {
        if (apt) {
            const receiptItems = (apt.items || []).map(it => {
                const srv = this.saloonServices.find(s => s.id == it.saloon_service_id) || it.service;
                const qty = parseInt(it.quantity) || 1;
                const origPrice = (it.price && parseFloat(it.price) > 0)
                    ? parseFloat(it.price)
                    : (parseFloat(it.discounted_price || (srv ? (srv.discounted_price || srv.price) : 0)) / qty);
                const discAmt = parseFloat(it.discount) || 0;
                const discPrice = parseFloat(it.discounted_price) || 0;
                const lineTotal = discPrice > 0 ? discPrice : (origPrice * qty - discAmt);
                const isItemAddon = (it.saloon_service_id == 137) || (srv && (srv.id == 137 || (srv.title && (srv.title.toLowerCase().includes('add on') || srv.title.toLowerCase().includes('ad on'))))) || (it.custom_title && (it.custom_title.toLowerCase().includes('add on') || it.custom_title.toLowerCase().includes('ad on')));
                return {
                    title: it.custom_title || (srv ? srv.title : 'Service'),
                    quantity: qty,
                    price: origPrice > 0 ? origPrice : lineTotal,
                    discount: discAmt,
                    discountText: discAmt > 0 ? `PKR ${discAmt.toLocaleString()} OFF` : '',
                    total: Math.max(0, lineTotal),
                    is_addon: isItemAddon
                };
            });

            const itemDiscountsTotal = receiptItems.reduce((sum, it) => sum + (parseFloat(it.discount) || 0), 0);
            let rawSubtotal = receiptItems.reduce((sum, it) => sum + (parseFloat(it.price) || 0) * (parseInt(it.quantity) || 1), 0);
            if (rawSubtotal <= 0) {
                rawSubtotal = (parseFloat(apt.total_amount) || 0) + itemDiscountsTotal;
            }
            const billDiscount = parseFloat(apt.discount) || 0;
            const totalDiscount = itemDiscountsTotal + billDiscount;

            let finalItems = receiptItems;
            if (itemDiscountsTotal === 0 && billDiscount > 0 && receiptItems.length > 0) {
                const eligibleItems = receiptItems.filter(it => !it.is_addon);
                const eligibleSubtotal = eligibleItems.reduce((sum, it) => sum + (it.price * it.quantity), 0);
                let allocated = 0;
                finalItems = receiptItems.map((it, idx) => {
                    if (it.is_addon || eligibleSubtotal <= 0) {
                        return it;
                    }
                    const gross = it.price * it.quantity;
                    const isLastEligible = eligibleItems.length > 0 && eligibleItems[eligibleItems.length - 1] === it;
                    let itemDisc = isLastEligible
                        ? (billDiscount - allocated)
                        : Math.round(billDiscount * (gross / eligibleSubtotal));
                    allocated += itemDisc;
                    return {
                        ...it,
                        discount: itemDisc,
                        discountText: itemDisc > 0 ? `PKR ${itemDisc.toLocaleString()} OFF` : '',
                        total: Math.max(0, gross - itemDisc)
                    };
                });
            }

            return {
                billNo: apt.booking_no,
                billDate: apt.appointment_date ? (typeof apt.appointment_date === 'string' ? apt.appointment_date.split('T')[0] : apt.appointment_date) : '{{ date('Y-m-d') }}',
                billTime: apt.start_time || '{{ date('h:i A') }}',
                orderType: apt.order_type || 'On Site',
                customerName: (apt.customer ? apt.customer.name : 'Walk-in Client'),
                customerPhone: (apt.customer ? (apt.customer.phone_no1 || '') : ''),
                items: finalItems,
                subtotal: rawSubtotal,
                serviceDiscount: itemDiscountsTotal,
                billDiscount: billDiscount,
                discount: totalDiscount,
                discountPercentage: parseFloat(apt.discount_percentage) || 0,
                extraPercentage: (parseFloat(apt.extra_amount) > 0 && (rawSubtotal - totalDiscount) > 0) ? (Math.round((parseFloat(apt.extra_amount) / (rawSubtotal - totalDiscount)) * 100 * 100) / 100) : 0,
                extraAmount: parseFloat(apt.extra_amount) || 0,
                netAmount: parseFloat(apt.net_amount) || (rawSubtotal - totalDiscount + (parseFloat(apt.extra_amount) || 0)),
                paidAmount: parseFloat(apt.paid_amount) || 0,
                balanceDue: parseFloat(apt.balance_due) || 0,
                changeReturn: Math.max(0, (parseFloat(apt.paid_amount) || 0) - (parseFloat(apt.net_amount) || 0)),
                paymentMode: apt.payment_mode || 'Cash',
                bankAccountId: apt.bank_account_id || null,
                bankAccountName: apt.bank_account ? (apt.bank_account.bank_name + (apt.bank_account.account_no ? ` (${apt.bank_account.account_no})` : '')) : null,
                splitPayments: apt.split_payments ? (typeof apt.split_payments === 'string' ? JSON.parse(apt.split_payments) : apt.split_payments) : null,
                status: apt.status || 'confirmed',
                notes: apt.notes || '',
                generatedBy: '{{ addslashes(auth()->user()->name ?? 'Staff') }}'
            };
        } else {
            if (this.items.length === 0) {
                alert('Please select at least one treatment / service to generate a receipt.');
                return null;
            }
            const cartItems = this.items.map(it => {
                const qty = parseInt(it.quantity) || 1;
                const unitPrice = parseFloat(it.price) || 0;
                const discAmt = this.getItemDiscountAmount(it);
                const netUnit = this.getItemNetUnitPrice(it);
                return {
                    title: it.custom_title || (it.service_obj ? it.service_obj.title : 'Service'),
                    quantity: qty,
                    price: unitPrice,
                    discount: discAmt * qty,
                    discountText: discAmt > 0 ? (it.discount_type === 'percentage' ? `${it.discount}% OFF` : `PKR ${discAmt.toLocaleString()} OFF`) : '',
                    total: netUnit * qty,
                    is_addon: this.isAddOn(it)
                };
            });

            const rawSubtotal = this.getRawSubtotal();
            const serviceDiscounts = this.getItemDiscountsTotal();
            const billDiscount = this.getCalculatedDiscount();
            const totalDiscount = serviceDiscounts + billDiscount;

            let finalCartItems = cartItems;
            if (serviceDiscounts === 0 && billDiscount > 0 && cartItems.length > 0) {
                const eligibleCartItems = cartItems.filter(it => !it.is_addon);
                const eligibleSubtotal = eligibleCartItems.reduce((sum, it) => sum + (it.price * it.quantity), 0);
                let allocated = 0;
                finalCartItems = cartItems.map((it, idx) => {
                    if (it.is_addon || eligibleSubtotal <= 0) {
                        return it;
                    }
                    const gross = it.price * it.quantity;
                    const isLastEligible = eligibleCartItems.length > 0 && eligibleCartItems[eligibleCartItems.length - 1] === it;
                    let itemDisc = isLastEligible
                        ? (billDiscount - allocated)
                        : Math.round(billDiscount * (gross / eligibleSubtotal));
                    allocated += itemDisc;
                    return {
                        ...it,
                        discount: itemDisc,
                        discountText: itemDisc > 0 ? `PKR ${itemDisc.toLocaleString()} OFF` : '',
                        total: Math.max(0, gross - itemDisc)
                    };
                });
            }

            return {
                billNo: this.editingAppointmentId ? this.editingBookingNo : this.billNo,
                billDate: this.billDate,
                billTime: '{{ date('h:i A') }}',
                orderType: this.orderType,
                customerName: this.selectedCustomerObj ? this.selectedCustomerObj.name : 'Walk-in Client',
                customerPhone: this.selectedCustomerObj ? (this.selectedCustomerObj.phone_no1 || '') : '',
                items: finalCartItems,
                subtotal: rawSubtotal,
                serviceDiscount: serviceDiscounts,
                billDiscount: billDiscount,
                discount: totalDiscount,
                discountPercentage: this.discountPercentage,
                extraPercentage: parseFloat(this.extraPercentage || 0),
                extraAmount: this.getExtraFee(),
                netAmount: this.getTotalAmount(),
                paidAmount: this.getTotalPaidAmount(),
                balanceDue: this.getStillPendingAmount(),
                changeReturn: this.getChangeReturn(),
                paymentMode: this.paymentMode,
                bankAccountId: (this.paymentMode === 'Bank' || (this.paymentMode === 'Split' && (this.splitPayments.Bank || 0) > 0)) ? this.bankAccountId : null,
                bankAccountName: (this.paymentMode === 'Bank' || (this.paymentMode === 'Split' && (this.splitPayments.Bank || 0) > 0)) ? (this.getSelectedBankAccount() ? (this.getSelectedBankAccount().bank_name + (this.getSelectedBankAccount().account_no ? ` (${this.getSelectedBankAccount().account_no})` : '')) : null) : null,
                splitPayments: this.paymentMode === 'Split' ? JSON.parse(JSON.stringify(this.splitPayments)) : null,
                status: this.bookingStatus,
                notes: this.notes,
                generatedBy: '{{ addslashes(auth()->user()->name ?? 'Staff') }}'
            };
        }
    },
openReceiptModal(apt = null) {
        const data = this.generateReceiptData(apt);
        if (data) {
            this.receiptData = data;
            this.receiptModalOpen = true;
        }
    },

    buildReceiptPrintDocument(data) {
        return (typeof window.buildAppointmentReceiptDocument === 'function') 
            ? window.buildAppointmentReceiptDocument(data, this.brandSettings) 
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

    print80mmReceiptDirect(apt = null) {
        let data = null;
        if (apt) {
            data = this.generateReceiptData(apt);
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

    // Submission Handlers
    submitForm(isPrint = false) {
        if (this.isSubmitting) return;

        if (this.items.length === 0) {
            alert('Please select at least one treatment service from the catalog.');
            return;
        }
        if (!this.selectedCustomerId) {
            alert('Please select or add a client.');
            return;
        }

        // Discount Authorization Rules:
        // 1. Membership Card (Silver 5%, Gold 15%, Platinum 20%):
        //    Fixed formula enforced automatically by company policy. No admin password required.
        // 2. No Card (Normal Flow):
        //    - User WITH permission: can allow any discount number directly.
        //    - User WITHOUT permission: allowed up to 10%. Discounts > 10% require Admin password authorization.
        if (!this.isCardDiscountEnforced()) {
            const calcDiscount = this.getCalculatedDiscount();
            if (calcDiscount > 0 && !this.isAdminUser && !this.adminAuthorized) {
                if (!this.hasDiscountPermission && this.discountPercentage > 10 && (!this.adminPassword || this.adminPassword.trim() === '')) {
                    this.adminAuthReason = 'discount';
                    this.adminAuthError = '';
                    this.adminAuthModalOpen = true;
                    return;
                }
            }
        }

        const form = document.getElementById('posBookingForm');
        if (!form) return;

        this.isSubmitting = true;

        if (isPrint) {
            this.receiptData = this.generateReceiptData();
            if (!this.receiptData) {
                this.isSubmitting = false;
                return;
            }
            this.printReceiptHtml(this.receiptData, () => {
                form.submit();
            });
            return;
        }
        form.submit();
    },

    async verifyAdminPasswordAsync() {
        if (!this.adminPassword || this.adminPassword.trim() === '') {
            this.adminAuthError = 'Please enter the administrator password.';
            return false;
        }
        this.isVerifyingAdmin = true;
        this.adminAuthError = '';

        try {
            const token = document.querySelector('input[name="_token"]')?.value 
                || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
                || '{{ csrf_token() }}';
            const response = await fetch('{{ route('manager.appointments.verify-admin-password') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify({ password: this.adminPassword })
            });

            const data = await response.json();
            if (response.ok && data.valid) {
                this.adminAuthorized = true;
                this.isVerifyingAdmin = false;
                return true;
            } else {
                this.adminAuthError = data.message || 'Incorrect administrator password.';
                this.isVerifyingAdmin = false;
                return false;
            }
        } catch (e) {
            console.error(e);
            this.adminAuthError = 'Verification request failed. Please check your network and try again.';
            this.isVerifyingAdmin = false;
            return false;
        }
    },

    async confirmAdminAuth() {
        const ok = await this.verifyAdminPasswordAsync();
        if (!ok) return;

        this.adminAuthModalOpen = false;
        const reason = this.adminAuthReason;
        const callback = this.pendingCartAction;
        this.pendingCartAction = null;

        if (reason === 'decrease_service' && callback) {
            callback();
        } else if (reason === 'discount' || !reason) {
            this.submitForm(false);
        }
    },

    authorizeAdminAndSubmit() {
        this.confirmAdminAuth();
    }
};
}
</script>



<div x-data="appointmentTerminal()" class="space-y-6 w-full">

    <!-- Top Action Bar & Navigation Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-none border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold text-slate-400 uppercase tracking-wider">
                <span>Salon Operations</span>
                <span>•</span>
                <span class="text-indigo-600">POS Billing & Appointments</span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight heading-font mt-0.5">
                <template x-if="editingAppointmentId">
                    <span>Edit Booking <span class="text-indigo-600" x-text="'#' + editingBookingNo"></span></span>
                </template>
                <template x-if="!editingAppointmentId">
                    <span>Billing & Service Checkout</span>
                </template>
            </h1>
        </div>

        <!-- Mode Switcher Tabs -->
        <div class="flex flex-wrap items-center gap-2">
            <!-- Held Bills Modal Trigger -->
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
                <a href="{{ route('manager.appointments.create') }}" 
                   class="px-4 py-2 text-xs rounded-none transition-all flex items-center gap-2 bg-white text-indigo-700 shadow-sm font-black">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    <span>New Bill Terminal</span>
                </a>
                <a href="{{ route('manager.appointments.upcoming') }}" 
                   class="px-4 py-2 text-xs rounded-none transition-all flex items-center gap-2 text-slate-600 hover:text-slate-900 font-bold">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <span>Upcoming Bookings</span>
                </a>
                <a href="{{ route('manager.appointments.index') }}" 
                   class="px-4 py-2 text-xs rounded-none transition-all flex items-center gap-2 text-slate-600 hover:text-slate-900 font-bold">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    <span>Bookings History ({{ $totalBookingsCount }})</span>
                </a>
                <a href="{{ route('manager.ratings.index') }}" 
                   class="px-4 py-2 text-xs rounded-none transition-all flex items-center gap-1.5 text-amber-700 hover:text-amber-800 hover:bg-amber-50 font-bold">
                    <svg class="w-3.5 h-3.5 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    <span>Staff Feedback</span>
                </a>
            </div>
        </div>
    </div>


    <div class="space-y-5">
        <!-- Active Editing Notification Banner -->
        <div x-show="editingAppointmentId" class="p-3.5 bg-amber-50 border border-amber-300 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs rounded-none">
            <div class="flex flex-wrap items-center gap-2.5">
                <span class="w-2.5 h-2.5 bg-amber-500 rounded-full animate-ping"></span>
                <span class="font-black text-amber-900">
                    Editing Booking <span class="underline font-mono" x-text="'#' + editingBookingNo"></span> 
                    (Client: <span x-text="selectedCustomerObj ? selectedCustomerObj.name : 'Client'"></span>)
                </span>
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-[10px] font-black uppercase" 
                      :class="orderType === 'Online' ? 'bg-cyan-100 text-cyan-800 border border-cyan-300' : 'bg-slate-200 text-slate-800 border border-slate-300'">
                    <template x-if="orderType === 'Online'">
                        <svg class="w-3 h-3 text-cyan-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                    </template>
                    <template x-if="orderType !== 'Online'">
                        <svg class="w-3 h-3 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    </template>
                    <span x-text="orderType === 'Online' ? 'Online Booking' : 'On Site Booking'"></span>
                </span>
                <template x-if="originalConfirmedBooking && !canFullyModifyCart()">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-200 text-amber-950 font-bold text-[10px] uppercase border border-amber-300">
                        <svg class="w-3 h-3 text-amber-800" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
                        <span>Confirmed: Decreasing/removing services requires Admin</span>
                    </span>
                </template>
                <template x-if="originalConfirmedBooking && canFullyModifyCart() && (isAdminUser || adminAuthorized)">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-100 text-emerald-900 font-bold text-[10px] uppercase border border-emerald-300">
                        <svg class="w-3 h-3 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg>
                        <span>Admin Full Edit Unlocked</span>
                    </span>
                </template>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <span class="text-[11px] font-bold text-amber-800" x-text="'Already Paid: PKR ' + getPreviouslyPaid().toLocaleString()"></span>
                <button type="button" @click="resetToNewBill()" class="px-3 py-1 bg-white hover:bg-amber-100 text-amber-900 font-bold border border-amber-300 transition-colors">
                    Cancel & Start New Bill
                </button>
            </div>
        </div>

        <form id="posBookingForm" method="POST" :action="editingAppointmentId ? ('/manager/appointments/' + editingAppointmentId) : '{{ route('manager.appointments.store') }}'" @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true;">
            @csrf
            <input type="hidden" name="form_submission_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <template x-if="editingAppointmentId">
                <input type="hidden" name="_method" value="PUT">
            </template>

            <input type="hidden" name="order_type" :value="orderType">
            <input type="hidden" name="account_id" :value="selectedCustomerId">
            <input type="hidden" name="employee_id" :value="selectedEmployeeId">
            <input type="hidden" name="appointment_date" :value="billDate">
            <input type="hidden" name="discount_type" :value="discountType">
            <input type="hidden" name="discount" :value="getCalculatedDiscount()">
            <input type="hidden" name="discount_percentage" :value="discountPercentage">
            <input type="hidden" name="extra_amount" :value="getExtraFee()">
            <input type="hidden" name="status" :value="bookingStatus">
            <input type="hidden" name="admin_password" :value="adminPassword">
            
            <!-- Send Total Paid to Backend -->
            <input type="hidden" name="paid_amount" :value="getTotalPaidAmount()">
            <input type="hidden" name="payment_mode" :value="paymentMode">
            <input type="hidden" name="bank_account_id" :value="(paymentMode === 'Bank' || (paymentMode === 'Split' && (splitPayments.Bank || 0) > 0)) ? bankAccountId : ''">
            <input type="hidden" name="split_payments" :value="paymentMode === 'Split' ? JSON.stringify(splitPayments) : ''">
            <input type="hidden" name="notes" :value="notes">

            <!-- Itemized Services with Custom Titles, Prices, Quantities & Service Discounts -->
            <template x-for="(item, idx) in items" :key="idx">
                <div>
                    <input type="hidden" name="service_ids[]" :value="item.service_id">
                    <input type="hidden" name="service_quantities[]" :value="item.quantity || 1">
                    <input type="hidden" name="service_custom_titles[]" :value="item.custom_title || ''">
                    <input type="hidden" name="service_prices[]" :value="item.price">
                    <input type="hidden" name="service_discounts[]" :value="item.discount || 0">
                    <input type="hidden" name="service_discount_types[]" :value="item.discount_type || 'percentage'">
                </div>
            </template>

            <div class="space-y-5">
                <!-- TOP HORIZONTAL CONTROL STRIP: Client Details & Booking Parameters Bar -->
                <div class="bg-white rounded-none border border-slate-200 p-4 sm:p-5 shadow-sm space-y-3.5">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-center">
                        
                        <!-- CLIENT PROFILE & MEMBERSHIP CARD (4 COLS) -->
                        <div class="lg:col-span-5 xl:col-span-4 p-3 bg-slate-50 border border-slate-200 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 overflow-hidden">
                                <div class="w-11 h-11 bg-indigo-600 text-white font-black text-xs flex items-center justify-center shrink-0 shadow-2xs">
                                    <span x-text="selectedCustomerObj ? selectedCustomerObj.name.substring(0, 2).toUpperCase() : 'CL'">CL</span>
                                </div>
                                <div class="overflow-hidden">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <h4 class="font-extrabold text-xs text-slate-900 truncate" x-text="selectedCustomerObj ? selectedCustomerObj.name : 'Select Client'">Sara Khan</h4>
                                        <!-- Card Tier Badge with SVG card icon -->
                                        <template x-if="isCardDiscountEnforced()">
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[9px] font-black uppercase shrink-0"
                                                  :class="{
                                                      'bg-slate-200 text-slate-800 border border-slate-300': getCustomerCardType().toLowerCase().includes('silver'),
                                                      'bg-amber-100 text-amber-900 border border-amber-300': getCustomerCardType().toLowerCase().includes('gold'),
                                                      'bg-purple-100 text-purple-900 border border-purple-300': getCustomerCardType().toLowerCase().includes('platinum')
                                                  }">
                                                <svg class="w-2.5 h-2.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                                <span x-text="getCustomerCardType()"></span>
                                            </span>
                                        </template>
                                        <template x-if="items.length > 0 && !hasNonDiscountedItem() && getCardFixedDiscountPercent() !== null">
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[9px] font-black uppercase shrink-0 bg-amber-100 text-amber-800 border border-amber-300">
                                                <svg class="w-2.5 h-2.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                                <span x-text="getCustomerCardType() + ' (All Disc)'"></span>
                                            </span>
                                        </template>
                                    </div>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <p class="text-[11px] text-slate-500 font-medium truncate" x-text="selectedCustomerObj ? (selectedCustomerObj.phone_no1 || 'No Phone') : '0300-1234567'">0300-1234567</p>
                                        <template x-if="!isCardDiscountEnforced() && getCustomerCardType() !== 'No Card'">
                                            <span class="text-[10px] text-slate-400 font-bold" x-text="'(' + getCustomerCardType() + ')'"></span>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <!-- Switch Client Action Button -->
                            <button type="button" @click="customerModalOpen = true" 
                                    class="px-3 py-1.5 text-xs font-extrabold text-indigo-700 bg-white hover:bg-indigo-50 border border-slate-300 hover:border-indigo-300 transition-colors flex items-center gap-1.5 shrink-0 shadow-2xs">
                                <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                <span>Change Client</span>
                            </button>
                        </div>

                        <!-- BOOKING PARAMETERS (ST YLIST, CHANNEL, DATE, STATUS) (8 COLS) -->
                        <div class="lg:col-span-7 xl:col-span-8 grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-xs font-bold text-slate-700">
                            <!-- Assigned Stylist / Staff -->
                            <div class="space-y-1">
                                <label class="block text-slate-500 font-bold text-[10px] uppercase tracking-wider flex items-center gap-1">
                                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                    <span>Stylist / Staff</span>
                                </label>
                                <select x-model="selectedEmployeeId" 
                                        class="w-full px-2.5 py-2 bg-slate-50 border border-slate-200 font-bold text-slate-800 text-xs focus:ring-1 focus:ring-indigo-500 focus:bg-white truncate">
                                    <option value="">-- No Specific Staff --</option>
                                    @foreach($employees as $emp)
                                        <option value="{{ $emp->id }}">
                                            {{ $emp->name }} {{ $emp->category ? '('.$emp->category->title.')' : '' }} {{ $emp->average_ranking > 0 ? '— Rating: '.number_format($emp->average_ranking, 1).'/5' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Service Date -->
                            <div class="space-y-1">
                                <label class="block text-slate-500 font-bold text-[10px] uppercase tracking-wider flex items-center gap-1">
                                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    <span>Service Date</span>
                                </label>
                                <input type="date" x-model="billDate" 
                                       class="w-full px-2.5 py-2 bg-slate-50 border border-slate-200 font-bold text-slate-800 text-xs focus:ring-1 focus:ring-indigo-500 focus:bg-white">
                            </div>

                            <!-- Order Channel -->
                            <div class="space-y-1">
                                <label class="block text-slate-500 font-bold text-[10px] uppercase tracking-wider flex items-center gap-1">
                                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                                    <span>Channel</span>
                                </label>
                                <select x-model="orderType" 
                                        class="w-full px-2.5 py-2 bg-slate-50 border border-slate-200 font-bold text-slate-800 text-xs focus:ring-1 focus:ring-indigo-500 focus:bg-white">
                                    <option value="On Site">On Site (POS)</option>
                                    <option value="Online">Online Booking</option>
                                </select>
                            </div>

                            <!-- Booking Status & Invoice Tag -->
                            <div class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <label class="block text-slate-500 font-bold text-[10px] uppercase tracking-wider flex items-center gap-1">
                                        <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        <span>Status</span>
                                    </label>
                                    <span class="text-[10px] font-mono font-bold text-indigo-600" x-text="billNo"></span>
                                </div>
                                <select x-model="bookingStatus" 
                                        class="w-full px-2.5 py-2 bg-slate-50 border border-slate-200 font-bold text-slate-800 text-xs focus:ring-1 focus:ring-indigo-500 focus:bg-white">
                                    <option value="confirmed">Confirmed</option>
                                    <option value="completed">Completed</option>
                                    <option value="pending">Pending</option>
                                    <option value="cancelled">Cancelled</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                        </div>

                    </div>

                    <!-- Client Notes / Special Instructions Ribbon -->
                    <div class="pt-2 border-t border-slate-100 flex items-center gap-2">
                        <span class="text-slate-400 shrink-0">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        </span>
                        <input type="text" x-model="notes" placeholder="Special hair, styling, color allergy or service instructions (optional)..."
                               class="w-full px-2 py-1 bg-transparent border-0 text-xs font-medium text-slate-700 placeholder-slate-400 focus:ring-0 focus:outline-none">
                    </div>
                </div>

                <!-- 2-COLUMN MAIN WORKSPACE (CATALOG 7 COLS | REGISTER 5 COLS) -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

                    <!-- LEFT COLUMN: Treatments & Services Catalog (col-span-7) -->
                    <div class="col-span-1 lg:col-span-7 xl:col-span-7 bg-white rounded-none border border-slate-200 p-4 sm:p-5 shadow-sm space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <span class="w-6 h-6 rounded-none bg-indigo-600 text-white font-black text-xs flex items-center justify-center">1</span>
                                <h3 class="font-extrabold text-sm text-slate-900 heading-font">Treatments & Ad Ons Catalog</h3>
                            </div>
                            <span class="text-xs font-bold text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded-none border border-indigo-100">
                                Click card to Add or Adjust
                            </span>
                        </div>

                        <!-- Search Filter Bar -->
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </span>
                            <input type="text" x-model="serviceSearchQuery" placeholder="Search treatments, ad ons, facial, bridal..." 
                                   class="w-full pl-10 pr-9 py-2.5 bg-slate-50 border border-slate-200 rounded-none text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:bg-white">
                            <button type="button" x-show="serviceSearchQuery" @click="serviceSearchQuery = ''" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>

                        <!-- Category Pills Horizontal Scroll -->
                        <div class="flex items-center gap-1.5 overflow-x-auto pb-1.5 no-scrollbar">
                            <button type="button" @click="activeCategory = 'All Services'"
                                    :class="activeCategory === 'All Services' ? 'bg-indigo-600 text-white font-black shadow-2xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 font-bold border border-slate-200'"
                                    class="px-3.5 py-1.5 text-xs rounded-none whitespace-nowrap transition-all">
                                All Treatments
                            </button>
                            <template x-for="cat in serviceCategories" :key="cat.id">
                                <button type="button" @click="activeCategory = cat.title"
                                        :class="activeCategory === cat.title ? 'bg-indigo-600 text-white font-black shadow-2xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 font-bold border border-slate-200'"
                                        class="px-3.5 py-1.5 text-xs rounded-none whitespace-nowrap transition-all"
                                        x-text="cat.title">
                                </button>
                            </template>
                        </div>

                        <!-- Services Grid Display (Spacious 2-3-4 columns) -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3 pt-1 max-h-[660px] overflow-y-auto pr-1">
                            <template x-for="srv in getFilteredServices()" :key="srv.id">
                                <div @click="toggleService(srv)"
                                     :class="isServiceSelected(srv.id) ? 'ring-2 ring-indigo-600 border-indigo-600 bg-indigo-50/40' : 'border-slate-200 hover:border-indigo-400 bg-white'"
                                     class="border rounded-none p-3 cursor-pointer transition-all flex flex-col justify-between group shadow-2xs relative">
                                    <div class="space-y-2">
                                        <div class="w-full aspect-[4/3] rounded-none overflow-hidden bg-slate-100 relative group/img"
                                             x-data="{ imgLoaded: false, imgError: false }">
                                            <!-- Shimmer Skeleton Placeholder while loading -->
                                            <div x-show="!imgLoaded && !imgError" 
                                                 class="absolute inset-0 bg-gradient-to-r from-slate-100 via-slate-200 to-slate-100 animate-pulse flex items-center justify-center">
                                                <svg class="w-6 h-6 text-slate-300 animate-spin" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                                </svg>
                                            </div>

                                            <!-- Enhanced Image with Native Lazy Loading -->
                                            <img :src="getImageUrl(srv)" 
                                                 loading="lazy"
                                                 decoding="async"
                                                 @load="imgLoaded = true"
                                                 x-on:error="imgError = true; imgLoaded = true; $el.src = 'https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=500&q=80'"
                                                 :class="imgLoaded ? 'opacity-100' : 'opacity-0'"
                                                 class="w-full h-full object-cover group-hover:scale-105 transition-all duration-500 ease-out" 
                                                 :alt="srv.title">
                                            
                                            <!-- Subtle Bottom Gradient on Hover -->
                                            <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"></div>

                                            <!-- Category Pill Badge on Image -->
                                            <template x-if="srv.category && srv.category.title">
                                                <span class="absolute top-2 left-2 bg-slate-900/80 backdrop-blur-xs text-white px-2 py-0.5 text-[9px] font-black rounded-none uppercase tracking-wider shadow-xs"
                                                      x-text="srv.category.title"></span>
                                            </template>

                                            <!-- Active Cart Quantity Badge -->
                                            <div x-show="getServiceCartQuantity(srv.id) > 0" class="absolute top-2 right-2 bg-indigo-600 text-white px-2 py-0.5 text-[10px] font-black rounded-none shadow-md flex items-center gap-1 ring-1 ring-white/50">
                                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                <span x-text="getServiceCartQuantity(srv.id)"></span>
                                            </div>
                                        </div>
                                        <h4 class="font-bold text-xs text-slate-900 leading-snug line-clamp-2" x-text="srv.title">Hair Cut</h4>
                                    </div>
                                    <div class="mt-2.5 pt-2 border-t border-slate-100 space-y-2">
                                        <div class="flex items-center justify-between">
                                            <span class="text-[10px] text-slate-400 font-semibold uppercase">Price</span>
                                            <div class="text-right">
                                                <template x-if="srv.discount > 0 || (srv.discounted_price > 0 && srv.discounted_price < srv.price)">
                                                    <div class="flex items-center gap-1 justify-end">
                                                        <span class="text-[10px] text-slate-400 line-through">PKR <span x-text="Number(srv.price).toLocaleString()"></span></span>
                                                        <span class="px-1 py-0.2 bg-rose-100 text-rose-800 text-[9px] font-black" x-text="(srv.discount > 0 ? srv.discount + '% OFF' : 'DISC')"></span>
                                                    </div>
                                                </template>
                                                <p class="font-black text-xs text-indigo-600">PKR <span x-text="Number(srv.discounted_price || srv.price).toLocaleString()">500</span></p>
                                            </div>
                                        </div>

                                        <!-- Quick Multi-Quantity Buttons on Service Card -->
                                        <div x-show="getServiceCartQuantity(srv.id) > 0" class="flex items-center justify-between bg-white border border-indigo-200 p-1 text-xs">
                                            <button type="button" @click="decrementService(srv, $event)" 
                                                    class="w-6 h-6 flex items-center justify-center font-black text-xs transition-colors"
                                                    :class="(!canFullyModifyCart() && getServiceCartQuantity(srv.id) <= getOriginalConfirmedQty(srv.id)) ? 'bg-amber-100 text-amber-800 hover:bg-amber-200' : 'bg-slate-100 hover:bg-rose-100 hover:text-rose-700 text-slate-700'"
                                                    :title="(!canFullyModifyCart() && getServiceCartQuantity(srv.id) <= getOriginalConfirmedQty(srv.id)) ? 'Decreasing confirmed service quantity requires Admin authorization' : 'Decrease'">
                                                <template x-if="!canFullyModifyCart() && getServiceCartQuantity(srv.id) <= getOriginalConfirmedQty(srv.id)">
                                                    <svg class="w-3 h-3 text-amber-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
                                                </template>
                                                <template x-if="canFullyModifyCart() || getServiceCartQuantity(srv.id) > getOriginalConfirmedQty(srv.id)">
                                                    <span>-</span>
                                                </template>
                                            </button>
                                            <span class="font-black text-xs text-indigo-700 px-1" x-text="'Qty: ' + getServiceCartQuantity(srv.id)"></span>
                                            <button type="button" @click="incrementService(srv, $event)" class="w-6 h-6 flex items-center justify-center bg-indigo-50 hover:bg-indigo-600 hover:text-white text-indigo-700 font-black text-xs transition-colors" title="Increase">
                                                +
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: Bill Register, Discount & Clearance (col-span-5) -->
                    <div class="col-span-1 lg:col-span-5 xl:col-span-5 bg-white rounded-none border border-slate-200 p-4 sm:p-5 shadow-sm space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div class="flex items-center gap-2.5">
                                <span class="w-6 h-6 rounded-none bg-indigo-600 text-white font-black text-xs flex items-center justify-center">2</span>
                                <h3 class="font-extrabold text-sm text-slate-900 heading-font">Bill & Clearance Summary</h3>
                            </div>
                            <span class="text-xs font-bold text-slate-700 bg-slate-100 px-2.5 py-0.5 rounded-none border border-slate-200" x-text="items.length + ' Service(s)'"></span>
                        </div>

                        <!-- DEDICATED ADD-ONS BAR -->
                        <div class="p-3.5 bg-slate-50/90 border border-slate-200 shadow-2xs space-y-3">
                            <!-- Header -->
                            <div class="flex items-center justify-between flex-wrap gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center justify-center w-5 h-5 bg-indigo-600 text-white shadow-2xs">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v12m6-6H6"></path></svg>
                                    </span>
                                    <h4 class="text-xs font-black text-slate-900 uppercase tracking-wider">Quick Add-Ons / Custom Services</h4>
                                    <span class="px-1.5 py-0.5 text-[9px] font-black uppercase bg-indigo-50 text-indigo-700 border border-indigo-200">Multiple Allowed</span>
                                </div>
                                <template x-if="items.filter(it => isAddOn(it)).length > 0">
                                    <span class="inline-flex items-center gap-1.5 text-[10px] font-black text-indigo-900 bg-indigo-100/90 px-2.5 py-0.5 border border-indigo-200 shadow-2xs">
                                        <span x-text="items.filter(it => isAddOn(it)).length + ' in bill'"></span>
                                        <span>·</span>
                                        <span x-text="'PKR ' + getAddOnsTotal().toLocaleString()"></span>
                                    </span>
                                </template>
                            </div>

                            <!-- Input Controls (2 Balanced, Substantial Rows) -->
                            <div class="space-y-2">
                                <!-- Row 1: Add-on Title (Full width, spacious & tactile) -->
                                <div class="relative w-full">
                                    <input type="text" 
                                           x-model="newAddonTitle" 
                                           @keydown.enter.prevent="addCustomAddon()"
                                           placeholder="Enter add-on title (e.g. Hair Wash, Blowdry, Extra Serum)..."
                                           class="w-full h-10 px-3 bg-white border border-slate-300 focus:border-indigo-600 focus:ring-1 focus:ring-indigo-500 text-xs font-bold text-slate-900 placeholder:text-slate-400 rounded-none shadow-2xs transition-colors">
                                </div>

                                <!-- Row 2: Price (PKR) + Healthy Stepper + Substantial Add Button -->
                                <div class="flex items-center gap-2 w-full">
                                    <!-- Price Input with integrated PKR tag -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center h-10 border border-slate-300 bg-white focus-within:border-indigo-600 focus-within:ring-1 focus-within:ring-indigo-500 shadow-2xs">
                                            <span class="h-full px-3 flex items-center justify-center text-[11px] font-black text-slate-600 bg-slate-100 border-r border-slate-200 select-none shrink-0">PKR</span>
                                            <input type="number" 
                                                   step="any" 
                                                   min="0" 
                                                   x-model.number="newAddonPrice" 
                                                   @keydown.enter.prevent="addCustomAddon()"
                                                   placeholder="0.00" 
                                                   title="Add-on Price in PKR"
                                                   class="w-full h-full px-3 text-right bg-transparent text-xs font-black text-slate-900 border-0 focus:outline-none">
                                        </div>
                                    </div>

                                    <!-- Compact Quantity Stepper (~86px wide, h-10) -->
                                    <div class="shrink-0">
                                        <div class="flex items-center h-10 border border-slate-300 bg-white shadow-2xs select-none" title="Quantity">
                                            <button type="button" 
                                                    @click="newAddonQty = Math.max(1, (parseInt(newAddonQty)||1) - 1)" 
                                                    class="w-7 h-full bg-slate-50 hover:bg-slate-100 active:bg-slate-200 text-slate-700 flex items-center justify-center font-black text-sm transition-colors cursor-pointer"
                                                    title="Decrease Quantity">−</button>
                                            <input type="number" 
                                                   min="1" 
                                                   x-model.number="newAddonQty" 
                                                   @keydown.enter.prevent="addCustomAddon()" 
                                                   class="w-10 h-full text-center text-xs font-black text-slate-900 border-x border-slate-200 focus:outline-none p-0 bg-white"
                                                   title="Quantity">
                                            <button type="button" 
                                                    @click="newAddonQty = (parseInt(newAddonQty)||1) + 1" 
                                                    class="w-7 h-full bg-slate-50 hover:bg-slate-100 active:bg-slate-200 text-slate-700 flex items-center justify-center font-black text-sm transition-colors cursor-pointer"
                                                    title="Increase Quantity">+</button>
                                        </div>
                                    </div>

                                    <!-- Substantial, Healthy Add Button -->
                                    <button type="button" 
                                            @click="addCustomAddon()"
                                            class="h-10 px-4 sm:px-5 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-black text-xs uppercase tracking-wider flex items-center justify-center gap-1.5 transition-all shadow-sm active:scale-95 shrink-0 cursor-pointer">
                                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                                        <span class="whitespace-nowrap">Add to Bill</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Selected Services Table -->
                        <div class="overflow-x-auto min-h-[110px] max-h-[260px] overflow-y-auto border border-slate-200/80 bg-slate-50/30">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-100 border-b border-slate-200 text-slate-500 font-extrabold text-[10px] uppercase tracking-wider">
                                        <th class="py-2 px-2.5">Service Title</th>
                                        <th class="py-2 px-1.5 text-right">Price</th>
                                        <th class="py-2 px-1 text-center w-24">Discount</th>
                                        <th class="py-2 px-1 text-center w-16">Qty</th>
                                        <th class="py-2 px-2 text-right">Net Total</th>
                                        <th class="py-2 px-1 text-center w-6"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200/70 font-semibold text-slate-700 bg-white">
                                    <template x-for="(item, idx) in items" :key="idx">
                                        <tr class="hover:bg-slate-50/80 transition-colors">
                                            <!-- Service Title -->
                                            <td class="py-2 px-2.5 align-middle">
                                                <template x-if="!isAddOn(item)">
                                                    <div>
                                                        <span x-text="item.service_obj ? item.service_obj.title : 'Service'" class="block font-bold text-slate-900 text-xs truncate max-w-[130px]">Service</span>
                                                        <div class="flex flex-wrap items-center gap-1 mt-0.5">
                                                            <template x-if="isItemDiscounted(item)">
                                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.2 text-[9px] font-black bg-rose-100 text-rose-800 border border-rose-200 uppercase tracking-tight">
                                                                    <svg class="w-2.5 h-2.5 text-rose-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                                                                    <span>Discounted</span>
                                                                </span>
                                                            </template>
                                                            <template x-if="originalConfirmedBooking && getOriginalConfirmedQty(item.service_id) > 0">
                                                                <span class="inline-flex items-center gap-0.5 px-1 py-0.2 text-[9px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 uppercase tracking-tight"
                                                                      :title="'Original confirmed booking quantity: ' + getOriginalConfirmedQty(item.service_id)">
                                                                    <svg class="w-2.5 h-2.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
                                                                    <span x-text="'Confirmed (' + getOriginalConfirmedQty(item.service_id) + ')'"></span>
                                                                </span>
                                                            </template>
                                                            <template x-if="originalConfirmedBooking && getOriginalConfirmedQty(item.service_id) === 0">
                                                                <span class="inline-flex items-center gap-0.5 px-1 py-0.2 text-[9px] font-bold bg-sky-50 text-sky-800 border border-sky-200 uppercase tracking-tight">
                                                                    <svg class="w-2.5 h-2.5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                                                    <span>New Added</span>
                                                                </span>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </template>

                                                <!-- Editable Ad-On Service Name -->
                                                <template x-if="isAddOn(item)">
                                                    <div class="space-y-1">
                                                        <div class="flex items-center gap-1">
                                                            <span class="px-1.5 py-0.2 text-[9px] font-black bg-amber-100 text-amber-900 border border-amber-300 uppercase inline-block">Ad-On</span>
                                                            <template x-if="isItemDiscounted(item)">
                                                                <span class="px-1.5 py-0.2 text-[9px] font-black bg-rose-100 text-rose-800 border border-rose-200 uppercase inline-block">Disc</span>
                                                            </template>
                                                        </div>
                                                        <input type="text" x-model="item.custom_title" placeholder="Add-on Name..." 
                                                               class="w-full px-2 py-1 bg-amber-50/70 border border-amber-300 focus:border-amber-500 focus:bg-white text-xs font-bold text-slate-900 rounded-none focus:ring-1 focus:ring-amber-500 transition-colors">
                                                    </div>
                                                </template>
                                            </td>

                                            <!-- Unit Price -->
                                            <td class="py-2 px-1.5 text-right align-middle whitespace-nowrap">
                                                <template x-if="!isAddOn(item)">
                                                    <div>
                                                        <template x-if="getItemDiscountAmount(item) > 0">
                                                            <div class="text-[10px] text-slate-400 line-through leading-none">PKR <span x-text="Number(item.price).toLocaleString()">0</span></div>
                                                        </template>
                                                        <span class="font-bold text-xs" :class="getItemDiscountAmount(item) > 0 ? 'text-emerald-700 font-black' : 'text-slate-700'">
                                                            PKR <span x-text="Number(getItemNetUnitPrice(item)).toLocaleString()">0</span>
                                                        </span>
                                                    </div>
                                                </template>

                                                <template x-if="isAddOn(item)">
                                                    <div>
                                                        <div class="inline-flex items-center border border-amber-300 bg-white shadow-2xs focus-within:border-amber-500 focus-within:ring-1 focus-within:ring-amber-400">
                                                            <span class="px-1.5 py-0.5 text-[9px] font-black text-amber-800 bg-amber-50 border-r border-amber-200 select-none">PKR</span>
                                                            <input type="number" step="any" min="0" x-model.number="item.price" @input="recalculateDiscount()" placeholder="0"
                                                                   class="w-16 px-1.5 py-0.5 text-right bg-transparent text-xs font-black text-slate-900 border-0 focus:outline-none">
                                                        </div>
                                                        <template x-if="getItemDiscountAmount(item) > 0">
                                                            <div class="text-[9px] font-bold text-emerald-700 mt-0.5">Net: <span x-text="Number(getItemNetUnitPrice(item)).toLocaleString()"></span></div>
                                                        </template>
                                                    </div>
                                                </template>
                                            </td>

                                            <!-- Service Discount Input & Toggle (% / PKR) -->
                                            <td class="py-2 px-1 text-center align-middle whitespace-nowrap">
                                                <div class="inline-flex items-center border bg-white shadow-2xs transition-colors"
                                                     :class="(item.discount && parseFloat(item.discount) > 0) ? 'border-rose-400 bg-rose-50/40 ring-1 ring-rose-200' : 'border-slate-300'">
                                                    <input type="number" step="any" min="0" 
                                                           x-model.number="item.discount" 
                                                           @input="recalculateDiscount()" 
                                                           placeholder="0"
                                                           title="Enter service discount"
                                                           class="w-10 text-right text-xs font-black border-0 p-0.5 focus:outline-none focus:bg-rose-50"
                                                           :class="(item.discount && parseFloat(item.discount) > 0) ? 'text-rose-700' : 'text-slate-700'">
                                                    <button type="button" 
                                                            @click="item.discount_type = (item.discount_type === 'fixed' ? 'percentage' : 'fixed'); recalculateDiscount()" 
                                                            class="px-1 py-0.5 text-[9px] font-black border-l border-slate-300 transition-colors"
                                                            :class="(item.discount_type === 'fixed') ? 'bg-amber-100 text-amber-900 border-amber-300' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'"
                                                            :title="'Switch discount mode: ' + (item.discount_type === 'fixed' ? 'Switch to % percentage' : 'Switch to PKR flat')"
                                                            x-text="item.discount_type === 'fixed' ? 'PKR' : '%'">
                                                    </button>
                                                </div>
                                                <template x-if="item.discount && parseFloat(item.discount) > 0">
                                                    <div class="text-[9px] font-black text-rose-600 mt-0.5" x-text="'-PKR ' + getItemDiscountAmount(item).toLocaleString()"></div>
                                                </template>
                                            </td>

                                            <!-- Quantity Stepper Controls -->
                                            <td class="py-2 px-1 text-center align-middle whitespace-nowrap">
                                                <div class="inline-flex items-center border border-slate-300 bg-white shadow-2xs">
                                                    <button type="button" 
                                                            @click="decrementCartItem(item, idx)" 
                                                            :class="!canDecreaseItemQty(item) ? 'bg-amber-50 text-amber-700 hover:bg-amber-100' : 'text-slate-500 hover:bg-rose-50 hover:text-rose-600'"
                                                            :title="!canDecreaseItemQty(item) ? 'Decreasing confirmed service quantity requires Admin authorization' : 'Decrease Quantity'"
                                                            class="w-4 h-5 flex items-center justify-center font-black text-xs transition-colors">
                                                        <template x-if="!canDecreaseItemQty(item)">
                                                            <svg class="w-2.5 h-2.5 text-amber-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
                                                        </template>
                                                        <template x-if="canDecreaseItemQty(item)">
                                                            <span>-</span>
                                                        </template>
                                                    </button>
                                                    <input type="number" 
                                                           :min="canFullyModifyCart() ? 1 : Math.max(1, getOriginalConfirmedQty(item.service_id))" 
                                                           x-model.number="item.quantity" 
                                                           @input="handleQuantityInput(item)" 
                                                           class="w-6 h-5 text-center text-xs font-black border-x border-slate-300 p-0 focus:outline-none focus:bg-indigo-50">
                                                    <button type="button" 
                                                            @click="item.quantity = (parseInt(item.quantity) || 1) + 1; recalculateDiscount()" 
                                                            class="w-4 h-5 flex items-center justify-center text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 font-black text-xs transition-colors"
                                                            title="Increase Quantity">
                                                        +
                                                    </button>
                                                </div>
                                            </td>

                                            <!-- Net Total -->
                                            <td class="py-2 px-2 text-right align-middle whitespace-nowrap">
                                                <span class="font-black text-indigo-700 text-xs">PKR <span x-text="Number(getItemLineTotal(item)).toLocaleString()">0</span></span>
                                            </td>

                                            <!-- Remove Row Action -->
                                            <td class="py-2 px-1 text-center align-middle">
                                                <button type="button" 
                                                        @click="removeItem(idx)" 
                                                        class="transition-colors p-1"
                                                        :class="!canRemoveItem(item) ? 'text-amber-500 hover:text-amber-700' : 'text-slate-300 hover:text-rose-600'" 
                                                        :title="!canRemoveItem(item) ? 'Removing confirmed service requires Admin authorization' : 'Remove Service'">
                                                    <template x-if="!canRemoveItem(item)">
                                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
                                                    </template>
                                                    <template x-if="canRemoveItem(item)">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                    </template>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="items.length === 0">
                                        <td colspan="6" class="py-8 text-center text-slate-400 font-medium">
                                            No treatments in bill. Select treatments from the catalog to add.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- DEDICATED BILL DISCOUNT BOX -->
                        <div class="p-3.5 bg-rose-50/40 border border-rose-200 rounded-none space-y-2.5">
                            <div class="flex items-center justify-between flex-wrap gap-2">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                                    <label class="text-xs font-black text-slate-900 uppercase tracking-wider">Bill Discount</label>
                                    
                                    <!-- Card Tier Badge (Locked Fixed Discount) -->
                                    <template x-if="isCardDiscountEnforced()">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-black border uppercase tracking-wider"
                                              :class="{
                                                  'bg-slate-200 text-slate-800 border-slate-300': getCustomerCardType().toLowerCase().includes('silver'),
                                                  'bg-amber-100 text-amber-900 border-amber-300': getCustomerCardType().toLowerCase().includes('gold'),
                                                  'bg-purple-100 text-purple-900 border-purple-300': getCustomerCardType().toLowerCase().includes('platinum')
                                              }">
                                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                            <span x-text="getCustomerCardType() + ' (' + getCardFixedDiscountPercent() + '%)'"></span>
                                        </span>
                                    </template>

                                    <!-- Card Discount when all items in cart are discounted -->
                                    <template x-if="items.length > 0 && !hasNonDiscountedItem() && getCardFixedDiscountPercent() !== null">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-black bg-amber-100 text-amber-800 border border-amber-300 uppercase tracking-wider">
                                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                            <span x-text="getCustomerCardType() + ' (No Non-Discounted Items)'"></span>
                                        </span>
                                    </template>

                                    <!-- Normal Flow Badges (No Card) -->
                                    <template x-if="hasNonDiscountedItem() && !isCardDiscountEnforced() && !hasDiscountPermission && !adminAuthorized && !isAdminUser">
                                        <span class="px-1.5 py-0.2 bg-amber-100 text-amber-800 text-[10px] font-black border border-amber-200">
                                            Max 10% (Admin >10%)
                                        </span>
                                    </template>
                                    <template x-if="hasNonDiscountedItem() && !isCardDiscountEnforced() && hasDiscountPermission && !isAdminUser">
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.2 bg-emerald-100 text-emerald-800 text-[10px] font-black border border-emerald-200">
                                            <svg class="w-3 h-3 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            <span>Permission Allowed</span>
                                        </span>
                                    </template>
                                    <template x-if="hasNonDiscountedItem() && !isCardDiscountEnforced() && adminAuthorized">
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.2 bg-emerald-100 text-emerald-800 text-[10px] font-black border border-emerald-200">
                                            <svg class="w-3 h-3 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            <span>Admin Authorized</span>
                                        </span>
                                    </template>
                                </div>

                                <!-- Discount Mode Switcher (% vs Flat PKR) -->
                                <template x-if="!isCardDiscountEnforced() && hasNonDiscountedItem()">
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
                                </template>

                                <!-- Suspended Badge if Cart contains ONLY discounted items -->
                                <template x-if="items.length > 0 && !hasNonDiscountedItem()">
                                    <span class="inline-flex items-center gap-1 text-[11px] font-black text-amber-800 bg-amber-100 px-2 py-0.5 border border-amber-300">
                                        <svg class="w-3 h-3 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                        <span>All Items Discounted</span>
                                    </span>
                                </template>

                                <!-- Partial Indicator when both discounted and non-discounted items exist -->
                                <template x-if="hasDiscountedItem() && hasNonDiscountedItem()">
                                    <span class="inline-flex items-center gap-1 text-[10px] font-black text-emerald-800 bg-emerald-50 px-2 py-0.5 border border-emerald-300">
                                        <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                        <span>Eligible Base: PKR <span x-text="getNonDiscountedSubtotal().toLocaleString()"></span></span>
                                    </span>
                                </template>

                                <!-- Locked Indicator when Cardholder Discount is Enforced on full bill -->
                                <template x-if="isCardDiscountEnforced() && !hasDiscountedItem()">
                                    <span class="inline-flex items-center gap-1 text-[11px] font-black text-indigo-700 bg-indigo-50 px-2 py-0.5 border border-indigo-200">
                                        <svg class="w-3 h-3 text-indigo-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                                        <span>Fixed Member Discount</span>
                                    </span>
                                </template>
                            </div>

                            <!-- Card Discount Explanatory Note -->
                            <template x-if="isCardDiscountEnforced()">
                                <div class="p-2.5 bg-indigo-50/70 border border-indigo-200 text-indigo-900 text-[11px] font-semibold flex items-center gap-2">
                                    <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    <div>
                                        Customer holds <strong><span x-text="getCustomerCardType()"></span></strong>. A fixed <strong><span x-text="getCardFixedDiscountPercent() + '% discount'"></span></strong> is applied to <span x-text="hasDiscountedItem() ? 'the non-discounted services (PKR ' + getNonDiscountedSubtotal().toLocaleString() + ')' : 'the total bill'"></span>.
                                    </div>
                                </div>
                            </template>

                            <!-- Notice if cart has both discounted and non-discounted items -->
                            <template x-if="hasDiscountedItem() && hasNonDiscountedItem() && !isCardDiscountEnforced()">
                                <div class="p-2.5 bg-blue-50 border border-blue-200 text-blue-900 text-xs font-semibold flex items-start gap-2">
                                    <svg class="w-4 h-4 text-blue-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <div class="space-y-0.5">
                                        <div class="font-black text-blue-950">Partial Discount Policy</div>
                                        <div class="text-[11px] text-blue-800 leading-tight">
                                            Cart contains both promotional and regular services. The bill discount will be applied <strong>only to the non-discounted items</strong> (Base: PKR <span x-text="getNonDiscountedSubtotal().toLocaleString()"></span>).
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Notice if ALL items are discounted -->
                            <template x-if="items.length > 0 && !hasNonDiscountedItem()">
                                <div class="p-2.5 bg-amber-50 border border-amber-300 text-amber-900 text-xs font-semibold flex items-start gap-2">
                                    <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                    <div class="space-y-0.5">
                                        <div class="font-black text-amber-950">Promotional Services Only</div>
                                        <div class="text-[11px] text-amber-800 leading-tight">
                                            All treatments in this order are already discounted. Extra bill discount or membership card discounts cannot be stacked.
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div class="space-y-2.5">
                                <!-- Custom Discount Input Field -->
                                <div class="relative">
                                    <!-- Percentage Input -->
                                    <div x-show="discountType === 'percentage'" class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-xs font-black text-slate-400">%</span>
                                        <input type="number" step="any" min="0" max="100" 
                                               x-model.number="discountPercentage" 
                                               @input="recalculateDiscount()"
                                               :readonly="!hasNonDiscountedItem() || isCardDiscountEnforced()"
                                               :disabled="!hasNonDiscountedItem() || isCardDiscountEnforced()"
                                               :placeholder="!hasNonDiscountedItem() ? 'Discounts disabled (All items already discounted)' : (hasDiscountedItem() ? 'Discount % on non-discounted (PKR ' + getNonDiscountedSubtotal() + ')' : 'Enter discount percentage (e.g. 10)')" 
                                               :class="(!hasNonDiscountedItem() || isCardDiscountEnforced()) ? 'bg-slate-100 text-slate-500 cursor-not-allowed border-slate-300 font-bold' : 'bg-white border-rose-300 text-slate-900 focus:ring-2 focus:ring-rose-500 font-black'"
                                               class="w-full pl-9 pr-4 py-2 border text-xs rounded-none">
                                    </div>

                                    <!-- Flat PKR Input -->
                                    <div x-show="discountType === 'fixed'" class="relative" x-cloak>
                                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-xs font-black text-slate-400">PKR</span>
                                        <input type="number" step="any" min="0" 
                                               x-model.number="billDiscount" 
                                               @input="recalculateDiscount()"
                                               :readonly="!hasNonDiscountedItem() || isCardDiscountEnforced()"
                                               :disabled="!hasNonDiscountedItem() || isCardDiscountEnforced()"
                                               :placeholder="!hasNonDiscountedItem() ? 'Discounts disabled (All items already discounted)' : (hasDiscountedItem() ? 'Discount on non-discounted (max PKR ' + getNonDiscountedSubtotal() + ')' : 'Enter discount amount in PKR')" 
                                               :class="(!hasNonDiscountedItem() || isCardDiscountEnforced()) ? 'bg-slate-100 text-slate-500 cursor-not-allowed border-slate-300 font-bold' : 'bg-white border-rose-300 text-slate-900 focus:ring-2 focus:ring-rose-500 font-black'"
                                               class="w-full pl-9 pr-4 py-2 border text-xs rounded-none">
                                    </div>
                                </div>

                                <!-- Live Discount Amount Preview -->
                                <div class="flex items-center justify-between text-[11px] font-bold pt-0.5">
                                    <span class="text-rose-700">
                                        Deducts: <strong class="font-black text-rose-800">PKR <span x-text="getCalculatedDiscount().toLocaleString()">0</span></strong>
                                        <template x-if="hasDiscountedItem() && hasNonDiscountedItem()">
                                            <span class="text-[10px] text-slate-500 font-normal">
                                                (from PKR <span x-text="getNonDiscountedSubtotal().toLocaleString()"></span> regular)
                                            </span>
                                        </template>
                                    </span>
                                    <template x-if="!isCardDiscountEnforced() && !hasDiscountPermission && !isAdminUser">
                                        <span class="text-[10px] text-slate-500 font-medium">
                                            (Allowed: up to 10% directly)
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Totals Breakdown -->
                        <div class="space-y-2 pt-1 border-t border-slate-100 text-xs font-bold text-slate-600">
                            <div class="flex items-center justify-between">
                                <span>Subtotal (Gross)</span>
                                <span class="text-slate-900 font-bold">PKR <span x-text="getRawSubtotal().toLocaleString()">0</span></span>
                            </div>

                            <!-- Non-discounted items eligible total (shown when cart has both) -->
                            <template x-if="hasDiscountedItem() && hasNonDiscountedItem()">
                                <div class="flex items-center justify-between text-[11px] text-slate-500 font-semibold bg-slate-50 px-2 py-1 border border-slate-200">
                                    <span>Regular Services Base:</span>
                                    <span class="font-black text-slate-800">PKR <span x-text="getNonDiscountedSubtotal().toLocaleString()">0</span></span>
                                </div>
                            </template>

                            <!-- Service Discounts Line (Item-level discounts) -->
                            <div class="flex items-center justify-between text-rose-600" x-show="getItemDiscountsTotal() > 0">
                                <div class="flex items-center gap-1.5">
                                    <span>Service Discounts</span>
                                    <span class="px-1.5 py-0.5 bg-rose-100 text-rose-800 font-black text-[10px] rounded-none border border-rose-200">Item Level</span>
                                </div>
                                <span class="font-extrabold">- PKR <span x-text="getItemDiscountsTotal().toLocaleString()">0</span></span>
                            </div>

                            <div class="flex items-center justify-between text-rose-600" x-show="getCalculatedDiscount() > 0">
                                <div class="flex items-center gap-1.5">
                                    <span>Bill Discount</span>
                                    <span class="px-1.5 py-0.5 bg-rose-100 text-rose-800 font-black text-[10px] rounded-none border border-rose-200" x-text="discountPercentage + '%'">0%</span>
                                </div>
                                <span class="font-extrabold">- PKR <span x-text="getCalculatedDiscount().toLocaleString()">0</span></span>
                            </div>

                            <!-- Card / Bank Surcharge Fee Line -->
                            <div class="flex items-center justify-between" x-show="getExtraFee() > 0">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-indigo-700 font-bold">Processing Fee:</span>
                                    <span class="px-1.5 py-0.5 bg-indigo-100 text-indigo-800 font-black text-[10px] rounded-none border border-indigo-200" x-text="(parseFloat(extraPercentage) || 0) + '%'">0%</span>
                                </div>
                                <span class="text-indigo-700 font-black">+ PKR <span x-text="getExtraFee().toLocaleString()">0</span></span>
                            </div>

                            <div class="flex items-center justify-between pt-2.5 border-t border-slate-200">
                                <span class="font-extrabold text-sm text-slate-900">Total Net Bill</span>
                                <span class="font-black text-2xl text-indigo-600">PKR <span x-text="getTotalAmount().toLocaleString()">0</span></span>
                            </div>
                        </div>

                        <!-- PAYMENT METHOD SELECTOR -->
                        <div class="space-y-2 pt-1">
                            <label class="block text-xs font-bold text-slate-700">Payment Mode</label>
                            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                                <!-- Cash -->
                                <button type="button" @click="paymentMode = 'Cash'; extraPercentage = 0; recalculateTotals()"
                                        :class="paymentMode === 'Cash' ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-black ring-1 ring-indigo-500' : 'border-slate-200 text-slate-600 font-bold bg-white hover:bg-slate-50'"
                                        class="p-2 border rounded-none text-xs flex flex-col items-center gap-1 transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                    <span>Cash</span>
                                </button>
                                <!-- Card -->
                                <button type="button" @click="paymentMode = 'Card'; recalculateTotals()"
                                        :class="paymentMode === 'Card' ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-black ring-1 ring-indigo-500' : 'border-slate-200 text-slate-600 font-bold bg-white hover:bg-slate-50'"
                                        class="p-2 border rounded-none text-xs flex flex-col items-center gap-1 transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                    <span>Card</span>
                                </button>
                                <!-- Bank -->
                                <button type="button" @click="paymentMode = 'Bank'; recalculateTotals()"
                                        :class="paymentMode === 'Bank' ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-black ring-1 ring-indigo-500' : 'border-slate-200 text-slate-600 font-bold bg-white hover:bg-slate-50'"
                                        class="p-2 border rounded-none text-xs flex flex-col items-center gap-1 transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                    <span>Bank</span>
                                </button>
                                <!-- Other -->
                                <button type="button" @click="paymentMode = 'Other'; extraPercentage = 0; recalculateTotals()"
                                        :class="paymentMode === 'Other' ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-black ring-1 ring-indigo-500' : 'border-slate-200 text-slate-600 font-bold bg-white hover:bg-slate-50'"
                                        class="p-2 border rounded-none text-xs flex flex-col items-center gap-1 transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span>Other</span>
                                </button>
                                <!-- Split -->
                                <button type="button" @click="paymentMode = 'Split'; if (getSplitTotal() === 0) fillSplitRemainder('Cash'); recalculateTotals()"
                                        :class="paymentMode === 'Split' ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-black ring-2 ring-indigo-600' : 'border-slate-200 text-slate-600 font-bold bg-white hover:bg-slate-50'"
                                        class="p-2 border rounded-none text-xs flex flex-col items-center gap-1 transition-all col-span-2 sm:col-span-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                                    <span class="flex items-center gap-1">
                                        <span>Split</span>
                                        <span class="text-[9px] font-black uppercase text-indigo-600 bg-indigo-100 px-1 py-0.2 rounded-xs">Multi</span>
                                    </span>
                                </button>
                            </div>

                            <!-- DEDICATED RECEIVING BANK ACCOUNT SELECTOR -->
                            <div x-show="paymentMode === 'Bank' || (paymentMode === 'Split' && ((splitPayments.Bank || 0) > 0))" 
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 x-cloak 
                                 class="p-3 bg-gradient-to-r from-blue-50/90 via-indigo-50/70 to-slate-50 border border-indigo-200 space-y-2">
                                <div class="flex items-center justify-between">
                                    <label class="block text-xs font-black text-indigo-950 uppercase tracking-wider flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-indigo-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                        <span>Deposit Into Bank Account</span>
                                    </label>
                                    <span class="text-[10px] font-bold text-indigo-700 bg-white px-2 py-0.5 border border-indigo-200">
                                        Where is money received?
                                    </span>
                                </div>
                                <select name="bank_account_id" x-model="bankAccountId" 
                                        class="w-full bg-white border border-indigo-300 py-2 px-3 text-xs font-extrabold text-slate-900 focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600 rounded-none shadow-2xs">
                                    <template x-for="b in bankAccountsList" :key="b.bankid">
                                        <option :value="b.bankid" 
                                                x-text="b.bank_name + ' — ' + b.account_title + ' (' + b.account_no + ')' + (b.branch_name ? ' • ' + b.branch_name : '')">
                                        </option>
                                    </template>
                                </select>
                                <template x-if="getSelectedBankAccount()">
                                    <div class="flex items-center justify-between p-2 bg-white border border-indigo-100 text-[11px] text-slate-600">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold text-slate-900" x-text="getSelectedBankAccount().bank_name"></span>
                                            <span class="text-slate-400">•</span>
                                            <span class="font-mono text-indigo-700 font-bold" x-text="getSelectedBankAccount().account_no"></span>
                                        </div>
                                        <span class="font-semibold text-slate-600" x-text="getSelectedBankAccount().account_title"></span>
                                    </div>
                                </template>
                            </div>

                            <!-- SPLIT PAYMENT BREAKDOWN PANEL -->
                            <div x-show="paymentMode === 'Split'" x-cloak class="p-3.5 bg-gradient-to-br from-indigo-50/80 via-white to-slate-50 border border-indigo-200 space-y-3 shadow-xs">
                                <div class="flex flex-wrap items-center justify-between gap-1.5 pb-2 border-b border-indigo-100">
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-indigo-600 animate-pulse"></span>
                                        <h4 class="text-xs font-black text-indigo-950 uppercase tracking-wider">Split Payment Breakdown</h4>
                                    </div>
                                    <div class="flex items-center gap-1 text-[10px]">
                                        <button type="button" @click="presetSplit('Cash', 'Card')" class="px-2 py-0.5 font-bold bg-white border border-indigo-200 text-indigo-700 hover:bg-indigo-100 transition-colors">50% Cash / Card</button>
                                        <button type="button" @click="presetSplit('Cash', 'Bank')" class="px-2 py-0.5 font-bold bg-white border border-indigo-200 text-indigo-700 hover:bg-indigo-100 transition-colors">50% Cash / Bank</button>
                                        <button type="button" @click="clearSplitPayments()" class="px-2 py-0.5 font-bold bg-white border border-slate-200 text-rose-600 hover:bg-rose-50 transition-colors">Reset</button>
                                    </div>
                                </div>

                                <p class="text-[11px] text-slate-500">Allocate the bill across multiple payment methods. Click <strong class="text-indigo-700 font-bold">Fill Remainder</strong> to auto-assign the remaining balance.</p>

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
                                                class="px-2.5 py-1.5 text-[10px] font-black bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100 transition-colors shrink-0 inline-flex items-center gap-1">
                                            <svg class="w-3 h-3 text-indigo-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/></svg>
                                            <span>Fill Remainder</span>
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
                                                class="px-2.5 py-1.5 text-[10px] font-black bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100 transition-colors shrink-0 inline-flex items-center gap-1">
                                            <svg class="w-3 h-3 text-indigo-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/></svg>
                                            <span>Fill Remainder</span>
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
                                                class="px-2.5 py-1.5 text-[10px] font-black bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100 transition-colors shrink-0 inline-flex items-center gap-1">
                                            <svg class="w-3 h-3 text-indigo-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/></svg>
                                            <span>Fill Remainder</span>
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
                                                class="px-2.5 py-1.5 text-[10px] font-black bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100 transition-colors shrink-0 inline-flex items-center gap-1">
                                            <svg class="w-3 h-3 text-indigo-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/></svg>
                                            <span>Fill Remainder</span>
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
                                                <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                <span>Matches bill exactly (PKR 0 remaining)</span>
                                            </span>
                                        </template>
                                        <template x-if="getSplitRemainder() > 0">
                                            <span class="inline-flex items-center gap-1 font-bold text-amber-700">
                                                <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                                <span>PKR <span x-text="getSplitRemainder().toLocaleString()"></span> remaining to allocate</span>
                                            </span>
                                        </template>
                                        <template x-if="getSplitRemainder() < 0">
                                            <span class="inline-flex items-center gap-1 font-bold text-rose-700">
                                                <svg class="w-3.5 h-3.5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                                <span>Allocated exceeds bill by PKR <span x-text="Math.abs(getSplitRemainder()).toLocaleString()"></span></span>
                                            </span>
                                        </template>
                                        <span class="text-slate-400 font-medium">Target: PKR <span x-text="getTotalAmount().toLocaleString()"></span></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Surcharge Percentage Input Field & Quick Buttons -->
                            <div x-show="paymentMode === 'Card' || paymentMode === 'Bank' || (paymentMode === 'Split' && ((splitPayments.Card || 0) > 0 || (splitPayments.Bank || 0) > 0))" class="p-3 bg-indigo-50/70 border border-indigo-200 space-y-2">
                                <div class="flex items-center justify-between">
                                    <label class="block text-[11px] font-bold text-indigo-900 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-indigo-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                        <span>Card / Bank Processing Charges (%)</span>
                                    </label>
                                    <div class="flex items-center gap-1">
                                        <button type="button" @click="extraPercentage = 1.5; recalculateTotals()" class="px-1.5 py-0.5 text-[10px] font-bold bg-white border border-indigo-200 text-indigo-700 hover:bg-indigo-100 transition-colors">1.5%</button>
                                        <button type="button" @click="extraPercentage = 2; recalculateTotals()" class="px-1.5 py-0.5 text-[10px] font-bold bg-white border border-indigo-200 text-indigo-700 hover:bg-indigo-100 transition-colors">2%</button>
                                        <button type="button" @click="extraPercentage = 2.5; recalculateTotals()" class="px-1.5 py-0.5 text-[10px] font-bold bg-white border border-indigo-200 text-indigo-700 hover:bg-indigo-100 transition-colors">2.5%</button>
                                        <button type="button" @click="extraPercentage = 3; recalculateTotals()" class="px-1.5 py-0.5 text-[10px] font-bold bg-white border border-indigo-200 text-indigo-700 hover:bg-indigo-100 transition-colors">3%</button>
                                        <button type="button" @click="extraPercentage = 0; recalculateTotals()" class="px-1.5 py-0.5 text-[10px] font-bold bg-white border border-slate-200 text-slate-500 hover:bg-rose-50 hover:text-rose-600 transition-colors">0%</button>
                                    </div>
                                </div>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-xs font-black text-indigo-500">%</span>
                                    <input type="number" step="any" min="0" max="100" x-model.number="extraPercentage" @input="recalculateTotals()" placeholder="Enter card surcharge % (e.g. 2)" 
                                           class="w-full pl-8 pr-3 py-1.5 bg-white border border-indigo-300 text-xs font-black text-indigo-900 rounded-none focus:ring-1 focus:ring-indigo-600">
                                </div>
                                <div class="flex items-center justify-between text-[11px] font-bold text-indigo-900 pt-0.5" x-show="getExtraFee() > 0">
                                    <span>Processing Fee Added:</span>
                                    <span class="font-black text-indigo-700">+ PKR <span x-text="getExtraFee().toLocaleString()">0</span> <span class="text-[10px] font-semibold text-indigo-500" x-text="'(' + extraPercentage + '%)'"></span></span>
                                </div>
                            </div>
                        </div>

                        <!-- PAYMENT STATUS & CLEARANCE BOX -->
                        <div class="p-3.5 bg-slate-50 border border-slate-200 space-y-3 rounded-none">
                            <div class="flex items-center justify-between text-xs font-bold">
                                <span class="text-slate-800 uppercase tracking-wider text-[11px] font-black flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                    <span>Payment & Settlement</span>
                                </span>
                                <template x-if="!isAlreadyFullyCleared()">
                                    <button type="button" @click="clearFullRemainingBalance()" class="inline-flex items-center gap-1 text-[11px] font-black text-indigo-600 hover:text-indigo-800 underline">
                                        <svg class="w-3 h-3 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span>Clear Full Balance</span>
                                    </button>
                                </template>
                            </div>

                            <!-- WHEN EDITING: Prominently show Already Paid and Still Pending Amount -->
                            <template x-if="editingAppointmentId">
                                <div class="p-3 bg-white border border-slate-200 space-y-2 text-xs">
                                    <div class="flex items-center justify-between font-bold">
                                        <span class="text-slate-600">Already Paid Amount:</span>
                                        <span class="text-emerald-700 font-black bg-emerald-50 px-2.5 py-0.5 border border-emerald-200">
                                            PKR <span x-text="getAlreadyPaid().toLocaleString()">0</span>
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between font-bold border-t border-slate-100 pt-1.5">
                                        <span class="text-slate-600">Still Pending Amount:</span>
                                        <span class="px-2.5 py-0.5 border font-black" 
                                              :class="getInitialPendingBeforePay() > 0 ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'"
                                              x-text="getInitialPendingBeforePay() > 0 ? ('PKR ' + getInitialPendingBeforePay().toLocaleString() + ' Due') : 'Cleared (PKR 0)'">
                                        </span>
                                    </div>
                                </div>
                            </template>

                            <!-- Payment Input Field (Single mode) -->
                            <div class="space-y-1.5" x-show="paymentMode !== 'Split'">
                                <template x-if="editingAppointmentId">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 flex items-center justify-between">
                                            <span>Collect Additional Payment (PKR)</span>
                                            <template x-if="isAlreadyFullyCleared()">
                                                <span class="inline-flex items-center gap-1 text-[10px] text-emerald-700 font-bold bg-emerald-50 px-1.5 py-0.2 border border-emerald-200">
                                                    <svg class="w-3 h-3 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                                                    <span>Disabled (Bill Already Paid)</span>
                                                </span>
                                            </template>
                                        </label>
                                        <input type="number" step="any" 
                                               x-model.number="additionalPayment" 
                                               :disabled="isAlreadyFullyCleared()"
                                               :placeholder="isAlreadyFullyCleared() ? '0 (Fully Paid)' : 'Enter amount to collect...'" 
                                               :class="isAlreadyFullyCleared() ? 'bg-slate-100 text-slate-400 border-slate-200 cursor-not-allowed' : 'bg-white text-slate-900 border-slate-300 focus:ring-2 focus:ring-indigo-500'"
                                               class="w-full px-3.5 py-2.5 text-xs font-black rounded-none border">
                                    </div>
                                </template>
                                <template x-if="!editingAppointmentId">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700">Amount Received (PKR)</label>
                                        <input type="number" step="any" x-model.number="receivedAmount" placeholder="Enter amount received..." 
                                               class="w-full px-3.5 py-2.5 bg-white border border-slate-300 text-xs font-black text-slate-900 focus:ring-2 focus:ring-indigo-500 rounded-none">
                                    </div>
                                </template>
                            </div>

                            <!-- Split mode note in clearance box -->
                            <div x-show="paymentMode === 'Split'" class="p-2.5 bg-indigo-50 border border-indigo-200 space-y-1">
                                <span class="text-xs font-black text-indigo-900 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span>Split Allocation Active</span>
                                </span>
                                <p class="text-[11px] text-slate-600">Amounts are allocated by payment method in the Split Breakdown panel above.</p>
                            </div>

                            <!-- Live Settlement Results -->
                            <div class="pt-2 space-y-1.5 text-xs font-bold border-t border-slate-200">
                                <div class="flex items-center justify-between text-slate-600">
                                    <span>Total Paid:</span>
                                    <span class="text-slate-900 font-extrabold">PKR <span x-text="getTotalPaidAmount().toLocaleString()">0</span></span>
                                </div>

                                <div class="flex items-center justify-between" x-show="getStillPendingAmount() > 0">
                                    <span class="text-rose-600 font-bold">Remaining Balance Due:</span>
                                    <span class="text-sm font-black text-rose-600">PKR <span x-text="getStillPendingAmount().toLocaleString()">0</span></span>
                                </div>

                                <div class="flex items-center justify-between" x-show="getChangeReturn() > 0">
                                    <span class="text-emerald-700 font-bold">Change Return / Excess:</span>
                                    <span class="text-sm font-black text-emerald-700">PKR <span x-text="getChangeReturn().toLocaleString()">0</span></span>
                                </div>

                                <div class="flex items-center justify-between text-emerald-700 pt-0.5" x-show="getStillPendingAmount() === 0">
                                    <span class="font-bold">Bill Status:</span>
                                    <span class="inline-flex items-center gap-1 text-xs font-black bg-emerald-100 text-emerald-900 px-2 py-0.5 border border-emerald-300">
                                        <svg class="w-3 h-3 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span>100% Cleared & Paid</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons (Hold, Preview Receipt, Save & Print 80mm, Complete) -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-2">
                            <button type="button" @click="holdCurrentBill()" :disabled="isSubmitting"
                                    class="py-3 px-2 bg-amber-50 hover:bg-amber-100 text-amber-900 font-extrabold text-xs rounded-none transition-all flex items-center justify-center gap-1.5 border border-amber-300 shadow-2xs"
                                    :class="{ 'opacity-50 cursor-not-allowed pointer-events-none': isSubmitting }">
                                <svg class="w-4 h-4 text-amber-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span>Hold</span>
                            </button>
                            <button type="button" @click="openReceiptModal()" :disabled="isSubmitting"
                                    class="py-3 px-2 bg-slate-100 hover:bg-slate-200 text-slate-800 font-extrabold text-xs rounded-none transition-all flex items-center justify-center gap-1.5 border border-slate-300 shadow-2xs"
                                    :class="{ 'opacity-50 cursor-not-allowed pointer-events-none': isSubmitting }">
                                <svg class="w-4 h-4 text-slate-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                <span>Receipt</span>
                            </button>
                            <button type="button" @click="submitForm(true)" :disabled="isSubmitting"
                                    class="py-3 px-2 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs rounded-none transition-all flex items-center justify-center gap-1.5 shadow-xs"
                                    :class="{ 'opacity-70 cursor-not-allowed pointer-events-none': isSubmitting }">
                                <template x-if="isSubmitting">
                                    <svg class="animate-spin w-4 h-4 text-white shrink-0" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                </template>
                                <template x-if="!isSubmitting">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                </template>
                                <span x-text="isSubmitting ? 'Processing...' : 'Print 80mm'"></span>
                            </button>
                            <button type="button" @click="submitForm(false)" :disabled="isSubmitting"
                                    class="py-3 px-2 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs rounded-none transition-all flex items-center justify-center gap-1.5 shadow-xs"
                                    :class="{ 'opacity-70 cursor-not-allowed pointer-events-none': isSubmitting }">
                                <template x-if="isSubmitting">
                                    <svg class="animate-spin w-4 h-4 text-white shrink-0" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                </template>
                                <template x-if="!isSubmitting">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                </template>
                                <span x-text="isSubmitting ? 'Processing...' : (editingAppointmentId ? 'Update' : 'Complete')"></span>
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </form>
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
                    <input type="text" x-model="customerSearchQuery" placeholder="Search client by name or phone..." 
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-none text-xs font-semibold focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="max-h-72 overflow-y-auto space-y-2 divide-y divide-slate-100">
                    <template x-for="cust in getFilteredCustomers()" :key="cust.id">
                        <div @click="selectCustomer(cust)" 
                             class="flex items-center justify-between p-3 hover:bg-indigo-50/50 rounded-none cursor-pointer transition-colors group">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-none bg-indigo-600 text-white font-bold text-xs flex items-center justify-center shrink-0">
                                    <span x-text="cust.name ? cust.name.substring(0, 2).toUpperCase() : 'CL'"></span>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-bold text-xs text-slate-900 group-hover:text-indigo-600" x-text="cust.name">Client</h4>
                                        <template x-if="cust.card_type && cust.card_type.trim() !== '' && cust.card_type.trim().toLowerCase() !== 'no card'">
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.2 text-[9px] font-black uppercase border"
                                                  :class="{
                                                      'bg-slate-100 text-slate-700 border-slate-300': cust.card_type.toLowerCase().includes('silver'),
                                                      'bg-amber-100 text-amber-900 border-amber-300': cust.card_type.toLowerCase().includes('gold'),
                                                      'bg-purple-100 text-purple-900 border-purple-300': cust.card_type.toLowerCase().includes('platinum')
                                                  }">
                                                <svg class="w-2.5 h-2.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                                <span x-text="cust.card_type"></span>
                                            </span>
                                        </template>
                                    </div>
                                    <p class="text-[11px] text-slate-400" x-text="cust.phone_no1 || 'No Phone'"></p>
                                </div>
                            </div>
                            <span class="text-xs font-bold text-indigo-600 opacity-0 group-hover:opacity-100 transition-opacity">Select &rarr;</span>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- ADMIN AUTHORIZATION MODAL (FOR DISCOUNT > 10% OR CONFIRMED CART MODIFICATIONS) -->
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
                    <template x-if="adminAuthReason === 'decrease_service'">
                        <div>
                            <p class="font-bold text-slate-800">
                                The booking <span class="underline font-mono text-indigo-700" x-text="'#' + (editingBookingNo || billNo)"></span> is already <span class="text-emerald-700 font-black">Confirmed</span>.
                            </p>
                            <p class="mt-1 text-slate-600">
                                Decreasing confirmed service quantities or removing confirmed treatments requires <strong>Administrator Authorization</strong>. (New services can still be added by anyone).
                            </p>
                        </div>
                    </template>
                    <template x-if="adminAuthReason !== 'decrease_service'">
                        <div>
                            <p class="font-bold text-slate-800">
                                This bill includes a discount of <span class="text-rose-600 font-black text-sm" x-text="(discountPercentage || 0) + '% (PKR ' + getCalculatedDiscount().toLocaleString() + ')'"></span> which exceeds your allowed 10% limit.
                            </p>
                            <p class="mt-1 text-slate-600">
                                Please enter the <strong>Administrator Password</strong> to authorize and complete this booking.
                            </p>
                        </div>
                    </template>
                </div>

                <!-- Error Message Banner -->
                <div x-show="adminAuthError" class="p-2.5 bg-rose-50 border border-rose-300 text-rose-800 text-xs font-bold flex items-center gap-2">
                    <svg class="w-4 h-4 text-rose-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                    <span x-text="adminAuthError"></span>
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-bold text-slate-700">Administrator Password</label>
                    <input type="password" x-model="adminPassword" placeholder="Enter admin password..." 
                           @keydown.enter.prevent="confirmAdminAuth()"
                           :disabled="isVerifyingAdmin"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-none text-xs font-bold text-slate-900 focus:ring-2 focus:ring-rose-500">
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                    <button type="button" @click="adminAuthModalOpen = false" :disabled="isVerifyingAdmin" class="px-4 py-2 bg-slate-100 text-slate-600 font-bold text-xs rounded-none hover:bg-slate-200 border border-slate-200 disabled:opacity-50">
                        Cancel
                    </button>
                    <button type="button" @click="confirmAdminAuth()" :disabled="isVerifyingAdmin" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white font-black text-xs rounded-none shadow-xs disabled:opacity-50 inline-flex items-center gap-1.5">
                        <template x-if="isVerifyingAdmin">
                            <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </template>
                        <span x-text="isVerifyingAdmin ? 'Verifying...' : (adminAuthReason === 'decrease_service' ? 'Authorize & Unlock' : 'Authorize & Complete')">Authorize</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- HELD APPOINTMENTS / BILLS MODAL -->


    <div x-show="heldBillsModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-xs" @click="heldBillsModalOpen = false"></div>
            <div class="inline-block w-full max-w-2xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-none border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 bg-amber-50 text-amber-700 flex items-center justify-center border border-amber-200 shrink-0">
                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-slate-900 heading-font">Held Appointments & Bills</h3>
                            <p class="text-[11px] text-slate-500 font-medium">Resume any held booking directly into the billing terminal</p>
                        </div>
                    </div>
                    <button type="button" @click="heldBillsModalOpen = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Empty State -->
                <template x-if="heldBills.length === 0">
                    <div class="py-12 text-center space-y-2">
                        <div class="w-12 h-12 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto">
                            <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <p class="text-xs font-bold text-slate-700">No appointments currently on hold.</p>
                        <p class="text-[11px] text-slate-400">Click "Hold Bill" in the billing terminal to park an appointment and resume it later.</p>
                    </div>
                </template>

                <!-- List of Held Bills -->
                <template x-if="heldBills.length > 0">
                    <div class="space-y-3 max-h-[60vh] overflow-y-auto divide-y divide-slate-100">
                        <template x-for="(held, index) in heldBills" :key="held.id">
                            <div class="p-3.5 bg-slate-50 border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-white transition-all">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs font-black text-indigo-700" x-text="held.billNo"></span>
                                        <span class="text-[10px] font-bold text-slate-400" x-text="'• Held at ' + held.heldAt"></span>
                                        <span class="px-1.5 py-0.2 bg-amber-100 text-amber-900 text-[10px] font-bold border border-amber-300">Held</span>
                                    </div>
                                    <div class="text-xs font-bold text-slate-900">
                                        Client: <span x-text="held.clientName"></span>
                                    </div>
                                    <div class="text-[11px] text-slate-500 font-medium">
                                        <span x-text="(held.items ? held.items.length : 0) + ' service(s)'"></span> &bull;
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

    <!-- 80MM THERMAL RECEIPT PREVIEW & PRINT MODAL -->


    @php
        $brandSettings = \App\Models\Setting::getSettings();
    @endphp
    <div x-show="receiptModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-xs" @click="receiptModalOpen = false"></div>
            <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-xl border border-slate-300 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 bg-indigo-50 text-indigo-700 flex items-center justify-center border border-indigo-200 shrink-0">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        </div>
                        <h3 class="text-sm font-extrabold text-slate-900 heading-font">POS 80mm Thermal Receipt</h3>
                    </div>
                    <button type="button" @click="receiptModalOpen = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Receipt Paper Simulation Container -->
                <div class="bg-slate-100 p-4 border border-slate-200 shadow-inner max-h-[70vh] overflow-y-auto flex justify-center">
                    <div id="pos80mmReceipt" class="pos-receipt-80mm-container bg-white p-4 border border-slate-300 shadow-sm w-full max-w-[80mm] text-black text-xs leading-normal space-y-2.5" style="font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif;">
                        @include('manager.appointments._receipt_content')
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-200">
                    <button type="button" @click="receiptModalOpen = false" 
                            class="px-4 py-2 bg-slate-100 text-slate-700 font-bold text-xs rounded-none hover:bg-slate-200">
                        Close
                    </button>
                    <button type="button" @click="print80mmReceiptDirect()" 
                            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs rounded-none transition-colors flex items-center gap-1.5 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        <span>Print 80mm Receipt</span>
                    </button>
                </div>
            </div>
        </div>
    </div>



    <!-- DEDICATED 80MM THERMAL RECEIPT PRINT CONTAINER (RENDERED CLEANLY ON PRINT) -->
    <div id="posReceiptPrintMount" class="hidden print:block">
        <div id="pos80mmReceiptPrint" class="pos-receipt-80mm-container bg-white p-0 text-black text-xs leading-normal space-y-2.5" style="font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif;">
            @include('manager.appointments._receipt_content')
        </div>
    </div>


</div>

<script>
window.buildAppointmentReceiptDocument = function(data, brand) {
    if (!data) return '';
    brand = brand || {};
    const items = data.items || [];
    let itemsRows = '';
    items.forEach(it => {
        const price = Number(it.price || 0).toLocaleString();
        const disc = Number(it.discount || 0).toLocaleString();
        const total = Number(it.total || 0).toLocaleString();
        const itemDiscHtml = it.discountText ? `
            <div style="font-size: 9.5px; font-weight: 400; color: #000;">${it.discountText}</div>
        ` : '';
        itemsRows += `
            <tr style="border-bottom: 1px solid #000;">
                <td style="padding: 3px 0; text-align: left; font-weight: 400; word-break: break-word; color: #000; font-size: 11.5px; line-height: 1.3;">
                    ${it.title || 'Service'}
                    ${itemDiscHtml}
                </td>
                <td style="padding: 3px 0; text-align: center; font-weight: 400; color: #000; font-size: 11.5px;">${it.quantity || 1}</td>
                <td style="padding: 3px 0; text-align: right; font-weight: 400; color: #000; font-size: 11.5px;">${price}</td>
                <td style="padding: 3px 0; text-align: right; font-weight: 400; color: #000; font-size: 11.5px;">${disc}</td>
                <td style="padding: 3px 0; text-align: right; font-weight: 500; color: #000; font-size: 11.5px;">${total}</td>
            </tr>
        `;
    });

    let discountRows = '';
    if (data.serviceDiscount > 0 && data.billDiscount > 0) {
        discountRows = `
            <div style="display: flex; justify-content: space-between; margin-bottom: 2px; color: #000; font-weight: 400; font-size: 11.5px;">
                <span>Service Discount:</span>
                <span style="font-weight: 500;">- PKR ${Number(data.serviceDiscount).toLocaleString()}</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 2px; color: #000; font-weight: 400; font-size: 11.5px;">
                <span>Bill Discount:</span>
                <span style="font-weight: 500;">- PKR ${Number(data.billDiscount).toLocaleString()}</span>
            </div>
        `;
    } else if (data.serviceDiscount > 0) {
        discountRows = `
            <div style="display: flex; justify-content: space-between; margin-bottom: 2px; color: #000; font-weight: 400; font-size: 11.5px;">
                <span>Service Discount:</span>
                <span style="font-weight: 500;">- PKR ${Number(data.serviceDiscount).toLocaleString()}</span>
            </div>
        `;
    } else if (data.discount > 0) {
        discountRows = `
            <div style="display: flex; justify-content: space-between; margin-bottom: 2px; color: #000; font-weight: 400; font-size: 11.5px;">
                <span>Service Discount:</span>
                <span style="font-weight: 500;">- PKR ${Number(data.discount).toLocaleString()}</span>
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

    let splitPaymentRows = '';
    if ((data.paymentMode || '').toLowerCase() === 'split' && data.splitPayments) {
        const sp = typeof data.splitPayments === 'string' ? JSON.parse(data.splitPayments) : data.splitPayments;
        for (const [mode, amt] of Object.entries(sp)) {
            if (Number(amt) > 0) {
                splitPaymentRows += `
                <div style="display: flex; justify-content: space-between; margin-bottom: 1.5px; padding-left: 8px; font-size: 11px; color: #000;">
                    <span style="font-weight: 400;">&bull; ${mode}:</span>
                    <span style="font-weight: 500;">PKR ${Number(amt).toLocaleString()}</span>
                </div>`;
            }
        }
    }

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
            SERVICE BILL / CASH RECEIPT
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
                <span style="font-weight: 400; color: #000;">Channel:</span>
                <span style="font-weight: 500; text-transform: uppercase; color: #000;">${data.orderType || 'On Site'}</span>
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
                        <th style="padding: 2.5px 0; text-align: left; font-size: 11px; font-weight: 600; color: #000;">ITEM / SERVICE</th>
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
            ${data.bankAccountName ? `
            <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                <span style="font-weight: 400; color: #000;">Deposit Bank:</span>
                <span style="font-weight: 500; color: #000;">${data.bankAccountName}</span>
            </div>` : ''}
            ${splitPaymentRows}

            <div style="display: flex; justify-content: space-between; margin-bottom: 2px; font-weight: 400; color: #000;">
                <span>Paid Amount:</span>
                <span style="font-weight: 500;">PKR ${Number(data.paidAmount || 0).toLocaleString()}</span>
            </div>

            ${balanceRow}
            ${changeRow}
        </div>

        ${notesSection}

        <div style="border-top: 1px solid #000; margin-top: 6px; padding-top: 5px; text-align: center; font-size: 11px; color: #000;">
            <div style="font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; font-size: 11.5px;">*** THANK YOU FOR VISITING ***</div>
            <div style="font-size: 11px; color: #000; font-weight: 400; margin-top: 3px; white-space: pre-line; line-height: 1.35;">${brand.appointmentReceiptNote ? brand.appointmentReceiptNote : 'Please inspect your receipt before leaving.'}</div>
            <div style="font-size: 11px; color: #000; font-weight: 400; margin-top: 4px;">Generated By: <strong style="color: #000; font-weight: 600;">${data.generatedBy || '{{ addslashes(auth()->user()->name ?? 'Staff') }}'}</strong></div>
            <div style="font-size: 10px; color: #000; font-weight: 400; margin-top: 3px;">Powered by RAPIDTECHPRO &bull; ${new Date().getFullYear()}</div>
        </div>
    </div>
</body>
</html>`;
};

window.uploadAppointmentReceiptSlip = async function(app) {
    const fileInput = app.$refs.slipFileInput;
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        alert('Please select an image file (PNG, JPG, WEBP) to upload.');
        return;
    }
    const file = fileInput.files[0];
    const formData = new FormData();
    formData.append('receipt_image', file);
    const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfTokenMeta) {
        formData.append('_token', csrfTokenMeta.getAttribute('content'));
    }

    app.proofModalData.uploading = true;
    app.proofModalData.errorMessage = '';

    try {
        const res = await fetch('/manager/appointments/' + app.proofModalData.appointmentId + '/upload-receipt', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        const data = await res.json();
        if (res.ok && data.success) {
            app.proofModalData.url = data.receipt_url + '?t=' + Date.now();
            app.proofModalData.imgError = false;
            app.proofModalData.imgLoading = true;
            app.proofModalData.uploadSuccess = true;
            app.proofModalData.showReplaceForm = false;
            fileInput.value = '';
            setTimeout(() => {
                app.proofModalData.uploadSuccess = false;
            }, 4000);
        } else {
            app.proofModalData.errorMessage = data.message || 'Failed to upload receipt slip. Please try again.';
        }
    } catch (err) {
        app.proofModalData.errorMessage = 'Upload error: ' + err.message;
    } finally {
        app.proofModalData.uploading = false;
    }
};
</script>
@endsection
