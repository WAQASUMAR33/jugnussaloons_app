<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'Jugnu Saloon')) - Material Suite</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Courier+Prime:ital,wght@0,400;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Scripts & Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine JS fallback -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .pos-receipt-font {
            font-family: 'Courier Prime', 'Courier New', Courier, monospace !important;
        }
        .pos-receipt-cursive {
            font-family: 'Dancing Script', cursive, sans-serif !important;
        }
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
        }

        @media print {
            aside, header, nav, .print\:hidden, form, button, .no-print, [x-cloak] {
                display: none !important;
            }
            body, html, main, div, table {
                background: #ffffff !important;
                color: #000000 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }
            main {
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }
            .print\:block {
                display: block !important;
            }
            .print\:flex {
                display: flex !important;
            }
            table {
                width: 100% !important;
                border-collapse: collapse !important;
            }
            th, td {
                border: 1px solid #e2e8f0 !important;
                padding: 6px 8px !important;
                font-size: 11px !important;
            }
            th {
                background-color: #f8fafc !important;
                color: #000000 !important;
                font-weight: 800 !important;
            }
            @page {
                size: portrait;
                margin: 12mm 15mm;
            }

            body.pos-receipt-active @page {
                size: 80mm auto !important;
                margin: 0mm !important;
            }
            body.pos-receipt-active .pos-receipt-80mm-container {
                width: 76mm !important;
                max-width: 76mm !important;
                margin: 0 auto !important;
                padding: 2mm !important;
                font-family: 'Courier New', Courier, monospace, monospace !important;
                font-size: 11px !important;
                color: #000000 !important;
            }
            body.pos-receipt-active .pos-receipt-80mm-container table,
            body.pos-receipt-active .pos-receipt-80mm-container td,
            body.pos-receipt-active .pos-receipt-80mm-container th {
                border: none !important;
                padding: 2px 0 !important;
                font-size: 11px !important;
            }
        }
    </style>
    <script>
        function print80mmPOSReceipt(containerId) {
            document.body.classList.add('pos-receipt-active');
            window.print();
            setTimeout(function() {
                document.body.classList.remove('pos-receipt-active');
            }, 1000);
        }
    </script>
</head>
<body class="h-full antialiased text-slate-800 bg-slate-50" x-data="{ sidebarOpen: false }">
    <div class="min-h-screen flex bg-slate-50">
        
        <!-- Mobile Sidebar Overlay -->
        <div x-show="sidebarOpen" 
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="sidebarOpen = false" 
             class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm lg:hidden" 
             x-cloak></div>

        <!-- Light Sidebar Navigation Drawer - Sharp Corners -->
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-50 w-72 bg-white border-r border-slate-200 transform transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0 flex flex-col shadow-sm lg:shadow-none">
            
            <!-- Brand Header -->
            <div class="h-20 px-6 flex items-center justify-between border-b border-slate-200/80 bg-gradient-to-r from-indigo-700 via-indigo-600 to-purple-700 text-white shadow-sm">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 group">
                    @if($appSetting->brand_logo)
                        <div class="w-10 h-10 bg-white p-1 border border-white/20 flex items-center justify-center shadow-inner group-hover:scale-105 transition-transform duration-200 overflow-hidden shrink-0">
                            <img src="{{ asset($appSetting->brand_logo) }}" alt="Logo" class="w-full h-full object-contain">
                        </div>
                    @else
                        <div class="w-10 h-10 bg-white/15 backdrop-blur-md border border-white/20 flex items-center justify-center text-white shadow-inner group-hover:scale-105 transition-transform duration-200 shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 0A3 3 0 104.5 4.5a3 3 0 004.621 4.621zm0 0L12 12m-2.879 2.879a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243z"></path></svg>
                        </div>
                    @endif
                    <div class="overflow-hidden">
                        <h1 class="font-black text-lg tracking-tight leading-tight truncate max-w-[140px]">{{ $appSetting->brand_name }}</h1>
                        <p class="text-[10px] text-indigo-100/90 font-bold tracking-widest uppercase truncate max-w-[140px]">{{ $appSetting->brand_slogan ?: 'Executive Suite' }}</p>
                    </div>
                </a>
                <button @click="sidebarOpen = false" class="lg:hidden text-white/80 hover:text-white p-1">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Current User Profile Card -->
            <div class="p-3.5 mx-3.5 mt-4 bg-gradient-to-r from-slate-50 to-indigo-50/30 border border-slate-200/80 flex items-center justify-between shadow-2xs">
                <div class="flex items-center gap-3 overflow-hidden">
                    <div class="w-9 h-9 bg-gradient-to-br from-indigo-600 to-purple-600 text-white flex items-center justify-center font-black text-xs shadow-xs shrink-0">
                        {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 2)) }}
                    </div>
                    <div class="overflow-hidden">
                        <h4 class="font-extrabold text-xs text-slate-900 truncate leading-tight">{{ Auth::user()->name }}</h4>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                                {{ Auth::user()->roles->first()->name ?? 'User' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation Links with Beautiful Collapsible Main Menu Topics & Sub-Menus -->
            <nav class="flex-1 px-3 py-4 space-y-2.5 overflow-y-auto" 
                 x-data="{ 
                     menuQuery: '',
                     openMenus: {
                         dashboards: true,
                         services: {{ request()->routeIs('manager.services.*') || request()->routeIs('manager.service-categories.*') || request()->routeIs('manager.appointments.*') || request()->routeIs('manager.galleries.*') ? 'true' : 'false' }},
                         inventory: {{ request()->routeIs('manager.products.*') || request()->routeIs('manager.sales.*') || request()->routeIs('manager.purchases.*') ? 'true' : 'false' }},
                         accounts: {{ request()->routeIs('manager.accounts.*') || request()->routeIs('manager.account-categories.*') || request()->routeIs('manager.ledger.*') ? 'true' : 'false' }},
                         reports: {{ request()->routeIs('manager.reports.*') ? 'true' : 'false' }},
                         admin: {{ request()->routeIs('admin.users.*') || request()->routeIs('admin.roles.*') || request()->routeIs('profile.*') ? 'true' : 'false' }}
                     },
                     toggleMenu(name) {
                         this.openMenus[name] = !this.openMenus[name];
                     }
                 }">
                
                <!-- Sleek Search Input -->
                <div class="px-0.5 pb-2">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </span>
                        <input type="text" 
                               x-model="menuQuery" 
                               placeholder="🔍 Search menu options..." 
                               class="w-full pl-9 pr-7 py-2.5 bg-slate-50 border border-slate-200 text-xs font-semibold focus:ring-2 focus:ring-indigo-600 focus:bg-white transition-all text-slate-800 placeholder-slate-400 shadow-2xs">
                        <button x-show="menuQuery.length > 0" @click="menuQuery = ''" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600" x-cloak>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                </div>

                <!-- MAIN MENU 1: DASHBOARDS OVERVIEW -->
                <div x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())" class="space-y-1">
                    <button type="button" 
                            @click="toggleMenu('dashboards')" 
                            class="w-full flex items-center justify-between px-3 py-2.5 font-black text-[11px] uppercase tracking-wider text-slate-700 bg-slate-50 hover:bg-slate-100 border border-slate-200/80 transition-all duration-150 group">
                        <div class="flex items-center gap-2.5">
                            <div class="p-1.5 bg-blue-50 text-blue-600 border border-blue-100 group-hover:scale-105 transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                            </div>
                            <span>Dashboards Overview</span>
                        </div>
                        <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': openMenus.dashboards || menuQuery.length > 0 }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <div x-show="openMenus.dashboards || menuQuery.length > 0" class="pl-3.5 space-y-1 border-l-2 border-indigo-200/80 ml-3.5 mt-1.5">
                        <a href="{{ route('dashboard') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold transition-all group {{ request()->routeIs('dashboard') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-extrabold' : 'text-slate-600 hover:bg-indigo-50/70 hover:text-indigo-600 hover:translate-x-0.5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('dashboard') ? 'bg-white' : 'bg-slate-300 group-hover:bg-indigo-500' }}"></span>
                            <span>Main Dashboard</span>
                        </a>

                        @if(Auth::user()->hasRole('admin'))
                        <a href="{{ route('admin.dashboard') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold transition-all group {{ request()->routeIs('admin.dashboard') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-extrabold' : 'text-slate-600 hover:bg-indigo-50/70 hover:text-indigo-600 hover:translate-x-0.5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('admin.dashboard') ? 'bg-white' : 'bg-slate-300 group-hover:bg-indigo-500' }}"></span>
                            <span>Admin Insights</span>
                        </a>
                        @endif

                        <a href="{{ route('manager.dashboard') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold transition-all group {{ request()->routeIs('manager.dashboard') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-extrabold' : 'text-slate-600 hover:bg-indigo-50/70 hover:text-indigo-600 hover:translate-x-0.5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.dashboard') ? 'bg-white' : 'bg-slate-300 group-hover:bg-indigo-500' }}"></span>
                            <span>Saloon Manager</span>
                        </a>
                    </div>
                </div>

                @if(Auth::user()->hasAnyRole(['admin', 'manager']))

                <!-- MAIN MENU 2: SALOON SERVICES -->
                <div x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())" class="space-y-1">
                    <button type="button" 
                            @click="toggleMenu('services')" 
                            class="w-full flex items-center justify-between px-3 py-2.5 font-black text-[11px] uppercase tracking-wider text-slate-700 bg-slate-50 hover:bg-slate-100 border border-slate-200/80 transition-all duration-150 group">
                        <div class="flex items-center gap-2.5">
                            <div class="p-1.5 bg-purple-50 text-purple-600 border border-purple-100 group-hover:scale-105 transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 0A3 3 0 104.5 4.5a3 3 0 004.621 4.621zm0 0L12 12m-2.879 2.879a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243z"></path></svg>
                            </div>
                            <span>Saloon Services</span>
                        </div>
                        <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': openMenus.services || menuQuery.length > 0 }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <div x-show="openMenus.services || menuQuery.length > 0" class="pl-3.5 space-y-1 border-l-2 border-purple-200/80 ml-3.5 mt-1.5">
                        <a href="{{ route('manager.services.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold transition-all group {{ request()->routeIs('manager.services.*') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-extrabold' : 'text-slate-600 hover:bg-indigo-50/70 hover:text-indigo-600 hover:translate-x-0.5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.services.*') ? 'bg-white' : 'bg-slate-300 group-hover:bg-indigo-500' }}"></span>
                            <span>Treatments Catalog</span>
                        </a>

                        <a href="{{ route('manager.service-categories.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold transition-all group {{ request()->routeIs('manager.service-categories.*') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-extrabold' : 'text-slate-600 hover:bg-indigo-50/70 hover:text-indigo-600 hover:translate-x-0.5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.service-categories.*') ? 'bg-white' : 'bg-slate-300 group-hover:bg-indigo-500' }}"></span>
                            <span>Service Categories</span>
                        </a>

                        <a href="{{ route('manager.appointments.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold transition-all group {{ request()->routeIs('manager.appointments.*') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-extrabold' : 'text-slate-600 hover:bg-indigo-50/70 hover:text-indigo-600 hover:translate-x-0.5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.appointments.*') ? 'bg-white' : 'bg-slate-300 group-hover:bg-indigo-500' }}"></span>
                            <span>Book Appointments</span>
                        </a>

                        <a href="{{ route('manager.galleries.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold transition-all group {{ request()->routeIs('manager.galleries.*') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-extrabold' : 'text-slate-600 hover:bg-indigo-50/70 hover:text-indigo-600 hover:translate-x-0.5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.galleries.*') ? 'bg-white' : 'bg-slate-300 group-hover:bg-indigo-500' }}"></span>
                            <span>Photo Gallery Showcase</span>
                        </a>
                    </div>
                </div>

                <!-- MAIN MENU 3: PRODUCTS & POS INVENTORY -->
                <div x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())" class="space-y-1">
                    <button type="button" 
                            @click="toggleMenu('inventory')" 
                            class="w-full flex items-center justify-between px-3 py-2.5 font-black text-[11px] uppercase tracking-wider text-slate-700 bg-slate-50 hover:bg-slate-100 border border-slate-200/80 transition-all duration-150 group">
                        <div class="flex items-center gap-2.5">
                            <div class="p-1.5 bg-emerald-50 text-emerald-600 border border-emerald-100 group-hover:scale-105 transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                            </div>
                            <span>Products & POS</span>
                        </div>
                        <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': openMenus.inventory || menuQuery.length > 0 }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <div x-show="openMenus.inventory || menuQuery.length > 0" class="pl-3.5 space-y-1 border-l-2 border-emerald-200/80 ml-3.5 mt-1.5">
                        <a href="{{ route('manager.products.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold transition-all group {{ request()->routeIs('manager.products.*') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-extrabold' : 'text-slate-600 hover:bg-indigo-50/70 hover:text-indigo-600 hover:translate-x-0.5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.products.*') ? 'bg-white' : 'bg-slate-300 group-hover:bg-indigo-500' }}"></span>
                            <span>Products Inventory</span>
                        </a>

                        <a href="{{ route('manager.sales.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold transition-all group {{ request()->routeIs('manager.sales.*') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-extrabold' : 'text-slate-600 hover:bg-indigo-50/70 hover:text-indigo-600 hover:translate-x-0.5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.sales.*') ? 'bg-white' : 'bg-slate-300 group-hover:bg-indigo-500' }}"></span>
                            <span>Product Sales (POS)</span>
                        </a>

                        <a href="{{ route('manager.purchases.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold transition-all group {{ request()->routeIs('manager.purchases.*') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-extrabold' : 'text-slate-600 hover:bg-indigo-50/70 hover:text-indigo-600 hover:translate-x-0.5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.purchases.*') ? 'bg-white' : 'bg-slate-300 group-hover:bg-indigo-500' }}"></span>
                            <span>Supplier Purchases</span>
                        </a>
                    </div>
                </div>

                <!-- MAIN MENU 4: ACCOUNTS & FINANCIALS -->
                <div x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())" class="space-y-1">
                    <button type="button" 
                            @click="toggleMenu('accounts')" 
                            class="w-full flex items-center justify-between px-3 py-2.5 font-black text-[11px] uppercase tracking-wider text-slate-700 bg-slate-50 hover:bg-slate-100 border border-slate-200/80 transition-all duration-150 group">
                        <div class="flex items-center gap-2.5">
                            <div class="p-1.5 bg-amber-50 text-amber-600 border border-amber-100 group-hover:scale-105 transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            </div>
                            <span>Accounts & Ledgers</span>
                        </div>
                        <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': openMenus.accounts || menuQuery.length > 0 }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <div x-show="openMenus.accounts || menuQuery.length > 0" class="pl-3.5 space-y-1 border-l-2 border-amber-200/80 ml-3.5 mt-1.5">
                        <a href="{{ route('manager.accounts.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold transition-all group {{ request()->routeIs('manager.accounts.*') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-extrabold' : 'text-slate-600 hover:bg-indigo-50/70 hover:text-indigo-600 hover:translate-x-0.5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.accounts.*') ? 'bg-white' : 'bg-slate-300 group-hover:bg-indigo-500' }}"></span>
                            <span>Customer & Party Accounts</span>
                        </a>

                        <a href="{{ route('manager.account-categories.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold transition-all group {{ request()->routeIs('manager.account-categories.*') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-extrabold' : 'text-slate-600 hover:bg-indigo-50/70 hover:text-indigo-600 hover:translate-x-0.5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.account-categories.*') ? 'bg-white' : 'bg-slate-300 group-hover:bg-indigo-500' }}"></span>
                            <span>Account Categories</span>
                        </a>

                        <a href="{{ route('manager.ledger.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold transition-all group {{ request()->routeIs('manager.ledger.*') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-extrabold' : 'text-slate-600 hover:bg-indigo-50/70 hover:text-indigo-600 hover:translate-x-0.5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.ledger.*') ? 'bg-white' : 'bg-slate-300 group-hover:bg-indigo-500' }}"></span>
                            <span>General Account Ledgers</span>
                        </a>
                    </div>
                </div>

                <!-- MAIN MENU 5: REPORTS & ANALYTICS -->
                <div x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())" class="space-y-1">
                    <button type="button" 
                            @click="toggleMenu('reports')" 
                            class="w-full flex items-center justify-between px-3 py-2.5 font-black text-[11px] uppercase tracking-wider text-slate-700 bg-slate-50 hover:bg-slate-100 border border-slate-200/80 transition-all duration-150 group">
                        <div class="flex items-center gap-2.5">
                            <div class="p-1.5 bg-rose-50 text-rose-600 border border-rose-100 group-hover:scale-105 transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                            </div>
                            <span>Reports & Analytics</span>
                        </div>
                        <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': openMenus.reports || menuQuery.length > 0 }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <div x-show="openMenus.reports || menuQuery.length > 0" class="pl-3.5 space-y-1 border-l-2 border-rose-200/80 ml-3.5 mt-1.5">
                        <a href="{{ route('manager.reports.sales') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold transition-all group {{ request()->routeIs('manager.reports.sales') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-extrabold' : 'text-slate-600 hover:bg-indigo-50/70 hover:text-indigo-600 hover:translate-x-0.5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.reports.sales') ? 'bg-white' : 'bg-slate-300 group-hover:bg-indigo-500' }}"></span>
                            <span>Sales Report</span>
                        </a>

                        <a href="{{ route('manager.reports.stock') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold transition-all group {{ request()->routeIs('manager.reports.stock') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-extrabold' : 'text-slate-600 hover:bg-indigo-50/70 hover:text-indigo-600 hover:translate-x-0.5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.reports.stock') ? 'bg-white' : 'bg-slate-300 group-hover:bg-indigo-500' }}"></span>
                            <span>Stock Inventory Report</span>
                        </a>

                        <a href="{{ route('manager.reports.services') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold transition-all group {{ request()->routeIs('manager.reports.services') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-extrabold' : 'text-slate-600 hover:bg-indigo-50/70 hover:text-indigo-600 hover:translate-x-0.5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.reports.services') ? 'bg-white' : 'bg-slate-300 group-hover:bg-indigo-500' }}"></span>
                            <span>Service Booking Reports</span>
                        </a>

                        <a href="{{ route('manager.reports.ledger') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold transition-all group {{ request()->routeIs('manager.reports.ledger') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-extrabold' : 'text-slate-600 hover:bg-indigo-50/70 hover:text-indigo-600 hover:translate-x-0.5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.reports.ledger') ? 'bg-white' : 'bg-slate-300 group-hover:bg-indigo-500' }}"></span>
                            <span>Account Ledger Report</span>
                        </a>

                        <a href="{{ route('manager.reports.purchases') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold transition-all group {{ request()->routeIs('manager.reports.purchases') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-extrabold' : 'text-slate-600 hover:bg-indigo-50/70 hover:text-indigo-600 hover:translate-x-0.5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.reports.purchases') ? 'bg-white' : 'bg-slate-300 group-hover:bg-indigo-500' }}"></span>
                            <span>Supplier Purchase Report</span>
                        </a>
                    </div>
                </div>

                @endif

                <!-- MAIN MENU 6: SYSTEM & USER ACCESS -->
                <div x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())" class="space-y-1">
                    <button type="button" 
                            @click="toggleMenu('admin')" 
                            class="w-full flex items-center justify-between px-3 py-2.5 font-black text-[11px] uppercase tracking-wider text-slate-700 bg-slate-50 hover:bg-slate-100 border border-slate-200/80 transition-all duration-150 group">
                        <div class="flex items-center gap-2.5">
                            <div class="p-1.5 bg-indigo-50 text-indigo-600 border border-indigo-100 group-hover:scale-105 transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            </div>
                            <span>User Access & Profile</span>
                        </div>
                        <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': openMenus.admin || menuQuery.length > 0 }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <div x-show="openMenus.admin || menuQuery.length > 0" class="pl-3.5 space-y-1 border-l-2 border-indigo-200/80 ml-3.5 mt-1.5">
                        @if(Auth::user()->hasAnyRole(['admin', 'manager']))
                        <a href="{{ route('admin.users.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center justify-between px-3 py-2 text-xs font-bold transition-all group {{ request()->routeIs('admin.users.*') || request()->routeIs('admin.roles.*') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-extrabold' : 'text-slate-600 hover:bg-indigo-50/70 hover:text-indigo-600 hover:translate-x-0.5' }}">
                            <div class="flex items-center gap-2.5">
                                <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('admin.users.*') ? 'bg-white' : 'bg-slate-300 group-hover:bg-indigo-500' }}"></span>
                                <span>Users & Permissions</span>
                            </div>
                            <span class="text-[9px] font-extrabold px-1.5 py-0.2 {{ request()->routeIs('admin.users.*') ? 'bg-white/20 text-white' : 'bg-indigo-100 text-indigo-700' }}">
                                Admin
                            </span>
                        </a>
                        @endif

                        <a href="{{ route('manager.settings.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold transition-all group {{ request()->routeIs('manager.settings.*') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-extrabold' : 'text-slate-600 hover:bg-indigo-50/70 hover:text-indigo-600 hover:translate-x-0.5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.settings.*') ? 'bg-white' : 'bg-slate-300 group-hover:bg-indigo-500' }}"></span>
                            <span>Branding & Store Settings</span>
                        </a>

                        <a href="{{ route('profile.edit') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold transition-all group {{ request()->routeIs('profile.*') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-extrabold' : 'text-slate-600 hover:bg-indigo-50/70 hover:text-indigo-600 hover:translate-x-0.5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('profile.*') ? 'bg-white' : 'bg-slate-300 group-hover:bg-indigo-500' }}"></span>
                            <span>My Profile</span>
                        </a>
                    </div>
                </div>
            </nav>

            <!-- Bottom Logout Action -->
            <div class="p-4 border-t border-slate-200 bg-slate-50/50">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center justify-center gap-2.5 px-4 py-2.5 font-bold text-sm text-red-600 bg-red-50 hover:bg-red-100 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        <span>Sign Out</span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            
            <!-- Topbar App Bar - Sharp Corners -->
            <header class="h-20 bg-white border-b border-slate-200 sticky top-0 z-30 flex items-center justify-between px-4 sm:px-8 shadow-sm">
                
                <div class="flex items-center gap-4">
                    <!-- Mobile Hamburger Button -->
                    <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 text-slate-600 hover:bg-slate-100 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>

                    <!-- Search Input - Sharp Corners -->
                    <div class="relative hidden sm:block w-72">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </span>
                        <input type="text" 
                               placeholder="Search customers, roles..." 
                               class="w-full pl-10 pr-4 py-2 bg-slate-100 border-none text-sm font-medium focus:ring-2 focus:ring-indigo-600 focus:bg-white transition-all text-slate-800 placeholder-slate-400">
                    </div>
                </div>

                <!-- Right Topbar Actions -->
                <div class="flex items-center gap-3">
                    
                    <!-- Quick Notification Icon -->
                    <button class="relative p-2.5 text-slate-600 hover:bg-slate-100 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        <span class="absolute top-2 right-2 w-2.5 h-2.5 bg-indigo-600 border-2 border-white rounded-full"></span>
                    </button>

                    <!-- Settings Button -->
                    <a href="{{ route('profile.edit') }}" class="p-2.5 text-slate-600 hover:bg-slate-100 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    </a>

                    <div class="h-6 w-px bg-slate-200 mx-1"></div>

                    <!-- User Profile Dropdown -->
                    <div class="relative" x-data="{ userMenuOpen: false }">
                        <button @click="userMenuOpen = !userMenuOpen" class="flex items-center gap-3 p-1.5 hover:bg-slate-100 transition-colors">
                            <div class="w-9 h-9 bg-indigo-600 text-white flex items-center justify-center font-extrabold text-xs">
                                {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 2)) }}
                            </div>
                            <div class="hidden md:block text-left">
                                <p class="text-xs font-bold text-slate-900 leading-tight">{{ Auth::user()->name }}</p>
                                <p class="text-[10px] font-extrabold text-indigo-600 uppercase tracking-wider">{{ Auth::user()->roles->first()->name ?? 'User' }}</p>
                            </div>
                            <svg class="w-4 h-4 text-slate-400 hidden md:inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>

                        <div x-show="userMenuOpen" 
                             @click.outside="userMenuOpen = false"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute right-0 mt-2 w-56 bg-white shadow-xl border border-slate-200 py-2 z-50"
                             x-cloak>
                            <div class="px-4 py-3 border-b border-slate-100">
                                <p class="text-xs font-bold text-slate-900">{{ Auth::user()->name }}</p>
                                <p class="text-xs text-slate-500 truncate">{{ Auth::user()->email }}</p>
                            </div>
                            <a href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                <span>Profile Settings</span>
                            </a>
                            @if(Auth::user()->hasRole('admin'))
                            <a href="{{ route('admin.users.index') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                <span>Users & Roles</span>
                            </a>
                            @endif
                            <div class="my-1 border-t border-slate-100"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left flex items-center gap-2.5 px-4 py-2.5 text-xs font-bold text-red-600 hover:bg-red-50 transition-colors">
                                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                                    <span>Sign Out</span>
                                </button>
                            </form>
                        </div>
                    </div>

                </div>

            </header>

            <!-- Floating Toast Notification Overlay - Sharp Corners -->
            @if(session('success') || session('error'))
            <div x-data="{ showToast: true }" 
                 x-init="setTimeout(() => showToast = false, 5000)" 
                 x-show="showToast"
                 x-transition:enter="transition ease-out duration-300 transform"
                 x-transition:enter-start="opacity-0 -translate-y-4 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-200 transform"
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 -translate-y-4 scale-95"
                 class="fixed top-6 right-6 z-50 max-w-md w-full shadow-2xl bg-white border border-slate-200 overflow-hidden pointer-events-auto"
                 x-cloak>
                
                @if(session('success'))
                    <div class="p-4 bg-white border-l-4 border-emerald-500 flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <div class="p-1 bg-emerald-100 text-emerald-600 shrink-0 mt-0.5">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            </div>
                            <div>
                                <h4 class="text-xs font-black uppercase text-emerald-800 tracking-wider">Success Notification</h4>
                                <p class="text-xs font-bold text-slate-800 mt-0.5 leading-snug">{{ session('success') }}</p>
                            </div>
                        </div>
                        <button @click="showToast = false" class="text-slate-400 hover:text-slate-600 p-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="p-4 bg-white border-l-4 border-rose-500 flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <div class="p-1 bg-rose-100 text-rose-600 shrink-0 mt-0.5">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <div>
                                <h4 class="text-xs font-black uppercase text-rose-800 tracking-wider">Attention Required</h4>
                                <p class="text-xs font-bold text-slate-800 mt-0.5 leading-snug">{{ session('error') }}</p>
                            </div>
                        </div>
                        <button @click="showToast = false" class="text-slate-400 hover:text-slate-600 p-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                @endif
            </div>
            @endif

            <!-- Main Content Slot -->
            <main class="flex-1 overflow-y-auto px-4 sm:px-8 py-6">
                @yield('content')
                {{ $slot ?? '' }}
            </main>

            <!-- Light Footer -->
            <footer class="py-4 px-8 border-t border-slate-200 bg-white text-center text-xs font-semibold text-slate-400">
                &copy; {{ date('Y') }} Jugnu Saloon Management Suite &bull; Material Light Theme
            </footer>

        </div>
    </div>
</body>
</html>
