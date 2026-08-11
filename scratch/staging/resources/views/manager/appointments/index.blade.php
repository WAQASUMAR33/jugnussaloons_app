@extends('layouts.material')

@section('title', 'Service Appointment Booking')

@section('content')
<div x-data="{ 
    createModalOpen: false,
    editModalOpen: false,
    viewModalOpen: false,
    selectedAppointment: null,
    customersList: {{ json_encode($customers) }},
    employeesList: {{ json_encode($employees) }},
    saloonServices: {{ json_encode($saloonServices) }},

    // Create Modal States
    customerSearchTerm: '',
    customerDropdownOpen: false,
    selectedCustomerId: '',
    employeeSearchTerm: '',
    employeeDropdownOpen: false,
    selectedEmployeeId: '',
    items: [
        { service_id: '', service_obj: null, search_term: '', open: false, price: 0, commission: 0 }
    ],
    billDiscount: 0,
    paidAmount: 0,
    paymentMode: 'Cash',
    extraAmount: 0,

    // Edit Modal States
    editAppointmentId: null,
    editStatus: 'confirmed',
    editAppointmentDate: '',
    editStartTime: '10:00',
    editNotes: '',
    editCustomerSearchTerm: '',
    editCustomerDropdownOpen: false,
    editSelectedCustomerId: '',
    editEmployeeSearchTerm: '',
    editEmployeeDropdownOpen: false,
    editSelectedEmployeeId: '',
    editItems: [],
    editBillDiscount: 0,
    editPaidAmount: 0,
    editPaymentMode: 'Cash',
    editExtraAmount: 0,

    // Customer Filtering
    getFilteredCustomers(term) {
        if (!term || term.trim() === '') return this.customersList;
        const query = term.toLowerCase();
        return this.customersList.filter(c => 
            c.name.toLowerCase().includes(query) || 
            (c.phone_no1 && c.phone_no1.includes(query)) ||
            (c.card_no && c.card_no.toLowerCase().includes(query))
        );
    },
    selectCustomer(c) {
        this.selectedCustomerId = c.id;
        this.customerSearchTerm = c.name + ' (Phone: ' + (c.phone_no1 || 'N/A') + ')';
        this.customerDropdownOpen = false;
    },

    // Employee Filtering
    getFilteredEmployees(term) {
        if (!term || term.trim() === '') return this.employeesList;
        const query = term.toLowerCase();
        return this.employeesList.filter(e => 
            e.name.toLowerCase().includes(query) || 
            (e.phone_no1 && e.phone_no1.includes(query))
        );
    },
    selectEmployee(e) {
        this.selectedEmployeeId = e.id;
        this.employeeSearchTerm = e.name + ' (' + (e.emp_type ? e.emp_type.toUpperCase() : 'JUNIOR') + ')';
        this.employeeDropdownOpen = false;
        this.recalculateCommissions();
    },

    // Edit Customer Filtering
    getEditFilteredCustomers(term) {
        if (!term || term.trim() === '') return this.customersList;
        const query = term.toLowerCase();
        return this.customersList.filter(c => 
            c.name.toLowerCase().includes(query) || 
            (c.phone_no1 && c.phone_no1.includes(query))
        );
    },
    selectEditCustomer(c) {
        this.editSelectedCustomerId = c.id;
        this.editCustomerSearchTerm = c.name + ' (Phone: ' + (c.phone_no1 || 'N/A') + ')';
        this.editCustomerDropdownOpen = false;
    },

    // Edit Employee Filtering
    getEditFilteredEmployees(term) {
        if (!term || term.trim() === '') return this.employeesList;
        const query = term.toLowerCase();
        return this.employeesList.filter(e => 
            e.name.toLowerCase().includes(query)
        );
    },
    selectEditEmployee(e) {
        this.editSelectedEmployeeId = e.id;
        this.editEmployeeSearchTerm = e.name + ' (' + (e.emp_type ? e.emp_type.toUpperCase() : 'JUNIOR') + ')';
        this.editEmployeeDropdownOpen = false;
        this.recalculateEditCommissions();
    },

    // Create Service Helpers
    getSelectedEmployeeType() {
        if (!this.selectedEmployeeId) return 'junior';
        const emp = this.employeesList.find(e => e.id == this.selectedEmployeeId);
        return (emp && emp.emp_type && emp.emp_type.toLowerCase() === 'senior') ? 'senior' : 'junior';
    },
    recalculateCommissions() {
        const isSenior = this.getSelectedEmployeeType() === 'senior';
        this.items.forEach(item => {
            if (item.service_obj) {
                item.commission = isSenior 
                    ? parseFloat(item.service_obj.senior_commission || item.service_obj.commission || 0)
                    : parseFloat(item.service_obj.junior_commission || item.service_obj.commission || 0);
            }
        });
    },
    addServiceItem() {
        this.items.push({ service_id: '', service_obj: null, search_term: '', open: false, price: 0, commission: 0 });
    },
    removeServiceItem(index) {
        if (this.items.length > 1) {
            this.items.splice(index, 1);
        }
    },
    selectService(index, srv) {
        const isSenior = this.getSelectedEmployeeType() === 'senior';
        this.items[index].service_id = srv.id;
        this.items[index].service_obj = srv;
        this.items[index].search_term = srv.title;
        this.items[index].price = parseFloat(srv.discounted_price || srv.price);
        this.items[index].commission = isSenior 
            ? parseFloat(srv.senior_commission || srv.commission || 0)
            : parseFloat(srv.junior_commission || srv.commission || 0);
        this.items[index].open = false;
    },
    getFilteredServicesList(term) {
        if (!term || term.trim() === '') return this.saloonServices;
        const query = term.toLowerCase();
        return this.saloonServices.filter(s => 
            s.title.toLowerCase().includes(query) || 
            (s.description && s.description.toLowerCase().includes(query))
        );
    },
    getGrossTotal() {
        return this.items.reduce((sum, item) => sum + (parseFloat(item.price) || 0), 0).toFixed(2);
    },
    getTotalCommission() {
        return this.items.reduce((sum, item) => sum + (parseFloat(item.commission) || 0), 0).toFixed(2);
    },
    getNetTotal() {
        const gross = parseFloat(this.getGrossTotal()) || 0;
        const discount = parseFloat(this.billDiscount) || 0;
        const extra = (this.paymentMode === 'Bank') ? (parseFloat(this.extraAmount) || 0) : 0;
        return Math.max(0, gross - discount + extra).toFixed(2);
    },
    getBalanceDue() {
        const net = parseFloat(this.getNetTotal()) || 0;
        const paid = parseFloat(this.paidAmount) || 0;
        return (net - paid).toFixed(2);
    },

    // Edit Service Helpers
    getEditEmployeeType() {
        if (!this.editSelectedEmployeeId) return 'junior';
        const emp = this.employeesList.find(e => e.id == this.editSelectedEmployeeId);
        return (emp && emp.emp_type && emp.emp_type.toLowerCase() === 'senior') ? 'senior' : 'junior';
    },
    recalculateEditCommissions() {
        const isSenior = this.getEditEmployeeType() === 'senior';
        this.editItems.forEach(item => {
            if (item.service_obj) {
                item.commission = isSenior 
                    ? parseFloat(item.service_obj.senior_commission || item.service_obj.commission || 0)
                    : parseFloat(item.service_obj.junior_commission || item.service_obj.commission || 0);
            }
        });
    },
    addEditServiceItem() {
        this.editItems.push({ service_id: '', service_obj: null, search_term: '', open: false, price: 0, commission: 0 });
    },
    removeEditServiceItem(index) {
        if (this.editItems.length > 1) {
            this.editItems.splice(index, 1);
        }
    },
    selectEditService(index, srv) {
        const isSenior = this.getEditEmployeeType() === 'senior';
        this.editItems[index].service_id = srv.id;
        this.editItems[index].service_obj = srv;
        this.editItems[index].search_term = srv.title;
        this.editItems[index].price = parseFloat(srv.discounted_price || srv.price);
        this.editItems[index].commission = isSenior 
            ? parseFloat(srv.senior_commission || srv.commission || 0)
            : parseFloat(srv.junior_commission || srv.commission || 0);
        this.editItems[index].open = false;
    },
    getEditGrossTotal() {
        return this.editItems.reduce((sum, item) => sum + (parseFloat(item.price) || 0), 0).toFixed(2);
    },
    getEditTotalCommission() {
        return this.editItems.reduce((sum, item) => sum + (parseFloat(item.commission) || 0), 0).toFixed(2);
    },
    getEditNetTotal() {
        const gross = parseFloat(this.getEditGrossTotal()) || 0;
        const discount = parseFloat(this.editBillDiscount) || 0;
        const extra = (this.editPaymentMode === 'Bank') ? (parseFloat(this.editExtraAmount) || 0) : 0;
        return Math.max(0, gross - discount + extra).toFixed(2);
    },
    getEditBalanceDue() {
        const net = parseFloat(this.getEditNetTotal()) || 0;
        const paid = parseFloat(this.editPaidAmount) || 0;
        return (net - paid).toFixed(2);
    },

    openEditModal(apt) {
        this.editAppointmentId = apt.id;
        this.editStatus = apt.status;
        this.editAppointmentDate = apt.appointment_date ? apt.appointment_date.substring(0, 10) : '';
        this.editStartTime = apt.start_time || '10:00';
        this.editNotes = apt.notes || '';
        
        const cust = this.customersList.find(c => c.id == apt.account_id);
        if (cust) {
            this.editSelectedCustomerId = cust.id;
            this.editCustomerSearchTerm = cust.name + ' (Phone: ' + (cust.phone_no1 || 'N/A') + ')';
        } else {
            this.editSelectedCustomerId = apt.account_id;
            this.editCustomerSearchTerm = apt.customer ? apt.customer.name : '';
        }
        
        const emp = this.employeesList.find(e => e.id == apt.employee_id);
        if (emp) {
            this.editSelectedEmployeeId = emp.id;
            this.editEmployeeSearchTerm = emp.name + ' (' + (emp.emp_type ? emp.emp_type.toUpperCase() : 'JUNIOR') + ')';
        } else {
            this.editSelectedEmployeeId = apt.employee_id;
            this.editEmployeeSearchTerm = apt.employee ? apt.employee.name : '';
        }
        
        this.editBillDiscount = parseFloat(apt.discount) || 0;
        this.editPaidAmount = parseFloat(apt.paid_amount) || 0;
        this.editPaymentMode = apt.payment_mode || 'Cash';
        this.editExtraAmount = parseFloat(apt.extra_amount) || 0;
        
        this.editItems = [];
        if (apt.items && apt.items.length > 0) {
            apt.items.forEach(it => {
                const srv = this.saloonServices.find(s => s.id == it.saloon_service_id) || it.service;
                this.editItems.push({
                    service_id: it.saloon_service_id,
                    service_obj: srv,
                    search_term: srv ? srv.title : 'Saloon Service',
                    open: false,
                    price: parseFloat(it.discounted_price || it.price),
                    commission: parseFloat(it.commission) || 0,
                });
            });
        } else {
            this.editItems = [{ service_id: '', service_obj: null, search_term: '', open: false, price: 0, commission: 0 }];
        }
        
        this.editModalOpen = true;
    }
}" class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="p-2.5 bg-indigo-50 text-indigo-600">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Service Appointment Booking</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">Book saloon treatments, assign employee stylists, filter services catalog, and track commissions.</p>
            </div>
        </div>

        <button @click="createModalOpen = true" 
                class="inline-flex items-center gap-2 px-5 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            <span>Book New Appointment</span>
        </button>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white p-4 border border-slate-200 shadow-sm">
        <form method="GET" action="{{ route('manager.appointments.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div class="relative flex-1">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search booking #, client, stylist..." 
                       class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600 focus:bg-white transition-all">
            </div>

            <div>
                <select name="status" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-indigo-600">
                    <option value="">All Statuses</option>
                    <option value="pending" {{ $status == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="confirmed" {{ $status == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                    <option value="completed" {{ $status == 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="cancelled" {{ $status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>

            <div>
                <input type="date" name="date" value="{{ $date }}" 
                       class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 py-2.5 bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors">
                    Filter Bookings
                </button>
                @if($search || $status || $date)
                    <a href="{{ route('manager.appointments.index') }}" class="px-4 py-2.5 bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200 flex items-center justify-center">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Appointments Table Card -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[11px] font-extrabold uppercase tracking-wider">
                        <th class="py-4 px-4">Booking #</th>
                        <th class="py-4 px-4">Client Name</th>
                        <th class="py-4 px-4">Stylist / Staff</th>
                        <th class="py-4 px-4">Date & Time</th>
                        <th class="py-4 px-4">Services</th>
                        <th class="py-4 px-4">Gross Total</th>
                        <th class="py-4 px-4">Discount</th>
                        <th class="py-4 px-4">Net Bill</th>
                        <th class="py-4 px-4">Paid</th>
                        <th class="py-4 px-4">Balance</th>
                        <th class="py-4 px-4">Receipt</th>
                        <th class="py-4 px-4">Status</th>
                        <th class="py-4 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @forelse($appointments as $apt)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="py-4 px-4 font-black text-indigo-700">#{{ $apt->booking_no }}</td>
                        <td class="py-4 px-4 font-bold text-slate-900">
                            {{ $apt->customer->name ?? 'Walk-in Client' }}
                            @if($apt->customer && $apt->customer->phone_no1)
                                <p class="text-[10px] text-slate-400 font-normal">{{ $apt->customer->phone_no1 }}</p>
                            @endif
                        </td>
                        <td class="py-4 px-4">
                            <span class="px-2 py-1 bg-purple-50 text-purple-900 font-extrabold text-[11px] rounded border border-purple-200 inline-flex items-center gap-1">
                                👤 {{ $apt->employee->name ?? 'Unassigned' }}
                            </span>
                            @if($apt->employee && $apt->employee->emp_type)
                                <span class="block mt-0.5 text-[9px] font-extrabold text-purple-600 uppercase">Level: {{ ucfirst($apt->employee->emp_type) }}</span>
                            @endif
                        </td>
                        <td class="py-4 px-4 font-bold text-slate-700">
                            {{ $apt->appointment_date ? $apt->appointment_date->format('M d, Y') : '—' }}
                            @if($apt->start_time)
                                <span class="block text-[10px] text-slate-400 font-semibold">{{ date('h:i A', strtotime($apt->start_time)) }}</span>
                            @endif
                        </td>
                        <td class="py-4 px-4">
                            <div class="space-y-0.5">
                                @foreach($apt->items as $item)
                                    <p class="font-bold text-slate-800 text-[11px]">• {{ $item->service->title ?? 'Service' }}</p>
                                @endforeach
                            </div>
                        </td>
                        <td class="py-4 px-4 font-bold text-slate-800">
                            {{ number_format($apt->total_amount, 2) }}
                        </td>
                        <td class="py-4 px-4 font-bold text-amber-700">
                            {{ number_format($apt->discount, 2) }}
                        </td>
                        <td class="py-4 px-4 font-black text-slate-900">
                            {{ number_format($apt->net_amount, 2) }}
                        </td>
                        <td class="py-4 px-4 font-bold text-emerald-600">
                            {{ number_format($apt->paid_amount, 2) }}
                        </td>
                        <td class="py-4 px-4 font-bold {{ $apt->balance_due > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                            {{ number_format($apt->balance_due, 2) }}
                        </td>
                        <td class="py-4 px-4">
                            <button @click="selectedAppointment = {{ json_encode($apt) }}; viewModalOpen = true" 
                                    class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 font-bold text-xs flex items-center gap-1">
                                <span>🧾 Receipt</span>
                            </button>
                            @if($apt->receipt_image)
                                <a href="{{ asset($apt->receipt_image) }}" target="_blank" class="inline-block mt-0.5 text-[10px] font-bold text-indigo-600 hover:underline">
                                    🖼️ Image Attached
                                </a>
                            @endif
                        </td>
                        <td class="py-4 px-4">
                            @if($apt->status == 'completed')
                                <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 text-[10px] font-bold uppercase">Completed</span>
                            @elseif($apt->status == 'confirmed')
                                <span class="px-2 py-0.5 bg-blue-100 text-blue-800 text-[10px] font-bold uppercase">Confirmed</span>
                            @elseif($apt->status == 'pending')
                                <span class="px-2 py-0.5 bg-amber-100 text-amber-800 text-[10px] font-bold uppercase">Pending</span>
                            @else
                                <span class="px-2 py-0.5 bg-rose-100 text-rose-800 text-[10px] font-bold uppercase">Cancelled</span>
                            @endif
                        </td>
                        <td class="py-4 px-4 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <button @click="openEditModal({{ json_encode($apt) }})" 
                                        class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-900 font-bold text-xs flex items-center gap-1 transition-colors" title="Edit Booking Record">
                                    <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    <span>Edit</span>
                                </button>

                                <button @click="selectedAppointment = {{ json_encode($apt) }}; viewModalOpen = true" 
                                        class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-800 font-bold text-xs">
                                    Details
                                </button>

                                <form method="POST" action="{{ route('manager.appointments.updateStatus', $apt) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <select name="status" onchange="this.form.submit()" class="py-1 px-1.5 text-[11px] font-bold bg-slate-100 border-none cursor-pointer">
                                        <option value="pending" {{ $apt->status == 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="confirmed" {{ $apt->status == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                                        <option value="completed" {{ $apt->status == 'completed' ? 'selected' : '' }}>Completed</option>
                                        <option value="cancelled" {{ $apt->status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                    </select>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="13" class="py-12 text-center text-slate-400">
                            <p class="text-sm font-semibold">No service appointment bookings found.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-slate-100">
            {{ $appointments->links() }}
        </div>
    </div>

    <!-- MODAL: BOOK NEW APPOINTMENT -->
    <div x-show="createModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="createModalOpen = false" class="bg-white max-w-4xl w-full p-6 shadow-2xl space-y-6 max-h-[92vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    Book Saloon Service Appointment
                </h3>
                <button @click="createModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('manager.appointments.store') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf
                
                <!-- Primary Assignment Grid (FILTERABLE DROPDOWNS) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Filterable Customer Account Dropdown -->
                    <div class="relative" @click.outside="customerDropdownOpen = false">
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Select Customer Account</label>
                        <input type="hidden" name="account_id" :value="selectedCustomerId" required>
                        
                        <div class="relative">
                            <input type="text" 
                                   x-model="customerSearchTerm" 
                                   @focus="customerDropdownOpen = true" 
                                   @input="customerDropdownOpen = true; selectedCustomerId = ''" 
                                   placeholder="🔍 Search customer name or phone..." 
                                   required
                                   class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </span>
                        </div>

                        <!-- Filterable Customer List Dropdown -->
                        <div x-show="customerDropdownOpen" 
                             class="absolute z-30 left-0 right-0 mt-1 bg-white border border-slate-200 shadow-xl max-h-56 overflow-y-auto divide-y divide-slate-100"
                             x-cloak>
                            <template x-for="cust in getFilteredCustomers(customerSearchTerm)" :key="cust.id">
                                <div @click="selectCustomer(cust)" 
                                     class="p-2.5 hover:bg-indigo-50 cursor-pointer flex items-center justify-between transition-colors text-xs">
                                    <div>
                                        <p class="font-bold text-slate-900" x-text="cust.name"></p>
                                        <p class="text-[10px] text-slate-400" x-text="'Phone: ' + (cust.phone_no1 || 'N/A') + ' | Balance: ' + cust.balance"></p>
                                    </div>
                                    <span class="px-2 py-0.5 bg-indigo-50 font-extrabold text-[10px] text-indigo-700 rounded" x-text="cust.category ? cust.category.title : 'General'"></span>
                                </div>
                            </template>
                            <div x-show="getFilteredCustomers(customerSearchTerm).length === 0" class="p-3 text-center text-xs text-slate-400 font-medium">
                                No matching customer accounts found
                            </div>
                        </div>
                    </div>

                    <!-- Filterable Employee Stylist Dropdown -->
                    <div class="relative" @click.outside="employeeDropdownOpen = false">
                        <label class="block text-xs font-bold text-purple-800 uppercase mb-1">Assigned Employee / Stylist</label>
                        <input type="hidden" name="employee_id" :value="selectedEmployeeId" required>
                        
                        <div class="relative">
                            <input type="text" 
                                   x-model="employeeSearchTerm" 
                                   @focus="employeeDropdownOpen = true" 
                                   @input="employeeDropdownOpen = true; selectedEmployeeId = ''" 
                                   placeholder="🔍 Search employee stylist..." 
                                   required
                                   class="w-full px-4 py-2.5 bg-purple-50 border border-purple-200 text-sm font-bold text-purple-900 focus:ring-2 focus:ring-purple-600">
                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-purple-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </span>
                        </div>

                        <!-- Filterable Employee Dropdown List -->
                        <div x-show="employeeDropdownOpen" 
                             class="absolute z-30 left-0 right-0 mt-1 bg-white border border-slate-200 shadow-xl max-h-56 overflow-y-auto divide-y divide-slate-100"
                             x-cloak>
                            <template x-for="emp in getFilteredEmployees(employeeSearchTerm)" :key="emp.id">
                                <div @click="selectEmployee(emp)" 
                                     class="p-2.5 hover:bg-purple-50 cursor-pointer flex items-center justify-between transition-colors text-xs">
                                    <div>
                                        <p class="font-bold text-slate-900" x-text="emp.name"></p>
                                        <p class="text-[10px] text-slate-400" x-text="'Phone: ' + (emp.phone_no1 || 'N/A')"></p>
                                    </div>
                                    <span class="px-2 py-0.5 font-extrabold text-[10px] uppercase rounded" 
                                          :class="emp.emp_type === 'senior' ? 'bg-purple-100 text-purple-900 border border-purple-300' : 'bg-sky-100 text-sky-900 border border-sky-300'" 
                                          x-text="emp.emp_type ? emp.emp_type.toUpperCase() : 'JUNIOR'"></span>
                                </div>
                            </template>
                            <div x-show="getFilteredEmployees(employeeSearchTerm).length === 0" class="p-3 text-center text-xs text-slate-400 font-medium">
                                No matching employee stylists found
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Appointment Date</label>
                        <input type="date" name="appointment_date" value="{{ date('Y-m-d') }}" required 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Start Time (Optional)</label>
                        <input type="time" name="start_time" value="10:00" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                    </div>
                </div>

                <!-- FILTERABLE SALOON SERVICES DROPDOWN SECTION -->
                <div class="border border-slate-200 p-4 bg-slate-50 space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                        <div>
                            <h4 class="text-xs font-black uppercase text-slate-900 tracking-wider">Select Saloon Treatments & Services</h4>
                            <p class="text-[11px] text-slate-500">Search and select service line items from the filterable dropdown below.</p>
                        </div>

                        <button type="button" @click="addServiceItem()" 
                                class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-sm transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            <span>+ Add Service Line</span>
                        </button>
                    </div>

                    <!-- Dynamic Service Line Items -->
                    <div class="space-y-3">
                        <template x-for="(item, index) in items" :key="index">
                            <div class="flex flex-col sm:flex-row items-center gap-3 p-3 bg-white border border-slate-200 shadow-2xs">
                                <!-- Filterable Service Select Dropdown Component -->
                                <div class="flex-1 w-full relative" @click.outside="item.open = false">
                                    <input type="hidden" name="service_ids[]" :value="item.service_id" required>
                                    
                                    <div class="relative">
                                        <input type="text" 
                                               x-model="item.search_term" 
                                               @focus="item.open = true" 
                                               @input="item.open = true; item.service_id = ''" 
                                               placeholder="🔍 Type service name to filter..." 
                                               required
                                               class="w-full px-3 py-2 pr-8 bg-white border border-slate-200 text-xs font-semibold focus:ring-2 focus:ring-indigo-600">
                                        <span class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-slate-400">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                        </span>
                                    </div>

                                    <!-- Filterable Service List Dropdown -->
                                    <div x-show="item.open" 
                                         class="absolute z-30 left-0 right-0 mt-1 bg-white border border-slate-200 shadow-xl max-h-52 overflow-y-auto divide-y divide-slate-100"
                                         x-cloak>
                                        <template x-for="srv in getFilteredServicesList(item.search_term)" :key="srv.id">
                                            <div @click="selectService(index, srv)" 
                                                 class="p-2.5 hover:bg-indigo-50 cursor-pointer flex items-center justify-between transition-colors text-xs">
                                                <div>
                                                    <p class="font-bold text-slate-900" x-text="srv.title"></p>
                                                    <p class="text-[10px] text-purple-700 font-semibold">
                                                        Commission: <span class="font-black" x-text="getSelectedEmployeeType() === 'senior' ? (srv.senior_commission || srv.commission || '0.00') : (srv.junior_commission || srv.commission || '0.00')"></span> 
                                                        <span class="text-[9px] text-slate-500 font-extrabold uppercase" x-text="'(' + getSelectedEmployeeType() + ' rate)'"></span>
                                                    </p>
                                                </div>
                                                <div class="text-right">
                                                    <span class="font-extrabold text-indigo-700" x-text="srv.discounted_price || srv.price"></span>
                                                    <template x-if="srv.discount > 0">
                                                        <span class="block text-[10px] text-amber-700 font-bold" x-text="srv.discount + '% OFF'"></span>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                        <div x-show="getFilteredServicesList(item.search_term).length === 0" class="p-3 text-center text-xs text-slate-400 font-medium">
                                            No matching saloon services found
                                        </div>
                                    </div>
                                </div>

                                <!-- Service Net Price -->
                                <div class="w-full sm:w-28 text-right font-black text-xs text-slate-900">
                                    <span x-text="item.price ? item.price : '0.00'"></span>
                                </div>

                                <!-- Remove Line Button -->
                                <button type="button" @click="removeServiceItem(index)" class="p-1.5 text-rose-500 hover:bg-rose-50 transition-colors" title="Remove Line">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </template>
                    </div>

                    <!-- Selected Summary Footer -->
                    <div class="pt-2 flex items-center justify-between text-xs border-t border-slate-200">
                        <span class="font-bold text-slate-600">Total Services Selected: <span class="text-indigo-600 font-black" x-text="items.filter(i => i.service_id).length"></span></span>
                        <span class="font-bold text-purple-700">Total Employee Commission: <span class="font-black" x-text="getTotalCommission()"></span> <span class="text-[10px] text-slate-500 font-bold uppercase" x-text="'(' + getSelectedEmployeeType() + ' level)'"></span></span>
                    </div>
                </div>

                <!-- Payment Summary Grid -->
                <div class="p-4 bg-indigo-50/50 border border-indigo-200/80 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Services Gross Total</label>
                        <div class="text-lg font-bold text-slate-700" x-text="getGrossTotal()"></div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Appointment Discount</label>
                        <input type="number" step="0.01" min="0" name="discount" x-model.number="billDiscount" placeholder="0.00" 
                               class="w-full px-3 py-2 bg-white border border-slate-300 text-sm font-bold text-amber-700 focus:ring-2 focus:ring-indigo-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Payment Mode</label>
                        <select name="payment_mode" x-model="paymentMode" class="w-full px-3 py-2 bg-white border border-slate-300 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600">
                            <option value="Cash">Cash</option>
                            <option value="Bank">Bank</option>
                        </select>
                    </div>

                    <div x-show="paymentMode === 'Bank'">
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Bank Extra Amount</label>
                        <input type="number" step="0.01" min="0" name="extra_amount" x-model.number="extraAmount" placeholder="0.00" 
                               class="w-full px-3 py-2 bg-white border border-slate-300 text-sm font-bold text-indigo-700 focus:ring-2 focus:ring-indigo-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Net Bill Amount</label>
                        <div class="text-xl font-black text-slate-900" x-text="getNetTotal()"></div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Payment Received</label>
                        <input type="number" step="0.01" min="0" name="paid_amount" x-model.number="paidAmount" required placeholder="0.00" 
                               class="w-full px-3 py-2 bg-white border border-slate-300 text-sm font-bold text-emerald-700 focus:ring-2 focus:ring-indigo-600">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Payment Slip / Receipt Proof Image (Optional Upload)</label>
                    <input type="file" name="receipt_image" accept="image/*" 
                           class="w-full px-4 py-2 bg-slate-50 border border-slate-200 text-xs font-medium focus:ring-2 focus:ring-indigo-600">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Appointment Notes / Special Instructions</label>
                    <textarea name="notes" rows="2" placeholder="Specific hair products requested, customer sensitivities..." 
                              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600"></textarea>
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="createModalOpen = false" class="px-4 py-2.5 text-slate-600 font-bold text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white font-bold text-xs shadow-md">
                        Confirm Appointment Booking
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: EDIT SERVICE APPOINTMENT BOOKING -->
    <div x-show="editModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="editModalOpen = false" class="bg-white max-w-4xl w-full p-6 shadow-2xl space-y-6 max-h-[92vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Edit Service Booking Record & Synchronize Ledgers
                </h3>
                <button @click="editModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form :action="'{{ url('manager/appointments') }}/' + editAppointmentId" method="POST" enctype="multipart/form-data" class="space-y-5">
                @csrf
                @method('PUT')
                
                <!-- Primary Assignment Grid (FILTERABLE DROPDOWNS) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Filterable Customer Account Dropdown -->
                    <div class="relative" @click.outside="editCustomerDropdownOpen = false">
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Select Customer Account</label>
                        <input type="hidden" name="account_id" :value="editSelectedCustomerId" required>
                        
                        <div class="relative">
                            <input type="text" 
                                   x-model="editCustomerSearchTerm" 
                                   @focus="editCustomerDropdownOpen = true" 
                                   @input="editCustomerDropdownOpen = true; editSelectedCustomerId = ''" 
                                   placeholder="🔍 Search customer name or phone..." 
                                   required
                                   class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </span>
                        </div>

                        <!-- Filterable Customer List Dropdown -->
                        <div x-show="editCustomerDropdownOpen" 
                             class="absolute z-30 left-0 right-0 mt-1 bg-white border border-slate-200 shadow-xl max-h-56 overflow-y-auto divide-y divide-slate-100"
                             x-cloak>
                            <template x-for="cust in getEditFilteredCustomers(editCustomerSearchTerm)" :key="cust.id">
                                <div @click="selectEditCustomer(cust)" 
                                     class="p-2.5 hover:bg-indigo-50 cursor-pointer flex items-center justify-between transition-colors text-xs">
                                    <div>
                                        <p class="font-bold text-slate-900" x-text="cust.name"></p>
                                        <p class="text-[10px] text-slate-400" x-text="'Phone: ' + (cust.phone_no1 || 'N/A')"></p>
                                    </div>
                                    <span class="px-2 py-0.5 bg-indigo-50 font-extrabold text-[10px] text-indigo-700 rounded" x-text="cust.category ? cust.category.title : 'General'"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Filterable Employee Stylist Dropdown -->
                    <div class="relative" @click.outside="editEmployeeDropdownOpen = false">
                        <label class="block text-xs font-bold text-purple-800 uppercase mb-1">Assigned Employee / Stylist</label>
                        <input type="hidden" name="employee_id" :value="editSelectedEmployeeId" required>
                        
                        <div class="relative">
                            <input type="text" 
                                   x-model="editEmployeeSearchTerm" 
                                   @focus="editEmployeeDropdownOpen = true" 
                                   @input="editEmployeeDropdownOpen = true; editSelectedEmployeeId = ''" 
                                   placeholder="🔍 Search employee stylist..." 
                                   required
                                   class="w-full px-4 py-2.5 bg-purple-50 border border-purple-200 text-sm font-bold text-purple-900 focus:ring-2 focus:ring-purple-600">
                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-purple-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </span>
                        </div>

                        <!-- Filterable Employee Dropdown List -->
                        <div x-show="editEmployeeDropdownOpen" 
                             class="absolute z-30 left-0 right-0 mt-1 bg-white border border-slate-200 shadow-xl max-h-56 overflow-y-auto divide-y divide-slate-100"
                             x-cloak>
                            <template x-for="emp in getEditFilteredEmployees(editEmployeeSearchTerm)" :key="emp.id">
                                <div @click="selectEditEmployee(emp)" 
                                     class="p-2.5 hover:bg-purple-50 cursor-pointer flex items-center justify-between transition-colors text-xs">
                                    <div>
                                        <p class="font-bold text-slate-900" x-text="emp.name"></p>
                                        <p class="text-[10px] text-slate-400" x-text="'Phone: ' + (emp.phone_no1 || 'N/A')"></p>
                                    </div>
                                    <span class="px-2 py-0.5 font-extrabold text-[10px] uppercase rounded" 
                                          :class="emp.emp_type === 'senior' ? 'bg-purple-100 text-purple-900 border border-purple-300' : 'bg-sky-100 text-sky-900 border border-sky-300'" 
                                          x-text="emp.emp_type ? emp.emp_type.toUpperCase() : 'JUNIOR'"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Appointment Date</label>
                        <input type="date" name="appointment_date" x-model="editAppointmentDate" required 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Start Time</label>
                        <input type="time" name="start_time" x-model="editStartTime" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Booking Status</label>
                        <select name="status" x-model="editStatus" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600">
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <!-- FILTERABLE SALOON SERVICES DROPDOWN SECTION -->
                <div class="border border-slate-200 p-4 bg-slate-50 space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                        <div>
                            <h4 class="text-xs font-black uppercase text-slate-900 tracking-wider">Update Saloon Treatments & Services</h4>
                            <p class="text-[11px] text-slate-500">Add or remove service line items for this booking.</p>
                        </div>

                        <button type="button" @click="addEditServiceItem()" 
                                class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-sm transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            <span>+ Add Service Line</span>
                        </button>
                    </div>

                    <!-- Dynamic Service Line Items -->
                    <div class="space-y-3">
                        <template x-for="(item, index) in editItems" :key="index">
                            <div class="flex flex-col sm:flex-row items-center gap-3 p-3 bg-white border border-slate-200 shadow-2xs">
                                <!-- Filterable Service Select Dropdown Component -->
                                <div class="flex-1 w-full relative" @click.outside="item.open = false">
                                    <input type="hidden" name="service_ids[]" :value="item.service_id" required>
                                    
                                    <div class="relative">
                                        <input type="text" 
                                               x-model="item.search_term" 
                                               @focus="item.open = true" 
                                               @input="item.open = true; item.service_id = ''" 
                                               placeholder="🔍 Type service name to filter..." 
                                               required
                                               class="w-full px-3 py-2 pr-8 bg-white border border-slate-200 text-xs font-semibold focus:ring-2 focus:ring-indigo-600">
                                        <span class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-slate-400">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                        </span>
                                    </div>

                                    <!-- Filterable Service List Dropdown -->
                                    <div x-show="item.open" 
                                         class="absolute z-30 left-0 right-0 mt-1 bg-white border border-slate-200 shadow-xl max-h-52 overflow-y-auto divide-y divide-slate-100"
                                         x-cloak>
                                        <template x-for="srv in getFilteredServicesList(item.search_term)" :key="srv.id">
                                            <div @click="selectEditService(index, srv)" 
                                                 class="p-2.5 hover:bg-indigo-50 cursor-pointer flex items-center justify-between transition-colors text-xs">
                                                <div>
                                                    <p class="font-bold text-slate-900" x-text="srv.title"></p>
                                                    <p class="text-[10px] text-purple-700 font-semibold">
                                                        Commission: <span class="font-black" x-text="getEditEmployeeType() === 'senior' ? (srv.senior_commission || srv.commission || '0.00') : (srv.junior_commission || srv.commission || '0.00')"></span> 
                                                        <span class="text-[9px] text-slate-500 font-extrabold uppercase" x-text="'(' + getEditEmployeeType() + ' rate)'"></span>
                                                    </p>
                                                </div>
                                                <div class="text-right">
                                                    <span class="font-extrabold text-indigo-700" x-text="srv.discounted_price || srv.price"></span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <!-- Service Net Price -->
                                <div class="w-full sm:w-28 text-right font-black text-xs text-slate-900">
                                    <span x-text="item.price ? item.price : '0.00'"></span>
                                </div>

                                <!-- Remove Line Button -->
                                <button type="button" @click="removeEditServiceItem(index)" class="p-1.5 text-rose-500 hover:bg-rose-50 transition-colors" title="Remove Line">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </template>
                    </div>

                    <!-- Selected Summary Footer -->
                    <div class="pt-2 flex items-center justify-between text-xs border-t border-slate-200">
                        <span class="font-bold text-slate-600">Total Services Selected: <span class="text-indigo-600 font-black" x-text="editItems.filter(i => i.service_id).length"></span></span>
                        <span class="font-bold text-purple-700">Total Employee Commission: <span class="font-black" x-text="getEditTotalCommission()"></span> <span class="text-[10px] text-slate-500 font-bold uppercase" x-text="'(' + getEditEmployeeType() + ' level)'"></span></span>
                    </div>
                </div>

                <!-- Payment Summary Grid -->
                <div class="p-4 bg-indigo-50/50 border border-indigo-200/80 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Services Gross Total</label>
                        <div class="text-lg font-bold text-slate-700" x-text="getEditGrossTotal()"></div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Appointment Discount</label>
                        <input type="number" step="0.01" min="0" name="discount" x-model.number="editBillDiscount" placeholder="0.00" 
                               class="w-full px-3 py-2 bg-white border border-slate-300 text-sm font-bold text-amber-700 focus:ring-2 focus:ring-indigo-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Payment Mode</label>
                        <select name="payment_mode" x-model="editPaymentMode" class="w-full px-3 py-2 bg-white border border-slate-300 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600">
                            <option value="Cash">Cash</option>
                            <option value="Bank">Bank</option>
                        </select>
                    </div>

                    <div x-show="editPaymentMode === 'Bank'">
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Bank Extra Amount</label>
                        <input type="number" step="0.01" min="0" name="extra_amount" x-model.number="editExtraAmount" placeholder="0.00" 
                               class="w-full px-3 py-2 bg-white border border-slate-300 text-sm font-bold text-indigo-700 focus:ring-2 focus:ring-indigo-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Net Bill Amount</label>
                        <div class="text-xl font-black text-slate-900" x-text="getEditNetTotal()"></div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Payment Received</label>
                        <input type="number" step="0.01" min="0" name="paid_amount" x-model.number="editPaidAmount" required placeholder="0.00" 
                               class="w-full px-3 py-2 bg-white border border-slate-300 text-sm font-bold text-emerald-700 focus:ring-2 focus:ring-indigo-600">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Change Payment Slip / Proof Image (Optional Upload)</label>
                    <input type="file" name="receipt_image" accept="image/*" 
                           class="w-full px-4 py-2 bg-slate-50 border border-slate-200 text-xs font-medium focus:ring-2 focus:ring-indigo-600">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Appointment Notes / Special Instructions</label>
                    <textarea name="notes" rows="2" x-model="editNotes" placeholder="Specific hair products requested, customer sensitivities..." 
                              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600"></textarea>
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="editModalOpen = false" class="px-4 py-2.5 text-slate-600 font-bold text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 bg-amber-600 text-white font-bold text-xs shadow-md hover:bg-amber-700">
                        Update Booking & Synchronize Ledgers
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: VIEW APPOINTMENT SERVICES -->
    <div x-show="viewModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="viewModalOpen = false" class="bg-white max-w-xl w-full p-6 shadow-2xl space-y-6" x-if="selectedAppointment" id="appointment-print-slip">
            
            <div class="pos-receipt-80mm-container pos-receipt-font bg-white p-5 max-w-sm mx-auto shadow-md border border-slate-200">
                <!-- Cursive Receipt Header & Store Info -->
                <div class="text-center pb-2">
                    <h1 class="pos-receipt-cursive text-4xl font-bold text-slate-900 leading-tight">Receipt</h1>
                    <h2 class="text-xs font-bold text-slate-900 uppercase tracking-tight mt-0.5">{{ $appSetting->brand_name }}</h2>
                    @if($appSetting->brand_address)
                        <p class="text-[11px] text-slate-700 leading-tight mt-1">Adress: {{ $appSetting->brand_address }}</p>
                    @endif
                    @if($appSetting->brand_phone1 || $appSetting->brand_phone2)
                        <p class="text-[11px] text-slate-700">Tel: {{ implode(' | ', array_filter([$appSetting->brand_phone1, $appSetting->brand_phone2])) }}</p>
                    @endif
                </div>

                <!-- Dashed Line -->
                <div class="border-t border-dashed border-slate-900 my-2"></div>

                <!-- Date & Time / Booking Info -->
                <div class="flex justify-between text-xs font-bold text-slate-900">
                    <span>Date: <span x-text="selectedAppointment.appointment_date"></span></span>
                    <span>Bk: #<span x-text="selectedAppointment.booking_no"></span></span>
                </div>
                <div class="flex justify-between text-[11px] text-slate-700 font-bold mt-0.5">
                    <span>Client: <span x-text="selectedAppointment.customer ? selectedAppointment.customer.name : 'Walk-in'"></span></span>
                    <span>Stylist: <span x-text="selectedAppointment.employee ? selectedAppointment.employee.name : 'Staff'"></span></span>
                </div>

                <!-- Dashed Line -->
                <div class="border-t border-dashed border-slate-900 my-2"></div>

                <!-- Services List -->
                <div class="space-y-1 text-xs py-1">
                    <template x-for="item in selectedAppointment.items" :key="item.id">
                        <div class="flex justify-between items-start">
                            <span class="font-bold text-slate-900 flex-1 pr-2" x-text="item.service ? item.service.title : 'Saloon Service'"></span>
                            <span class="font-bold text-slate-900" x-text="item.discounted_price"></span>
                        </div>
                    </template>
                </div>

                <!-- Dashed Line -->
                <div class="border-t border-dashed border-slate-900 my-2"></div>

                <!-- Main AMOUNT Row -->
                <div class="flex justify-between items-baseline py-1">
                    <span class="text-sm font-bold uppercase tracking-wider text-slate-900">AMOUNT</span>
                    <span class="text-xl font-bold text-slate-900" x-text="selectedAppointment.net_amount"></span>
                </div>

                <!-- Sub-total & Financial Breakdown -->
                <div class="space-y-1 text-xs pt-1.5 border-t border-slate-200">
                    <div class="flex justify-between text-slate-800">
                        <span>Sub-total</span>
                        <span x-text="selectedAppointment.total_amount"></span>
                    </div>
                    <div class="flex justify-between text-slate-800" x-show="selectedAppointment.discount > 0">
                        <span>Discount</span>
                        <span x-text="selectedAppointment.discount"></span>
                    </div>
                    <div class="flex justify-between text-slate-800">
                        <span>Paid</span>
                        <span x-text="selectedAppointment.paid_amount"></span>
                    </div>
                    <div class="flex justify-between font-bold text-slate-900" x-show="selectedAppointment.balance_due > 0">
                        <span>Balance</span>
                        <span x-text="selectedAppointment.balance_due"></span>
                    </div>
                </div>

                <!-- Dashed Line -->
                <div class="border-t border-dashed border-slate-900 my-3"></div>

                <!-- Barcode Representation -->
                <div class="text-center space-y-1">
                    <div class="font-mono text-base tracking-tighter text-slate-900 leading-none overflow-hidden select-none">
                        ||| | ||||| || |||||| | |||| ||| ||||||| | |||||| |||||
                    </div>
                    <p class="text-[10px] text-slate-600 font-bold uppercase tracking-wider pt-1">Thank You For Booking With Us</p>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" @click="viewModalOpen = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs">
                    Close
                </button>
                <button type="button" onclick="window.print()" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    <span>Print Receipt</span>
                </button>
            </div>
        </div>
    </div>

</div>
@endsection
