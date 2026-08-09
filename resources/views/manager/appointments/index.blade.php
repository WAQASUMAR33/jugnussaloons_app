@extends('layouts.material')

@section('title', 'Service Appointment Booking')

@section('content')
<div x-data="{ 
    createModalOpen: false,
    viewModalOpen: false,
    selectedAppointment: null,
    saloonServices: {{ json_encode($saloonServices) }},
    items: [
        { service_id: '', search_term: '', open: false, price: 0, commission: 0 }
    ],
    billDiscount: 0,
    paidAmount: 0,
    
    addServiceItem() {
        this.items.push({ service_id: '', search_term: '', open: false, price: 0, commission: 0 });
    },
    
    removeServiceItem(index) {
        if (this.items.length > 1) {
            this.items.splice(index, 1);
        }
    },
    
    selectService(index, srv) {
        this.items[index].service_id = srv.id;
        this.items[index].search_term = srv.title;
        this.items[index].price = parseFloat(srv.discounted_price || srv.price);
        this.items[index].commission = parseFloat(srv.commission || 0);
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
        return Math.max(0, gross - discount).toFixed(2);
    },
    
    getBalanceDue() {
        const net = parseFloat(this.getNetTotal()) || 0;
        const paid = parseFloat(this.paidAmount) || 0;
        return (net - paid).toFixed(2);
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

    <!-- Search & Filters -->
    <div class="bg-white p-4 border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <form method="GET" action="{{ route('manager.appointments.index') }}" class="flex-1 grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div class="relative flex-1">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search booking #, customer, stylist..." 
                       class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600 focus:bg-white transition-all">
            </div>

            <select name="status" class="py-2.5 px-4 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-indigo-600">
                <option value="">All Booking Statuses</option>
                <option value="pending" {{ $status == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="confirmed" {{ $status == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                <option value="completed" {{ $status == 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="cancelled" {{ $status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>

            <input type="date" name="date" value="{{ $date }}" class="py-2.5 px-4 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">

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
                        <th class="py-4 px-4">Customer</th>
                        <th class="py-4 px-4">Employee (Stylist)</th>
                        <th class="py-4 px-4">Date & Time</th>
                        <th class="py-4 px-4">Total Payment</th>
                        <th class="py-4 px-4">Bill Discount</th>
                        <th class="py-4 px-4">Net Amount</th>
                        <th class="py-4 px-4">Paid</th>
                        <th class="py-4 px-4">Remaining</th>
                        <th class="py-4 px-4">Receipt</th>
                        <th class="py-4 px-4">Status</th>
                        <th class="py-4 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($appointments as $apt)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="py-4 px-4 font-mono font-bold text-xs text-indigo-700">
                            {{ $apt->booking_no }}
                        </td>
                        <td class="py-4 px-4">
                            <p class="font-bold text-slate-900 leading-tight">{{ $apt->customer->name ?? 'Walk-in Customer' }}</p>
                            <span class="text-[10px] text-slate-400 font-medium">{{ $apt->customer->phone_no1 ?? '' }}</span>
                        </td>
                        <td class="py-4 px-4">
                            <span class="px-2.5 py-1 bg-purple-100 text-purple-800 text-xs font-bold flex items-center gap-1.5 w-fit">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                {{ $apt->employee->name ?? 'Unassigned' }}
                            </span>
                            <span class="text-[10px] text-slate-400 font-bold block mt-0.5">Comm: {{ number_format($apt->total_commission, 2) }}</span>
                        </td>
                        <td class="py-4 px-4 text-xs text-slate-600 font-semibold">
                            {{ $apt->appointment_date->format('M d, Y') }}
                            @if($apt->start_time)
                                <span class="text-slate-400 block font-normal">{{ $apt->start_time }}</span>
                            @endif
                        </td>
                        <td class="py-4 px-4 font-extrabold text-slate-700">
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
                        <td colspan="12" class="py-12 text-center text-slate-400">
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
                
                <!-- Primary Assignment Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Select Customer Account</label>
                        <select name="account_id" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600">
                            <option value="">Select Customer Account</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}">
                                    {{ $customer->name }} (Category: {{ $customer->category->title ?? 'General' }} | Balance: {{ number_format($customer->balance, 2) }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-purple-800 uppercase mb-1">Assigned Employee / Stylist (Employee Category)</label>
                        <select name="employee_id" required class="w-full px-4 py-2.5 bg-purple-50 border border-purple-200 text-sm font-bold text-purple-900 focus:ring-2 focus:ring-purple-600">
                            <option value="">Select Employee Stylist</option>
                            @forelse($employees as $employee)
                                <option value="{{ $employee->id }}">
                                    👤 {{ $employee->name }} (Category: {{ $employee->category->title ?? 'Employee' }})
                                </option>
                            @empty
                                <option value="" disabled class="text-rose-600 font-bold">⚠️ No Employee accounts found (Create an account with category 'Employee')</option>
                            @endforelse
                        </select>
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
                                                    <p class="text-[10px] text-purple-600 font-semibold">Commission: <span x-text="srv.commission"></span></p>
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

                                <!-- Staff Commission -->
                                <div class="w-full sm:w-28 text-right font-extrabold text-xs text-purple-700">
                                    <span x-text="item.commission ? 'Comm: ' + item.commission : '0.00'"></span>
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
                        <span class="font-bold text-purple-700">Total Employee Commission: <span class="font-black" x-text="getTotalCommission()"></span></span>
                    </div>
                </div>

                <!-- Payment Summary Grid -->
                <div class="p-4 bg-indigo-50/50 border border-indigo-200/80 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
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
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Net Bill Amount</label>
                        <div class="text-xl font-black text-slate-900" x-text="getNetTotal()"></div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Payment Received</label>
                        <input type="number" step="0.01" min="0" name="paid_amount" x-model.number="paidAmount" required placeholder="0.00" 
                               class="w-full px-3 py-2 bg-white border border-slate-300 text-sm font-bold text-emerald-700 focus:ring-2 focus:ring-indigo-600">
                    </div>
                </div>

                <div class="p-3 bg-rose-50 border border-rose-200/80 flex items-center justify-between">
                    <span class="text-xs font-bold text-rose-800 uppercase">Balance Due / Pending Credit</span>
                    <span class="text-xl font-black text-rose-700" x-text="getBalanceDue()"></span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Special Instructions / Notes (Optional)</label>
                        <textarea name="notes" rows="2" placeholder="Styling preferences, allergy warnings..." 
                                  class="w-full px-4 py-2 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-indigo-600"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Payment Receipt Image (Upload to Server)</label>
                        <input type="file" name="receipt_image" accept="image/*,.pdf" 
                               class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-medium focus:ring-2 focus:ring-indigo-600">
                        <span class="text-[10px] text-slate-400 font-semibold mt-1 block">Upload bank receipt, slip or payment screenshot to store image URL in database.</span>
                    </div>
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

            <div class="pt-4 flex flex-wrap justify-between items-center gap-2 border-t border-slate-100 print:hidden">
                <div class="flex items-center gap-2">
                    <button type="button" @click="print80mmPOSReceipt('appointment-print-slip')" 
                            class="px-4 py-2 bg-emerald-600 text-white font-extrabold text-xs shadow-xs hover:bg-emerald-700 flex items-center gap-1.5">
                        <span>🧾 Print 80mm POS Thermal Slip</span>
                    </button>
                    <button type="button" @click="window.print()" 
                            class="px-4 py-2 bg-indigo-600 text-white font-extrabold text-xs shadow-xs hover:bg-indigo-700 flex items-center gap-1.5">
                        <span>📄 Print A4 Slip</span>
                    </button>
                </div>

                <button type="button" @click="viewModalOpen = false" class="px-5 py-2 bg-slate-900 text-white font-bold text-xs">
                    Close
                </button>
            </div>
        </div>
    </div>

</div>
@endsection
