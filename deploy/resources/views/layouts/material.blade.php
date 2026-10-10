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

    <!-- Alpine JS fallback (only if not bundled) -->
    <script>
        if (typeof window.Alpine === 'undefined') {
            const s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js';
            s.defer = true;
            document.head.appendChild(s);
        }
    </script>

    <style>
        @media (min-width: 1024px) {
            #app-sidebar {
                z-index: 10 !important;
            }
        }
        header.sticky {
            z-index: 20 !important;
        }
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
            /* General resets for full-page multi-sheet printing */
            html, body {
                height: auto !important;
                min-height: 100% !important;
                overflow: visible !important;
                background: #ffffff !important;
                color: #000000 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* Unclip flex wrappers so multi-page tables can paginate seamlessly */
            .min-h-screen,
            .flex-1,
            .overflow-hidden,
            .overflow-y-auto,
            .overflow-x-auto {
                overflow: visible !important;
                height: auto !important;
                max-height: none !important;
                min-height: 0 !important;
            }

            /* Hide non-printable app shell elements */
            aside, header, nav, footer, .print\:hidden, form, button, .no-print, [x-cloak] {
                display: none !important;
            }

            main {
                display: block !important;
                overflow: visible !important;
                height: auto !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                background: #ffffff !important;
            }

            .print\:block {
                display: block !important;
            }
            .print\:flex {
                display: flex !important;
            }

            /* Print Report Header */
            .report-print-header {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                border-bottom: 2px solid #0f172a !important;
                padding-bottom: 1rem !important;
                margin-bottom: 1.5rem !important;
                width: 100% !important;
            }

            /* Table Formatting & Pagination */
            table {
                width: 100% !important;
                border-collapse: collapse !important;
                page-break-inside: auto !important;
                font-size: 8.5pt !important;
            }
            tr {
                page-break-inside: avoid !important;
                page-break-after: auto !important;
            }
            thead {
                display: table-header-group !important;
            }
            tfoot {
                display: table-footer-group !important;
            }
            th, td {
                border: 0.5pt solid #cbd5e1 !important;
                padding: 4px 6px !important;
                font-size: 8.5pt !important;
                color: #000000 !important;
            }
            th {
                background-color: #f1f5f9 !important;
                color: #000000 !important;
                font-weight: 800 !important;
                text-transform: uppercase !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* Crystal Reports Print Engine Rules */
            .crystal-container {
                font-family: 'Segoe UI', Tahoma, Arial, sans-serif !important;
                color: #000000 !important;
                background: #ffffff !important;
            }
            .crystal-double-rule {
                border: none !important;
                border-top: 1pt solid #000000 !important;
                border-bottom: 2.5pt solid #000000 !important;
                height: 4px !important;
                margin: 6px 0 10px 0 !important;
            }
            .crystal-title-banner {
                background-color: #f1f5f9 !important;
                border: 1pt solid #94a3b8 !important;
                text-align: center !important;
                padding: 4px 8px !important;
                margin-bottom: 8px !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .crystal-title-banner h2 {
                font-size: 11pt !important;
                font-weight: 900 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5px !important;
                color: #0f172a !important;
                margin: 0 !important;
            }
            .crystal-title-banner p {
                font-size: 7.5pt !important;
                font-weight: 700 !important;
                color: #475569 !important;
                margin: 2px 0 0 0 !important;
                text-transform: uppercase !important;
            }
            .crystal-param-grid {
                width: 100% !important;
                border-collapse: collapse !important;
                margin-bottom: 10px !important;
                font-size: 7.5pt !important;
            }
            .crystal-param-grid td {
                border: 0.5pt solid #cbd5e1 !important;
                padding: 3px 6px !important;
                color: #0f172a !important;
            }
            .crystal-param-grid td.label {
                background-color: #f8fafc !important;
                font-weight: 700 !important;
                color: #334155 !important;
                width: 15% !important;
                text-transform: uppercase !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .crystal-param-grid td.val {
                font-weight: 600 !important;
                color: #000000 !important;
                width: 35% !important;
            }
            .crystal-grid {
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 8pt !important;
            }
            .crystal-grid th {
                background-color: #e2e8f0 !important;
                border-top: 1.5pt solid #000000 !important;
                border-bottom: 1.5pt solid #000000 !important;
                border-left: 0.5pt solid #cbd5e1 !important;
                border-right: 0.5pt solid #cbd5e1 !important;
                padding: 4px 5px !important;
                font-size: 7.5pt !important;
                font-weight: 800 !important;
                text-transform: uppercase !important;
                color: #000000 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .crystal-grid td {
                border: 0.5pt solid #cbd5e1 !important;
                padding: 3.5px 5px !important;
                font-size: 8pt !important;
                color: #000000 !important;
            }
            .crystal-grid tbody tr:nth-child(even) {
                background-color: #f8fafc !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .crystal-grid tfoot td {
                border-top: 1.5pt solid #000000 !important;
                border-bottom: 3.5pt double #000000 !important;
                border-left: 0.5pt solid #cbd5e1 !important;
                border-right: 0.5pt solid #cbd5e1 !important;
                padding: 5px 5px !important;
                font-size: 8pt !important;
                font-weight: 900 !important;
                background-color: #f1f5f9 !important;
                color: #000000 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .crystal-sign-band {
                display: flex !important;
                justify-content: space-between !important;
                align-items: flex-end !important;
                margin-top: 35px !important;
                padding-top: 10px !important;
                page-break-inside: avoid !important;
            }
            .crystal-sign-box {
                width: 28% !important;
                text-align: center !important;
                border-top: 1pt solid #000000 !important;
                padding-top: 4px !important;
                font-size: 7.5pt !important;
                font-weight: 700 !important;
                color: #000000 !important;
                text-transform: uppercase !important;
            }
            .crystal-audit-footer {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                border-top: 0.5pt solid #94a3b8 !important;
                margin-top: 14px !important;
                padding-top: 3px !important;
                font-size: 7pt !important;
                color: #475569 !important;
                page-break-inside: avoid !important;
            }

            /* Page Margins */
            @page {
                size: A4 portrait;
                margin: 8mm 10mm 10mm 10mm;
            }

            /* Thermal POS 80mm Receipt Mode */
            body.pos-receipt-active {
                width: 80mm !important;
                margin: 0 !important;
                padding: 0 !important;
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
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                margin-left: 0 !important;
                padding: 0 1.5mm !important;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
                font-size: 12px !important;
                line-height: 1.35 !important;
                color: #000000 !important;
                background: #ffffff !important;
                display: block !important;
                box-sizing: border-box !important;
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
                font-size: 12px !important;
                color: #000000 !important;
            }
        }
        [x-cloak] {
            display: none !important;
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
<body class="h-full antialiased text-slate-800 bg-slate-50 font-sans" 
      x-data="{ 
          sidebarOpen: false,
          sidebarCollapsed: localStorage.getItem('sidebar_collapsed') === 'true',
          toggleSidebar() {
              if (window.innerWidth >= 1024) {
                  this.sidebarCollapsed = !this.sidebarCollapsed;
                  localStorage.setItem('sidebar_collapsed', this.sidebarCollapsed);
              } else {
                  this.sidebarOpen = !this.sidebarOpen;
              }
          },
          handleShortcut(e) {
              if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'b') {
                  const tag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
                  if (tag === 'input' || tag === 'textarea' || document.activeElement?.isContentEditable) {
                      return;
                  }
                  e.preventDefault();
                  this.toggleSidebar();
              }
          }
      }"
      @resize.window="if (window.innerWidth >= 1024 && sidebarOpen) sidebarOpen = false"
      @keydown.escape.window="if (sidebarOpen) sidebarOpen = false"
      @keydown.window="handleShortcut($event)">
    <!-- Top Progress Indicator for Smooth Page Navigation -->
    <div id="app-page-progress" class="fixed top-0 left-0 right-0 h-[3px] z-[99999] pointer-events-none opacity-0 transition-opacity duration-200">
        <div id="app-page-progress-bar" class="h-full w-0 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500"></div>
    </div>

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
        <aside id="app-sidebar"
               :class="{
                   'translate-x-0': sidebarOpen, 
                   '-translate-x-full': !sidebarOpen,
                   'lg:w-0 lg:-translate-x-full lg:opacity-0 lg:border-r-0 lg:pointer-events-none': sidebarCollapsed,
                   'lg:w-72 lg:translate-x-0 lg:opacity-100 lg:border-r lg:pointer-events-auto': !sidebarCollapsed
               }"
               class="fixed inset-y-0 left-0 z-50 w-72 bg-white text-slate-800 border-r border-slate-200 transform transition-all duration-300 ease-in-out lg:static lg:inset-0 lg:z-10 flex flex-col shadow-2xl lg:shadow-sm select-none overflow-hidden shrink-0">
            
            <div class="w-72 min-w-[18rem] flex flex-col h-full shrink-0">
            
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
                <button @click="toggleSidebar()" 
                        type="button"
                        :title="sidebarCollapsed ? 'Show Sidebar' : 'Hide Sidebar'"
                        class="text-white/80 hover:text-white p-1.5 rounded-lg hover:bg-white/10 transition-colors flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path>
                    </svg>
                </button>
            </div>

            <!-- Navigation Links Container -->
            <nav class="flex-1 px-3 py-4 space-y-1.5 overflow-y-auto" 
                 x-data="{ 
                     menuQuery: '',
                     openMenus: {
                         services: {{ request()->routeIs('manager.services.*') || request()->routeIs('manager.service-categories.*') || request()->routeIs('manager.galleries.*') ? 'true' : 'false' }},
                         inventory: {{ request()->routeIs('manager.products.*') || request()->routeIs('manager.product-categories.*') || request()->routeIs('manager.sales.*') || request()->routeIs('manager.purchases.*') || request()->routeIs('manager.stores.*') || request()->routeIs('manager.store-stocks.*') || request()->routeIs('manager.stock-transfers.*') ? 'true' : 'false' }},
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

                    <!-- Appointments: New Bill Terminal -->
                    @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-appointments') || Auth::user()->hasPermission('book-appointment'))
                    <a href="{{ route('manager.appointments.create') }}" 
                       x-show="!menuQuery || 'appointments booking pos billing new bill terminal checkout'.includes(menuQuery.toLowerCase())"
                       class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold transition-all duration-200 group {{ (request()->routeIs('manager.appointments.create') || request()->routeIs('manager.appointments.edit')) ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-extrabold' : 'text-slate-700 hover:bg-slate-100 hover:text-indigo-600' }}">
                        <div class="flex items-center gap-3">
                            <div class="p-1.5 rounded-lg {{ (request()->routeIs('manager.appointments.create') || request()->routeIs('manager.appointments.edit')) ? 'bg-white/20 text-white' : 'bg-indigo-50 text-indigo-600 group-hover:scale-105' }} transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            </div>
                            <span>New Bill Terminal</span>
                        </div>
                        <span class="px-1.5 py-0.5 rounded-md text-[10px] font-black {{ (request()->routeIs('manager.appointments.create') || request()->routeIs('manager.appointments.edit')) ? 'bg-white/20 text-white' : 'bg-indigo-100 text-indigo-800' }}">
                            + POS
                        </span>
                    </a>

                    <!-- Appointments: Bookings History -->
                    <a href="{{ route('manager.appointments.index') }}" 
                       x-show="!menuQuery || 'appointments booking history calendar records schedule'.includes(menuQuery.toLowerCase())"
                       class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold transition-all duration-200 group {{ request()->routeIs('manager.appointments.index') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-extrabold' : 'text-slate-700 hover:bg-slate-100 hover:text-indigo-600' }}">
                        <div class="flex items-center gap-3">
                            <div class="p-1.5 rounded-lg {{ request()->routeIs('manager.appointments.index') ? 'bg-white/20 text-white' : 'bg-amber-50 text-amber-600 group-hover:scale-105' }} transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                            </div>
                            <span>Bookings History</span>
                        </div>
                        <span class="px-1.5 py-0.5 rounded-md text-[10px] font-black {{ request()->routeIs('manager.appointments.index') ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800' }}">
                            History
                        </span>
                    </a>
                    @endif

                    <!-- Staff Feedback & Ratings -->
                    @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-ratings') || Auth::user()->hasPermission('manage-appointments'))
                    <a href="{{ route('manager.ratings.index') }}" 
                       x-show="!menuQuery || 'staff feedback ratings reviews stylists performance'.includes(menuQuery.toLowerCase())"
                       class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold transition-all duration-200 group {{ request()->routeIs('manager.ratings.*') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-extrabold' : 'text-slate-700 hover:bg-slate-100 hover:text-indigo-600' }}">
                        <div class="flex items-center gap-3">
                            <div class="p-1.5 rounded-lg {{ request()->routeIs('manager.ratings.*') ? 'bg-white/20 text-white' : 'bg-amber-50 text-amber-600 group-hover:scale-105' }} transition-transform">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                            </div>
                            <span>Staff Feedback & Rating</span>
                        </div>
                        <span class="px-1.5 py-0.5 rounded-md text-[10px] font-black {{ request()->routeIs('manager.ratings.*') ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800' }}">
                            ⭐ 5.0
                        </span>
                    </a>
                    @endif

                    <!-- Profit & Loss Report Direct Button -->
                    @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-profit-report'))
                    <a href="{{ route('manager.reports.profit') }}" 
                       x-show="!menuQuery || 'profit loss profit-report p&l revenue earnings margins business'.includes(menuQuery.toLowerCase())"
                       class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold transition-all duration-200 group {{ request()->routeIs('manager.reports.profit') ? 'bg-emerald-600 text-white shadow-md shadow-emerald-100 font-extrabold' : 'text-slate-700 hover:bg-slate-100 hover:text-emerald-600' }}">
                        <div class="flex items-center gap-3">
                            <div class="p-1.5 rounded-lg {{ request()->routeIs('manager.reports.profit') ? 'bg-white/20 text-white' : 'bg-emerald-50 text-emerald-600 group-hover:scale-105' }} transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <span>Profit Report</span>
                        </div>
                        <span class="px-1.5 py-0.5 rounded-md text-[10px] font-black {{ request()->routeIs('manager.reports.profit') ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-800' }}">
                            P&L
                        </span>
                    </a>
                    @endif

                    <!-- Daily Day Closing Direct Button -->
                    @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-day-closing'))
                    <a href="{{ route('manager.reports.day-closing') }}" 
                       x-show="!menuQuery || 'day closing daily register cash bank balance reconciliation settlement'.includes(menuQuery.toLowerCase())"
                       class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold transition-all duration-200 group {{ request()->routeIs('manager.reports.day-closing*') ? 'bg-teal-600 text-white shadow-md shadow-teal-100 font-extrabold' : 'text-slate-700 hover:bg-slate-100 hover:text-teal-600' }}">
                        <div class="flex items-center gap-3">
                            <div class="p-1.5 rounded-lg {{ request()->routeIs('manager.reports.day-closing*') ? 'bg-white/20 text-white' : 'bg-teal-50 text-teal-600 group-hover:scale-105' }} transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                            <span>Day Closing</span>
                        </div>
                        <span class="px-1.5 py-0.5 rounded-md text-[10px] font-black {{ request()->routeIs('manager.reports.day-closing*') ? 'bg-white/20 text-white' : 'bg-teal-100 text-teal-800' }}">
                            Daily
                        </span>
                    </a>
                    @endif
                </div>

                <!-- SECTION 2: SALOON OPERATIONS -->
                @if(Auth::user()->hasRole('admin') || Auth::user()->hasAnyPermission(['manage-services', 'manage-service-categories', 'manage-gallery']))
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
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-service-categories') || Auth::user()->hasPermission('manage-services'))
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
                @if(Auth::user()->hasRole('admin') || Auth::user()->hasAnyPermission(['manage-sales', 'manage-products', 'manage-product-categories', 'manage-purchases', 'manage-stores', 'manage-store-stocks', 'manage-stock-transfers']))
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

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-product-categories') || Auth::user()->hasPermission('manage-products'))
                        <a href="{{ route('manager.product-categories.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.product-categories.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.product-categories.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Product Categories</span>
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

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-stores'))
                        <a href="{{ route('manager.stores.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.stores.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.stores.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Store Locations / Branches</span>
                        </a>
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-store-stocks'))
                        <a href="{{ route('manager.store-stocks.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.store-stocks.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.store-stocks.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Store-Wise Stock Ledger</span>
                        </a>
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-stock-transfers'))
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
                @if(Auth::user()->hasRole('admin') || Auth::user()->hasAnyPermission(['manage-accounts', 'manage-account-categories', 'manage-ledger', 'manage-expenses', 'manage-expense-categories', 'manage-payroll', 'manage-payroll-deductions', 'manage-payroll-advances', 'manage-attendance', 'manage-commissions']))
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
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-account-categories') || Auth::user()->hasPermission('manage-accounts'))
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
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-expense-categories') || Auth::user()->hasPermission('manage-expenses'))
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
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-payroll-deductions') || Auth::user()->hasPermission('manage-payroll'))
                        <a href="{{ route('manager.payroll.deductions.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.payroll.deductions.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.payroll.deductions.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Salary Deductions</span>
                        </a>
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-payroll-advances') || Auth::user()->hasPermission('manage-payroll'))
                        <a href="{{ route('manager.payroll.advances.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.payroll.advances.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.payroll.advances.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Advance Salary</span>
                        </a>
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-attendance'))
                        <a href="{{ route('manager.attendance.index') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.attendance.*') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.attendance.*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Staff Attendance</span>
                        </a>
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-commissions') || Auth::user()->hasPermission('manage-payroll'))
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
                @if(Auth::user()->hasRole('admin') || Auth::user()->hasAnyPermission(['view-profit-report', 'manage-day-closing', 'view-sales-reports', 'view-stock-reports', 'view-service-reports', 'view-ledger-reports', 'view-purchase-reports', 'view-reports']))
                <div class="pt-2 space-y-1" x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())">
                    <p class="px-3 pt-1 pb-0.5 text-[10px] font-black uppercase tracking-wider text-slate-400">Business Reports</p>
                    
                    <!-- Direct Profit Report Button -->
                    @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-profit-report'))
                    <a href="{{ route('manager.reports.profit') }}" 
                       x-show="!menuQuery || 'profit loss profit-report p&l revenue earnings margins business'.includes(menuQuery.toLowerCase())"
                       class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold transition-all duration-200 group {{ request()->routeIs('manager.reports.profit') ? 'bg-emerald-600 text-white shadow-md shadow-emerald-100 font-extrabold' : 'text-slate-700 hover:bg-slate-100 hover:text-emerald-600' }}">
                        <div class="flex items-center gap-3">
                            <div class="p-1.5 rounded-lg {{ request()->routeIs('manager.reports.profit') ? 'bg-white/20 text-white' : 'bg-emerald-50 text-emerald-600 group-hover:scale-105' }} transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <span>Profit & Loss Report</span>
                        </div>
                        <span class="px-1.5 py-0.5 rounded-md text-[10px] font-black {{ request()->routeIs('manager.reports.profit') ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-800' }}">
                            P&L
                        </span>
                    </a>
                    @endif

                    <!-- Direct Day Closing Button -->
                    @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-day-closing'))
                    <a href="{{ route('manager.reports.day-closing') }}" 
                       x-show="!menuQuery || 'day closing daily register cash bank balance reconciliation settlement'.includes(menuQuery.toLowerCase())"
                       class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold transition-all duration-200 group {{ request()->routeIs('manager.reports.day-closing*') ? 'bg-teal-600 text-white shadow-md shadow-teal-100 font-extrabold' : 'text-slate-700 hover:bg-slate-100 hover:text-teal-600' }}">
                        <div class="flex items-center gap-3">
                            <div class="p-1.5 rounded-lg {{ request()->routeIs('manager.reports.day-closing*') ? 'bg-white/20 text-white' : 'bg-teal-50 text-teal-600 group-hover:scale-105' }} transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                            <span>Daily Day Closing</span>
                        </div>
                        <span class="px-1.5 py-0.5 rounded-md text-[10px] font-black {{ request()->routeIs('manager.reports.day-closing*') ? 'bg-white/20 text-white' : 'bg-teal-100 text-teal-800' }}">
                            Cash/Bank
                        </span>
                    </a>
                    @endif

                    <button type="button" 
                            @click="toggleMenu('reports')" 
                            class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition-all group">
                        <div class="flex items-center gap-3">
                            <div class="p-1.5 rounded-lg bg-rose-50 text-rose-600 group-hover:scale-105 transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                            </div>
                            <span>Other Reports & Analytics</span>
                        </div>
                        <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': openMenus.reports || menuQuery.length > 0 }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <div x-show="openMenus.reports || menuQuery.length > 0" class="pl-6 pr-1 space-y-1 mt-1 border-l-2 border-rose-200 ml-4">
                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-day-closing'))
                        <a href="{{ route('manager.reports.day-closing') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.reports.day-closing*') ? 'bg-teal-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-teal-600 hover:bg-teal-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.reports.day-closing*') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Day Closing & Cash</span>
                        </a>
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-profit-report'))
                        <a href="{{ route('manager.reports.profit') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.reports.profit') ? 'bg-emerald-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-emerald-600 hover:bg-emerald-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.reports.profit') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Profit & Loss Report</span>
                        </a>
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-sales-reports') || Auth::user()->hasPermission('view-reports'))
                        <a href="{{ route('manager.reports.sales') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.reports.sales') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.reports.sales') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Sales Reports</span>
                        </a>
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-stock-reports') || Auth::user()->hasPermission('view-reports'))
                        <a href="{{ route('manager.reports.stock') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.reports.stock') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.reports.stock') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Stock Inventory</span>
                        </a>
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-service-reports') || Auth::user()->hasPermission('view-reports'))
                        <a href="{{ route('manager.reports.services') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.reports.services') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.reports.services') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Service Reports</span>
                        </a>
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-ledger-reports') || Auth::user()->hasPermission('view-reports'))
                        <a href="{{ route('manager.reports.ledger') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.reports.ledger') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.reports.ledger') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Ledger Reports</span>
                        </a>
                        @endif

                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-purchase-reports') || Auth::user()->hasPermission('view-reports'))
                        <a href="{{ route('manager.reports.purchases') }}" 
                           x-show="!menuQuery || $el.innerText.toLowerCase().includes(menuQuery.toLowerCase())"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('manager.reports.purchases') ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/70' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('manager.reports.purchases') ? 'bg-white' : 'bg-slate-300' }}"></span>
                            <span>Purchases Reports</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endif

                <!-- SECTION 6: SETTINGS & ADMIN -->
                @if(Auth::user()->hasRole('admin') || Auth::user()->hasAnyPermission(['manage-settings', 'manage-bank-accounts', 'manage-users', 'manage-roles']))
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
                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-users') || Auth::user()->hasPermission('manage-roles'))
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
            </div>
        </aside>

        <!-- Main Workspace Area -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-50">
            
            <!-- Topbar Header -->
            <header class="h-20 bg-white border-b border-slate-200/80 sticky top-0 z-30 flex items-center justify-between px-3 sm:px-6 lg:px-8 shadow-xs">
                
                <div class="flex items-center gap-2 sm:gap-4 min-w-0">
                    <!-- Sidebar Toggle Button (Desktop & Mobile) -->
                    <button @click="toggleSidebar()" 
                            type="button"
                            :title="sidebarCollapsed ? 'Show Sidebar (Ctrl+B)' : 'Hide Sidebar (Ctrl+B)'" 
                            aria-label="Toggle Sidebar"
                            class="p-2 sm:px-3 sm:py-2 rounded-xl text-slate-600 hover:text-indigo-600 hover:bg-indigo-50 border border-slate-200/80 hover:border-indigo-200 transition-all shadow-2xs flex items-center gap-2 group shrink-0">
                        <svg class="w-5 h-5 transition-transform duration-300 text-slate-600 group-hover:text-indigo-600" 
                             :class="sidebarCollapsed ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path>
                        </svg>
                        <span class="hidden sm:inline text-xs font-bold text-slate-600 group-hover:text-indigo-600 transition-colors"
                              x-text="sidebarCollapsed ? 'Show Sidebar' : 'Hide Sidebar'"></span>
                    </button>

                    <!-- Salon Status -->
                    <div class="hidden md:flex items-center gap-2 text-xs truncate">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 font-bold border border-emerald-200/60 shrink-0">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                            Live Salon System
                        </span>
                        <span class="text-slate-300 hidden xl:inline">•</span>
                        <span class="text-slate-500 font-semibold hidden xl:inline">{{ now()->format('l, F j, Y') }}</span>
                    </div>
                </div>

                <!-- Right Topbar Action Hub -->
                <div class="flex items-center gap-1.5 sm:gap-3 shrink-0">
                    
                    <!-- Quick Direct Action: New POS Sale -->
                    @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-sales'))
                    <a href="{{ route('manager.sales.index') }}" 
                       class="hidden md:inline-flex items-center gap-2 px-3 py-2 sm:px-3.5 sm:py-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 font-extrabold text-xs border border-emerald-200/80 transition-all shadow-2xs">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        <span class="hidden lg:inline">POS Sale</span>
                    </a>
                    @endif

                    <!-- Quick Direct Action: New Appointment -->
                    @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-appointments') || Auth::user()->hasPermission('book-appointment'))
                    <a href="{{ route('manager.appointments.create') }}" 
                       class="hidden sm:inline-flex items-center gap-1.5 sm:gap-2 px-3 py-2 sm:px-3.5 sm:py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-sm shadow-indigo-200 transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        <span>+ Booking</span>
                    </a>
                    @endif

                    <!-- Online Booking Notifications Dropdown -->
                    @php
                        $pendingOnlineQuery = \App\Models\Appointment::where('order_type', 'Online')
                            ->where(function($q) {
                                $q->where('status', 'pending')->orWhereNull('status');
                            });
                        $pendingOnlineCount = (clone $pendingOnlineQuery)->count();
                        $onlineNotifications = (clone $pendingOnlineQuery)
                            ->with(['customer'])
                            ->orderBy('id', 'desc')
                            ->take(10)
                            ->get();
                    @endphp
                    <div class="relative" x-data="{ notifOpen: false }">
                        <button @click="notifOpen = !notifOpen" 
                                class="relative p-2 sm:p-2.5 rounded-xl hover:bg-slate-100 transition-colors border border-slate-200 text-slate-600 hover:text-indigo-600 cursor-pointer"
                                title="Website Online Appointments & Notifications">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                            @if($pendingOnlineCount > 0)
                                <span class="absolute -top-1 -right-1 flex h-5 min-w-5 px-1 items-center justify-center rounded-full bg-rose-600 text-[10px] font-black text-white ring-2 ring-white shadow-xs animate-pulse">
                                    {{ $pendingOnlineCount > 99 ? '99+' : $pendingOnlineCount }}
                                </span>
                            @endif
                        </button>

                        <div x-show="notifOpen" 
                             @click.outside="notifOpen = false"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95 translate-y-1"
                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                             x-transition:leave-end="opacity-0 scale-95 translate-y-1"
                             class="absolute right-0 mt-3 w-[calc(100vw-2rem)] max-w-sm sm:w-96 bg-white rounded-2xl shadow-2xl border border-slate-200 py-0 z-50 overflow-hidden"
                             x-cloak>
                            
                            <!-- Header -->
                            <div class="px-4 py-3 bg-gradient-to-r from-slate-900 to-indigo-950 text-white flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-base">🌐</span>
                                    <div>
                                        <h4 class="text-xs font-black uppercase tracking-wider">Pending Online Bookings</h4>
                                        <p class="text-[10px] text-indigo-200 font-medium">{{ $pendingOnlineCount }} awaiting confirmation</p>
                                    </div>
                                </div>
                                <a href="{{ route('manager.appointments.index', ['order_type' => 'Online', 'status' => 'pending', 'tab' => 'history']) }}" 
                                   class="text-[10px] font-extrabold text-indigo-300 hover:text-white transition-colors underline">
                                    View All &rarr;
                                </a>
                            </div>

                            <!-- Bookings List (Only Pending) -->
                            <div class="max-h-80 overflow-y-auto divide-y divide-slate-100">
                                @forelse($onlineNotifications as $notifApt)
                                    @php
                                        $hasProof = !empty($notifApt->receipt_image);
                                    @endphp
                                    <a href="{{ route('manager.appointments.index', ['order_type' => 'Online', 'tab' => 'history', 'search' => $notifApt->booking_no]) }}" 
                                       class="block p-3.5 hover:bg-slate-50 transition-colors">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="space-y-0.5">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="text-xs font-black text-slate-900">#{{ $notifApt->booking_no }}</span>
                                                    <span class="px-1.5 py-0.5 text-[9px] font-black uppercase rounded-sm bg-amber-50 text-amber-800 border border-amber-300">
                                                        {{ $notifApt->status ?: 'Pending' }}
                                                    </span>
                                                </div>
                                                <p class="text-xs font-bold text-indigo-700">{{ $notifApt->customer->name ?? 'Online Client' }}</p>
                                                @if($notifApt->customer && $notifApt->customer->phone_no1)
                                                    <p class="text-[10px] text-slate-500 font-medium">{{ $notifApt->customer->phone_no1 }}</p>
                                                @endif
                                            </div>
                                            <div class="text-right shrink-0">
                                                <span class="text-xs font-black text-slate-900 block">PKR {{ number_format($notifApt->net_amount, 2) }}</span>
                                                <span class="text-[10px] text-slate-500 font-medium block">
                                                    {{ $notifApt->appointment_date ? $notifApt->appointment_date->format('M d') : '' }} 
                                                    {{ $notifApt->start_time ? date('h:i A', strtotime($notifApt->start_time)) : '' }}
                                                </span>
                                            </div>
                                        </div>

                                        @if($hasProof)
                                            <div class="mt-2 flex items-center gap-1.5 text-[10px] font-bold text-emerald-800 bg-emerald-50 px-2 py-1 rounded border border-emerald-200">
                                                <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                <span>Payment Slip Attached by Client</span>
                                            </div>
                                        @endif
                                    </a>
                                @empty
                                    <div class="p-6 text-center text-slate-400 text-xs">
                                        <div class="text-2xl mb-1">✨</div>
                                        <p class="font-bold text-slate-600">No Pending Online Bookings</p>
                                        <p class="text-[10px] text-slate-400 mt-0.5">All online appointments have been processed or confirmed.</p>
                                    </div>
                                @endforelse
                            </div>

                            <!-- Footer -->
                            <div class="p-2.5 bg-slate-50 border-t border-slate-100 text-center">
                                <a href="{{ route('manager.appointments.index', ['order_type' => 'Online', 'status' => 'pending', 'tab' => 'history']) }}" 
                                   class="text-xs font-black text-indigo-600 hover:text-indigo-800 transition-colors inline-flex items-center gap-1">
                                    <span>Manage Pending Online Bookings</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                </a>
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
                 class="fixed top-4 right-4 sm:top-6 sm:right-6 z-50 max-w-[calc(100vw-2rem)] sm:max-w-md w-full shadow-2xl bg-white rounded-2xl border border-slate-200 overflow-hidden pointer-events-auto"
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
            <main id="mainContent" class="flex-1 overflow-y-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-6 page-smooth-enter max-w-[1920px] w-full mx-auto">
                @yield('content')
                {{ $slot ?? '' }}
            </main>

            <!-- Light Luxury Footer -->
            <footer class="py-4 px-4 sm:px-8 border-t border-slate-200 bg-white text-center text-xs font-semibold text-slate-400">
                &copy; {{ date('Y') }} {{ $appSetting->brand_name ?? 'Jugnu Saloon' }} &bull; Executive Saloon Management Suite
            </footer>

        </div>
    </div>
</body>
</html>
