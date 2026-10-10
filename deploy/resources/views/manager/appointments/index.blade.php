@extends('layouts.material')

@section('title', 'Bookings & Appointment History')

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
function appointmentHistory() {
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
        uploadSlip() {
            if (typeof window.uploadAppointmentReceiptSlip === 'function') {
                window.uploadAppointmentReceiptSlip(this);
            }
        },

        rateModalOpen: false,
        ratingApt: null,
        ratingScore: 5,
        ratingNotes: '',
        openRateModal(apt) {
            if (!apt) return;
            this.ratingApt = apt;
            this.ratingScore = parseInt(apt.ranking) || 5;
            this.ratingNotes = apt.ranking_notes || '';
            this.rateModalOpen = true;
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
            const rawItems = (apt.items || []).map(it => {
                const qty = parseInt(it.quantity) || 1;
                const origPrice = parseFloat(it.price) || 0;
                const discAmt = parseFloat(it.discount) || 0;
                const discPrice = parseFloat(it.discounted_price) || 0;
                const effectiveOrig = origPrice > 0 ? origPrice : (discPrice + discAmt);
                const lineTotal = discPrice > 0 ? discPrice : (effectiveOrig * qty - discAmt);
                return {
                    title: it.custom_title || (it.service ? it.service.title : 'Service'),
                    quantity: qty,
                    price: effectiveOrig > 0 ? effectiveOrig : lineTotal,
                    discount: discAmt,
                    discountText: discAmt > 0 ? `PKR ${discAmt.toLocaleString()} OFF` : '',
                    total: Math.max(0, lineTotal)
                };
            });

            let itemDiscountsTotal = rawItems.reduce((sum, it) => sum + (parseFloat(it.discount) || 0), 0);
            let rawSubtotal = rawItems.reduce((sum, it) => sum + (parseFloat(it.price) || 0) * (parseInt(it.quantity) || 1), 0);
            if (rawSubtotal <= 0) {
                rawSubtotal = parseFloat(apt.total_amount || 0) + itemDiscountsTotal;
            }

            const billDiscount = parseFloat(apt.discount || 0);
            const totalDiscount = itemDiscountsTotal + billDiscount;

            let finalItems = rawItems;
            if (itemDiscountsTotal === 0 && billDiscount > 0 && rawItems.length > 0 && rawSubtotal > 0) {
                let allocated = 0;
                finalItems = rawItems.map((it, idx) => {
                    const gross = it.price * it.quantity;
                    let itemDisc = (idx === rawItems.length - 1)
                        ? (billDiscount - allocated)
                        : Math.round(billDiscount * (gross / rawSubtotal));
                    allocated += itemDisc;
                    return {
                        ...it,
                        discount: itemDisc,
                        discountText: itemDisc > 0 ? `PKR ${itemDisc.toLocaleString()} OFF` : '',
                        total: Math.max(0, gross - itemDisc)
                    };
                });
            }

            this.receiptData = {
                billNo: apt.booking_no,
                billDate: apt.appointment_date ? (typeof apt.appointment_date === 'string' ? apt.appointment_date.split('T')[0] : apt.appointment_date) : '',
                billTime: apt.start_time || '',
                customerName: apt.customer ? apt.customer.name : 'Walk-in Client',
                customerPhone: apt.customer ? apt.customer.phone_no1 : '',
                orderType: apt.order_type || 'On Site',
                items: finalItems,
                subtotal: rawSubtotal,
                serviceDiscount: itemDiscountsTotal,
                billDiscount: billDiscount,
                discount: totalDiscount,
                discountPercentage: parseFloat(apt.discount_percentage || 0),
                extraAmount: parseFloat(apt.extra_amount || 0),
                extraPercentage: (parseFloat(apt.extra_amount || 0) > 0 && (rawSubtotal - totalDiscount) > 0) 
                    ? Math.round((parseFloat(apt.extra_amount || 0) / (rawSubtotal - totalDiscount)) * 100) 
                    : 0,
                netAmount: parseFloat(apt.net_amount || (rawSubtotal - totalDiscount + (parseFloat(apt.extra_amount) || 0))),
                paymentMode: apt.payment_mode || 'Cash',
                paidAmount: parseFloat(apt.paid_amount || 0),
                balanceDue: parseFloat(apt.balance_due || 0),
                changeReturn: Math.max(0, parseFloat(apt.paid_amount || 0) - parseFloat(apt.net_amount || 0)),
                notes: apt.notes || '',
                generatedBy: '{{ addslashes(auth()->user()->name ?? 'Staff') }}'
            };
            this.receiptModalOpen = true;
        },
        print80mmReceiptDirect() {
            if (!this.receiptData) return;
            const receiptHtml = window.buildAppointmentReceiptDocument(this.receiptData, this.brandSettings);
            let printIframe = document.getElementById('posReceiptPrintIframe');
            if (!printIframe) {
                printIframe = document.createElement('iframe');
                printIframe.id = 'posReceiptPrintIframe';
                printIframe.style.position = 'fixed';
                printIframe.style.right = '0';
                printIframe.style.bottom = '0';
                printIframe.style.width = '0px';
                printIframe.style.height = '0px';
                printIframe.style.border = '0';
                printIframe.style.visibility = 'hidden';
                document.body.appendChild(printIframe);
            }
            const doc = printIframe.contentWindow.document;
            doc.open();
            doc.write(receiptHtml);
            doc.close();

            const triggerPrint = () => {
                try {
                    printIframe.contentWindow.focus();
                    printIframe.contentWindow.print();
                } catch(err) {
                    console.error('Print error:', err);
                }
            };

            const logoImg = doc.querySelector('img');
            if (logoImg && !logoImg.complete) {
                logoImg.onload = triggerPrint;
                logoImg.onerror = triggerPrint;
                setTimeout(triggerPrint, 800);
            } else {
                setTimeout(triggerPrint, 350);
            }
        },

        appointmentsList: @json($appointments->items()),
        getAppointmentById(id) {
            if (!id) return null;
            return (this.appointmentsList || []).find(a => a.id == id) || null;
        }
    };
}
</script>


<div x-data="appointmentHistory()" class="space-y-6 w-full">

    <!-- Top Action Bar & Navigation Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-none border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold text-slate-400 uppercase tracking-wider">
                <span>Salon Operations</span>
                <span>•</span>
                <span class="text-indigo-600">POS Billing & Appointments</span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight heading-font mt-0.5">
                <span>Bookings & Appointment History</span>
            </h1>
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
                   class="px-4 py-2 text-xs rounded-none transition-all flex items-center gap-2 text-slate-600 hover:text-slate-900 font-bold">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    <span>New Bill Terminal</span>
                </a>
                <a href="{{ route('manager.appointments.index') }}" 
                   class="px-4 py-2 text-xs rounded-none transition-all flex items-center gap-2 bg-white text-indigo-700 shadow-sm font-black">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    <span>Bookings History ({{ $appointments->total() }})</span>
                </a>
                <a href="{{ route('manager.ratings.index') }}" 
                   class="px-4 py-2 text-xs rounded-none transition-all flex items-center gap-1.5 text-amber-700 hover:text-amber-800 hover:bg-amber-50 font-bold">
                    <span>⭐</span>
                    <span>Staff Feedback</span>
                </a>
            </div>
        </div>
    </div>


    <!-- ALL BOOKINGS HISTORY -->
    <div class="space-y-5">
                <!-- KPI Metrics (Interactive Clickable Filter Widgets) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- 1. Total Bookings (Click to Show All) -->
            <a href="{{ route('manager.appointments.index', array_merge(request()->except(['order_type', 'page']), ['tab' => 'history'])) }}" 
               class="block p-4 rounded-none shadow-xs transition-all cursor-pointer group hover:shadow-md
               {{ empty($orderType) ? 'bg-white border-2 border-slate-900 ring-2 ring-slate-900/10' : 'bg-white border border-slate-200 hover:border-slate-400' }}">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Bookings</span>
                            @if(empty($orderType))
                                <span class="px-1.5 py-0.2 bg-slate-900 text-white text-[9px] font-black rounded-none">ALL CHANNELS</span>
                            @endif
                        </div>
                        <span class="text-2xl font-black text-slate-900 mt-1 block">{{ $totalBookingsCount ?? $appointments->total() }}</span>
                        <span class="text-[10px] font-bold text-slate-400 group-hover:text-indigo-600 transition-colors">Click to reset & show all &rarr;</span>
                    </div>
                    <div class="p-3 bg-slate-100 text-slate-700 rounded-none group-hover:bg-slate-200 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    </div>
                </div>
            </a>

            <!-- 2. Online Bookings (Click to Filter Website Bookings) -->
            <a href="{{ route('manager.appointments.index', array_merge(request()->except(['page']), ['order_type' => 'Online', 'tab' => 'history'])) }}" 
               class="block p-4 rounded-none shadow-xs transition-all cursor-pointer group hover:shadow-md
               {{ $orderType === 'Online' ? 'bg-cyan-50 border-2 border-cyan-600 ring-2 ring-cyan-500/20 shadow-md' : 'bg-white border border-cyan-200 hover:border-cyan-400 bg-cyan-50/20' }}">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-[10px] font-black text-cyan-800 uppercase tracking-wider block">🌐 Online Bookings</span>
                            @if($orderType === 'Online')
                                <span class="px-1.5 py-0.2 bg-cyan-600 text-white text-[9px] font-black rounded-none animate-pulse">FILTER ACTIVE</span>
                            @endif
                        </div>
                        <span class="text-2xl font-black text-cyan-700 mt-1 block">{{ $onlineBookingsCount }}</span>
                        <span class="text-[10px] font-bold text-cyan-600 group-hover:text-cyan-800 transition-colors">Click to filter Online bookings &rarr;</span>
                    </div>
                    <div class="p-3 bg-cyan-100 text-cyan-700 rounded-none group-hover:bg-cyan-200 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                    </div>
                </div>
            </a>

            <!-- 3. On-Site POS (Click to Filter POS Bookings) -->
            <a href="{{ route('manager.appointments.index', array_merge(request()->except(['page']), ['order_type' => 'On Site', 'tab' => 'history'])) }}" 
               class="block p-4 rounded-none shadow-xs transition-all cursor-pointer group hover:shadow-md
               {{ $orderType === 'On Site' ? 'bg-indigo-50 border-2 border-indigo-600 ring-2 ring-indigo-500/20 shadow-md' : 'bg-white border border-indigo-200 hover:border-indigo-400 bg-indigo-50/20' }}">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-[10px] font-black text-indigo-800 uppercase tracking-wider block">🏢 On-Site POS</span>
                            @if($orderType === 'On Site')
                                <span class="px-1.5 py-0.2 bg-indigo-600 text-white text-[9px] font-black rounded-none animate-pulse">FILTER ACTIVE</span>
                            @endif
                        </div>
                        <span class="text-2xl font-black text-indigo-700 mt-1 block">{{ $onSiteBookingsCount }}</span>
                        <span class="text-[10px] font-bold text-indigo-600 group-hover:text-indigo-800 transition-colors">Click to filter POS bookings &rarr;</span>
                    </div>
                    <div class="p-3 bg-indigo-100 text-indigo-700 rounded-none group-hover:bg-indigo-200 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    </div>
                </div>
            </a>

            <!-- 4. Staff Service Ranking -->
            <div class="bg-white p-4 border border-amber-200 rounded-none shadow-xs flex items-center justify-between bg-amber-50/30">
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
            <form method="GET" action="{{ route('manager.appointments.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-7 gap-3">
                <input type="hidden" name="tab" value="history">

                <!-- Channel Filter (Online vs On-Site) -->
                <div>
                    <select name="order_type" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-none text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-600">
                        <option value="">All Channels</option>
                        <option value="Online" {{ $orderType == 'Online' ? 'selected' : '' }}>🌐 Online Bookings</option>
                        <option value="On Site" {{ $orderType == 'On Site' ? 'selected' : '' }}>🏢 On-Site POS</option>
                    </select>
                </div>
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
                        <option value="rejected" {{ $status == 'rejected' ? 'selected' : '' }}>Rejected</option>
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
                <table class="w-full text-left border-collapse min-w-[1100px]">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-400 text-[10px] font-black uppercase tracking-wider">
                            <th class="py-4 px-4">Booking #</th>
                            <th class="py-4 px-4">Channel</th>
                            <th class="py-4 px-4">Client Name</th>
                            <th class="py-4 px-4">Assigned Staff & Ranking</th>
                            <th class="py-4 px-4">Apt & Booking Date</th>
                            <th class="py-4 px-4">Services / Ad-Ons</th>
                            <th class="py-4 px-4">Net Bill</th>
                            <th class="py-4 px-4">Paid Amount</th>
                            <th class="py-4 px-4">Remaining Balance</th>
                            <th class="py-4 px-4">Status</th>
                            <th class="py-4 px-4 text-center">Payment Proof</th>
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
                                            <a href="{{ route('manager.ratings.index') }}?appointment_id={{ $apt->id }}&employee_id={{ $apt->employee_id }}" 
                                               class="inline-flex items-center gap-1 px-2 py-0.5 bg-slate-100 hover:bg-amber-100 hover:border-amber-300 text-slate-600 hover:text-amber-800 border border-slate-200 text-[10px] font-bold transition-colors">
                                                <span class="text-amber-500">★</span>
                                                <span>Staff Feedback</span>
                                            </a>
                                        @endif
                                    </div>
                                @else
                                    <a href="{{ route('manager.ratings.index') }}?appointment_id={{ $apt->id }}" 
                                       class="text-[11px] text-slate-400 hover:text-indigo-600 font-medium italic underline">
                                        + Staff Feedback
                                    </a>
                                @endif
                            </td>

                            <td class="py-4 px-4 whitespace-nowrap">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-1.5 font-bold text-slate-900 text-xs">
                                        <span class="text-[9px] font-black uppercase tracking-wider px-1.5 py-0.5 bg-indigo-50 text-indigo-700 border border-indigo-200 shrink-0">Apt</span>
                                        <span>{{ $apt->appointment_date ? $apt->appointment_date->format('M d, Y') : '—' }}</span>
                                        @if(!empty($apt->start_time))
                                            <span class="text-[10.5px] font-semibold text-slate-500">({{ $apt->start_time }})</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-1.5 text-slate-500 text-[11px] font-medium">
                                        <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 bg-slate-100 text-slate-600 border border-slate-200 shrink-0">Booked</span>
                                        <span>{{ $apt->created_at ? $apt->created_at->format('M d, Y h:i A') : '—' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-4 px-4">
                                <div class="space-y-0.5">
                                    @foreach($apt->items as $item)
                                        <p class="font-bold text-slate-800 text-[11px] flex items-center gap-1">
                                            <span>• {{ $item->custom_title ?: ($item->service->title ?? 'Service') }}</span>
                                            @if(($item->quantity ?? 1) > 1)
                                                <span class="px-1.5 py-0.2 bg-indigo-100 text-indigo-800 text-[10px] font-black rounded-none">x{{ $item->quantity }}</span>
                                            @endif
                                            @if($item->discount > 0)
                                                <span class="px-1 py-0.2 bg-rose-100 text-rose-800 text-[9px] font-black rounded-none">Disc: PKR {{ number_format($item->discount, 0) }}</span>
                                            @endif
                                        </p>
                                    @endforeach
                                </div>
                            </td>
                            <td class="py-4 px-4 font-black text-slate-900">PKR {{ number_format($apt->net_amount, 2) }}</td>
                            <td class="py-4 px-4 font-bold text-emerald-600">PKR {{ number_format($apt->paid_amount, 2) }}</td>
                            <td class="py-4 px-4 font-bold">
                                @if($apt->balance_due > 0)
                                    <span class="px-2 py-0.5 bg-rose-50 text-rose-700 font-black border border-rose-200 block text-center">
                                        PKR {{ number_format($apt->balance_due, 2) }} Due
                                    </span>
                                    @if(!in_array($apt->status, ['cancelled', 'rejected']))
                                        <button type="button" 
                                                @click="openPayRemainingModal({{ $apt->id }}, '{{ $apt->booking_no }}', '{{ addslashes($apt->customer->name ?? 'Client') }}', {{ (float)$apt->net_amount }}, {{ (float)$apt->paid_amount }}, {{ (float)$apt->balance_due }})"
                                                class="mt-1 block w-full text-center text-[10px] font-extrabold text-emerald-800 hover:text-white bg-emerald-50 hover:bg-emerald-600 border border-emerald-300 hover:border-emerald-600 px-1.5 py-0.5 transition-all cursor-pointer shadow-2xs">
                                            + Pay Remaining
                                        </button>
                                    @endif
                                @else
                                    <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 font-bold border border-emerald-200 block text-center">
                                        Cleared (PKR 0.00)
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-4">
                                <span class="px-2.5 py-1 text-[10px] font-black uppercase rounded-none 
                                    {{ $apt->status === 'confirmed' ? 'bg-blue-50 text-blue-700 border border-blue-200' : '' }}
                                    {{ $apt->status === 'completed' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : '' }}
                                    {{ $apt->status === 'pending' ? 'bg-amber-50 text-amber-700 border border-amber-200' : '' }}
                                    {{ $apt->status === 'cancelled' ? 'bg-rose-50 text-rose-700 border border-rose-200' : '' }}
                                    {{ $apt->status === 'rejected' ? 'bg-rose-100 text-rose-800 border border-rose-300' : '' }}">
                                    {{ $apt->status }}
                                </span>
                            </td>
                            <!-- Payment Proof Column -->
                            <td class="py-4 px-4 text-center">
                                @php
                                    $proofUrl = null;
                                    if (!empty($apt->receipt_image)) {
                                        $rawPath = $apt->receipt_image;
                                        if (preg_match('#(?:https?://[^/]+)?/(?:public/)?((?:storage|uploads)/.+)#i', $rawPath, $m)) {
                                            $proofUrl = asset($m[1]);
                                        } elseif (\Illuminate\Support\Str::startsWith($rawPath, ['http://', 'https://'])) {
                                            $proofUrl = $rawPath;
                                        } else {
                                            $proofUrl = asset(ltrim($rawPath, '/'));
                                        }
                                    }
                                @endphp
                                @if($proofUrl)
                                    <div class="inline-flex items-center gap-1.5">
                                        <button type="button" 
                                                @click="openProofModal({{ $apt->id }}, '{{ $proofUrl }}', '{{ $apt->booking_no }}', '{{ addslashes($apt->customer->name ?? 'Online Client') }}', '{{ number_format($apt->net_amount, 2) }}', '{{ $apt->appointment_date ? $apt->appointment_date->format('M d, Y') : '' }} (Booked: {{ $apt->created_at ? $apt->created_at->format('M d, Y') : '' }})', '{{ $apt->order_type }}')"
                                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 font-extrabold text-[11px] rounded-none transition-all shadow-2xs cursor-pointer"
                                                title="Click to view full payment slip attached by customer">
                                            <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            <span>View Slip</span>
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        </button>
                                        <a href="{{ $proofUrl }}" target="_blank" class="p-1 text-slate-400 hover:text-emerald-700 transition-colors" title="Open original slip in new tab">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                        </a>
                                    </div>
                                @else
                                    <div class="inline-flex items-center gap-1.5">
                                        <span class="text-[10px] text-slate-400 font-semibold italic">— None —</span>
                                        <button type="button" 
                                                @click="openProofModal({{ $apt->id }}, '', '{{ $apt->booking_no }}', '{{ addslashes($apt->customer->name ?? 'Online Client') }}', '{{ number_format($apt->net_amount, 2) }}', '{{ $apt->appointment_date ? $apt->appointment_date->format('M d, Y') : '' }} (Booked: {{ $apt->created_at ? $apt->created_at->format('M d, Y') : '' }})', '{{ $apt->order_type }}')"
                                                class="px-1.5 py-0.5 text-[10px] font-bold text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition-colors cursor-pointer"
                                                title="Attach customer payment proof / slip">
                                            + Slip
                                        </button>
                                    </div>
                                @endif
                            </td>
                            <td class="py-4 px-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if($apt->balance_due > 0 && !in_array($apt->status, ['cancelled', 'rejected']))
                                        <button type="button" 
                                                @click="openPayRemainingModal({{ $apt->id }}, '{{ $apt->booking_no }}', '{{ addslashes($apt->customer->name ?? 'Client') }}', {{ (float)$apt->net_amount }}, {{ (float)$apt->paid_amount }}, {{ (float)$apt->balance_due }})"
                                                class="px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-[11px] rounded-none transition-colors border border-emerald-700 inline-flex items-center gap-1 shadow-xs cursor-pointer"
                                                title="Pay remaining balance (PKR {{ number_format($apt->balance_due, 2) }})">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                            <span>Pay Due</span>
                                        </button>
                                    @endif
                                    <button type="button" @click="openRateModal(getAppointmentById({{ $apt->id }}))" 
                                            class="px-2.5 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-800 font-bold text-[11px] rounded-none transition-colors border border-amber-200 inline-flex items-center gap-1"
                                            title="Rank / Rate Assigned Employee">
                                        <span class="text-amber-600">★</span>
                                        <span>Rank</span>
                                    </button>
                                    <button type="button" @click="openReceiptModal(getAppointmentById({{ $apt->id }}))" 
                                            class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[11px] rounded-none transition-colors border border-slate-300 inline-flex items-center gap-1"
                                            title="View & Print 80mm Receipt">
                                        <svg class="w-3.5 h-3.5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                        <span>Receipt</span>
                                    </button>
                                    <a href="{{ route('manager.appointments.edit', $apt->id) }}" 
                                       class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-[11px] rounded-none transition-colors border border-indigo-200 inline-flex items-center gap-1"
                                       title="Edit booking in New Bill Terminal">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        <span>Edit</span>
                                    </a>
                                    @if($apt->status === 'pending')
                                        <form method="POST" action="{{ route('manager.appointments.updateStatus', $apt->id) }}" class="inline" onsubmit="return confirm('Reject booking #{{ $apt->booking_no }}? This will mark it rejected and prevent any funds from being counted in Day Closing.');">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="rejected">
                                            <button type="submit" 
                                                    class="px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 hover:text-rose-900 font-bold text-[11px] rounded-none transition-colors border border-rose-200 inline-flex items-center gap-1 cursor-pointer"
                                                    title="Reject booking">
                                                <svg class="w-3.5 h-3.5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                <span>Reject</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="12" class="py-8 text-center text-slate-400 font-semibold">No service booking records found matching the filters.</td>
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

    <!-- MODAL: CUSTOMER PAYMENT PROOF SLIP LIGHTBOX -->


    <div x-show="proofModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/70 backdrop-blur-xs" @click="proofModalOpen = false"></div>
            <div class="inline-block w-full max-w-2xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-none border border-slate-300 space-y-4">
                
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">📎</span>
                        <div>
                            <h3 class="text-base font-extrabold text-slate-900 heading-font">Customer Payment Proof / Slip</h3>
                            <p class="text-xs text-slate-500 font-medium">Uploaded by client during online website booking</p>
                        </div>
                    </div>
                    <button type="button" @click="proofModalOpen = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Booking Quick Summary Header -->
                <div class="p-3 bg-slate-50 border border-slate-200 grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs font-mono">
                    <div>
                        <span class="text-slate-400 block text-[10px]">Booking #:</span>
                        <strong class="text-indigo-700 font-black" x-text="'#' + proofModalData.bookingNo"></strong>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px]">Client:</span>
                        <strong class="text-slate-900" x-text="proofModalData.clientName"></strong>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px]">Net Bill:</span>
                        <strong class="text-emerald-700 font-black" x-text="'PKR ' + proofModalData.amount"></strong>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px]">Date:</span>
                        <strong class="text-slate-700" x-text="proofModalData.date"></strong>
                    </div>
                </div>

                <!-- Feedback Alerts inside Modal -->
                <div x-show="proofModalData.uploadSuccess" x-cloak class="p-3 bg-emerald-50 border border-emerald-300 text-emerald-800 text-xs font-bold flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span>Payment proof slip uploaded & updated successfully!</span>
                </div>
                <div x-show="proofModalData.errorMessage" x-cloak class="p-3 bg-rose-50 border border-rose-300 text-rose-800 text-xs font-bold flex items-center gap-2">
                    <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span x-text="proofModalData.errorMessage"></span>
                </div>

                <!-- Slip Image Container (Scrollable & Scalable) -->
                <div class="bg-slate-900/95 p-4 border border-slate-300 min-h-[260px] max-h-[60vh] overflow-auto flex flex-col items-center justify-center rounded-none shadow-inner relative">
                    
                    <!-- 1. Loading State -->
                    <div x-show="proofModalData.imgLoading && proofModalData.url && !proofModalData.imgError" class="py-12 flex flex-col items-center gap-3 text-slate-400">
                        <svg class="animate-spin w-8 h-8 text-indigo-400" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span class="text-xs font-bold uppercase tracking-wider">Loading payment slip image...</span>
                    </div>

                    <!-- 2. Active Image Display -->
                    <template x-if="proofModalData.url && !proofModalData.imgError">
                        <img :src="proofModalData.url" alt="Customer Payment Slip" 
                             x-on:load="proofModalData.imgLoading = false; proofModalData.imgError = false"
                             x-on:error="proofModalData.imgLoading = false; proofModalData.imgError = true"
                             class="max-w-full max-h-[55vh] object-contain shadow-2xl border border-slate-700 transition-opacity duration-300"
                             :class="proofModalData.imgLoading ? 'opacity-0' : 'opacity-100'">
                    </template>

                    <!-- 3. Error / Missing File Fallback State -->
                    <div x-show="proofModalData.imgError || !proofModalData.url" class="w-full max-w-md py-6 px-4 bg-white/95 rounded-none border border-slate-200 text-center space-y-4 my-auto shadow-md">
                        <div class="w-12 h-12 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mx-auto text-xl">
                            ⚠️
                        </div>
                        <div>
                            <h4 class="text-sm font-extrabold text-slate-900 heading-font">Payment Slip Image Not Found</h4>
                            <p class="text-xs text-slate-500 mt-1">
                                The slip image for this booking is missing, link has expired, or customer has not yet attached it.
                            </p>
                        </div>

                        <!-- Direct Inline Upload Box -->
                        <div class="p-3 bg-slate-50 border border-dashed border-slate-300 space-y-2 text-left">
                            <label class="block text-[11px] font-black text-slate-700 uppercase">
                                Attach or Replace Slip File (PNG, JPG, PDF)
                            </label>
                            <input type="file" x-ref="slipFileInput" accept="image/png,image/jpeg,image/webp,application/pdf" 
                                   class="block w-full text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-none file:border-0 file:text-xs file:font-bold file:bg-slate-800 file:text-white hover:file:bg-black cursor-pointer">
                            <button type="button" @click="uploadSlip($event)" :disabled="proofModalData.uploading"
                                    class="w-full py-2 bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-400 text-white font-black text-xs rounded-none transition-colors flex items-center justify-center gap-1.5 cursor-pointer shadow-xs">
                                <template x-if="proofModalData.uploading">
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="animate-spin w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                        <span>Uploading Slip...</span>
                                    </span>
                                </template>
                                <template x-if="!proofModalData.uploading">
                                    <span>Upload & Attach Payment Slip</span>
                                </template>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Replace Slip Toggle Section (when image is already visible) -->
                <div x-show="proofModalData.showReplaceForm && !proofModalData.imgError && proofModalData.url" x-cloak class="p-3 bg-slate-50 border border-slate-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-black text-slate-800 uppercase">Replace Current Slip Image</label>
                        <button type="button" @click="proofModalData.showReplaceForm = false" class="text-xs text-slate-400 hover:text-slate-600 font-bold">&times; Close</button>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-2 items-center">
                        <input type="file" x-ref="slipFileInput" accept="image/png,image/jpeg,image/webp,application/pdf" 
                               class="w-full text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-none file:border-0 file:text-xs file:font-bold file:bg-slate-800 file:text-white hover:file:bg-black cursor-pointer">
                        <button type="button" @click="uploadSlip($event)" :disabled="proofModalData.uploading"
                                class="shrink-0 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs rounded-none transition-colors flex items-center justify-center gap-1 shadow-xs cursor-pointer">
                            <span>Upload New</span>
                        </button>
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-200">
                    <button type="button" @click="proofModalOpen = false" 
                            class="px-4 py-2 bg-slate-100 text-slate-700 font-bold text-xs rounded-none hover:bg-slate-200 cursor-pointer">
                        Close
                    </button>
                    <div class="flex items-center gap-2">
                        <template x-if="!proofModalData.imgError && proofModalData.url">
                            <button type="button" @click="proofModalData.showReplaceForm = !proofModalData.showReplaceForm"
                                    class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-none transition-colors inline-flex items-center gap-1 border border-slate-300 cursor-pointer">
                                <span>🔄 Replace Slip</span>
                            </button>
                        </template>
                        <template x-if="!proofModalData.imgError && proofModalData.url">
                            <a :href="proofModalData.url" target="_blank" 
                               class="px-4 py-2 bg-slate-900 hover:bg-black text-white font-bold text-xs rounded-none transition-colors inline-flex items-center gap-1.5 shadow-xs cursor-pointer">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                <span>Open Full Resolution</span>
                            </a>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: PAY REMAINING APPOINTMENT BALANCE -->
    <div x-show="payRemainingModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/70 backdrop-blur-xs" @click="payRemainingModalOpen = false"></div>
            <div class="inline-block w-full max-w-lg p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-none border border-slate-300 space-y-4">
                
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">💳</span>
                        <div>
                            <h3 class="text-base font-extrabold text-slate-900 heading-font">Pay Remaining Balance</h3>
                            <p class="text-xs text-slate-500 font-medium">Record payment received for booking <span class="font-bold text-indigo-600" x-text="'#' + payRemainingBookingNo"></span></p>
                        </div>
                    </div>
                    <button type="button" @click="payRemainingModalOpen = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Booking Balance Summary Cards -->
                <div class="p-4 bg-slate-50 border border-slate-200 grid grid-cols-3 gap-3 text-center">
                    <div class="bg-white p-2.5 border border-slate-200">
                        <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Total Bill</span>
                        <span class="text-xs font-black text-slate-900" x-text="'PKR ' + Number(payRemainingNetAmount).toLocaleString('en-US', {minimumFractionDigits: 2})"></span>
                    </div>
                    <div class="bg-white p-2.5 border border-slate-200">
                        <span class="text-[10px] uppercase font-bold text-emerald-600 block tracking-wider">Already Paid</span>
                        <span class="text-xs font-black text-emerald-700" x-text="'PKR ' + Number(payRemainingPaidAmount).toLocaleString('en-US', {minimumFractionDigits: 2})"></span>
                    </div>
                    <div class="bg-rose-50 p-2.5 border border-rose-200">
                        <span class="text-[10px] uppercase font-bold text-rose-600 block tracking-wider">Balance Due</span>
                        <span class="text-xs font-black text-rose-700" x-text="'PKR ' + Number(payRemainingBalanceDue).toLocaleString('en-US', {minimumFractionDigits: 2})"></span>
                    </div>
                </div>

                <div class="text-xs text-slate-600 font-medium flex items-center gap-1.5 px-1">
                    <span class="font-bold text-slate-500">Customer:</span>
                    <span class="font-bold text-slate-900" x-text="payRemainingCustomerName"></span>
                </div>

                <!-- Payment Form -->
                <form method="POST" :action="'/manager/appointments/' + payRemainingAptId + '/pay-remaining'" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Payment Amount -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Receiving Amount (PKR) <span class="text-rose-600">*</span>
                            </label>
                            <input type="number" step="0.01" min="0.01" :max="payRemainingBalanceDue" name="payment_amount" x-model="payRemainingAmount" required
                                   class="w-full px-3 py-2 bg-white border border-slate-300 font-black text-sm text-slate-900 focus:ring-2 focus:ring-emerald-500 rounded-none">
                            <span class="text-[10px] text-slate-400 font-semibold mt-0.5 block">Can be full balance or partial installment</span>
                        </div>

                        <!-- Payment Date -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Payment Date <span class="text-rose-600">*</span>
                            </label>
                            <input type="date" name="payment_date" x-model="payRemainingDate" required
                                   class="w-full px-3 py-2 bg-white border border-slate-300 font-bold text-xs text-slate-900 focus:ring-2 focus:ring-emerald-500 rounded-none">
                            <span class="text-[10px] text-emerald-600 font-semibold mt-0.5 block">Exact date money entered salon drawer / bank</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Payment Mode -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Payment Method <span class="text-rose-600">*</span>
                            </label>
                            <select name="payment_mode" x-model="payRemainingMode" required
                                    class="w-full px-3 py-2 bg-white border border-slate-300 font-bold text-xs text-slate-900 focus:ring-2 focus:ring-emerald-500 rounded-none">
                                <option value="Cash">💵 Cash</option>
                                <option value="Bank">🏦 Bank Transfer</option>
                                <option value="Card">💳 Credit / Debit Card</option>
                                <option value="Other">✨ Other</option>
                            </select>
                        </div>

                        <!-- Bank Account Picker (conditional) -->
                        <div x-show="['Bank', 'Card'].includes(payRemainingMode)" x-cloak>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Deposited Bank Account
                            </label>
                            <select name="bank_account_id" x-model="payRemainingBankId"
                                    class="w-full px-3 py-2 bg-white border border-slate-300 font-bold text-xs text-slate-900 focus:ring-2 focus:ring-emerald-500 rounded-none">
                                <option value="">-- Select Bank Account --</option>
                                @if(isset($bankAccounts))
                                    @foreach($bankAccounts as $bank)
                                        <option value="{{ $bank->bankid }}">
                                            {{ $bank->bank_name }} - {{ $bank->account_title }} ({{ $bank->account_no }})
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Notes / Remarks</label>
                        <input type="text" name="notes" x-model="payRemainingNotes" placeholder="e.g. Paid remaining balance at desk"
                               class="w-full px-3 py-2 bg-white border border-slate-300 text-xs text-slate-800 focus:ring-2 focus:ring-emerald-500 rounded-none">
                    </div>

                    <!-- Modal Action Buttons -->
                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                        <button type="button" @click="payRemainingModalOpen = false"
                                class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-none transition-colors cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs rounded-none transition-colors shadow-xs flex items-center gap-1.5 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Record Payment</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- DEDICATED 80MM THERMAL RECEIPT PRINT CONTAINER (RENDERED CLEANLY ON PRINT) -->


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
                <span>Service Discount (Items):</span>
                <span style="font-weight: 500;">- PKR ${Number(data.serviceDiscount).toLocaleString()}</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 2px; color: #000; font-weight: 400; font-size: 11.5px;">
                <span>Bill Discount:</span>
                <span style="font-weight: 500;">- PKR ${Number(data.billDiscount).toLocaleString()}</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 2px; color: #000; font-weight: 500; font-size: 11.5px;">
                <span>Total Service Discount:</span>
                <span style="font-weight: 500;">- PKR ${Number(data.discount).toLocaleString()}</span>
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
