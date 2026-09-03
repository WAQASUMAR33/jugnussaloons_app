<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'Jugnu Saloon')) - Saloon Executive Suite</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Courier+Prime:ital,wght@0,400;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Scripts & Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine JS fallback -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .heading-font {
            font-family: 'Outfit', sans-serif;
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
            border-radius: 9999px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
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
            body.pos-receipt-active * {
                visibility: hidden !important;
            }
            body.pos-receipt-active .pos-receipt-80mm-container,
            body.pos-receipt-active .pos-receipt-80mm-container * {
                visibility: visible !important;
            }
            body.pos-receipt-active .pos-receipt-80mm-container {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 76mm !important;
                max-width: 76mm !important;
                margin: 0 auto !important;
                padding: 2mm 3mm !important;
                font-family: 'Courier New', Courier, monospace !important;
                font-size: 11px !important;
                line-height: 1.35 !important;
                color: #000000 !important;
                background: #ffffff !important;
                display: block !important;
            }
            body.pos-receipt-active .pos-receipt-80mm-container table {
                width: 100% !important;
                border: none !important;
                border-collapse: collapse !important;
            }
            body.pos-receipt-active .pos-receipt-80mm-container td,
            body.pos-receipt-active .pos-receipt-80mm-container th {
                border: none !important;
                padding: 2px 0 !important;
                font-size: 11px !important;
                color: #000000 !important;
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
<body class="h-full antialiased text-slate-800 bg-slate-50 font-sans" x-data="{ sidebarOpen: false }">
    <div class="min-h-screen flex bg-slate-50">
        
        <!-- Mobile Sidebar Backdrop Overlay -->
        <div x-show="sidebarOpen" 
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="sidebarOpen = false" 
             class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-sm lg:hidden" 
             x-cloak></div>

        <!-- High-Contrast Clean Luxury Sidebar -->
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-50 w-72 bg-white text-slate-800 border-r border-slate-200 transform transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0 flex flex-col shadow-sm select-none">
            
            <!-- Brand & Salon Header -->
            <div class="h-20 px-5 flex items-center justify-between border-b border-slate-100 bg-gradient-to-r from-indigo-700 via-indigo-600 to-purple-700 text-white shadow-xs">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3.5 group overflow-hidden">
                    @if(isset($appSetting) && $appSetting->brand_logo)
                        <div class="w-10 h-10 rounded-xl bg-white p-1 border border-white/20 flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform duration-200 overflow-hidden shrink-0">
                            <img src="{{ asset($appSetting->brand_logo) }}" alt="Logo" class="w-full h-full object-contain">
                        </div>
                    @else
                        <div class="w-10 h-10 rounded-xl bg-white/15 backdrop-blur-md border border-white/20 text-white flex items-center justify-center shadow-inner group-hover:scale-105 transition-transform duration-200 shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 0A3 3 0 104.5 4.5a3 3 0 004.621 4.621zm0 0L12 12m-2.879 2.879a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243z"></path></svg>
                        </div>
                    @endif
                    <div class="overflow-hidden">
                        <h1 class="font-black text-sm text-white tracking-tight leading-tight truncate heading-font">
                            {{ $appSetting->brand_name ?? 'Jugnu Saloon' }}
                        </h1>
                        <p class="text-[10px] text-indigo-100 font-bold tracking-wider uppercase truncate mt-0.5">
                            {{ $appSetting->brand_slogan ?: 'Executive Suite' }}
                        </p>
                    </div>
                </a>
                <button @click="sidebarOpen = false" class="lg:hidden text-white/80 hover:text-white p-1.5 rounded-lg hover:bg-white/10 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Navigation Links Container -->
            <nav class="flex-1 px-3 py-4 space-y-1.5 overflow-y-auto" 
                 x-data="{ 
                     menuQuery: '',
                     openMenus: {
                         services: {{ request()->routeIs('manager.services.*') || request()->routeIs('manager.service-categories.*') || request()->routeIs('manager.galleries.*') ? 'true' : 'false' }},
                         inventory: {{ request()->routeIs('manager.products.*') || request()->routeIs('manager.sales.*') || request()->routeIs('manager.purchases.*') || request()->routeIs('manager.stores.*') || request()->routeIs('manager.store-stocks.*') || request()->routeIs('manager.stock-transfers.*') ? 'true' : 'false' }},
                         accounts: {{ request()->routeIs('manager.accounts.*') || request()->routeIs('manager.account-categories.*') || request()->routeIs('manager.ledger.*') || request()->routeIs('manager.expenses.*') || request()->routeIs('manager.expense-categories.*') || request()->routeIs('manager.payroll.*') || request()->routeIs('manager.attendance.*') || request()->routeIs('manager.commissions.*') ? 'true' : 'false' }},
                         reports: {{ request()->routeIs('manager.reports.*') ? 'true' : 'false' }},
                         system: {{ request()->routeIs('admin.*') || request()->routeIs('manager.settings.*') || request()->routeIs('manager.bank-accounts.*') ? 'true' : 'false' }}
                     },
                     toggleMenu(name) {
                         this.openMenus[name] = !this.openMenus[name];
                     }
                 }">
                
                <!-- Quick Search Filter Input -->
                <div class="pb-2.5 px-0.5">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </span>
                        <input type="search" 
                               name="sidebar_menu_filter_search"
                               id="sidebar_menu_filter_search"
                               autocomplete="off"
                               autocorrect="off"
                               autocapitalize="off"
                               spellcheck="false"
                               data-lpignore="true"
                               data-1p-ignore="true"
                               data-form-type="other"
                               readonly
                               onfocus="this.removeAttribute('readonly');"
                               x-model="menuQuery" 
                               placeholder="🔍 Filter menu..." 
                               class="w-full pl-8 pr-7 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:border-indigo-600 focus:ring-2 focus:ring-indigo-100 focus:bg-white text-slate-800 placeholder-slate-400 transition-all">
                        <button type="button" x-show="menuQuery.length > 0" @click="menuQuery = ''" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-700" x-cloak>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                </div>

                <!-- SECTION 1: CORE MODULES -->
                <div class="space-y-1">
                    <p class="px-3 pt-1 pb-0.5 text-[10px] font-black uppercase tracking-wider text-slate-400">Core Modules</p>
                    
                    <!-- Dashboard -->
                    <a href="{{ route('dashboard') }}" 
                       x-show="!menuQuery || 'dashboard overview home insights'.includes(menuQuery.toLowerCase())"
                       class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold transition-all duration-200 group {{ request()->routeIs('dashboard') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-extrabold' : 'text-slate-700 hover:bg-slate-100 hover:text-indigo-600' }}">
                        <div class="flex items-center gap-3">
                            <div class="p-1.5 rounded-lg {{ request()->routeIs('dashboard') ? 'bg-white/20 text-white' : 'bg-indigo-50 text-indigo-600 group-hover:scale-105' }} transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                            </div>
                            <span>Dashboard Overview</span>
                        </div>
                        @if(request()->routeIs('dashboard'))
                            <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                        @endif
                    </a>

                    <!-- Appointments Calendar -->
                    @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-appointments') || Auth::user()->hasPermission('book-appointment'))
                    <a href="{{ route('manager.appointments.index') }}" 
                       x-show="!menuQuery || 'appointments booking calendar schedule clients pos billing'.includes(menuQuery.toLowerCase())"
                       class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold transition-all duration-200 group {{ request()->routeIs('manager.appointments.*') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-extrabold' : 'text-slate-700 hover:bg-slate-100 hover:text-indigo-600' }}">
                        <div class="flex items-center gap-3">
                            <div class="p-1.5 rounded-lg {{ request()->routeIs('manager.appointments.*') ? 'bg-white/20 text-white' : 'bg-amber-50 text-amber-600 group-hover:scale-105' }} transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                            <span>Appointments & POS</span>
                        </div>
                        <span class="px-1.5 py-0.5 rounded-md text-[10px] font-black {{ request()->routeIs('manager.appointments.*') ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800' }}">
                            Book
                        </span>
                    </a>
                    @endif
                </div>

                <!-- SECTION 2: SALOON OPERATIONS -->
                @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-services') || Auth::user()->hasPermission('manage-gallery'))
                <div class="pt-2 space-y-1" x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())">
                    <p class="px-3 pt-1 pb-0.5 text-[10px] font-black uppercase tracking-wider text-slate-400">Saloon Operations</p>
                    
                    <button type="button" 
                            @click="toggleMenu('services')" 
                            class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition-all group">
                        <div class="flex items-center gap-3">
                            <div class="p-1.5 rounded-lg bg-purple-50 text-purple-600 group-hover:scale-105 transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 0A3 3 0 104.5 4.5a3 3 0 004.621 4.621zm0 0L12 12m-2.879 2.879a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243z"></path></svg>
                            </div>
                            <span>Services & Gallery</span>
                        </div>
                        <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': openMenus.services || menuQuery.length > 0 }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <div x-show="openMenus.services || menuQuery.length > 0" class="pl-6 pr-1 space-y-1 mt-1 border-l-2 border-purple-200 ml-4">
                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-services'))
                        <a href="{{ route('manager.services.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.services.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.services.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Treatments Catalog</span>
                        </a>

                        <a href="{{ route('manager.service-categories.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.service-categories.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.service-categories.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Service Categories</span>
                        </a>
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-gallery'))
                        <a href="{{ route('manager.galleries.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.galleries.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.galleries.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Photo Gallery Showcase</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endif

                <!-- SECTION 3: INVENTORY & POS -->
                @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-sales') || Auth::user()->hasPermission('manage-products') || Auth::user()->hasPermission('manage-purchases'))
                <div class="pt-2 space-y-1" x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())">
                    <p class="px-3 pt-1 pb-0.5 text-[10px] font-black uppercase tracking-wider text-slate-400">Inventory & POS</p>
                    
                    <button type="button" 
                            @click="toggleMenu('inventory')" 
                            class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition-all group">
                        <div class="flex items-center gap-3">
                            <div class="p-1.5 rounded-lg bg-emerald-50 text-emerald-600 group-hover:scale-105 transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                            </div>
                            <span>Products & POS</span>
                        </div>
                        <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': openMenus.inventory || menuQuery.length > 0 }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <div x-show="openMenus.inventory || menuQuery.length > 0" class="pl-6 pr-1 space-y-1 mt-1 border-l-2 border-emerald-200 ml-4">
                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-sales'))
                        <a href="{{ route('manager.sales.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.sales.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <div class="flex items-center gap-2.5">
                                <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.sales.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                                <span>POS Sale Checkout</span>
                            </div>
                            <span class="text-[9px] font-black px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800">⚡ Live</span>
                        </a>
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-products'))
                        <a href="{{ route('manager.products.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.products.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.products.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Products Inventory</span>
                        </a>
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-purchases'))
                        <a href="{{ route('manager.purchases.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.purchases.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.purchases.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Supplier Purchases</span>
                        </a>
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-products'))
                        <a href="{{ route('manager.stores.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.stores.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.stores.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Store Locations / Branches</span>
                        </a>

                        <a href="{{ route('manager.store-stocks.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.store-stocks.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.store-stocks.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Store-Wise Stock Ledger</span>
                        </a>

                        <a href="{{ route('manager.stock-transfers.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.stock-transfers.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.stock-transfers.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Stock Transfers</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endif

                <!-- SECTION 4: FINANCE & ACCOUNTS -->
                @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-accounts') || Auth::user()->hasPermission('manage-ledger') || Auth::user()->hasPermission('manage-expenses') || Auth::user()->hasPermission('manage-payroll') || Auth::user()->hasPermission('manage-attendance'))
                <div class="pt-2 space-y-1" x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())">
                    <p class="px-3 pt-1 pb-0.5 text-[10px] font-black uppercase tracking-wider text-slate-400">Finance & Accounts</p>
                    
                    <button type="button" 
                            @click="toggleMenu('accounts')" 
                            class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition-all group">
                        <div class="flex items-center gap-3">
                            <div class="p-1.5 rounded-lg bg-amber-50 text-amber-600 group-hover:scale-105 transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            </div>
                            <span>Accounts & Ledgers</span>
                        </div>
                        <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': openMenus.accounts || menuQuery.length > 0 }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <div x-show="openMenus.accounts || menuQuery.length > 0" class="pl-6 pr-1 space-y-1 mt-1 border-l-2 border-amber-200 ml-4">
                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-accounts'))
                        <a href="{{ route('manager.accounts.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.accounts.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.accounts.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Customer Accounts</span>
                        </a>

                        <a href="{{ route('manager.account-categories.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.account-categories.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.account-categories.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Account Categories</span>
                        </a>
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-ledger') || Auth::user()->hasPermission('manage-accounts'))
                        <a href="{{ route('manager.ledger.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.ledger.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.ledger.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>General Ledgers</span>
                        </a>
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-expenses'))
                        <a href="{{ route('manager.expenses.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.expenses.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.expenses.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Daily Expenses</span>
                        </a>

                        <a href="{{ route('manager.expense-categories.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.expense-categories.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.expense-categories.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Expense Categories</span>
                        </a>
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-payroll'))
                        <a href="{{ route('manager.payroll.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.payroll.index') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.payroll.index') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Staff Payroll</span>
                        </a>

                        <a href="{{ route('manager.payroll.deductions.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.payroll.deductions.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.payroll.deductions.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Salary Deductions</span>
                        </a>
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-attendance') || Auth::user()->hasPermission('manage-payroll'))
                        <a href="{{ route('manager.attendance.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.attendance.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.attendance.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Staff Attendance</span>
                        </a>
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-payroll') || Auth::user()->hasPermission('manage-accounts'))
                        <a href="{{ route('manager.commissions.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.commissions.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.commissions.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Commission Management</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endif

                <!-- SECTION 5: BUSINESS INTELLIGENCE -->
                @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-reports'))
                <div class="pt-2 space-y-1" x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())">
                    <p class="px-3 pt-1 pb-0.5 text-[10px] font-black uppercase tracking-wider text-slate-400">Business Reports</p>
                    
                    <button type="button" 
                            @click="toggleMenu('reports')" 
                            class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition-all group">
                        <div class="flex items-center gap-3">
                            <div class="p-1.5 rounded-lg bg-rose-50 text-rose-600 group-hover:scale-105 transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                            </div>
                            <span>Reports & Analytics</span>
                        </div>
                        <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': openMenus.reports || menuQuery.length > 0 }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <div x-show="openMenus.reports || menuQuery.length > 0" class="pl-6 pr-1 space-y-1 mt-1 border-l-2 border-rose-200 ml-4">
                        <a href="{{ route('manager.reports.sales') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.reports.sales') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.reports.sales') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Sales Reports</span>
                        </a>

                        <a href="{{ route('manager.reports.stock') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.reports.stock') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.reports.stock') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Stock Inventory</span>
                        </a>

                        <a href="{{ route('manager.reports.services') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.reports.services') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.reports.services') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Service Reports</span>
                        </a>

                        <a href="{{ route('manager.reports.ledger') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.reports.ledger') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.reports.ledger') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Ledger Reports</span>
                        </a>

                        <a href="{{ route('manager.reports.purchases') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.reports.purchases') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.reports.purchases') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Purchases Reports</span>
                        </a>
                    </div>
                </div>
                @endif

                <!-- SECTION 6: SETTINGS & ADMIN -->
                @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-settings') || Auth::user()->hasPermission('manage-bank-accounts') || Auth::user()->hasPermission('approve-discounts') || Auth::user()->hasPermission('manage-users') || Auth::user()->hasPermission('manage-roles'))
                <div class="pt-2 space-y-1" x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())">
                    <p class="px-3 pt-1 pb-0.5 text-[10px] font-black uppercase tracking-wider text-slate-400">Settings & Admin</p>
                    
                    <button type="button" 
                            @click="toggleMenu('system')" 
                            class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition-all group">
                        <div class="flex items-center gap-3">
                            <div class="p-1.5 rounded-lg bg-indigo-50 text-indigo-600 group-hover:scale-105 transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            </div>
                            <span>Salon Settings</span>
                        </div>
                        <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': openMenus.system || menuQuery.length > 0 }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <div x-show="openMenus.system || menuQuery.length > 0" class="pl-6 pr-1 space-y-1 mt-1 border-l-2 border-indigo-200 ml-4">
                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-users'))
                        <a href="{{ route('admin.users.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('admin.users.*') || request()->routeIs('admin.roles.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <div class="flex items-center gap-2.5">
                                <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('admin.users.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                                <span>Users & Permissions</span>
                            </div>
                            <span class="text-[9px] font-black px-1.5 py-0.2 rounded bg-indigo-100 text-indigo-800">Admin</span>
                        </a>
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('approve-discounts'))
                        @php
                            $sidebarPendingCount = \App\Models\DiscountRequest::where('status', 'pending')->count();
                        @endphp
                        <a href="{{ route('admin.discount-requests.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('admin.discount-requests.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <div class="flex items-center gap-2.5">
                                <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('admin.discount-requests.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                                <span>Discount Approvals</span>
                            </div>
                            @if($sidebarPendingCount > 0)
                                <span class="text-[9px] font-black px-2 py-0.5 rounded-full bg-rose-600 text-white animate-pulse">
                                    {{ $sidebarPendingCount }}
                                </span>
                            @endif
                        </a>
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-settings'))
                        <a href="{{ route('manager.settings.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.settings.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.settings.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Branding & Invoice</span>
                        </a>
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-bank-accounts') || Auth::user()->hasPermission('manage-settings'))
                        <a href="{{ route('manager.bank-accounts.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.bank-accounts.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.bank-accounts.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Bank Accounts</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endif
            </nav>

            <!-- Bottom User Profile Footer Card -->
            <div class="p-3 border-t border-slate-100 bg-slate-50/80">
                <div class="flex items-center justify-between p-2 rounded-xl bg-white border border-slate-200/80 shadow-2xs">
                    <div class="flex items-center gap-2.5 overflow-hidden">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-600 to-purple-600 text-white flex items-center justify-center font-black text-xs shadow-xs shrink-0">
                            {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 2)) }}
                        </div>
                        <div class="overflow-hidden">
                            <h4 class="font-bold text-xs text-slate-900 truncate leading-tight">{{ Auth::user()->name }}</h4>
                            <span class="text-[10px] font-extrabold text-indigo-600 uppercase tracking-wider">
                                {{ Auth::user()->roles->first()->name ?? 'Staff' }}
                            </span>
                        </div>
                    </div>

                    <!-- Sign Out Button -->
                    <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                        @csrf
                        <button type="submit" 
                                title="Sign Out" 
                                class="p-2 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Main Workspace Area -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-50">
            
            <!-- Topbar Header -->
            <header class="h-20 bg-white border-b border-slate-200/80 sticky top-0 z-30 flex items-center justify-between px-4 sm:px-8 shadow-xs">
                
                <div class="flex items-center gap-4">
                    <!-- Mobile Hamburger Button -->
                    <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 rounded-xl text-slate-600 hover:bg-slate-100 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>

                    <!-- Salon Status -->
                    <div class="hidden sm:flex items-center gap-2 text-xs">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 font-bold border border-emerald-200/60">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                            Live Salon System
                        </span>
                        <span class="text-slate-300">•</span>
                        <span class="text-slate-500 font-semibold">{{ now()->format('l, F j, Y') }}</span>
                    </div>
                </div>

                <!-- Right Topbar Action Hub -->
                <div class="flex items-center gap-3">
                    
                    <!-- Quick Direct Action: New POS Sale -->
                    @if(Auth::user()->hasAnyRole(['admin', 'manager']))
                    <a href="{{ route('manager.sales.index') }}" 
                       class="hidden md:inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 font-extrabold text-xs border border-emerald-200/80 transition-all shadow-2xs">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        <span>POS Sale</span>
                    </a>

                    <!-- Quick Direct Action: New Appointment -->
                    <a href="{{ route('manager.appointments.index') }}" 
                       class="hidden sm:inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-sm shadow-indigo-200 transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        <span>+ Booking</span>
                    </a>
                    @endif

                    <div class="h-6 w-px bg-slate-200 mx-1 hidden sm:block"></div>

                    <!-- Notifications Dropdown -->
                    @php
                        $pendingDiscountCount = \App\Models\DiscountRequest::where('status', 'pending')->count();
                        $pendingDiscountRequests = Auth::user()->hasRole('admin') 
                            ? \App\Models\DiscountRequest::with(['appointment.customer', 'requester'])->where('status', 'pending')->latest()->take(5)->get()
                            : collect();
                    @endphp
                    <div class="relative" x-data="{ notifOpen: false }" @click.outside="notifOpen = false">
                        <button @click="notifOpen = !notifOpen" 
                                class="relative p-2.5 rounded-xl text-slate-600 hover:text-indigo-600 hover:bg-slate-100 transition-colors" 
                                title="Notifications">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                            @if($pendingDiscountCount > 0)
                                <span class="absolute top-1.5 right-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-rose-600 text-[9px] font-black text-white ring-2 ring-white animate-pulse">
                                    {{ $pendingDiscountCount }}
                                </span>
                            @endif
                        </button>

                        <!-- Notification Flyout -->
                        <div x-show="notifOpen" 
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95 translate-y-1"
                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                             x-transition:leave-end="opacity-0 scale-95 translate-y-1"
                             class="absolute right-0 mt-3 w-80 sm:w-96 bg-white rounded-2xl border border-slate-200 shadow-2xl z-50 overflow-hidden divide-y divide-slate-100"
                             x-cloak>
                            <div class="p-4 bg-slate-50 flex items-center justify-between">
                                <h4 class="font-extrabold text-xs text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                    <span>🔔 Discount Alerts</span>
                                    @if($pendingDiscountCount > 0)
                                        <span class="px-2 py-0.5 bg-rose-100 text-rose-800 text-[10px] font-black rounded-full">{{ $pendingDiscountCount }} Pending</span>
                                    @endif
                                </h4>
                                @if(Auth::user()->hasRole('admin'))
                                    <a href="{{ route('admin.discount-requests.index') }}" class="text-[11px] font-bold text-indigo-600 hover:text-indigo-700">View All &rarr;</a>
                                @endif
                            </div>

                            <div class="max-h-72 overflow-y-auto divide-y divide-slate-100">
                                @forelse($pendingDiscountRequests as $req)
                                    <div class="p-3.5 bg-amber-50/50 hover:bg-amber-50 transition-colors text-xs space-y-2">
                                        <div class="flex items-start justify-between gap-2">
                                            <div>
                                                <p class="font-extrabold text-slate-900">
                                                    Booking #{{ $req->appointment ? $req->appointment->booking_no : 'N/A' }}
                                                </p>
                                                <p class="text-[11px] font-bold text-amber-900 mt-0.5">
                                                    Discount > 10% Requested: <span class="font-black text-rose-600">{{ $req->discount_percentage }}% (PKR {{ number_format($req->discount_amount, 2) }})</span>
                                                </p>
                                                <p class="text-[10px] text-slate-500 mt-0.5">
                                                    Client: {{ $req->appointment && $req->appointment->customer ? $req->appointment->customer->name : 'N/A' }}
                                                </p>
                                            </div>
                                            <span class="text-[9px] font-bold text-slate-400 shrink-0">{{ $req->created_at->diffForHumans() }}</span>
                                        </div>

                                        <div class="flex items-center justify-end gap-2 pt-1 border-t border-amber-200/60">
                                            <form method="POST" action="{{ route('admin.discount-requests.approve', $req) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] rounded-md shadow-xs">
                                                    ✓ Approve
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.discount-requests.reject', $req) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="px-3 py-1 bg-rose-600 hover:bg-rose-700 text-white font-bold text-[10px] rounded-md shadow-xs">
                                                    ✕ Reject
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @empty
                                    <div class="p-6 text-center text-xs text-slate-400 font-semibold space-y-1">
                                        <div class="text-2xl">✨</div>
                                        <p>No pending discount alerts.</p>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <!-- User Account Dropdown -->
                    <div class="relative" x-data="{ userMenuOpen: false }">
                        <button @click="userMenuOpen = !userMenuOpen" 
                                class="flex items-center gap-3 p-1.5 pr-2.5 rounded-xl hover:bg-slate-100 transition-colors border border-transparent hover:border-slate-200">
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-600 to-purple-600 text-white flex items-center justify-center font-extrabold text-xs shadow-sm">
                                {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 2)) }}
                            </div>
                            <div class="hidden md:block text-left">
                                <p class="text-xs font-bold text-slate-900 leading-tight">{{ Auth::user()->name }}</p>
                                <p class="text-[10px] font-extrabold text-indigo-600 uppercase tracking-wider">{{ Auth::user()->roles->first()->name ?? 'User' }}</p>
                            </div>
                            <svg class="w-4 h-4 text-slate-400 hidden md:inline transition-transform duration-200" :class="{ 'rotate-180': userMenuOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>

                        <div x-show="userMenuOpen" 
                             @click.outside="userMenuOpen = false"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95 translate-y-1"
                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                             x-transition:leave-end="opacity-0 scale-95 translate-y-1"
                             class="absolute right-0 mt-3 w-60 bg-white rounded-2xl shadow-2xl border border-slate-200 py-2 z-50 divide-y divide-slate-100"
                             x-cloak>
                            <div class="px-4 py-3">
                                <p class="text-xs font-bold text-slate-900">{{ Auth::user()->name }}</p>
                                <p class="text-[11px] text-slate-500 truncate mt-0.5">{{ Auth::user()->email }}</p>
                            </div>
                            
                            <div class="py-1">
                                <a href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 transition-colors">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                    <span>Profile Settings</span>
                                </a>

                                @if(Auth::user()->hasRole('admin'))
                                <a href="{{ route('admin.users.index') }}" class="flex items-center gap-2.5 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 transition-colors">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                    <span>Users & Permissions</span>
                                </a>
                                @endif
                            </div>

                            <div class="py-1">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full text-left flex items-center gap-2.5 px-4 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 transition-colors">
                                        <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                                        <span>Sign Out</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                </div>

            </header>

            <!-- Floating Toast Notification -->
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
                 class="fixed top-6 right-6 z-50 max-w-md w-full shadow-2xl bg-white rounded-2xl border border-slate-200 overflow-hidden pointer-events-auto"
                 x-cloak>
                
                @if(session('success'))
                    <div class="p-4 bg-white border-l-4 border-emerald-500 flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <div class="p-1.5 rounded-lg bg-emerald-100 text-emerald-600 shrink-0 mt-0.5">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            </div>
                            <div>
                                <h4 class="text-xs font-black uppercase text-emerald-800 tracking-wider">Success</h4>
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
                            <div class="p-1.5 rounded-lg bg-rose-100 text-rose-600 shrink-0 mt-0.5">
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

            <!-- Light Luxury Footer -->
            <footer class="py-4 px-8 border-t border-slate-200 bg-white text-center text-xs font-semibold text-slate-400">
                &copy; {{ date('Y') }} {{ $appSetting->brand_name ?? 'Jugnu Saloon' }} &bull; Executive Saloon Management Suite
            </footer>

        </div>
    </div>
</body>
</html>
