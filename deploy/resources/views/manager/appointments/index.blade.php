@extends('layouts.material')

@section('title', 'Appointments & POS Billing')

@section('content')
<div x-data="{
    activeTab: 'pos',
    customerModalOpen: false,
    adminAuthModalOpen: false,
    isAdminUser: {{ auth()->user()->hasRole('admin') ? 'true' : 'false' }},
    hasDiscountPermission: {{ (auth()->user()->hasRole('admin') || auth()->user()->hasPermission('allow-bill-discount')) ? 'true' : 'false' }},
    canApplyDiscount: true,
    adminPassword: '',
    adminAuthorized: false,

    selectedAppointment: null,
    editingAppointmentId: null,
    editingBookingNo: null,
    bookingStatus: 'confirmed',
    orderType: 'On Site', // 'On Site' (POS/In-store) or 'Online' (Website/App)

    // Backend Lists
    customersList: {{ json_encode($customers) }},
    employeesList: {{ json_encode($employees) }},
    saloonServices: {{ json_encode($saloonServices) }},
    serviceCategories: {{ json_encode($serviceCategories) }},

    // Selected Client State (Default: Walk-in Customer)
    selectedCustomerId: '{{ $defaultCustomer->id ?? ($customers->first()->id ?? '') }}',
    selectedCustomerObj: {{ json_encode($defaultCustomer ?? ($customers->first() ?? null)) }},
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
            case 5: return '⭐⭐⭐⭐⭐ Top Excellent (5/5)';
            case 4: return '⭐⭐⭐⭐ Very Good (4/5)';
            case 3: return '⭐⭐⭐ Good Service (3/5)';
            case 2: return '⭐⭐ Fair Performance (2/5)';
            case 1: return '⭐ Needs Improvement (1/5)';
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

    // Calculations State
    discountType: 'percentage', // 'percentage' or 'fixed'
    discountPercentage: 0,
    billDiscount: 0,
    taxPercentage: 0,
    paymentMode: 'Cash', // 'Cash', 'Card', 'Bank', 'Other'
    extraPercentage: 0,  // Card / Bank Surcharge Fee (%)
    
    // Payment & Clearance State
    previouslyPaidAmount: 0, // For existing booking
    additionalPayment: 0,    // New payment being collected now
    receivedAmount: 0,       // For new bill or direct override

    // Hold Bills Feature State
    heldBillsModalOpen: false,
    heldBills: [],

    // Receipt Modal & 80mm Thermal Print State
    receiptModalOpen: false,
    receiptData: null,

    init() {
        this.recalculateTotals();
        this.loadHeldBills();
    },

    // Methods for Selecting Client
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
        if (!item || !item.service_obj) return false;
        const catTitle = (item.service_obj.category && item.service_obj.category.title) ? item.service_obj.category.title.toLowerCase() : '';
        const srvTitle = (item.service_obj.title) ? item.service_obj.title.toLowerCase() : '';
        return catTitle.includes('ad on') || catTitle.includes('add on') || catTitle.includes('adon') || srvTitle.includes('ad on') || srvTitle.includes('add on') || srvTitle.includes('custom');
    },

    // Service Cart Handlers & Multi-Quantity Support
    isServiceSelected(serviceId) {
        return this.items.some(item => item.service_id == serviceId);
    },
    getServiceCartQuantity(serviceId) {
        const item = this.items.find(it => it.service_id == serviceId);
        return item ? (parseInt(item.quantity) || 1) : 0;
    },
    incrementService(srv, event) {
        if (event) event.stopPropagation();
        const item = this.items.find(it => it.service_id == srv.id);
        if (item) {
            item.quantity = (parseInt(item.quantity) || 1) + 1;
        } else {
            this.items.push({
                service_id: srv.id,
                service_obj: srv,
                custom_title: srv.title,
                quantity: 1,
                price: parseFloat(srv.discounted_price || srv.price || 0)
            });
        }
        this.recalculateDiscount();
    },
    decrementService(srv, event) {
        if (event) event.stopPropagation();
        const index = this.items.findIndex(it => it.service_id == srv.id);
        if (index > -1) {
            if (parseInt(this.items[index].quantity) > 1) {
                this.items[index].quantity = parseInt(this.items[index].quantity) - 1;
            } else {
                this.items.splice(index, 1);
            }
            this.recalculateDiscount();
        }
    },
    toggleService(srv) {
        this.incrementService(srv);
    },
    removeItem(index) {
        this.items.splice(index, 1);
        this.recalculateDiscount();
    },

    // Calculations logic
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
    applyDiscountPercent(pct) {
        this.discountType = 'percentage';
        this.discountPercentage = pct;
        this.recalculateDiscount();
    },
    clearDiscount() {
        this.discountPercentage = 0;
        this.billDiscount = 0;
        this.recalculateDiscount();
    },
    recalculateTotals() {
        const tot = this.getTotalAmount();
        if (!this.editingAppointmentId) {
            if (this.receivedAmount === 0 || this.receivedAmount < tot) {
                this.receivedAmount = tot;
            }
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
            const netBeforeExtra = Math.max(0, this.getSubtotal() - this.getCalculatedDiscount());
            const pct = Math.max(0, parseFloat(this.extraPercentage || 0));
            return Math.round((netBeforeExtra * pct) / 100);
        }
        return 0;
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
        this.discountType = apt.discount_type || 'percentage';
        this.discountPercentage = parseFloat(apt.discount_percentage) || 0;
        this.billDiscount = parseFloat(apt.discount) || 0;
        this.paymentMode = apt.payment_mode || 'Cash';
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
        if (apt.items && apt.items.length > 0) {
            apt.items.forEach(it => {
                const srv = this.saloonServices.find(s => s.id == it.saloon_service_id) || it.service;
                const qty = parseInt(it.quantity) || 1;
                const unitPrice = (it.price && parseFloat(it.price) > 0)
                    ? parseFloat(it.price)
                    : (parseFloat(it.discounted_price || (srv ? (srv.discounted_price || srv.price) : 0)) / qty);
                this.items.push({
                    service_id: it.saloon_service_id,
                    service_obj: srv,
                    custom_title: it.custom_title || (srv ? srv.title : 'Service'),
                    quantity: qty,
                    price: unitPrice
                });
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
        this.receivedAmount = 0;
        this.extraPercentage = 0;
        this.paymentMode = 'Cash';
        this.adminPassword = '';
        this.adminAuthorized = false;
        this.selectedCustomerId = '{{ $defaultCustomer->id ?? ($customers->first()->id ?? '') }}';
        this.selectedCustomerObj = {{ json_encode($defaultCustomer ?? ($customers->first() ?? null)) }};
        this.selectedEmployeeId = '';
        this.ranking = 0;
        this.rankingNotes = '';
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
            extraPercentage: this.extraPercentage,
            receivedAmount: this.receivedAmount,
            additionalPayment: this.additionalPayment,
            previouslyPaidAmount: this.previouslyPaidAmount,
            editingAppointmentId: this.editingAppointmentId,
            editingBookingNo: this.editingBookingNo,
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
        this.orderType = record.orderType || 'On Site';
        this.bookingStatus = record.bookingStatus || 'confirmed';
        this.selectedCustomerId = record.selectedCustomerId;
        this.selectedCustomerObj = record.selectedCustomerObj;
        this.billNo = record.billNo || ('BILL-{{ date('Y') }}-{{ str_pad(\App\Models\Appointment::count() + 1, 4, '0', STR_PAD_LEFT) }}');
        this.billDate = record.billDate || '{{ date('Y-m-d') }}';
        this.notes = record.notes || '';
        this.discountType = record.discountType || 'percentage';
        this.discountPercentage = record.discountPercentage || 0;
        this.billDiscount = record.billDiscount || 0;
        this.paymentMode = record.paymentMode || 'Cash';
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
                const unitPrice = (it.price && parseFloat(it.price) > 0)
                    ? parseFloat(it.price)
                    : (parseFloat(it.discounted_price || (srv ? (srv.discounted_price || srv.price) : 0)) / qty);
                return {
                    title: it.custom_title || (srv ? srv.title : 'Service'),
                    quantity: qty,
                    price: unitPrice,
                    total: parseFloat(it.discounted_price || (unitPrice * qty))
                };
            });

            return {
                billNo: apt.booking_no,
                billDate: apt.appointment_date ? (typeof apt.appointment_date === 'string' ? apt.appointment_date.split('T')[0] : apt.appointment_date) : '{{ date('Y-m-d') }}',
                billTime: apt.start_time || '{{ date('h:i A') }}',
                orderType: apt.order_type || 'On Site',
                customerName: (apt.customer ? apt.customer.name : 'Walk-in Client'),
                customerPhone: (apt.customer ? (apt.customer.phone_no1 || '') : ''),
                items: receiptItems,
                subtotal: parseFloat(apt.total_amount) || 0,
                discount: parseFloat(apt.discount) || 0,
                discountPercentage: parseFloat(apt.discount_percentage) || 0,
                extraPercentage: (parseFloat(apt.extra_amount) > 0 && (parseFloat(apt.total_amount) - parseFloat(apt.discount)) > 0) ? (Math.round((parseFloat(apt.extra_amount) / (parseFloat(apt.total_amount) - parseFloat(apt.discount))) * 100 * 100) / 100) : 0,
                extraAmount: parseFloat(apt.extra_amount) || 0,
                netAmount: parseFloat(apt.net_amount) || 0,
                paidAmount: parseFloat(apt.paid_amount) || 0,
                balanceDue: parseFloat(apt.balance_due) || 0,
                changeReturn: Math.max(0, (parseFloat(apt.paid_amount) || 0) - (parseFloat(apt.net_amount) || 0)),
                paymentMode: apt.payment_mode || 'Cash',
                status: apt.status || 'confirmed',
                notes: apt.notes || ''
            };
        } else {
            if (this.items.length === 0) {
                alert('Please select at least one treatment / service to generate a receipt.');
                return null;
            }
            const cartItems = this.items.map(it => {
                const qty = parseInt(it.quantity) || 1;
                const unitPrice = parseFloat(it.price) || 0;
                return {
                    title: it.custom_title || (it.service_obj ? it.service_obj.title : 'Service'),
                    quantity: qty,
                    price: unitPrice,
                    total: unitPrice * qty
                };
            });

            return {
                billNo: this.editingAppointmentId ? this.editingBookingNo : this.billNo,
                billDate: this.billDate,
                billTime: '{{ date('h:i A') }}',
                orderType: this.orderType,
                customerName: this.selectedCustomerObj ? this.selectedCustomerObj.name : 'Walk-in Client',
                customerPhone: this.selectedCustomerObj ? (this.selectedCustomerObj.phone_no1 || '') : '',
                items: cartItems,
                subtotal: this.getSubtotal(),
                discount: this.getCalculatedDiscount(),
                discountPercentage: this.discountPercentage,
                extraPercentage: parseFloat(this.extraPercentage || 0),
                extraAmount: this.getExtraFee(),
                netAmount: this.getTotalAmount(),
                paidAmount: this.getTotalPaidAmount(),
                balanceDue: this.getRemainingBalanceDue(),
                changeReturn: this.getChangeReturn(),
                paymentMode: this.paymentMode,
                status: this.bookingStatus,
                notes: this.notes
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

    print80mmReceiptDirect(apt = null) {
        const data = this.generateReceiptData(apt);
        if (data) {
            this.receiptData = data;
            this.$nextTick(() => {
                document.body.classList.add('pos-receipt-active');
                window.print();
                setTimeout(() => {
                    document.body.classList.remove('pos-receipt-active');
                }, 1000);
            });
        }
    },

    // Submission Handlers
    submitForm(isPrint = false) {
        if (this.items.length === 0) {
            alert('Please select at least one treatment service from the catalog.');
            return;
        }
        if (!this.selectedCustomerId) {
            alert('Please select or add a client.');
            return;
        }

        // Check 10% discount restriction:
        // <= 10% is allowed for every user unconditionally.
        // > 10% is allowed if user has allow-bill-discount permission or admin, OR if admin authorization code/password is provided.
        if (this.discountPercentage > 10 && !this.hasDiscountPermission && !this.adminAuthorized && (!this.adminPassword || this.adminPassword.trim() === '')) {
            this.adminAuthModalOpen = true;
            return;
        }

        const form = document.getElementById('posBookingForm');
        if (isPrint) {
            this.receiptData = this.generateReceiptData();
            this.$nextTick(() => {
                document.body.classList.add('pos-receipt-active');
                window.print();
                setTimeout(() => {
                    document.body.classList.remove('pos-receipt-active');
                    form.submit();
                }, 1000);
            });
            return;
        }
        form.submit();
    },

    authorizeAdminAndSubmit() {
        if (!this.adminPassword || this.adminPassword.trim() === '') {
            alert('Please enter the Admin Password.');
            return;
        }
        this.adminAuthorized = true;
        this.adminAuthModalOpen = false;
        this.submitForm(false);
    }
}" class="space-y-6">

    <!-- Top Action Bar & Tabs Header -->
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
        <div class="flex items-center gap-2 shrink-0">
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

            <div class="inline-flex p-1 bg-slate-100 rounded-none border border-slate-200">
                <button type="button" @click="resetToNewBill()" 
                        :class="activeTab === 'pos' && !editingAppointmentId ? 'bg-white text-indigo-700 shadow-sm font-black' : 'text-slate-600 hover:text-slate-900 font-bold'"
                        class="px-4 py-2 text-xs rounded-none transition-all flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    <span>New Bill Terminal</span>
                </button>
                <button type="button" @click="activeTab = 'history'" 
                        :class="activeTab === 'history' ? 'bg-white text-indigo-700 shadow-sm font-black' : 'text-slate-600 hover:text-slate-900 font-bold'"
                        class="px-4 py-2 text-xs rounded-none transition-all flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    <span>Bookings History ({{ $appointments->total() }})</span>
                </button>
            </div>
        </div>
    </div>

    <!-- TAB 1: POS TERMINAL BILLING WORKFLOW -->
    <div x-show="activeTab === 'pos'" class="space-y-5">

        <!-- Active Editing Notification Banner -->
        <div x-show="editingAppointmentId" class="p-3.5 bg-amber-50 border border-amber-300 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs rounded-none">
            <div class="flex items-center gap-2.5">
                <span class="w-2.5 h-2.5 bg-amber-500 rounded-full animate-ping"></span>
                <span class="font-black text-amber-900">
                    Editing Booking <span class="underline font-mono" x-text="'#' + editingBookingNo"></span> 
                    (Client: <span x-text="selectedCustomerObj ? selectedCustomerObj.name : 'Client'"></span>)
                </span>
                <span class="px-2 py-0.5 text-[10px] font-black uppercase" 
                      :class="orderType === 'Online' ? 'bg-cyan-100 text-cyan-800 border border-cyan-300' : 'bg-slate-200 text-slate-800 border border-slate-300'"
                      x-text="orderType === 'Online' ? '🌐 Online Booking' : '🏢 On Site Booking'"></span>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-[11px] font-bold text-amber-800" x-text="'Already Paid: PKR ' + getPreviouslyPaid().toLocaleString()"></span>
                <button type="button" @click="resetToNewBill()" class="px-3 py-1 bg-white hover:bg-amber-100 text-amber-900 font-bold border border-amber-300 transition-colors">
                    ✕ Cancel & Start New Bill
                </button>
            </div>
        </div>

        <form id="posBookingForm" method="POST" :action="editingAppointmentId ? ('/manager/appointments/' + editingAppointmentId) : '{{ route('manager.appointments.store') }}'">
            @csrf
            <template x-if="editingAppointmentId">
                <input type="hidden" name="_method" value="PUT">
            </template>

            <input type="hidden" name="order_type" :value="orderType">
            <input type="hidden" name="account_id" :value="selectedCustomerId">
            <input type="hidden" name="employee_id" :value="selectedEmployeeId">
            <input type="hidden" name="ranking" :value="ranking">
            <input type="hidden" name="ranking_notes" :value="rankingNotes">
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
            <input type="hidden" name="notes" :value="notes">

            <!-- Itemized Services with Custom Titles, Prices & Quantities (for Multi-Services & Ad Ons) -->
            <template x-for="(item, idx) in items" :key="idx">
                <div>
                    <input type="hidden" name="service_ids[]" :value="item.service_id">
                    <input type="hidden" name="service_quantities[]" :value="item.quantity || 1">
                    <input type="hidden" name="service_custom_titles[]" :value="item.custom_title || ''">
                    <input type="hidden" name="service_prices[]" :value="item.price">
                </div>
            </template>

            <!-- 3-Column Modern Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

                <!-- LEFT COLUMN: Client & Bill Meta Information (col-span-3) -->
                <div class="lg:col-span-3 space-y-5">

                    <!-- Client Selector Card -->
                    <div class="bg-white rounded-none border border-slate-200 p-5 shadow-sm space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <span class="w-6 h-6 rounded-none bg-indigo-600 text-white font-black text-xs flex items-center justify-center">1</span>
                                <h3 class="font-extrabold text-sm text-slate-900 heading-font">Client Details</h3>
                            </div>
                            <button type="button" @click="customerModalOpen = true" 
                                    class="px-2.5 py-1 text-xs font-bold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-none transition-colors border border-indigo-200">
                                Change
                            </button>
                        </div>

                        <!-- Selected Client Overview Box -->
                        <div class="p-3.5 bg-slate-50 rounded-none border border-slate-200 flex items-center gap-3">
                            <div class="w-10 h-10 rounded-none bg-indigo-600 text-white font-black text-xs flex items-center justify-center shrink-0">
                                <span x-text="selectedCustomerObj ? selectedCustomerObj.name.substring(0, 2).toUpperCase() : 'CL'"></span>
                            </div>
                            <div class="overflow-hidden">
                                <h4 class="font-bold text-xs text-slate-900 truncate" x-text="selectedCustomerObj ? selectedCustomerObj.name : 'Select Client'">Sara Khan</h4>
                                <p class="text-[11px] text-slate-500 font-medium truncate" x-text="selectedCustomerObj ? (selectedCustomerObj.phone_no1 || 'No Phone') : '0300-1234567'">0300-1234567</p>
                            </div>
                        </div>
                    </div>

                    <!-- Bill Meta Information Card -->
                    <div class="bg-white rounded-none border border-slate-200 p-5 shadow-sm space-y-4">
                        <div class="flex items-center gap-2.5">
                            <span class="w-6 h-6 rounded-none bg-indigo-600 text-white font-black text-xs flex items-center justify-center">2</span>
                            <h3 class="font-extrabold text-sm text-slate-900 heading-font">Booking Parameters</h3>
                        </div>

                        <div class="space-y-3.5 text-xs font-bold text-slate-700">
                            <!-- Bill Identifier & Order Type -->
                            <div class="grid grid-cols-2 gap-2">
                                <div class="space-y-1">
                                    <label class="block text-slate-500 font-bold text-[11px]">Invoice #</label>
                                    <input type="text" x-model="billNo" readonly 
                                           class="w-full px-2.5 py-2.5 bg-slate-100 border border-slate-200 rounded-none font-mono font-bold text-slate-800 text-xs">
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-slate-500 font-bold text-[11px]">Order Channel</label>
                                    <select x-model="orderType" 
                                            class="w-full px-2.5 py-2.5 bg-slate-50 border border-slate-200 rounded-none font-bold text-slate-800 text-xs focus:ring-2 focus:ring-indigo-500">
                                        <option value="On Site">🏢 On Site</option>
                                        <option value="Online">🌐 Online</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Bill Date & Status -->
                            <div class="grid grid-cols-2 gap-2">
                                <div class="space-y-1">
                                    <label class="block text-slate-500 font-bold text-[11px]">Service Date</label>
                                    <input type="date" x-model="billDate" 
                                           class="w-full px-2.5 py-2.5 bg-slate-50 border border-slate-200 rounded-none font-bold text-slate-800 text-xs focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-slate-500 font-bold text-[11px]">Status</label>
                                    <select x-model="bookingStatus" 
                                            class="w-full px-2.5 py-2.5 bg-slate-50 border border-slate-200 rounded-none font-bold text-slate-800 text-xs focus:ring-2 focus:ring-indigo-500">
                                        <option value="confirmed">Confirmed</option>
                                        <option value="completed">Completed</option>
                                        <option value="pending">Pending</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Assigned Stylist / Staff Employee -->
                            <div class="space-y-1">
                                <label class="block text-slate-500 font-bold text-[11px]">Assigned Stylist / Staff</label>
                                <select x-model="selectedEmployeeId" 
                                        class="w-full px-2.5 py-2.5 bg-slate-50 border border-slate-200 rounded-none font-bold text-slate-800 text-xs focus:ring-2 focus:ring-indigo-500">
                                    <option value="">-- No Specific Staff Assigned --</option>
                                    @foreach($employees as $emp)
                                        <option value="{{ $emp->id }}">
                                            💇 {{ $emp->name }} {{ $emp->category ? '('.$emp->category->title.')' : '' }} {{ $emp->average_ranking > 0 ? '— ⭐ '.number_format($emp->average_ranking, 1) : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Staff Service Ranking System Widget -->
                            <div class="space-y-2 p-3 bg-amber-50/50 border border-amber-200 rounded-none">
                                <div class="flex items-center justify-between">
                                    <label class="block text-slate-800 font-black text-[11px] uppercase tracking-wider">
                                        ⭐ Staff Service Ranking
                                    </label>
                                    <span class="text-[10px] font-black text-amber-800" x-text="ranking > 0 ? (ranking + '/5 Stars') : 'Unrated'"></span>
                                </div>
                                
                                <!-- Star Buttons -->
                                <div class="flex items-center gap-1.5 py-1">
                                    <template x-for="star in [1, 2, 3, 4, 5]" :key="star">
                                        <button type="button" @click="ranking = (ranking === star ? 0 : star)" 
                                                class="text-2xl transition-transform hover:scale-125 focus:outline-hidden"
                                                :title="'Rank ' + star + ' Star'">
                                            <span :class="ranking >= star ? 'text-amber-500' : 'text-slate-300'">★</span>
                                        </button>
                                    </template>
                                    <button type="button" x-show="ranking > 0" @click="ranking = 0" class="text-[10px] text-slate-400 hover:text-slate-600 ml-2 font-bold underline">
                                        Clear
                                    </button>
                                </div>
                                <div class="text-[10px] font-bold text-slate-600" x-text="getRankingLabel(ranking)"></div>

                                <!-- Review / Ranking Notes -->
                                <input type="text" x-model="rankingNotes" placeholder="Performance feedback / review notes..."
                                       class="w-full px-2.5 py-1.5 bg-white border border-amber-200 text-slate-800 text-[11px] font-medium focus:ring-1 focus:ring-amber-500">
                            </div>

                            <!-- Notes -->
                            <div class="space-y-1">
                                <label class="block text-slate-500 font-bold text-[11px]">Notes / Special Requests</label>
                                <textarea x-model="notes" rows="2" placeholder="Special hair/skin instructions..."
                                          class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-none font-medium text-slate-800 text-xs focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MIDDLE COLUMN: Service Catalog (col-span-5) -->
                <div class="lg:col-span-5 bg-white rounded-none border border-slate-200 p-5 shadow-sm space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <span class="w-6 h-6 rounded-none bg-indigo-600 text-white font-black text-xs flex items-center justify-center">3</span>
                            <h3 class="font-extrabold text-sm text-slate-900 heading-font">Select Treatments & Ad Ons</h3>
                        </div>
                        <span class="text-xs font-bold text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded-none border border-indigo-100">
                            Click to Add / Remove
                        </span>
                    </div>

                    <!-- Search Filter Bar -->
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </span>
                        <input type="text" x-model="serviceSearchQuery" placeholder="Search treatments, ad ons, facial..." 
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-none text-xs font-semibold focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <!-- Category Pills Horizontal Scroll -->
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-1.5 no-scrollbar">
                        <button type="button" @click="activeCategory = 'All Services'"
                                :class="activeCategory === 'All Services' ? 'bg-indigo-600 text-white font-black' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 font-bold border border-slate-200'"
                                class="px-3.5 py-1.5 text-xs rounded-none whitespace-nowrap transition-all">
                            All Treatments
                        </button>
                        <template x-for="cat in serviceCategories" :key="cat.id">
                            <button type="button" @click="activeCategory = cat.title"
                                    :class="activeCategory === cat.title ? 'bg-indigo-600 text-white font-black' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 font-bold border border-slate-200'"
                                    class="px-3.5 py-1.5 text-xs rounded-none whitespace-nowrap transition-all"
                                    x-text="cat.title">
                            </button>
                        </template>
                    </div>

                    <!-- Services Grid Display -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 pt-1 max-h-[620px] overflow-y-auto pr-1">
                        <template x-for="srv in getFilteredServices()" :key="srv.id">
                            <div @click="toggleService(srv)"
                                 :class="isServiceSelected(srv.id) ? 'ring-2 ring-indigo-600 border-indigo-600 bg-indigo-50/40' : 'border-slate-200 hover:border-indigo-400 bg-white'"
                                 class="border rounded-none p-3 cursor-pointer transition-all flex flex-col justify-between group shadow-2xs relative">
                                <div class="space-y-2">
                                    <div class="w-full aspect-[4/3] rounded-none overflow-hidden bg-slate-100 relative">
                                        <img :src="getImageUrl(srv)" 
                                             x-on:error="$el.src = 'https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=400&q=80'"
                                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" 
                                             :alt="srv.title">
                                        
                                        <!-- Active Cart Quantity Badge -->
                                        <div x-show="getServiceCartQuantity(srv.id) > 0" class="absolute top-2 right-2 bg-indigo-600 text-white px-2 py-0.5 text-[10px] font-black rounded-none shadow-md flex items-center gap-1">
                                            <span>✓ Qty:</span>
                                            <span x-text="getServiceCartQuantity(srv.id)"></span>
                                        </div>
                                    </div>
                                    <h4 class="font-bold text-xs text-slate-900 leading-snug line-clamp-2" x-text="srv.title">Hair Cut</h4>
                                </div>
                                <div class="mt-2.5 pt-2 border-t border-slate-100 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] text-slate-400 font-semibold uppercase">Price</span>
                                        <p class="font-black text-xs text-indigo-600">PKR <span x-text="Number(srv.discounted_price || srv.price).toLocaleString()">500</span></p>
                                    </div>

                                    <!-- Quick Multi-Quantity Buttons on Service Card -->
                                    <div x-show="getServiceCartQuantity(srv.id) > 0" class="flex items-center justify-between bg-white border border-indigo-200 p-1 text-xs">
                                        <button type="button" @click="decrementService(srv, $event)" class="w-6 h-6 flex items-center justify-center bg-slate-100 hover:bg-rose-100 hover:text-rose-700 text-slate-700 font-black text-xs transition-colors">
                                            -
                                        </button>
                                        <span class="font-black text-xs text-indigo-700 px-1" x-text="'Qty: ' + getServiceCartQuantity(srv.id)"></span>
                                        <button type="button" @click="incrementService(srv, $event)" class="w-6 h-6 flex items-center justify-center bg-indigo-50 hover:bg-indigo-600 hover:text-white text-indigo-700 font-black text-xs transition-colors">
                                            +
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Bill Summary, Discount, Clearance & Checkout (col-span-4) -->
                <div class="lg:col-span-4 bg-white rounded-none border border-slate-200 p-5 shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-6 h-6 rounded-none bg-indigo-600 text-white font-black text-xs flex items-center justify-center">4</span>
                            <h3 class="font-extrabold text-sm text-slate-900 heading-font">Bill & Clearance Summary</h3>
                        </div>
                        <span class="text-xs font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-none border border-slate-200" x-text="items.length + ' Service(s)'"></span>
                    </div>

                    <!-- Selected Services Table (SERVICE TITLE | PRICE * QTY = NET TOTAL) -->
                    <div class="overflow-x-auto min-h-[110px] max-h-[250px] overflow-y-auto border border-slate-200/80 bg-slate-50/30">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-100 border-b border-slate-200 text-slate-500 font-extrabold text-[10px] uppercase tracking-wider">
                                    <th class="py-2 px-2.5">Service Title</th>
                                    <th class="py-2 px-2 text-right">Price</th>
                                    <th class="py-2 px-1 text-center w-20">Qty</th>
                                    <th class="py-2 px-2.5 text-right">Net Total</th>
                                    <th class="py-2 px-1 text-center w-6"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/70 font-semibold text-slate-700 bg-white">
                                <template x-for="(item, idx) in items" :key="idx">
                                    <tr class="hover:bg-slate-50/80 transition-colors">
                                        <!-- Service Title -->
                                        <td class="py-2 px-2.5 align-middle">
                                            <!-- Standard Service Title -->
                                            <template x-if="!isAddOn(item)">
                                                <div>
                                                    <span x-text="item.service_obj ? item.service_obj.title : 'Service'" class="block font-bold text-slate-900 text-xs truncate max-w-[120px]">Service</span>
                                                </div>
                                            </template>

                                            <!-- Editable Ad-On Service Name -->
                                            <template x-if="isAddOn(item)">
                                                <div class="space-y-1">
                                                    <span class="px-1.5 py-0.2 text-[9px] font-black bg-amber-100 text-amber-900 border border-amber-200 uppercase inline-block">Ad-On</span>
                                                    <input type="text" x-model="item.custom_title" placeholder="Custom Service..." 
                                                           class="w-full px-1.5 py-1 bg-amber-50/60 border border-amber-300 text-xs font-bold text-slate-900 rounded-none focus:ring-1 focus:ring-indigo-500">
                                                </div>
                                            </template>
                                        </td>

                                        <!-- Unit Price -->
                                        <td class="py-2 px-2 text-right align-middle whitespace-nowrap">
                                            <!-- Standard Fixed Price -->
                                            <template x-if="!isAddOn(item)">
                                                <span class="font-bold text-slate-700 text-xs">PKR <span x-text="Number(item.price).toLocaleString()">0</span></span>
                                            </template>

                                            <!-- Editable Ad-On Price -->
                                            <template x-if="isAddOn(item)">
                                                <input type="number" step="any" x-model.number="item.price" @input="recalculateDiscount()" placeholder="0"
                                                       class="w-16 px-1.5 py-1 text-right bg-amber-50/60 border border-amber-300 text-xs font-black text-slate-900 rounded-none focus:ring-1 focus:ring-indigo-500">
                                            </template>
                                        </td>

                                        <!-- Quantity Stepper Controls (* Qty) -->
                                        <td class="py-2 px-1 text-center align-middle whitespace-nowrap">
                                            <div class="inline-flex items-center border border-slate-300 bg-white shadow-2xs">
                                                <button type="button" 
                                                        @click="if(parseInt(item.quantity) > 1) { item.quantity = parseInt(item.quantity) - 1; recalculateDiscount(); } else { removeItem(idx); }" 
                                                        class="w-5 h-5 flex items-center justify-center text-slate-500 hover:bg-rose-50 hover:text-rose-600 font-black text-xs transition-colors">
                                                    -
                                                </button>
                                                <input type="number" min="1" 
                                                       x-model.number="item.quantity" 
                                                       @input="if(!item.quantity || parseInt(item.quantity) < 1) item.quantity = 1; recalculateDiscount()" 
                                                       class="w-7 h-5 text-center text-xs font-black border-x border-slate-300 p-0 focus:outline-none focus:bg-indigo-50">
                                                <button type="button" 
                                                        @click="item.quantity = (parseInt(item.quantity) || 1) + 1; recalculateDiscount()" 
                                                        class="w-5 h-5 flex items-center justify-center text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 font-black text-xs transition-colors">
                                                    +
                                                </button>
                                            </div>
                                        </td>

                                        <!-- Net Total (= Net Total) -->
                                        <td class="py-2 px-2.5 text-right align-middle whitespace-nowrap">
                                            <span class="font-black text-indigo-700 text-xs">PKR <span x-text="Number((parseFloat(item.price) || 0) * (parseInt(item.quantity) || 1)).toLocaleString()">0</span></span>
                                        </td>

                                        <!-- Remove Row Action -->
                                        <td class="py-2 px-1 text-center align-middle">
                                            <button type="button" @click="removeItem(idx)" class="text-slate-300 hover:text-rose-600 transition-colors p-1" title="Remove Service">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="items.length === 0">
                                    <td colspan="5" class="py-8 text-center text-slate-400 font-medium">
                                        No services in cart. Click treatments from the middle catalog to add.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- PROMINENT DEDICATED BILL DISCOUNT BOX -->
                    <div class="p-3.5 bg-rose-50/40 border border-rose-200 rounded-none space-y-2.5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                                <label class="text-xs font-black text-slate-900 uppercase tracking-wider">Bill Discount</label>
                            </div>

                            <!-- Discount Mode Switcher (% vs Flat PKR) -->
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

                        <div class="space-y-2.5">
                            <!-- Custom Discount Input Field (Available for Everyone) -->
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

                        <div class="flex items-center justify-between text-rose-600" x-show="getCalculatedDiscount() > 0">
                            <div class="flex items-center gap-1.5">
                                <span>Discount Applied</span>
                                <span class="px-1.5 py-0.5 bg-rose-100 text-rose-800 font-black text-[10px] rounded-none border border-rose-200" x-text="discountPercentage + '%'">0%</span>
                            </div>
                            <span class="font-extrabold">- PKR <span x-text="getCalculatedDiscount().toLocaleString()">0</span></span>
                        </div>

                        <!-- Card / Bank Surcharge Fee Line -->
                        <div class="flex items-center justify-between" x-show="getExtraFee() > 0">
                            <div class="flex items-center gap-1.5">
                                <span class="text-indigo-700 font-bold">Card Surcharge Fee:</span>
                                <span class="px-1.5 py-0.5 bg-indigo-100 text-indigo-800 font-black text-[10px] rounded-none border border-indigo-200" x-text="(parseFloat(extraPercentage) || 0) + '%'">0%</span>
                            </div>
                            <span class="text-indigo-700 font-black">+ PKR <span x-text="getExtraFee().toLocaleString()">0</span></span>
                        </div>

                        <div class="flex items-center justify-between pt-2 border-t border-slate-200">
                            <span class="font-extrabold text-sm text-slate-900">Total Net Bill</span>
                            <span class="font-black text-2xl text-indigo-600">PKR <span x-text="getTotalAmount().toLocaleString()">0</span></span>
                        </div>
                    </div>

                    <!-- PAYMENT METHOD SELECTOR -->
                    <div class="space-y-2 pt-1">
                        <label class="block text-xs font-bold text-slate-700">Payment Mode</label>
                        <div class="grid grid-cols-4 gap-2">
                            <!-- Cash -->
                            <button type="button" @click="paymentMode = 'Cash'; extraPercentage = 0; recalculateTotals()"
                                    :class="paymentMode === 'Cash' ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-black' : 'border-slate-200 text-slate-600 font-bold bg-white hover:bg-slate-50'"
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
                                    :class="paymentMode === 'Bank' ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-black' : 'border-slate-200 text-slate-600 font-bold bg-white hover:bg-slate-50'"
                                    class="p-2 border rounded-none text-xs flex flex-col items-center gap-1 transition-all">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                <span>Bank</span>
                            </button>
                            <!-- Other -->
                            <button type="button" @click="paymentMode = 'Other'; extraPercentage = 0; recalculateTotals()"
                                    :class="paymentMode === 'Other' ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-black' : 'border-slate-200 text-slate-600 font-bold bg-white hover:bg-slate-50'"
                                    class="p-2 border rounded-none text-xs flex flex-col items-center gap-1 transition-all">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span>Other</span>
                            </button>
                        </div>

                        <!-- Card Surcharge Percentage Input Field & Quick Buttons -->
                        <div x-show="paymentMode === 'Card' || paymentMode === 'Bank'" class="p-3 bg-indigo-50/70 border border-indigo-200 space-y-2">
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
                                <button type="button" @click="clearFullRemainingBalance()" class="text-[11px] font-black text-indigo-600 hover:text-indigo-800 underline">
                                    ⚡ Clear Full Balance
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

                        <!-- Payment Input Field -->
                        <div class="space-y-1.5">
                            <template x-if="editingAppointmentId">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 flex items-center justify-between">
                                        <span>Collect Additional Payment (PKR)</span>
                                        <template x-if="isAlreadyFullyCleared()">
                                            <span class="text-[10px] text-emerald-700 font-bold bg-emerald-50 px-1.5 py-0.2 border border-emerald-200">🔒 Disabled (Bill Already Paid)</span>
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
                                    <input type="number" step="any" x-model.number="receivedAmount" placeholder="0" 
                                           class="w-full px-3.5 py-2.5 bg-white border border-slate-300 text-xs font-black text-slate-900 focus:ring-2 focus:ring-indigo-500 rounded-none">
                                </div>
                            </template>
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
                                <span class="text-xs font-black bg-emerald-100 text-emerald-900 px-2 py-0.5 border border-emerald-300">✓ 100% Cleared & Paid</span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons (Hold, Preview Receipt, Save & Print 80mm, Complete) -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-2">
                        <button type="button" @click="holdCurrentBill()" 
                                class="py-3 px-2 bg-amber-50 hover:bg-amber-100 text-amber-900 font-extrabold text-xs rounded-none transition-all flex items-center justify-center gap-1 border border-amber-300 shadow-2xs">
                            <svg class="w-4 h-4 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span>Hold</span>
                        </button>
                        <button type="button" @click="openReceiptModal()" 
                                class="py-3 px-2 bg-slate-100 hover:bg-slate-200 text-slate-800 font-extrabold text-xs rounded-none transition-all flex items-center justify-center gap-1 border border-slate-300 shadow-2xs">
                            <svg class="w-4 h-4 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            <span>Receipt</span>
                        </button>
                        <button type="button" @click="submitForm(true)" 
                                class="py-3 px-2 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs rounded-none transition-all flex items-center justify-center gap-1 shadow-xs">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                            <span>Print 80mm</span>
                        </button>
                        <button type="button" @click="submitForm(false)" 
                                class="py-3 px-2 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs rounded-none transition-all flex items-center justify-center gap-1 shadow-xs">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span x-text="editingAppointmentId ? 'Update' : 'Complete'"></span>
                        </button>
                    </div>
                </div>

            </div>
        </form>
    </div>

    <!-- TAB 2: ALL BOOKINGS HISTORY -->
    <div x-show="activeTab === 'history'" class="space-y-5" x-cloak>

        <!-- KPI Metrics (Online vs On-Site vs Staff Ranking) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white p-4 border border-slate-200 rounded-none shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Bookings</span>
                    <span class="text-2xl font-black text-slate-900 mt-1 block">{{ $appointments->total() }}</span>
                </div>
                <div class="p-3 bg-slate-100 text-slate-700 rounded-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                </div>
            </div>

            <div class="bg-white p-4 border border-cyan-200 rounded-none shadow-sm flex items-center justify-between bg-cyan-50/20">
                <div>
                    <span class="text-[10px] font-black text-cyan-800 uppercase tracking-wider block">🌐 Online Bookings</span>
                    <span class="text-2xl font-black text-cyan-700 mt-1 block">{{ $onlineBookingsCount }}</span>
                </div>
                <div class="p-3 bg-cyan-100 text-cyan-700 rounded-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                </div>
            </div>

            <div class="bg-white p-4 border border-indigo-200 rounded-none shadow-sm flex items-center justify-between bg-indigo-50/20">
                <div>
                    <span class="text-[10px] font-black text-indigo-800 uppercase tracking-wider block">🏢 On-Site POS</span>
                    <span class="text-2xl font-black text-indigo-700 mt-1 block">{{ $onSiteBookingsCount }}</span>
                </div>
                <div class="p-3 bg-indigo-100 text-indigo-700 rounded-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                </div>
            </div>

            <div class="bg-white p-4 border border-amber-200 rounded-none shadow-sm flex items-center justify-between bg-amber-50/30">
                <div>
                    <span class="text-[10px] font-black text-amber-800 uppercase tracking-wider block">⭐ Staff Service Ranking</span>
                    <span class="text-2xl font-black text-amber-600 mt-1 block">
                        {{ $avgSaloonRanking > 0 ? $avgSaloonRanking . ' / 5.0' : 'N/A' }}
                    </span>
                    <span class="text-[10px] font-bold text-slate-500">{{ $totalRankedCount }} Ranked ({{ $fiveStarCount }} 5★)</span>
                </div>
                <div class="p-3 bg-amber-100 text-amber-700 rounded-none">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                </div>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div class="bg-white p-4 border border-slate-200 rounded-none shadow-sm">
            <form method="GET" action="{{ route('manager.appointments.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </span>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search booking, client..." 
                           class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-none text-xs font-semibold focus:ring-2 focus:ring-indigo-600">
                </div>

                <!-- Stylist / Employee Filter -->
                <div>
                    <select name="employee_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-none text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-600">
                        <option value="">All Stylists / Staff</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}" {{ (string)$employeeId === (string)$emp->id ? 'selected' : '' }}>
                                💇 {{ $emp->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Staff Ranking Filter -->
                <div>
                    <select name="ranking" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-none text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-600">
                        <option value="">All Ranking Levels</option>
                        <option value="5" {{ (string)$ranking === '5' ? 'selected' : '' }}>⭐⭐⭐⭐⭐ 5 Stars (Top)</option>
                        <option value="4" {{ (string)$ranking === '4' ? 'selected' : '' }}>⭐⭐⭐⭐ 4 Stars</option>
                        <option value="3" {{ (string)$ranking === '3' ? 'selected' : '' }}>⭐⭐⭐ 3 Stars</option>
                        <option value="2" {{ (string)$ranking === '2' ? 'selected' : '' }}>⭐⭐ 2 Stars</option>
                        <option value="1" {{ (string)$ranking === '1' ? 'selected' : '' }}>⭐ 1 Star</option>
                        <option value="unranked" {{ $ranking === 'unranked' ? 'selected' : '' }}>Unranked Bookings</option>
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <select name="status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-none text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-600">
                        <option value="">All Statuses</option>
                        <option value="pending" {{ $status == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="confirmed" {{ $status == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                        <option value="completed" {{ $status == 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="cancelled" {{ $status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>

                <!-- Date Filter -->
                <div>
                    <input type="date" name="date" value="{{ $date }}" 
                           class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-none text-xs font-semibold focus:ring-2 focus:ring-indigo-600">
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" class="flex-1 py-2 bg-indigo-600 text-white font-bold text-xs rounded-none hover:bg-indigo-700 transition-colors shadow-xs">
                        Filter
                    </button>
                    @if($search || $status || $date || $orderType || $employeeId || $ranking)
                        <a href="{{ route('manager.appointments.index') }}" class="px-3 py-2 bg-slate-100 text-slate-600 font-bold text-xs rounded-none hover:bg-slate-200 flex items-center justify-center border border-slate-200">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Appointments History Table -->
        <div class="bg-white border border-slate-200 rounded-none shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-400 text-[10px] font-black uppercase tracking-wider">
                            <th class="py-4 px-4">Booking #</th>
                            <th class="py-4 px-4">Channel</th>
                            <th class="py-4 px-4">Client Name</th>
                            <th class="py-4 px-4">Assigned Staff & Ranking</th>
                            <th class="py-4 px-4">Date</th>
                            <th class="py-4 px-4">Services / Ad-Ons</th>
                            <th class="py-4 px-4">Net Bill</th>
                            <th class="py-4 px-4">Paid Amount</th>
                            <th class="py-4 px-4">Remaining Balance</th>
                            <th class="py-4 px-4">Status</th>
                            <th class="py-4 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                        @forelse($appointments as $apt)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-4 px-4 font-black text-indigo-600">
                                #{{ $apt->booking_no }}
                            </td>
                            <td class="py-4 px-4">
                                @if($apt->order_type === 'Online')
                                    <span class="px-2 py-0.5 text-[10px] font-black uppercase bg-cyan-100 text-cyan-800 border border-cyan-300 inline-flex items-center gap-1">
                                        🌐 Online
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 text-[10px] font-black uppercase bg-slate-100 text-slate-700 border border-slate-200 inline-flex items-center gap-1">
                                        🏢 On Site
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-4 font-bold text-slate-900">
                                {{ $apt->customer->name ?? 'Walk-in Client' }}
                                @if($apt->customer && $apt->customer->phone_no1)
                                    <p class="text-[10px] text-slate-400 font-normal">{{ $apt->customer->phone_no1 }}</p>
                                @endif
                            </td>

                            <!-- Assigned Staff & Ranking Badge -->
                            <td class="py-4 px-4">
                                @if($apt->employee)
                                    <div class="space-y-1">
                                        <p class="font-black text-slate-900 flex items-center gap-1">
                                            <span>💇 {{ $apt->employee->name }}</span>
                                        </p>
                                        @if($apt->ranking > 0)
                                            <div class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-50 border border-amber-200 text-amber-900 font-black text-[10px]" title="{{ $apt->ranking_notes ?: 'Rated ' . $apt->ranking . '/5 stars' }}">
                                                <span class="text-amber-500">
                                                    @for($i = 1; $i <= $apt->ranking; $i++)★@endfor
                                                </span>
                                                <span>{{ $apt->ranking }}.0</span>
                                            </div>
                                        @else
                                            <button type="button" @click="openRateModal({{ json_encode($apt) }})" 
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 bg-slate-100 hover:bg-amber-100 hover:border-amber-300 text-slate-600 hover:text-amber-800 border border-slate-200 text-[10px] font-bold transition-colors">
                                                <span class="text-amber-500">★</span>
                                                <span>Give Ranking</span>
                                            </button>
                                        @endif
                                    </div>
                                @else
                                    <button type="button" @click="openRateModal({{ json_encode($apt) }})" 
                                            class="text-[11px] text-slate-400 hover:text-indigo-600 font-medium italic underline">
                                        + Assign & Rate Staff
                                    </button>
                                @endif
                            </td>

                            <td class="py-4 px-4 font-bold text-slate-700">
                                {{ $apt->appointment_date ? $apt->appointment_date->format('M d, Y') : '—' }}
                            </td>
                            <td class="py-4 px-4">
                                <div class="space-y-0.5">
                                    @foreach($apt->items as $item)
                                        <p class="font-bold text-slate-800 text-[11px] flex items-center gap-1">
                                            <span>• {{ $item->custom_title ?: ($item->service->title ?? 'Service') }}</span>
                                            @if(($item->quantity ?? 1) > 1)
                                                <span class="px-1.5 py-0.2 bg-indigo-100 text-indigo-800 text-[10px] font-black rounded-none">x{{ $item->quantity }}</span>
                                            @endif
                                        </p>
                                    @endforeach
                                </div>
                            </td>
                            <td class="py-4 px-4 font-black text-slate-900">PKR {{ number_format($apt->net_amount, 2) }}</td>
                            <td class="py-4 px-4 font-bold text-emerald-600">PKR {{ number_format($apt->paid_amount, 2) }}</td>
                            <td class="py-4 px-4 font-bold">
                                @if($apt->balance_due > 0)
                                    <span class="px-2 py-0.5 bg-rose-50 text-rose-700 font-black border border-rose-200">
                                        PKR {{ number_format($apt->balance_due, 2) }} Due
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 font-bold border border-emerald-200">
                                        Cleared (PKR 0.00)
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-4">
                                <span class="px-2.5 py-1 text-[10px] font-black uppercase rounded-none 
                                    {{ $apt->status === 'confirmed' ? 'bg-blue-50 text-blue-700 border border-blue-200' : '' }}
                                    {{ $apt->status === 'completed' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : '' }}
                                    {{ $apt->status === 'pending' ? 'bg-amber-50 text-amber-700 border border-amber-200' : '' }}
                                    {{ $apt->status === 'cancelled' ? 'bg-rose-50 text-rose-700 border border-rose-200' : '' }}">
                                    {{ $apt->status }}
                                </span>
                            </td>
                            <td class="py-4 px-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button" @click="openRateModal({{ json_encode($apt) }})" 
                                            class="px-2.5 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-800 font-bold text-[11px] rounded-none transition-colors border border-amber-200 inline-flex items-center gap-1"
                                            title="Rank / Rate Assigned Employee">
                                        <span class="text-amber-600">★</span>
                                        <span>Rank</span>
                                    </button>
                                    <button type="button" @click="openReceiptModal({{ json_encode($apt) }})" 
                                            class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[11px] rounded-none transition-colors border border-slate-300 inline-flex items-center gap-1"
                                            title="View & Print 80mm Receipt">
                                        <svg class="w-3.5 h-3.5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                        <span>Receipt</span>
                                    </button>
                                    <button type="button" @click="loadAppointmentForEdit({{ json_encode($apt) }})" 
                                            class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-[11px] rounded-none transition-colors border border-indigo-200 inline-flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        <span>Edit</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="11" class="py-8 text-center text-slate-400 font-semibold">No service booking records found matching the filters.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($appointments->hasPages())
                <div class="p-4 border-t border-slate-200 bg-slate-50">
                    {{ $appointments->links() }}
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
                                    <h4 class="font-bold text-xs text-slate-900 group-hover:text-indigo-600" x-text="cust.name">Client</h4>
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

    <!-- ADMIN AUTHORIZATION MODAL (FOR DISCOUNT > 10%) -->
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
                    <p class="font-bold text-slate-800">
                        This bill includes a discount of <span class="text-rose-600 font-black text-sm" x-text="discountPercentage + '%'"></span> which exceeds the allowed 10% limit.
                    </p>
                    <p>
                        Please enter the <strong>Administrator Password</strong> to authorize and complete this booking.
                    </p>
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-bold text-slate-700">Admin Password</label>
                    <input type="password" x-model="adminPassword" placeholder="Enter admin password..." 
                           @keydown.enter.prevent="authorizeAdminAndSubmit()"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-none text-xs font-bold text-slate-900 focus:ring-2 focus:ring-rose-500">
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                    <button type="button" @click="adminAuthModalOpen = false" class="px-4 py-2 bg-slate-100 text-slate-600 font-bold text-xs rounded-none hover:bg-slate-200 border border-slate-200">
                        Cancel
                    </button>
                    <button type="button" @click="authorizeAdminAndSubmit()" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white font-black text-xs rounded-none shadow-xs">
                        Authorize & Complete
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
                    <div class="flex items-center gap-2">
                        <span class="text-xl">⏸️</span>
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
                        <div class="w-12 h-12 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto text-xl">⏸️</div>
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
            <div class="inline-block w-full max-w-sm p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-none border border-slate-300 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">🖨️</span>
                        <h3 class="text-sm font-extrabold text-slate-900 heading-font">POS 80mm Thermal Receipt</h3>
                    </div>
                    <button type="button" @click="receiptModalOpen = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Receipt Paper Simulation Container -->
                <div class="bg-slate-50 p-4 border border-slate-200 shadow-inner max-h-[65vh] overflow-y-auto flex justify-center">
                    <div id="pos80mmReceipt" class="pos-receipt-80mm-container bg-white p-3 border border-dashed border-slate-300 w-full max-w-[76mm] text-slate-900 font-mono text-[11px] leading-tight space-y-2">
                        
                        <!-- Header / Brand Info -->
                        <div class="text-center space-y-0.5">
                            <h2 class="text-base font-black tracking-wide uppercase">{{ $brandSettings->brand_name ?? 'JUGNU SALOON' }}</h2>
                            @if(!empty($brandSettings->brand_slogan))
                                <p class="text-[10px] text-slate-600 uppercase">{{ $brandSettings->brand_slogan }}</p>
                            @endif
                            @if(!empty($brandSettings->brand_address))
                                <p class="text-[9px] text-slate-600">{{ $brandSettings->brand_address }}</p>
                            @endif
                            @if(!empty($brandSettings->brand_phone1) || !empty($brandSettings->brand_phone2))
                                <p class="text-[9px] text-slate-600">Tel: {{ $brandSettings->brand_phone1 }} {{ $brandSettings->brand_phone2 ? '/ ' . $brandSettings->brand_phone2 : '' }}</p>
                            @endif
                        </div>

                        <div class="border-t border-b border-black py-1 text-center font-bold text-[10px] uppercase">
                            SERVICE BILL / CASH RECEIPT
                        </div>

                        <!-- Bill & Customer Metadata -->
                        <div class="text-[10px] space-y-0.5 pt-0.5">
                            <div class="flex justify-between">
                                <span>Invoice #:</span>
                                <span class="font-bold" x-text="receiptData ? receiptData.billNo : '—'"></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Date & Time:</span>
                                <span x-text="receiptData ? (receiptData.billDate + ' ' + (receiptData.billTime || '')) : '—'"></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Channel:</span>
                                <span class="font-bold uppercase" x-text="receiptData ? receiptData.orderType : 'On Site'"></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Client:</span>
                                <span class="font-bold" x-text="receiptData ? receiptData.customerName : 'Walk-in Client'"></span>
                            </div>
                            <template x-if="receiptData && receiptData.customerPhone">
                                <div class="flex justify-between">
                                    <span>Phone:</span>
                                    <span x-text="receiptData.customerPhone"></span>
                                </div>
                            </template>
                        </div>

                        <!-- Items Table -->
                        <div class="border-t border-black pt-1">
                            <table class="w-full text-left text-[10px] font-mono">
                                <thead>
                                    <tr class="border-b border-black font-bold">
                                        <th class="py-1 text-left">ITEM / SERVICE</th>
                                        <th class="py-1 text-center w-8">QTY</th>
                                        <th class="py-1 text-right w-14">PRICE</th>
                                        <th class="py-1 text-right w-16">TOTAL</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-dashed divide-slate-300">
                                    <template x-for="(item, i) in (receiptData ? receiptData.items : [])" :key="i">
                                        <tr>
                                            <td class="py-1 text-left font-bold" x-text="item.title"></td>
                                            <td class="py-1 text-center" x-text="item.quantity"></td>
                                            <td class="py-1 text-right" x-text="Number(item.price).toLocaleString()"></td>
                                            <td class="py-1 text-right font-bold" x-text="Number(item.total).toLocaleString()"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <!-- Calculations Summary -->
                        <div class="border-t border-black pt-1 space-y-0.5 text-[10px]">
                            <div class="flex justify-between">
                                <span>Subtotal (Gross):</span>
                                <span>PKR <span x-text="receiptData ? Number(receiptData.subtotal).toLocaleString() : '0'"></span></span>
                            </div>

                            <template x-if="receiptData && receiptData.discount > 0">
                                <div class="flex justify-between text-rose-700 font-bold">
                                    <span>Discount <span x-text="receiptData.discountPercentage > 0 ? '(' + receiptData.discountPercentage + '%)' : ''">:</span></span>
                                    <span>- PKR <span x-text="Number(receiptData.discount).toLocaleString()"></span></span>
                                </div>
                            </template>

                            <template x-if="receiptData && receiptData.extraAmount > 0">
                                <div class="flex justify-between font-bold">
                                    <span>Card / Bank Fee <span x-text="receiptData.extraPercentage > 0 ? '(' + receiptData.extraPercentage + '%)' : ''">:</span></span>
                                    <span>+ PKR <span x-text="Number(receiptData.extraAmount).toLocaleString()"></span></span>
                                </div>
                            </template>

                            <div class="flex justify-between font-black text-xs border-t border-b border-black py-1 my-1">
                                <span>TOTAL NET BILL:</span>
                                <span>PKR <span x-text="receiptData ? Number(receiptData.netAmount).toLocaleString() : '0'"></span></span>
                            </div>

                            <div class="flex justify-between">
                                <span>Payment Mode:</span>
                                <span class="font-bold uppercase" x-text="receiptData ? receiptData.paymentMode : 'Cash'"></span>
                            </div>

                            <div class="flex justify-between font-bold">
                                <span>Paid Amount:</span>
                                <span>PKR <span x-text="receiptData ? Number(receiptData.paidAmount).toLocaleString() : '0'"></span></span>
                            </div>

                            <template x-if="receiptData && receiptData.balanceDue > 0">
                                <div class="flex justify-between text-rose-700 font-black">
                                    <span>Balance Remaining:</span>
                                    <span>PKR <span x-text="Number(receiptData.balanceDue).toLocaleString()"></span> (DUE)</span>
                                </div>
                            </template>

                            <template x-if="receiptData && receiptData.balanceDue <= 0">
                                <div class="flex justify-between text-emerald-800 font-bold">
                                    <span>Bill Status:</span>
                                    <span>PAID IN FULL (CLEARED)</span>
                                </div>
                            </template>

                            <template x-if="receiptData && receiptData.changeReturn > 0">
                                <div class="flex justify-between text-emerald-700 font-bold">
                                    <span>Change Return:</span>
                                    <span>PKR <span x-text="Number(receiptData.changeReturn).toLocaleString()"></span></span>
                                </div>
                            </template>
                        </div>

                        <!-- Notes (if any) -->
                        <template x-if="receiptData && receiptData.notes">
                            <div class="border-t border-dashed border-slate-300 pt-1 text-[9px] text-slate-600">
                                <strong>Notes:</strong> <span x-text="receiptData.notes"></span>
                            </div>
                        </template>

                        <!-- Footer -->
                        <div class="border-t border-black pt-2 text-center text-[9px] space-y-1">
                            <p class="font-bold tracking-wider uppercase">*** THANK YOU FOR VISITING ***</p>
                            <p class="text-[8px] text-slate-500">Please inspect your receipt before leaving.</p>
                            <div class="pt-1 font-mono tracking-widest text-[10px] text-slate-400">||| | |||| || ||||| |||</div>
                            <p class="text-[7px] text-slate-400">Powered by Jugnu Saloon Suite &bull; {{ date('Y') }}</p>
                        </div>

                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-200">
                    <button type="button" @click="receiptModalOpen = false" 
                            class="px-4 py-2 bg-slate-100 text-slate-700 font-bold text-xs rounded-none hover:bg-slate-200">
                        Close
                    </button>
                    <button type="button" @click="print80mmReceiptDirect(null)" 
                            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs rounded-none transition-colors flex items-center gap-1.5 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        <span>Print 80mm Receipt</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: QUICK EMPLOYEE RANKING / RATING -->
    <div x-show="rateModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-xs" @click="rateModalOpen = false"></div>
            <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-none border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-extrabold text-slate-900 heading-font flex items-center gap-2">
                        <span>⭐ Rank Employee Performance</span>
                    </h3>
                    <button type="button" @click="rateModalOpen = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <template x-if="ratingApt">
                    <form method="POST" :action="'/manager/appointments/' + ratingApt.id + '/rate'" class="space-y-4">
                        @csrf
                        
                        <!-- Booking Summary Header -->
                        <div class="p-3 bg-slate-50 border border-slate-200 space-y-1 text-xs">
                            <div class="flex justify-between">
                                <span class="text-slate-500 font-bold">Booking:</span>
                                <span class="font-black text-indigo-700" x-text="'#' + ratingApt.booking_no"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500 font-bold">Client:</span>
                                <span class="font-bold text-slate-800" x-text="ratingApt.customer ? ratingApt.customer.name : 'Walk-in Client'"></span>
                            </div>
                        </div>

                        <!-- Employee Selection Dropdown -->
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-700 uppercase">Assigned Staff Employee</label>
                            <select name="employee_id" x-model="modalEmployeeId" required
                                    class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-amber-500">
                                <option value="">-- Select Staff Employee --</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->id }}">
                                        💇 {{ $emp->name }} {{ $emp->category ? '('.$emp->category->title.')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Star Rating Picker -->
                        <div class="space-y-2 p-4 bg-amber-50 border border-amber-200 text-center">
                            <label class="block text-xs font-black text-slate-800 uppercase">
                                Staff Service Rating (1 - 5 Stars)
                            </label>
                            
                            <input type="hidden" name="ranking" :value="modalRanking">

                            <div class="flex items-center justify-center gap-2 py-2">
                                <template x-for="star in [1, 2, 3, 4, 5]" :key="star">
                                    <button type="button" @click="modalRanking = star" 
                                            class="text-3xl transition-transform hover:scale-125 focus:outline-hidden"
                                            :title="'Rate ' + star + ' Star'">
                                        <span :class="modalRanking >= star ? 'text-amber-500' : 'text-slate-300'">★</span>
                                    </button>
                                </template>
                            </div>

                            <div class="text-xs font-black text-amber-900" x-text="getRankingLabel(modalRanking)"></div>
                        </div>

                        <!-- Feedback Notes -->
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-700 uppercase">Ranking / Review Notes (Optional)</label>
                            <textarea name="ranking_notes" x-model="modalRankingNotes" rows="2" 
                                      placeholder="Feedback on service quality, punctuality, client satisfaction..." 
                                      class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-medium text-slate-800 focus:ring-2 focus:ring-amber-500"></textarea>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                            <button type="button" @click="rateModalOpen = false" 
                                    class="px-4 py-2 bg-slate-100 text-slate-700 font-bold text-xs hover:bg-slate-200">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white font-black text-xs shadow-xs transition-colors">
                                Save Staff Ranking
                            </button>
                        </div>
                    </form>
                </template>
            </div>
        </div>
    </div>

</div>
@endsection
