@extends('layouts.material')

@section('title', 'Manager Panel')

@section('content')
<div class="space-y-6">

    <!-- Birthday & Anniversary Celebrations Popup Alert -->
    @include('partials.celebrations-modal')

    <!-- Header Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs">
        <div class="flex items-center gap-3.5">
            <div class="p-3 rounded-xl bg-amber-50 text-amber-600">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight heading-font">Saloon Operations Hub</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">Manage daily salon workflows, staff schedules, and client bookings.</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('manager.appointments.index') }}" 
               class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>New Booking</span>
            </a>
            <a href="{{ route('manager.sales.index') }}" 
               class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                <span>POS Sale</span>
            </a>
        </div>
    </div>

    <!-- Manager Operations Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md transition-all space-y-4 flex flex-col justify-between">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 0A3 3 0 104.5 4.5a3 3 0 004.621 4.621zm0 0L12 12m-2.879 2.879a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243z"></path></svg>
                    </div>
                    <span class="px-2.5 py-1 bg-indigo-50 text-indigo-700 text-xs font-black rounded-lg border border-indigo-200/60">
                        {{ $servicesCount ?? 0 }} Services
                    </span>
                </div>
                <h3 class="text-lg font-extrabold text-slate-900 heading-font">Saloon Services</h3>
                <p class="text-xs text-slate-500 font-medium leading-relaxed">Configure saloon haircutting, coloring, beard grooming, and facial packages.</p>
            </div>
            <a href="{{ route('manager.services.index') }}" class="block w-full text-center py-2.5 bg-indigo-50 text-indigo-700 font-bold text-xs rounded-xl hover:bg-indigo-100 transition-colors">
                Manage Services Catalog &rarr;
            </a>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md transition-all space-y-4 flex flex-col justify-between">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 text-xs font-black rounded-lg border border-emerald-200/60">
                        {{ $appointmentsCount ?? 0 }} Bookings
                    </span>
                </div>
                <h3 class="text-lg font-extrabold text-slate-900 heading-font">Appointment Bookings</h3>
                <p class="text-xs text-slate-500 font-medium leading-relaxed">View and reschedule customer appointments and assign available stylists.</p>
            </div>
            <a href="{{ route('manager.appointments.index') }}" class="block w-full text-center py-2.5 bg-emerald-50 text-emerald-700 font-bold text-xs rounded-xl hover:bg-emerald-100 transition-colors">
                View Schedule Calendar &rarr;
            </a>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md transition-all space-y-4 flex flex-col justify-between">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    </div>
                    <span class="px-2.5 py-1 bg-purple-50 text-purple-700 text-xs font-black rounded-lg border border-purple-200/60">
                        PKR {{ number_format($todaySales ?? 0, 2) }}
                    </span>
                </div>
                <h3 class="text-lg font-extrabold text-slate-900 heading-font">Reports & Analytics</h3>
                <p class="text-xs text-slate-500 font-medium leading-relaxed">Track daily saloon sales, staff performance metrics, and customer feedback.</p>
            </div>
            <a href="{{ route('manager.reports.sales') }}" class="block w-full text-center py-2.5 bg-purple-50 text-purple-700 font-bold text-xs rounded-xl hover:bg-purple-100 transition-colors">
                Generate Sales Report &rarr;
            </a>
        </div>

    </div>

</div>
@endsection
