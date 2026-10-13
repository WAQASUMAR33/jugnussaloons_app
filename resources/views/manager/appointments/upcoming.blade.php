@extends('layouts.material')

@section('title', 'Upcoming Bookings')

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
        aside, nav, header, footer, .sidebar, .print\:hidden {
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
            padding: 0 1.5mm !important;
            background: #ffffff !important;
            z-index: 999999 !important;
            box-sizing: border-box !important;
        }
    }
</style>

<script>
function upcomingBookingsManager() {
    return {
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

        payRemainingModalOpen: false,
        payRemainingAptId: null,
        payRemainingBookingNo: '',
        payRemainingCustomerName: '',
        payRemainingNetAmount: 0,
        payRemainingPaidAmount: 0,
        payRemainingBalanceDue: 0,
        payRemainingAmount: 0,
        payRemainingMode: 'Cash',
        payRemainingBankId: '',
        payRemainingDate: '{{ now()->toDateString() }}',
        payRemainingNotes: '',

        openPayRemainingModal(id, bookingNo, customerName, netAmount, paidAmount, balanceDue) {
            this.payRemainingAptId = id;
            this.payRemainingBookingNo = bookingNo;
            this.payRemainingCustomerName = customerName;
            this.payRemainingNetAmount = netAmount;
            this.payRemainingPaidAmount = paidAmount;
            this.payRemainingBalanceDue = balanceDue;
            this.payRemainingAmount = balanceDue;
            this.payRemainingMode = 'Cash';
            this.payRemainingBankId = '';
            this.payRemainingDate = '{{ now()->toDateString() }}';
            this.payRemainingNotes = '';
            this.payRemainingModalOpen = true;
        },

        receiptModalOpen: false,
        receiptData: null,
        openReceiptModal(apt) {
            if (!apt) return;
            this.receiptData = {
                id: apt.id,
                booking_no: apt.booking_no,
                order_type: apt.order_type || 'On Site',
                appointment_date: apt.appointment_date,
                start_time: apt.start_time,
                total_amount: Number(apt.total_amount || 0),
                discount: Number(apt.discount || 0),
                discount_percentage: Number(apt.discount_percentage || 0),
                net_amount: Number(apt.net_amount || 0),
                paid_amount: Number(apt.paid_amount || 0),
                balance_due: Number(apt.balance_due || 0),
                status: apt.status,
                payment_mode: apt.payment_mode || 'Cash',
                bank_name: apt.bank_account ? (apt.bank_account.bank_name || 'Bank') : '',
                customer_name: apt.customer ? apt.customer.name : 'Walk-in Client',
                customer_phone: apt.customer ? (apt.customer.phone_no1 || '') : '',
                employee_name: apt.employee ? apt.employee.name : 'Specialist',
                created_at: apt.created_at,
                items: (apt.items || []).map(i => ({
                    title: i.custom_title || (i.service ? i.service.title : 'Service'),
                    quantity: Number(i.quantity || 1),
                    price: Number(i.price || 0),
                    discount: Number(i.discount || 0),
                    discounted_price: Number(i.discounted_price || 0)
                }))
            };
            this.receiptModalOpen = true;
        },
        printReceipt() {
            document.body.classList.add('pos-receipt-active');
            window.print();
            setTimeout(() => {
                document.body.classList.remove('pos-receipt-active');
            }, 800);
        },

        statusModalOpen: false,
        statusApt: null,
        newStatus: '',
        openStatusModal(apt) {
            this.statusApt = apt;
            this.newStatus = apt.status;
            this.statusModalOpen = true;
        }
    };
}
</script>

<div x-data="upcomingBookingsManager()" class="space-y-6 w-full">

    <!-- Top Action Bar & Navigation Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold text-slate-400 uppercase tracking-wider">
                <span>Salon Operations</span>
                <span>•</span>
                <span class="text-indigo-600">Appointments Schedule</span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight heading-font mt-0.5">
                Upcoming Bookings
            </h1>
            <p class="text-xs font-semibold text-slate-500 mt-0.5">Scheduled appointments from today onward across Online site and On-Site POS channels.</p>
        </div>

        <!-- Mode Switcher Tabs & Quick Action -->
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('manager.appointments.create') }}" 
               class="px-4 py-2 text-xs rounded-none transition-all flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-black shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>+ New Bill / Booking</span>
            </a>

            <div class="inline-flex flex-wrap sm:flex-nowrap p-1 bg-slate-100 rounded-none border border-slate-200">
                <a href="{{ route('manager.appointments.create') }}" 
                   class="px-3.5 py-1.5 text-xs transition-all flex items-center gap-1.5 text-slate-600 hover:text-slate-900 font-bold">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    <span>New Bill Terminal</span>
                </a>
                <a href="{{ route('manager.appointments.upcoming') }}" 
                   class="px-3.5 py-1.5 text-xs transition-all flex items-center gap-1.5 bg-white text-indigo-700 shadow-xs font-black">
                    <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <span>Upcoming Bookings</span>
                </a>
                <a href="{{ route('manager.appointments.index') }}" 
                   class="px-3.5 py-1.5 text-xs transition-all flex items-center gap-1.5 text-slate-600 hover:text-slate-900 font-bold">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    <span>Bookings History</span>
                </a>
                <a href="{{ route('manager.ratings.index') }}" 
                   class="px-3.5 py-1.5 text-xs transition-all flex items-center gap-1 text-amber-700 hover:text-amber-800 hover:bg-amber-50 font-bold">
                    <span>Staff Feedback</span>
                </a>
            </div>
        </div>
    </div>

    <!-- 1. THREE CHANNEL OPTIONS: SHOW ALL, SITE BOOKINGS, POS BOOKINGS -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        
        <!-- OPTION 1: SHOW ALL BOOKINGS -->
        <a href="{{ route('manager.appointments.upcoming', array_merge(request()->except(['page', 'order_type']))) }}" 
           class="p-4 shadow-xs transition-all cursor-pointer group hover:shadow-md border-2
           {{ empty($orderType) ? 'bg-white border-slate-900 ring-2 ring-slate-900/10' : 'bg-white border-slate-200 hover:border-slate-400' }}">
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] font-black uppercase tracking-wider {{ empty($orderType) ? 'text-slate-900' : 'text-slate-500' }}">
                            Show All Bookings
                        </span>
                        @if(empty($orderType))
                            <span class="px-1.5 py-0.5 bg-slate-900 text-white text-[9px] font-black">ACTIVE</span>
                        @endif
                    </div>
                    <span class="text-3xl font-black text-slate-900 mt-1 block font-mono">{{ $totalUpcomingCount }}</span>
                    <span class="text-[10px] font-bold text-slate-400 group-hover:text-slate-700 transition-colors">
                        Online Site + POS Bookings combined
                    </span>
                </div>
                <div class="p-3 {{ empty($orderType) ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600' }} transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                </div>
            </div>
        </a>

        <!-- OPTION 2: SITE BOOKINGS (ONLINE WEBSITE) -->
        <a href="{{ route('manager.appointments.upcoming', array_merge(request()->except(['page']), ['order_type' => 'Online'])) }}" 
           class="p-4 shadow-xs transition-all cursor-pointer group hover:shadow-md border-2
           {{ $orderType === 'Online' ? 'bg-cyan-50/70 border-cyan-600 ring-2 ring-cyan-500/20' : 'bg-white border-cyan-200/80 hover:border-cyan-400 bg-cyan-50/20' }}">
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] font-black uppercase tracking-wider text-cyan-800">
                            Site Bookings
                        </span>
                        @if($orderType === 'Online')
                            <span class="px-1.5 py-0.5 bg-cyan-600 text-white text-[9px] font-black animate-pulse">ACTIVE</span>
                        @endif
                    </div>
                    <span class="text-3xl font-black text-cyan-700 mt-1 block font-mono">{{ $siteUpcomingCount }}</span>
                    <span class="text-[10px] font-bold text-cyan-600 group-hover:text-cyan-800 transition-colors">
                        Online bookings placed from website
                    </span>
                </div>
                <div class="p-3 {{ $orderType === 'Online' ? 'bg-cyan-600 text-white' : 'bg-cyan-100 text-cyan-700' }} transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                </div>
            </div>
        </a>

        <!-- OPTION 3: POS BOOKINGS (ON-SITE SALON POS) -->
        <a href="{{ route('manager.appointments.upcoming', array_merge(request()->except(['page']), ['order_type' => 'On Site'])) }}" 
           class="p-4 shadow-xs transition-all cursor-pointer group hover:shadow-md border-2
           {{ $orderType === 'On Site' ? 'bg-indigo-50/70 border-indigo-600 ring-2 ring-indigo-500/20' : 'bg-white border-indigo-200/80 hover:border-indigo-400 bg-indigo-50/20' }}">
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] font-black uppercase tracking-wider text-indigo-800">
                            POS Bookings
                        </span>
                        @if($orderType === 'On Site')
                            <span class="px-1.5 py-0.5 bg-indigo-600 text-white text-[9px] font-black animate-pulse">ACTIVE</span>
                        @endif
                    </div>
                    <span class="text-3xl font-black text-indigo-700 mt-1 block font-mono">{{ $posUpcomingCount }}</span>
                    <span class="text-[10px] font-bold text-indigo-600 group-hover:text-indigo-800 transition-colors">
                        In-salon front desk & terminal entries
                    </span>
                </div>
                <div class="p-3 {{ $orderType === 'On Site' ? 'bg-indigo-600 text-white' : 'bg-indigo-100 text-indigo-700' }} transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                </div>
            </div>
        </a>

    </div>

    <!-- 2. DATE FILTER TABS: TODAY, TOMORROW, THIS WEEK, ALL UPCOMING -->
    <div class="bg-white border border-slate-200 shadow-xs p-4 sm:p-5 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <span class="text-xs font-black uppercase text-slate-500 tracking-wider">Scheduled Period:</span>
                <div class="flex flex-wrap gap-1.5">
                    
                    <!-- TAB: TODAY -->
                    <a href="{{ route('manager.appointments.upcoming', array_merge(request()->except(['page', 'date']), ['time_filter' => 'today'])) }}" 
                       class="px-3 py-1.5 text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer border
                       {{ ($timeFilter === 'today' && empty($customDate)) ? 'bg-indigo-600 border-indigo-600 text-white font-black shadow-xs' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100' }}">
                        <span>Today</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ ($timeFilter === 'today' && empty($customDate)) ? 'bg-white text-indigo-700 font-black' : 'bg-slate-200 text-slate-700' }}">
                            {{ $todayCount }}
                        </span>
                    </a>

                    <!-- TAB: TOMORROW -->
                    <a href="{{ route('manager.appointments.upcoming', array_merge(request()->except(['page', 'date']), ['time_filter' => 'tomorrow'])) }}" 
                       class="px-3 py-1.5 text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer border
                       {{ ($timeFilter === 'tomorrow' && empty($customDate)) ? 'bg-indigo-600 border-indigo-600 text-white font-black shadow-xs' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100' }}">
                        <span>Tomorrow</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ ($timeFilter === 'tomorrow' && empty($customDate)) ? 'bg-white text-indigo-700 font-black' : 'bg-slate-200 text-slate-700' }}">
                            {{ $tomorrowCount }}
                        </span>
                    </a>

                    <!-- TAB: THIS WEEK -->
                    <a href="{{ route('manager.appointments.upcoming', array_merge(request()->except(['page', 'date']), ['time_filter' => 'this_week'])) }}" 
                       class="px-3 py-1.5 text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer border
                       {{ ($timeFilter === 'this_week' && empty($customDate)) ? 'bg-indigo-600 border-indigo-600 text-white font-black shadow-xs' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100' }}">
                        <span>This Week</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ ($timeFilter === 'this_week' && empty($customDate)) ? 'bg-white text-indigo-700 font-black' : 'bg-slate-200 text-slate-700' }}">
                            {{ $thisWeekCount }}
                        </span>
                    </a>

                    <!-- TAB: ALL UPCOMING -->
                    <a href="{{ route('manager.appointments.upcoming', array_merge(request()->except(['page', 'date']), ['time_filter' => 'all'])) }}" 
                       class="px-3 py-1.5 text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer border
                       {{ ($timeFilter === 'all' && empty($customDate)) ? 'bg-indigo-600 border-indigo-600 text-white font-black shadow-xs' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100' }}">
                        <span>All Upcoming</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ ($timeFilter === 'all' && empty($customDate)) ? 'bg-white text-indigo-700 font-black' : 'bg-slate-200 text-slate-700' }}">
                            {{ $allUpcomingCount }}
                        </span>
                    </a>

                </div>
            </div>

            <div class="text-xs font-semibold text-slate-400">
                Audited Timeline: <strong class="text-slate-800">{{ now()->format('d M Y') }}</strong> onward
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <form method="GET" action="{{ route('manager.appointments.upcoming') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 items-end">
            <input type="hidden" name="time_filter" value="{{ $timeFilter }}">
            <input type="hidden" name="order_type" value="{{ $orderType }}">

            <!-- Search -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Search</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </span>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Booking #, client, phone..." 
                           class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 text-xs font-semibold focus:ring-2 focus:ring-indigo-600">
                </div>
            </div>

            <!-- Stylist / Staff Filter -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Assigned Specialist</label>
                <select name="employee_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-600">
                    <option value="">All Stylists / Staff</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" {{ (string)$employeeId === (string)$emp->id ? 'selected' : '' }}>
                            {{ $emp->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Status Filter -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-600">
                    <option value="">All Statuses</option>
                    <option value="confirmed" {{ $status == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                    <option value="pending" {{ $status == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="completed" {{ $status == 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="cancelled" {{ $status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    <option value="rejected" {{ $status == 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>

            <!-- Target Date Picker -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Target Date</label>
                <input type="date" name="date" value="{{ $customDate }}" 
                       class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-semibold focus:ring-2 focus:ring-indigo-600">
            </div>

            <!-- Channel Dropdown -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Booking Channel</label>
                <select name="order_type" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-600">
                    <option value="">Show All Bookings</option>
                    <option value="Online" {{ $orderType == 'Online' ? 'selected' : '' }}>Site Bookings (Online)</option>
                    <option value="On Site" {{ $orderType == 'On Site' ? 'selected' : '' }}>POS Bookings (On-Site)</option>
                </select>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs transition-colors shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                    <span>Filter</span>
                </button>
                @if($search || $status || $customDate || $orderType || $employeeId)
                    <a href="{{ route('manager.appointments.upcoming') }}" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs border border-slate-200 transition-colors">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- 3. UPCOMING BOOKINGS TABLE -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <h3 class="text-xs font-black uppercase text-slate-800 tracking-wider">
                    Upcoming Appointments Queue ({{ $appointments->total() }})
                </h3>
            </div>
            <div class="text-xs font-semibold text-slate-500">
                Sorted chronologically (soonest first)
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[1050px]">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-400 text-[10px] font-black uppercase tracking-wider">
                        <th class="py-3.5 px-4">Booking #</th>
                        <th class="py-3.5 px-4">Scheduled Date & Slot</th>
                        <th class="py-3.5 px-4">Channel</th>
                        <th class="py-3.5 px-4">Client Name & Phone</th>
                        <th class="py-3.5 px-4">Specialist / Staff</th>
                        <th class="py-3.5 px-4">Services Booked</th>
                        <th class="py-3.5 px-4 text-right">Net Bill</th>
                        <th class="py-3.5 px-4 text-right">Paid</th>
                        <th class="py-3.5 px-4 text-right">Remaining Due</th>
                        <th class="py-3.5 px-4 text-center">Status</th>
                        <th class="py-3.5 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                    @forelse($appointments as $apt)
                    @php
                        $aptDate = $apt->appointment_date ? \Carbon\Carbon::parse($apt->appointment_date) : null;
                        $isToday = $aptDate && $aptDate->isToday();
                        $isTomorrow = $aptDate && $aptDate->isTomorrow();
                    @endphp
                    <tr class="hover:bg-slate-50/70 transition-colors {{ $isToday ? 'bg-amber-50/30' : '' }}">
                        <!-- Booking # -->
                        <td class="py-3.5 px-4">
                            <span class="font-mono font-black text-indigo-700 block text-xs">#{{ $apt->booking_no }}</span>
                            <span class="text-[10px] text-slate-400">ID: #{{ $apt->id }}</span>
                        </td>

                        <!-- Scheduled Date & Time Slot -->
                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-1.5">
                                @if($isToday)
                                    <span class="px-2 py-0.5 bg-amber-100 text-amber-900 font-black text-[9px] uppercase">Today</span>
                                @elseif($isTomorrow)
                                    <span class="px-2 py-0.5 bg-sky-100 text-sky-900 font-black text-[9px] uppercase">Tomorrow</span>
                                @endif
                                <strong class="text-slate-900 font-extrabold text-xs">
                                    {{ $aptDate ? $aptDate->format('d M Y') : 'Date TBD' }}
                                </strong>
                            </div>
                            <div class="text-[11px] text-slate-500 font-mono mt-0.5 flex items-center gap-1">
                                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span>{{ $apt->start_time ? date('h:i A', strtotime($apt->start_time)) : 'Anytime / Slot' }}</span>
                            </div>
                        </td>

                        <!-- Channel -->
                        <td class="py-3.5 px-4">
                            @if($apt->order_type === 'Online')
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-cyan-50 border border-cyan-200 text-cyan-800 text-[10px] font-black uppercase">
                                    <svg class="w-3 h-3 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                                    <span>Site Booking</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-indigo-50 border border-indigo-200 text-indigo-800 text-[10px] font-black uppercase">
                                    <svg class="w-3 h-3 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                    <span>POS Booking</span>
                                </span>
                            @endif
                        </td>

                        <!-- Client Name & Phone -->
                        <td class="py-3.5 px-4">
                            <span class="font-bold text-slate-900 block text-xs">{{ $apt->customer->name ?? 'Walk-in Client' }}</span>
                            @if(!empty($apt->customer?->phone_no1))
                                <a href="tel:{{ $apt->customer->phone_no1 }}" class="text-[11px] font-mono text-slate-500 hover:text-indigo-600 flex items-center gap-1 mt-0.5">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                    <span>{{ $apt->customer->phone_no1 }}</span>
                                </a>
                            @endif
                        </td>

                        <!-- Specialist / Staff -->
                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center text-[10px] font-black uppercase">
                                    {{ strtoupper(substr($apt->employee->name ?? 'S', 0, 1)) }}
                                </div>
                                <div>
                                    <span class="font-bold text-slate-900 block text-xs">{{ $apt->employee->name ?? 'Unassigned' }}</span>
                                    <span class="text-[10px] text-slate-400 block">{{ $apt->employee?->category?->title ?? 'Staff' }}</span>
                                </div>
                            </div>
                        </td>

                        <!-- Services Booked -->
                        <td class="py-3.5 px-4 max-w-xs">
                            <div class="space-y-1">
                                @forelse($apt->items as $item)
                                    <span class="inline-block px-2 py-0.5 bg-slate-100 text-slate-800 text-[10px] font-bold mr-1">
                                        {{ $item->custom_title ?: ($item->service?->title ?? 'Service') }}
                                    </span>
                                @empty
                                    <span class="text-slate-400 text-xs italic">No specific service items</span>
                                @endforelse
                            </div>
                        </td>

                        <!-- Net Bill -->
                        <td class="py-3.5 px-4 text-right font-mono font-bold text-slate-900 text-xs">
                            PKR {{ number_format($apt->net_amount, 2) }}
                        </td>

                        <!-- Paid -->
                        <td class="py-3.5 px-4 text-right font-mono text-emerald-600 font-bold text-xs">
                            PKR {{ number_format($apt->paid_amount, 2) }}
                        </td>

                        <!-- Remaining Due -->
                        <td class="py-3.5 px-4 text-right">
                            @if($apt->balance_due > 0)
                                <span class="font-mono font-black text-rose-600 text-xs block">
                                    PKR {{ number_format($apt->balance_due, 2) }}
                                </span>
                                <button type="button" 
                                        @click="openPayRemainingModal({{ $apt->id }}, '{{ $apt->booking_no }}', '{{ addslashes($apt->customer->name ?? 'Walk-in') }}', {{ $apt->net_amount }}, {{ $apt->paid_amount }}, {{ $apt->balance_due }})"
                                        class="text-[9px] font-black uppercase text-indigo-600 hover:text-indigo-800 underline mt-0.5 cursor-pointer">
                                    Collect Due
                                </button>
                            @else
                                <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 text-[10px] font-black uppercase">
                                    Paid in Full
                                </span>
                            @endif
                        </td>

                        <!-- Status -->
                        <td class="py-3.5 px-4 text-center">
                            @php
                                $statusClasses = [
                                    'confirmed' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                                    'pending' => 'bg-amber-100 text-amber-800 border-amber-300',
                                    'completed' => 'bg-slate-100 text-slate-800 border-slate-300',
                                    'cancelled' => 'bg-rose-100 text-rose-800 border-rose-300',
                                    'rejected' => 'bg-slate-200 text-slate-600 border-slate-300',
                                ];
                            @endphp
                            <span class="inline-block px-2.5 py-0.5 text-[10px] font-black uppercase border {{ $statusClasses[$apt->status] ?? 'bg-slate-100 text-slate-700' }}">
                                {{ $apt->status }}
                            </span>
                        </td>

                        <!-- Actions -->
                        <td class="py-3.5 px-4 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                
                                <!-- Open / Edit in POS -->
                                <a href="{{ route('manager.appointments.create', ['edit' => $apt->id]) }}" 
                                   title="Edit & Bill in POS Terminal"
                                   class="p-1.5 bg-slate-100 hover:bg-indigo-600 hover:text-white text-slate-600 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </a>

                                <!-- Print Thermal Receipt -->
                                <button type="button" 
                                        @click="openReceiptModal({{ json_encode($apt) }})"
                                        title="Print Receipt"
                                        class="p-1.5 bg-slate-100 hover:bg-slate-900 hover:text-white text-slate-600 transition-colors cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                </button>

                                <!-- Change Status Modal -->
                                <button type="button" 
                                        @click="openStatusModal({{ json_encode($apt) }})"
                                        title="Update Booking Status"
                                        class="p-1.5 bg-slate-100 hover:bg-amber-500 hover:text-white text-slate-600 transition-colors cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                </button>

                                <!-- View Proof Slip (Online Booking) -->
                                @if($apt->order_type === 'Online')
                                    <button type="button" 
                                            @click="openProofModal({{ $apt->id }}, '{{ $apt->receipt_image ? asset($apt->receipt_image) : '' }}', '{{ $apt->booking_no }}', '{{ addslashes($apt->customer->name ?? 'Client') }}', '{{ number_format($apt->paid_amount, 2) }}', '{{ $aptDate ? $aptDate->format('d M Y') : '' }}', '{{ $apt->order_type }}')"
                                            title="View Payment Proof"
                                            class="p-1.5 bg-cyan-50 hover:bg-cyan-600 hover:text-white text-cyan-700 transition-colors cursor-pointer">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    </button>
                                @endif

                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="11" class="py-12 text-center text-slate-400">
                            <div class="max-w-sm mx-auto space-y-2">
                                <svg class="w-10 h-10 mx-auto text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <p class="text-sm font-bold text-slate-700">No upcoming appointments found</p>
                                <p class="text-xs text-slate-400">There are no upcoming bookings matching the selected timeline or channel filters.</p>
                                <div class="pt-2">
                                    <a href="{{ route('manager.appointments.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs">
                                        <span>+ Book New Appointment</span>
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($appointments->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $appointments->links() }}
            </div>
        @endif
    </div>

    <!-- MODAL 1: STATUS CHANGER MODAL -->
    <div x-show="statusModalOpen" x-cloak 
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="statusModalOpen = false" 
             class="bg-white border border-slate-200 shadow-xl max-w-md w-full p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-sm font-black uppercase text-slate-900 tracking-wider">Update Booking Status</h3>
                <button type="button" @click="statusModalOpen = false" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>
            
            <template x-if="statusApt">
                <form :action="'{{ url('manager/appointments') }}/' + statusApt.id + '/status'" method="POST" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    
                    <div class="p-3 bg-slate-50 border border-slate-200 text-xs">
                        <span class="text-slate-400 block font-bold">Booking Reference:</span>
                        <strong class="text-slate-900 font-mono text-sm block" x-text="'#' + statusApt.booking_no"></strong>
                        <span class="text-slate-500 block mt-0.5" x-text="(statusApt.customer ? statusApt.customer.name : 'Walk-in Client') + ' • ' + statusApt.appointment_date"></span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">New Booking Status</label>
                        <select name="status" x-model="newStatus" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600">
                            <option value="confirmed">Confirmed</option>
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="statusModalOpen = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs">Update Status</button>
                    </div>
                </form>
            </template>
        </div>
    </div>

    <!-- MODAL 2: PAY REMAINING BALANCE MODAL -->
    <div x-show="payRemainingModalOpen" x-cloak 
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="payRemainingModalOpen = false" 
             class="bg-white border border-slate-200 shadow-xl max-w-md w-full p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-sm font-black uppercase text-slate-900 tracking-wider">Collect Remaining Balance</h3>
                <button type="button" @click="payRemainingModalOpen = false" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <form :action="'{{ url('manager/appointments') }}/' + payRemainingAptId + '/pay-remaining'" method="POST" class="space-y-4">
                @csrf
                <div class="p-3 bg-slate-50 border border-slate-200 text-xs space-y-1">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Booking:</span>
                        <strong class="font-mono text-indigo-700" x-text="'#' + payRemainingBookingNo"></strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Client:</span>
                        <strong class="text-slate-900" x-text="payRemainingCustomerName"></strong>
                    </div>
                    <div class="flex justify-between border-t border-slate-200 pt-1 mt-1 font-bold">
                        <span class="text-rose-600">Balance Due:</span>
                        <strong class="text-rose-600 font-mono text-sm" x-text="'PKR ' + Number(payRemainingBalanceDue).toFixed(2)"></strong>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Paying Amount (PKR)</label>
                    <input type="number" step="0.01" name="amount" x-model="payRemainingAmount" :max="payRemainingBalanceDue" required
                           class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-sm font-mono font-black text-slate-900 focus:ring-2 focus:ring-indigo-600">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Payment Mode</label>
                        <select name="payment_mode" x-model="payRemainingMode" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800">
                            <option value="Cash">Cash</option>
                            <option value="Bank">Bank Transfer</option>
                            <option value="Card">Card</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Payment Date</label>
                        <input type="date" name="payment_date" x-model="payRemainingDate" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-bold">
                    </div>
                </div>

                <div x-show="payRemainingMode === 'Bank' || payRemainingMode === 'Card'">
                    <label class="block text-xs font-bold text-slate-700 mb-1">Select Bank Account</label>
                    <select name="bank_account_id" x-model="payRemainingBankId" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-bold">
                        <option value="">Select Target Bank</option>
                        @foreach($bankAccounts as $bank)
                            <option value="{{ $bank->id }}">{{ $bank->bank_name }} ({{ $bank->account_number }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button type="button" @click="payRemainingModalOpen = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs">Confirm & Collect</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 3: PAYMENT PROOF SLIP MODAL (ONLINE SITE BOOKINGS) -->
    <div x-show="proofModalOpen" x-cloak 
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="proofModalOpen = false" 
             class="bg-white border border-slate-200 shadow-xl max-w-md w-full p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-sm font-black uppercase text-slate-900 tracking-wider">Site Booking Payment Proof</h3>
                <button type="button" @click="proofModalOpen = false" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <div class="space-y-3">
                <div class="flex justify-between text-xs">
                    <span class="text-slate-400">Booking #</span>
                    <strong class="font-mono text-slate-900" x-text="'#' + proofModalData.bookingNo"></strong>
                </div>
                <div class="flex justify-between text-xs">
                    <span class="text-slate-400">Client</span>
                    <strong class="text-slate-900" x-text="proofModalData.clientName"></strong>
                </div>

                <template x-if="proofModalData.url">
                    <div class="border border-slate-200 p-2 bg-slate-50 text-center">
                        <img :src="proofModalData.url" alt="Payment Proof" class="max-h-72 w-auto mx-auto object-contain">
                    </div>
                </template>

                <template x-if="!proofModalData.url">
                    <div class="p-6 bg-slate-50 border border-dashed border-slate-300 text-center text-xs text-slate-500">
                        No payment slip attached for this booking yet.
                    </div>
                </template>
            </div>

            <div class="flex justify-end pt-2 border-t border-slate-100">
                <button type="button" @click="proofModalOpen = false" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold text-xs">Close</button>
            </div>
        </div>
    </div>

    <!-- MODAL 4: 80MM POS THERMAL RECEIPT MODAL -->
    <div x-show="receiptModalOpen" x-cloak 
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 print:hidden">
        <div @click.away="receiptModalOpen = false" 
             class="bg-white border border-slate-200 shadow-xl max-w-sm w-full p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-sm font-black uppercase text-slate-900 tracking-wider">Print Receipt</h3>
                <button type="button" @click="receiptModalOpen = false" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <template x-if="receiptData">
                <div class="p-4 bg-slate-50 border border-slate-200 font-mono text-xs space-y-2">
                    <div class="text-center border-b border-slate-300 pb-2">
                        <h4 class="font-black text-sm uppercase" x-text="brandSettings.name"></h4>
                        <p class="text-[10px] text-slate-500" x-text="brandSettings.slogan"></p>
                        <p class="text-[10px] text-slate-500 mt-1" x-text="brandSettings.address"></p>
                    </div>
                    <div class="flex justify-between text-[11px] pt-1">
                        <span>Invoice #</span>
                        <strong x-text="'#' + receiptData.booking_no"></strong>
                    </div>
                    <div class="flex justify-between text-[11px]">
                        <span>Client:</span>
                        <strong x-text="receiptData.customer_name"></strong>
                    </div>
                    <div class="flex justify-between text-[11px]">
                        <span>Date:</span>
                        <strong x-text="receiptData.appointment_date"></strong>
                    </div>
                    <div class="border-t border-slate-300 pt-2 space-y-1">
                        <template x-for="item in receiptData.items" :key="item.title">
                            <div class="flex justify-between text-[11px]">
                                <span x-text="item.title"></span>
                                <strong x-text="'PKR ' + item.discounted_price.toFixed(2)"></strong>
                            </div>
                        </template>
                    </div>
                    <div class="border-t-2 border-slate-400 pt-2 space-y-1 font-bold">
                        <div class="flex justify-between">
                            <span>Total Bill:</span>
                            <span x-text="'PKR ' + receiptData.net_amount.toFixed(2)"></span>
                        </div>
                        <div class="flex justify-between text-emerald-700">
                            <span>Paid:</span>
                            <span x-text="'PKR ' + receiptData.paid_amount.toFixed(2)"></span>
                        </div>
                        <div class="flex justify-between text-rose-600" x-show="receiptData.balance_due > 0">
                            <span>Balance Due:</span>
                            <span x-text="'PKR ' + receiptData.balance_due.toFixed(2)"></span>
                        </div>
                    </div>
                </div>
            </template>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" @click="receiptModalOpen = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs">Close</button>
                <button type="button" @click="printReceipt()" class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs flex items-center gap-1.5 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    <span>Print 80mm</span>
                </button>
            </div>
        </div>
    </div>

    <!-- PRINT MOUNT FOR THERMAL PRINTER (HIDDEN UNTIL PRINT) -->
    <div id="posReceiptPrintMount" class="hidden font-mono text-xs text-black">
        <template x-if="receiptData">
            <div class="p-2 text-center space-y-1">
                <h2 class="text-sm font-black uppercase" x-text="brandSettings.name"></h2>
                <p class="text-[10px]" x-text="brandSettings.slogan"></p>
                <p class="text-[10px]" x-text="brandSettings.address"></p>
                <div class="border-b border-black my-2"></div>
                <div class="flex justify-between text-[10px] text-left">
                    <span>Receipt: <strong x-text="'#' + receiptData.booking_no"></strong></span>
                    <span x-text="receiptData.appointment_date"></span>
                </div>
                <div class="text-left text-[10px]">
                    Client: <strong x-text="receiptData.customer_name"></strong>
                </div>
                <div class="border-b border-black my-2"></div>
                <template x-for="item in receiptData.items" :key="item.title">
                    <div class="flex justify-between text-[10px] text-left">
                        <span x-text="item.title"></span>
                        <span x-text="item.discounted_price.toFixed(2)"></span>
                    </div>
                </template>
                <div class="border-t border-black my-2"></div>
                <div class="flex justify-between font-black text-xs">
                    <span>NET TOTAL:</span>
                    <span x-text="'PKR ' + receiptData.net_amount.toFixed(2)"></span>
                </div>
                <div class="flex justify-between text-[10px]">
                    <span>PAID:</span>
                    <span x-text="'PKR ' + receiptData.paid_amount.toFixed(2)"></span>
                </div>
                <div class="flex justify-between text-[10px]" x-show="receiptData.balance_due > 0">
                    <span>BALANCE:</span>
                    <span x-text="'PKR ' + receiptData.balance_due.toFixed(2)"></span>
                </div>
                <p class="text-[9px] mt-3" x-text="brandSettings.appointmentReceiptNote"></p>
            </div>
        </template>
    </div>

</div>
@endsection
