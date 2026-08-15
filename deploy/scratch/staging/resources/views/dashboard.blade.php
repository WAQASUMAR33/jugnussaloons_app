@extends('layouts.material')

@section('title', 'Saloon Overview & Dashboard')

@section('content')
<div class="space-y-6">

    <!-- Welcome Hero Banner - Sharp Corners -->
    <div class="relative overflow-hidden bg-indigo-600 p-8 text-white shadow-md">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div class="space-y-2">
                <div class="inline-flex items-center gap-2 px-3 py-1 bg-white/20 text-xs font-bold uppercase tracking-wider">
                    <svg class="w-4 h-4 text-amber-300" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                    <span>Material Light Suite</span>
                </div>
                <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight">Welcome back, {{ Auth::user()->name }}! 👋</h1>
                <p class="text-sm text-indigo-100 max-w-xl font-medium">
                    Here is what's happening at Jugnu Saloon today. You are signed in with 
                    <strong class="underline decoration-indigo-300">{{ Auth::user()->roles->first()->name ?? 'User' }}</strong> access level.
                </p>
            </div>

            <div class="flex items-center gap-3">
                @if(Auth::user()->hasRole('admin'))
                    <a href="{{ route('admin.users.index') }}" 
                       class="px-5 py-3 bg-white text-indigo-700 font-extrabold text-xs shadow-md hover:bg-indigo-50 transition-all flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        <span>Manage Users</span>
                    </a>
                @endif
                <a href="{{ route('profile.edit') }}" 
                   class="px-5 py-3 bg-indigo-500/50 hover:bg-indigo-500/70 text-white font-bold text-xs border border-white/20 transition-all">
                    <span>Edit Profile</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Stat KPI Cards - Sharp Corners -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        
        <!-- Total Revenue -->
        <div class="bg-white p-6 border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Today's Revenue</span>
                <div class="p-2.5 bg-emerald-50 text-emerald-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
            <div class="flex items-baseline justify-between">
                <h3 class="text-2xl font-extrabold text-slate-900">1,480.00</h3>
                <span class="text-xs font-bold text-emerald-600 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                    +14%
                </span>
            </div>
            <p class="text-xs text-slate-400 font-medium mt-1">vs yesterday (1,290)</p>
        </div>

        <!-- Appointments -->
        <div class="bg-white p-6 border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Appointments</span>
                <div class="p-2.5 bg-indigo-50 text-indigo-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
            </div>
            <div class="flex items-baseline justify-between">
                <h3 class="text-2xl font-extrabold text-slate-900">24 Bookings</h3>
                <span class="text-xs font-bold text-indigo-600 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Active
                </span>
            </div>
            <p class="text-xs text-slate-400 font-medium mt-1">6 pending confirm</p>
        </div>

        <!-- Registered Clients -->
        <div class="bg-white p-6 border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Clients / Customers</span>
                <div class="p-2.5 bg-purple-50 text-purple-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </div>
            </div>
            <div class="flex items-baseline justify-between">
                <h3 class="text-2xl font-extrabold text-slate-900">142 Total</h3>
                <span class="text-xs font-bold text-purple-600 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                    +8 this week
                </span>
            </div>
            <p class="text-xs text-slate-400 font-medium mt-1">Active customer base</p>
        </div>

        <!-- Stylists & Staff -->
        <div class="bg-white p-6 border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Active Staff</span>
                <div class="p-2.5 bg-amber-50 text-amber-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 012-2h2a2 2 0 012 2v1m-6 0h6"></path></svg>
                </div>
            </div>
            <div class="flex items-baseline justify-between">
                <h3 class="text-2xl font-extrabold text-slate-900">8 Stylists</h3>
                <span class="text-xs font-bold text-amber-600 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 0A3 3 0 104.5 4.5a3 3 0 004.621 4.621zm0 0L12 12m-2.879 2.879a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243z"></path></svg>
                    On Duty
                </span>
            </div>
            <p class="text-xs text-slate-400 font-medium mt-1">Saloon operational</p>
        </div>

    </div>

    <!-- Main Grid Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Today's Schedule Table - Sharp Corners -->
        <div class="lg:col-span-2 bg-white border border-slate-200 p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-extrabold text-slate-900">Today's Saloon Appointments</h3>
                    <p class="text-xs text-slate-500 font-medium">Real-time schedule for active customer services</p>
                </div>
                <span class="px-3 py-1 bg-indigo-50 text-indigo-700 text-xs font-bold">Live Updates</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-400 text-[11px] font-extrabold uppercase border-b border-slate-100">
                            <th class="py-3 px-4">Time</th>
                            <th class="py-3 px-4">Customer</th>
                            <th class="py-3 px-4">Service</th>
                            <th class="py-3 px-4">Stylist</th>
                            <th class="py-3 px-4 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-medium">
                        <tr>
                            <td class="py-3.5 px-4 font-bold text-slate-900">10:00 AM</td>
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-indigo-600 text-white font-bold flex items-center justify-center text-[10px]">SJ</div>
                                    <span class="font-bold text-slate-800">Sarah Johnson</span>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-slate-600">Hair Coloring & Blowdry</td>
                            <td class="py-3.5 px-4 text-slate-600">Elena Rostova</td>
                            <td class="py-3.5 px-4 text-right">
                                <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 font-bold text-[10px]">Completed</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="py-3.5 px-4 font-bold text-slate-900">11:30 AM</td>
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-purple-600 text-white font-bold flex items-center justify-center text-[10px]">JD</div>
                                    <span class="font-bold text-slate-800">John Doe</span>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-slate-600">Beard Trim & Facial</td>
                            <td class="py-3.5 px-4 text-slate-600">Marcus Vance</td>
                            <td class="py-3.5 px-4 text-right">
                                <span class="px-2.5 py-1 bg-indigo-100 text-indigo-800 font-bold text-[10px]">In Progress</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="py-3.5 px-4 font-bold text-slate-900">02:00 PM</td>
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-amber-600 text-white font-bold flex items-center justify-center text-[10px]">AM</div>
                                    <span class="font-bold text-slate-800">Alice Miller</span>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-slate-600">Keratin Treatment</td>
                            <td class="py-3.5 px-4 text-slate-600">Elena Rostova</td>
                            <td class="py-3.5 px-4 text-right">
                                <span class="px-2.5 py-1 bg-amber-100 text-amber-800 font-bold text-[10px]">Confirmed</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Actions (Right Col) - Sharp Corners -->
        <div class="space-y-6">
            
            <div class="bg-white border border-slate-200 p-6 shadow-sm space-y-4">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Quick Actions</h3>
                
                <div class="space-y-2">
                    @if(Auth::user()->hasRole('admin'))
                    <a href="{{ route('admin.users.index') }}" 
                       class="w-full flex items-center justify-between p-3.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-xs transition-colors">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                            <span>Add New User / Customer</span>
                        </div>
                        <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </a>
                    @endif

                    <a href="{{ route('profile.edit') }}" 
                       class="w-full flex items-center justify-between p-3.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs transition-colors">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            <span>Update Profile Info</span>
                        </div>
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </a>
                </div>
            </div>

            <!-- Top Services Card - Sharp Corners -->
            <div class="bg-white border border-slate-200 p-6 space-y-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Popular Services</h3>
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 0A3 3 0 104.5 4.5a3 3 0 004.621 4.621zm0 0L12 12m-2.879 2.879a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243z"></path></svg>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <div class="flex justify-between font-bold mb-1 text-slate-800">
                            <span>Haircut & Styling</span>
                            <span class="text-indigo-600">42%</span>
                        </div>
                        <div class="w-full h-2 bg-slate-100 overflow-hidden">
                            <div class="bg-indigo-600 h-full" style="width: 42%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between font-bold mb-1 text-slate-800">
                            <span>Beard Grooming</span>
                            <span class="text-purple-600">28%</span>
                        </div>
                        <div class="w-full h-2 bg-slate-100 overflow-hidden">
                            <div class="bg-purple-600 h-full" style="width: 28%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between font-bold mb-1 text-slate-800">
                            <span>Hair Color Treatment</span>
                            <span class="text-amber-600">30%</span>
                        </div>
                        <div class="w-full h-2 bg-slate-100 overflow-hidden">
                            <div class="bg-amber-500 h-full" style="width: 30%"></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>

</div>
@endsection
