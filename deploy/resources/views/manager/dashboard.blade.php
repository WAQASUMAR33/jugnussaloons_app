@extends('layouts.material')

@section('title', 'Manager Panel')

@section('content')
<div class="space-y-6">

    <!-- Header Banner - Sharp Corners -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="p-3 bg-amber-50 text-amber-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Saloon Manager Control Panel</h1>
                <p class="text-xs font-semibold text-slate-500">Manage daily saloon operations, staff rosters, and client appointments.</p>
            </div>
        </div>
    </div>

    <!-- Manager Operations Cards - Sharp Corners -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <div class="bg-white p-6 border border-slate-200 shadow-sm space-y-3">
            <div class="w-12 h-12 bg-indigo-50 text-indigo-600 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 0A3 3 0 104.5 4.5a3 3 0 004.621 4.621zm0 0L12 12m-2.879 2.879a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243z"></path></svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900">Saloon Services</h3>
            <p class="text-xs text-slate-500 font-medium">Configure saloon haircutting, coloring, beard grooming, and facial packages.</p>
            <button class="w-full py-2.5 bg-indigo-50 text-indigo-700 font-bold text-xs hover:bg-indigo-100 transition-colors">
                Manage Services Catalog
            </button>
        </div>

        <div class="bg-white p-6 border border-slate-200 shadow-sm space-y-3">
            <div class="w-12 h-12 bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900">Appointment Bookings</h3>
            <p class="text-xs text-slate-500 font-medium">View and reschedule customer appointments and assign available stylists.</p>
            <button class="w-full py-2.5 bg-emerald-50 text-emerald-700 font-bold text-xs hover:bg-emerald-100 transition-colors">
                View Schedule Calendar
            </button>
        </div>

        <div class="bg-white p-6 border border-slate-200 shadow-sm space-y-3">
            <div class="w-12 h-12 bg-purple-50 text-purple-600 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900">Reports & Analytics</h3>
            <p class="text-xs text-slate-500 font-medium">Track daily saloon sales, staff performance metrics, and customer feedback.</p>
            <button class="w-full py-2.5 bg-purple-50 text-purple-700 font-bold text-xs hover:bg-purple-100 transition-colors">
                Generate Sales Report
            </button>
        </div>

    </div>

</div>
@endsection
