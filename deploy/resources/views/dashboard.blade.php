@extends('layouts.material')

@section('title', 'Salon Overview & Dashboard')

@section('content')
<div class="space-y-6">

    <!-- Birthday & Anniversary Celebrations Popup Alert -->
    @include('partials.celebrations-modal')
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        
        <!-- Total Revenue -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs hover:shadow-md transition-all group">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Today's Revenue</span>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
            <div class="flex items-baseline justify-between">
                <h3 class="text-2xl font-black text-slate-900">PKR {{ number_format($todayRevenue, 2) }}</h3>
            </div>
            <div class="mt-2 flex items-center justify-between text-xs">
                @if($revenueGrowth >= 0)
                    <span class="inline-flex items-center gap-1 font-extrabold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-md">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                        +{{ $revenueGrowth }}%
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 font-extrabold text-rose-600 bg-rose-50 px-2 py-0.5 rounded-md">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6"></path></svg>
                        {{ $revenueGrowth }}%
                    </span>
                @endif
                <span class="text-slate-400 font-medium">vs yesterday</span>
            </div>
        </div>

        <!-- Appointments -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs hover:shadow-md transition-all group">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Appointments</span>
                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
            </div>
            <div class="flex items-baseline justify-between">
                <h3 class="text-2xl font-black text-slate-900">{{ $todayAppointmentsCount }} <span class="text-sm font-semibold text-slate-500">Today</span></h3>
                <span class="text-xs font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md">Active</span>
            </div>
            <div class="mt-2 text-xs text-slate-400 font-medium flex items-center justify-between">
                <span>{{ $pendingAppointmentsCount }} pending approval</span>
                <span class="font-bold text-slate-600">{{ $totalAppointmentsCount }} total</span>
            </div>
        </div>

        <!-- Registered Clients -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs hover:shadow-md transition-all group">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Clients / Customers</span>
                <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </div>
            </div>
            <div class="flex items-baseline justify-between">
                <h3 class="text-2xl font-black text-slate-900">{{ $totalCustomersCount }} <span class="text-sm font-semibold text-slate-500">Total</span></h3>
                <span class="text-xs font-bold text-purple-600 bg-purple-50 px-2 py-0.5 rounded-md">+{{ $newCustomersThisWeekCount }} this wk</span>
            </div>
            <div class="mt-2 text-xs text-slate-400 font-medium">
                <span>Active customer base</span>
            </div>
        </div>

        <!-- Stylists & Staff -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs hover:shadow-md transition-all group">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Active Staff</span>
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 012-2h2a2 2 0 012 2v1m-6 0h6"></path></svg>
                </div>
            </div>
            <div class="flex items-baseline justify-between">
                <h3 class="text-2xl font-black text-slate-900">{{ $activeStaffCount }} <span class="text-sm font-semibold text-slate-500">Staff</span></h3>
                <span class="text-xs font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-md">On Duty</span>
            </div>
            <div class="mt-2 text-xs text-slate-400 font-medium">
                <span>Salon operational</span>
            </div>
        </div>

    </div>

    <!-- Main Grid Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Appointments Schedule -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200/80 p-6 shadow-xs space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h3 class="text-base font-black text-slate-900 heading-font">
                        {{ $isFallbackSchedule ? 'Recent Saloon Appointments' : "Today's Appointment Schedule" }}
                    </h3>
                    <p class="text-xs text-slate-500 font-medium mt-0.5">
                        {{ $isFallbackSchedule ? 'Showing latest recorded bookings from database' : 'Real-time schedule for today\'s active salon services' }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('manager.appointments.index') }}" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition-colors shadow-xs">
                        View Calendar &rarr;
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-400 text-[10px] font-black uppercase tracking-wider border-b border-slate-100 rounded-xl">
                            <th class="py-3 px-4 rounded-l-xl">Time / Date</th>
                            <th class="py-3 px-4">Client</th>
                            <th class="py-3 px-4">Treatment</th>
                            <th class="py-3 px-4">Stylist</th>
                            <th class="py-3 px-4 text-right rounded-r-xl">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        @forelse($todayAppointments as $apt)
                        <tr class="hover:bg-indigo-50/40 transition-colors">
                            <td class="py-3.5 px-4 font-bold text-slate-900">
                                @if($apt->start_time)
                                    <span class="text-indigo-600 font-extrabold">{{ date('h:i A', strtotime($apt->start_time)) }}</span>
                                    <span class="block text-[10px] text-slate-400 font-medium">{{ $apt->appointment_date ? $apt->appointment_date->format('M d, Y') : '' }}</span>
                                @else
                                    <span>{{ $apt->appointment_date ? $apt->appointment_date->format('M d, Y') : '—' }}</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-lg bg-gradient-to-tr from-indigo-600 to-purple-600 text-white font-black flex items-center justify-center text-[10px] shadow-xs shrink-0">
                                        {{ strtoupper(substr($apt->customer->name ?? 'WC', 0, 2)) }}
                                    </div>
                                    <div>
                                        <span class="font-bold text-slate-800 block">{{ $apt->customer->name ?? 'Walk-in Client' }}</span>
                                        <span class="text-[10px] text-slate-400 font-mono">#{{ $apt->booking_no }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-slate-600 font-semibold max-w-[150px] truncate">
                                {{ $apt->items->map(fn($item) => $item->service->title ?? 'Service')->implode(', ') ?: 'Saloon Treatment' }}
                            </td>
                            <td class="py-3.5 px-4 text-slate-600 font-medium">
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[11px] font-semibold">
                                    👤 {{ $apt->employee->name ?? 'Unassigned' }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                @if($apt->status == 'completed')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 font-black text-[10px] uppercase border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Completed
                                    </span>
                                @elseif($apt->status == 'confirmed')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 font-black text-[10px] uppercase border border-blue-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                        Confirmed
                                    </span>
                                @elseif($apt->status == 'pending')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 font-black text-[10px] uppercase border border-amber-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                        Pending
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-rose-50 text-rose-700 font-black text-[10px] uppercase border border-rose-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                        Cancelled
                                    </span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-slate-400">
                                <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </div>
                                <p class="text-sm font-bold text-slate-700">No salon appointment bookings yet</p>
                                <p class="text-xs text-slate-400 mt-1">Book customer treatments directly into the live calendar.</p>
                                <a href="{{ route('manager.appointments.index') }}" class="inline-flex items-center gap-2 mt-4 px-4 py-2 bg-indigo-600 text-white font-bold text-xs rounded-xl hover:bg-indigo-700 transition-colors shadow-sm">
                                    + Book First Appointment
                                </a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Workflow Actions & Popular Services -->
        <div class="space-y-6">
            
            <!-- Quick Actions Hub -->
            <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-xs space-y-4">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Quick Actions</h3>
                
                <div class="space-y-2.5">
                    <a href="{{ route('manager.appointments.index') }}" 
                       class="w-full flex items-center justify-between p-3.5 rounded-xl bg-gradient-to-r from-indigo-50 to-purple-50 hover:from-indigo-100 hover:to-purple-100 text-indigo-900 font-bold text-xs transition-all border border-indigo-100/80 group">
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-lg bg-indigo-600 text-white shadow-xs group-hover:scale-105 transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                            <span>Book New Appointment</span>
                        </div>
                        <svg class="w-4 h-4 text-indigo-400 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </a>

                    @if(Auth::user()->hasAnyRole(['admin', 'manager']))
                    <a href="{{ route('manager.sales.index') }}" 
                       class="w-full flex items-center justify-between p-3.5 rounded-xl bg-gradient-to-r from-emerald-50 to-teal-50 hover:from-emerald-100 hover:to-teal-100 text-emerald-900 font-bold text-xs transition-all border border-emerald-100/80 group">
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-lg bg-emerald-600 text-white shadow-xs group-hover:scale-105 transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            </div>
                            <span>Point of Sale (POS)</span>
                        </div>
                        <svg class="w-4 h-4 text-emerald-400 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </a>

                    <a href="{{ route('manager.accounts.index') }}" 
                       class="w-full flex items-center justify-between p-3.5 rounded-xl bg-gradient-to-r from-purple-50 to-indigo-50 hover:from-purple-100 hover:to-indigo-100 text-purple-900 font-bold text-xs transition-all border border-purple-100/80 group">
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-lg bg-purple-600 text-white shadow-xs group-hover:scale-105 transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                            </div>
                            <span>Add Customer Account</span>
                        </div>
                        <svg class="w-4 h-4 text-purple-400 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </a>

                    <a href="{{ route('manager.expenses.index') }}" 
                       class="w-full flex items-center justify-between p-3.5 rounded-xl bg-gradient-to-r from-rose-50 to-orange-50 hover:from-rose-100 hover:to-orange-100 text-rose-900 font-bold text-xs transition-all border border-rose-100/80 group">
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-lg bg-rose-600 text-white shadow-xs group-hover:scale-105 transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            </div>
                            <span>Record Daily Expense</span>
                        </div>
                        <svg class="w-4 h-4 text-rose-400 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </a>
                    @endif
                </div>
            </div>

            <!-- Top Salon Services Popularity Widget -->
            <div class="bg-white rounded-2xl border border-slate-200/80 p-6 space-y-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Popular Services</h3>
                    <span class="text-xs font-extrabold text-indigo-600">★ High Demand</span>
                </div>

                <div class="space-y-3.5 text-xs">
                    @php 
                        $gradients = [
                            'from-indigo-600 to-purple-600',
                            'from-purple-600 to-pink-600',
                            'from-amber-500 to-orange-500',
                            'from-emerald-500 to-teal-500'
                        ]; 
                    @endphp
                    @forelse($popularServices as $index => $service)
                        @php $grad = $gradients[$index % count($gradients)]; @endphp
                        <div class="space-y-1.5">
                            <div class="flex justify-between items-center font-bold text-slate-800">
                                <span class="truncate max-w-[180px]">{{ $service['title'] }}</span>
                                <span class="text-slate-900 font-black px-2 py-0.5 rounded-md bg-slate-100 text-[11px]">{{ $service['percentage'] }}%</span>
                            </div>
                            <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                                <div class="bg-gradient-to-r {{ $grad }} h-full rounded-full transition-all duration-500" style="width: {{ $service['percentage'] }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-slate-400 text-xs font-medium">No salon services recorded yet.</p>
                    @endforelse
                </div>
            </div>

        </div>

    </div>

</div>
@endsection
