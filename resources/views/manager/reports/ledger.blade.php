@extends('layouts.material')

@section('title', 'Ledger Report (Account Wise)')

@section('content')
@php
    $accountsPayload = $accounts->map(function($a) {
        $catTitle = $a->category->title ?? 'General';
        $lowerCat = strtolower($catTitle);
        $type = 'other';
        if (str_contains($lowerCat, 'customer') || str_contains($lowerCat, 'client')) {
            $type = 'customer';
        } elseif (str_contains($lowerCat, 'supplier') || str_contains($lowerCat, 'vendor')) {
            $type = 'supplier';
        } elseif (str_contains($lowerCat, 'staff') || str_contains($lowerCat, 'employee') || !empty($a->emp_type)) {
            $type = 'staff';
        }
        return [
            'id' => (int) $a->id,
            'name' => $a->name,
            'phone' => (string) ($a->phone_no1 ?: ($a->phone_no2 ?: '')),
            'category' => $catTitle,
            'type' => $type,
            'balance' => (float) $a->balance,
        ];
    })->values();

    $selectedPayload = $selectedAccount ? [
        'id' => (int) $selectedAccount->id,
        'name' => $selectedAccount->name,
        'phone' => (string) ($selectedAccount->phone_no1 ?: ($selectedAccount->phone_no2 ?: '')),
        'category' => $selectedAccount->category->title ?? 'General',
        'balance' => (float) $selectedAccount->balance,
    ] : null;
@endphp

<script>
window.ledgerAccountsData = @json($accountsPayload);
window.ledgerInitialSelected = @json($selectedPayload);

function ledgerAccountPicker(accountsList, initialSelected) {
    return {
        open: false,
        search: '',
        categoryTab: 'customer',
        selectedId: initialSelected ? initialSelected.id : '',
        selectedAccount: initialSelected || null,
        accounts: accountsList || [],
        highlightedIndex: 0,

        get filteredAccounts() {
            const q = this.search.trim().toLowerCase();
            return this.accounts.filter(acc => {
                if (this.categoryTab === 'customer' && acc.type !== 'customer') return false;
                if (this.categoryTab === 'supplier' && acc.type !== 'supplier') return false;
                if (this.categoryTab === 'staff' && acc.type !== 'staff') return false;

                if (!q) return true;
                return (
                    acc.name.toLowerCase().includes(q) ||
                    (acc.phone && acc.phone.includes(q)) ||
                    (acc.category && acc.category.toLowerCase().includes(q))
                );
            });
        },

        get counts() {
            return {
                all: this.accounts.length,
                customer: this.accounts.filter(a => a.type === 'customer').length,
                supplier: this.accounts.filter(a => a.type === 'supplier').length,
                staff: this.accounts.filter(a => a.type === 'staff').length,
            };
        },

        selectAccount(acc) {
            this.selectedAccount = acc;
            this.selectedId = acc ? acc.id : '';
            this.open = false;
            this.search = '';

            const hiddenInput = document.getElementById('ledgerHiddenAccountId');
            if (hiddenInput) {
                hiddenInput.value = this.selectedId;
            }

            const form = document.getElementById('ledgerReportFilterForm');
            if (form) {
                form.submit();
            }
        },

        clearSelection() {
            this.selectedAccount = null;
            this.selectedId = '';
            this.search = '';
            this.open = false;

            const hiddenInput = document.getElementById('ledgerHiddenAccountId');
            if (hiddenInput) {
                hiddenInput.value = '';
            }

            const form = document.getElementById('ledgerReportFilterForm');
            if (form) {
                form.submit();
            }
        },

        toggleDropdown() {
            this.open = !this.open;
            if (this.open) {
                this.highlightedIndex = 0;
                this.$nextTick(() => {
                    if (this.$refs.searchInput) {
                        this.$refs.searchInput.focus();
                    }
                });
            }
        },

        closeDropdown() {
            this.open = false;
            this.search = '';
        },

        onKeyDown(e) {
            if (!this.open) {
                if (e.key === 'ArrowDown' || e.key === 'Enter') {
                    this.toggleDropdown();
                    e.preventDefault();
                }
                return;
            }

            const list = this.filteredAccounts;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (this.highlightedIndex < list.length - 1) {
                    this.highlightedIndex++;
                    this.scrollToHighlighted();
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (this.highlightedIndex > 0) {
                    this.highlightedIndex--;
                    this.scrollToHighlighted();
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (list[this.highlightedIndex]) {
                    this.selectAccount(list[this.highlightedIndex]);
                }
            } else if (e.key === 'Escape') {
                this.closeDropdown();
            }
        },

        scrollToHighlighted() {
            this.$nextTick(() => {
                const listEl = this.$refs.dropdownList;
                if (listEl && listEl.children[this.highlightedIndex]) {
                    listEl.children[this.highlightedIndex].scrollIntoView({ block: 'nearest' });
                }
            });
        }
    };
}
</script>

<div class="space-y-6">

    <!-- CRYSTAL REPORTS OFFICIAL DOCUMENT HEADER (PRINT ONLY) -->
    <div class="hidden print:block crystal-container mb-4">
        <!-- Top Company Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                @if(isset($appSetting) && $appSetting->brand_logo)
                    <img src="{{ asset($appSetting->brand_logo) }}" class="h-12 w-auto object-contain">
                @endif
                <div>
                    <h1 class="text-xl font-black uppercase text-slate-900 tracking-tight">{{ $appSetting->brand_name ?? 'Jugnu Saloon' }}</h1>
                    @if(isset($appSetting) && $appSetting->brand_slogan)
                        <p class="text-[10px] font-bold text-slate-600 italic leading-none">{{ $appSetting->brand_slogan }}</p>
                    @endif
                    @if(isset($appSetting) && $appSetting->brand_address)
                        <p class="text-[9px] text-slate-500 mt-0.5 leading-tight">{{ $appSetting->brand_address }}</p>
                    @endif
                    @if(isset($appSetting) && ($appSetting->brand_phone1 || $appSetting->brand_phone2))
                        <p class="text-[9px] font-bold text-slate-700 mt-0.5">
                            Tel: {{ implode(' | ', array_filter([$appSetting->brand_phone1, $appSetting->brand_phone2])) }}
                        </p>
                    @endif
                </div>
            </div>
            <div class="text-right text-[8.5pt]">
                <div class="font-bold text-slate-800 uppercase tracking-wide">General Ledger & Accounts</div>
                <div class="text-[7.5pt] text-slate-500">Official Financial Audit Statement</div>
                <div class="text-[7.5pt] font-semibold text-slate-600 mt-0.5">Doc ID: RPT-LDG-{{ now()->format('YmdHi') }}</div>
            </div>
        </div>

        <!-- Double Divider Rule -->
        <div class="crystal-double-rule"></div>

        <!-- Title Banner -->
        <div class="crystal-title-banner">
            <h2>{{ $selectedAccount ? 'INDIVIDUAL ACCOUNT STATEMENT OF LEDGER' : 'GENERAL ACCOUNT STATEMENT OF LEDGER' }}</h2>
            <p>{{ $selectedAccount ? $selectedAccount->name . ' (ID: #' . $selectedAccount->id . ')' : 'All Accounts & Clients Consolidated Statement' }}</p>
        </div>

        <!-- Crystal Reports Parameters Matrix -->
        <table class="crystal-param-grid">
            <tr>
                <td class="label">Account Name:</td>
                <td class="val font-bold">{{ $selectedAccount ? $selectedAccount->name : 'All Accounts & Clients' }}</td>
                <td class="label">Category / Nature:</td>
                <td class="val">{{ $selectedAccount && $selectedAccount->category ? $selectedAccount->category->title : 'Consolidated Audit' }}</td>
            </tr>
            <tr>
                <td class="label">Statement Period:</td>
                <td class="val">{{ $startDate ? \Carbon\Carbon::parse($startDate)->format('M d, Y') : 'Beginning' }} — {{ $endDate ? \Carbon\Carbon::parse($endDate)->format('M d, Y') : 'Present' }}</td>
                <td class="label">Opening Balance:</td>
                <td class="val font-mono">{{ $selectedAccount ? 'PKR ' . number_format($openingBalance, 2) : 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">{{ $selectedAccount ? 'Current Balance:' : 'Total Debit / Credit:' }}</td>
                <td class="val font-mono font-bold">{{ $selectedAccount ? 'PKR ' . number_format($selectedAccount->balance, 2) : 'PKR ' . number_format($totalDebit, 2) . ' / PKR ' . number_format($totalCredit, 2) }}</td>
                <td class="label">Generated By:</td>
                <td class="val">{{ Auth::user()->name }} | {{ now()->format('d-M-Y h:i A') }}</td>
            </tr>
        </table>
    </div>

    <!-- Header & Reports Navigation Tabs (WEB ONLY) -->
    <div class="bg-white border border-slate-200 shadow-sm p-6 space-y-6 print:hidden">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-indigo-50 text-indigo-600">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <div>
                    <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Business Intelligence & Reports</h1>
                    <p class="text-xs font-semibold text-slate-500 mt-0.5">Comprehensive sales, stock inventory, service bookings, account ledgers, and purchase reports.</p>
                </div>
            </div>

            <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-xs transition-colors cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                <span>Print Ledger</span>
            </button>
        </div>

        <!-- Reports Tabs -->
        <div class="flex border-b border-slate-200 overflow-x-auto gap-2 text-xs font-bold">
            @if(Auth::user()->hasRole('admin'))
            <a href="{{ route('manager.reports.profit') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.profit') ? 'border-emerald-600 text-emerald-700 bg-emerald-50/50 font-extrabold' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>💰 Profit & Loss</span>
            </a>
            <a href="{{ route('manager.reports.aesthetic-profit') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.aesthetic-profit') ? 'border-rose-600 text-rose-700 bg-rose-50/50 font-extrabold' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>Aesthetic Profit</span>
            </a>
            <a href="{{ route('manager.reports.day-closing') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.day-closing') ? 'border-teal-600 text-teal-700 bg-teal-50/50 font-extrabold' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>📅 Day Closing</span>
            </a>
            @endif
            <a href="{{ route('manager.reports.sales') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.sales') ? 'border-indigo-600 text-indigo-600 bg-indigo-50/40' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>📊 Sales Report</span>
            </a>
            <a href="{{ route('manager.reports.stock') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.stock') ? 'border-indigo-600 text-indigo-600 bg-indigo-50/40' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>📦 Stock Report</span>
            </a>
            <a href="{{ route('manager.reports.services') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.services') ? 'border-indigo-600 text-indigo-600 bg-indigo-50/40' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>💈 Service Booking Reports</span>
            </a>
            <a href="{{ route('manager.reports.ledger') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.ledger') ? 'border-indigo-600 text-indigo-600 bg-indigo-50/40' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>📗 Ledger Report</span>
            </a>
            <a href="{{ route('manager.reports.purchases') }}" class="py-3 px-4 flex items-center gap-2 border-b-2 transition-all whitespace-nowrap {{ request()->routeIs('manager.reports.purchases') ? 'border-indigo-600 text-indigo-600 bg-indigo-50/40' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                <span>🛒 Purchase Report</span>
            </a>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    @if($selectedAccount)
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 print:hidden">
        <div class="bg-white p-4 border border-slate-200 shadow-xs">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Opening Balance</span>
            <span class="text-xl font-black text-slate-700 mt-1 block">PKR {{ number_format($openingBalance, 2) }}</span>
        </div>
        <div class="bg-white p-4 border border-slate-200 shadow-xs">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Debit (+)</span>
            <span class="text-xl font-black text-indigo-600 mt-1 block">PKR {{ number_format($totalDebit, 2) }}</span>
        </div>
        <div class="bg-white p-4 border border-slate-200 shadow-xs">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Credit (-)</span>
            <span class="text-xl font-black text-emerald-600 mt-1 block">PKR {{ number_format($totalCredit, 2) }}</span>
        </div>
        <div class="bg-white p-4 border border-slate-200 shadow-xs">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Current Account Balance</span>
            <span class="text-xl font-black {{ $selectedAccount->balance >= 0 ? 'text-emerald-700' : 'text-rose-600' }} mt-1 block">PKR {{ number_format($selectedAccount->balance, 2) }}</span>
        </div>
    </div>
    @else
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 print:hidden">
        <div class="bg-white p-4 border border-slate-200 shadow-xs">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Statements</span>
            <span class="text-xl font-black text-slate-800 mt-1 block">{{ is_object($ledgers) && method_exists($ledgers, 'total') ? $ledgers->total() : count($ledgers) }} entries</span>
        </div>
        <div class="bg-white p-4 border border-slate-200 shadow-xs">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Debit (+)</span>
            <span class="text-xl font-black text-indigo-600 mt-1 block">PKR {{ number_format($totalDebit, 2) }}</span>
        </div>
        <div class="bg-white p-4 border border-slate-200 shadow-xs">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Credit (-)</span>
            <span class="text-xl font-black text-emerald-600 mt-1 block">PKR {{ number_format($totalCredit, 2) }}</span>
        </div>
        <div class="bg-white p-4 border border-slate-200 shadow-xs">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Net Period Movement</span>
            <span class="text-xl font-black {{ ($totalDebit - $totalCredit) >= 0 ? 'text-indigo-600' : 'text-rose-600' }} mt-1 block">PKR {{ number_format(abs($totalDebit - $totalCredit), 2) }}</span>
        </div>
    </div>
    @endif

    <!-- Filter Form Bar (WEB ONLY) -->
    <div class="bg-white p-4 border border-slate-200 shadow-xs print:hidden">
        <form id="ledgerReportFilterForm" method="GET" action="{{ route('manager.reports.ledger') }}">
            <div style="display: flex; flex-direction: row; align-items: flex-end; gap: 10px; width: 100%; flex-wrap: wrap;">
                
                <!-- 1. Customer / Account Searchable Combobox -->
                <div x-data="ledgerAccountPicker(window.ledgerAccountsData, window.ledgerInitialSelected)" 
                     @click.outside="closeDropdown()" 
                     @keydown="onKeyDown($event)"
                     class="relative"
                     style="flex: 3 1 260px; min-width: 220px;">
                    
                    <input type="hidden" name="account_id" id="ledgerHiddenAccountId" :value="selectedId">

                    <!-- Combobox Button / Trigger -->
                    <div>
                        <label class="block text-[10px] font-black text-slate-700 uppercase tracking-wider mb-1 flex items-center justify-between">
                            <span class="flex items-center gap-1 truncate">
                                <svg class="w-3.5 h-3.5 text-indigo-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                <span>Filter Customer / Account</span>
                            </span>
                            <template x-if="selectedAccount">
                                <button type="button" @click="clearSelection()" class="text-[9px] font-bold text-rose-600 hover:text-rose-800 underline cursor-pointer shrink-0">
                                    Show All
                                </button>
                            </template>
                        </label>

                        <div @click="toggleDropdown()" 
                             style="height: 40px;"
                             class="px-3 bg-slate-50 hover:bg-white border border-slate-300 hover:border-indigo-500 transition-all cursor-pointer flex items-center justify-between gap-2 shadow-2xs">
                            <div class="flex items-center gap-1.5 overflow-hidden flex-1 min-w-0">
                                <template x-if="selectedAccount">
                                    <div class="flex items-center gap-1.5 truncate w-full">
                                        <span class="px-1.5 py-0.5 bg-indigo-100 text-indigo-800 text-[9px] font-black uppercase shrink-0" x-text="selectedAccount.category"></span>
                                        <strong class="text-slate-900 text-xs font-black truncate" x-text="selectedAccount.name"></strong>
                                        <template x-if="selectedAccount.phone">
                                            <span class="text-slate-500 text-[11px] font-mono shrink-0" x-text="'(' + selectedAccount.phone + ')'"></span>
                                        </template>
                                        <span class="text-xs font-bold text-slate-400 shrink-0">&bull;</span>
                                        <span class="text-xs font-extrabold text-slate-700 shrink-0" x-text="'PKR ' + selectedAccount.balance.toLocaleString()"></span>
                                    </div>
                                </template>
                                <template x-if="!selectedAccount">
                                    <div class="flex items-center gap-1.5 text-slate-500 min-w-0 truncate w-full">
                                        <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                        <span class="text-xs font-bold text-slate-700 truncate">All Accounts & Clients (Click to filter)</span>
                                    </div>
                                </template>
                            </div>

                            <div class="flex items-center gap-1 shrink-0">
                                <template x-if="selectedAccount">
                                    <button type="button" @click.stop="clearSelection()" 
                                            class="w-4 h-4 rounded-full bg-slate-200 hover:bg-rose-100 text-slate-600 hover:text-rose-700 flex items-center justify-center text-[11px] font-black transition-colors"
                                            title="Clear selected account">
                                        &times;
                                    </button>
                                </template>
                                <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </div>
                    </div>

                    <!-- DROPDOWN POPUP MENU (Wide, Spacious, Floating overlay) -->
                    <div x-show="open" x-cloak 
                         class="absolute z-50 left-0 mt-1 bg-white border-2 border-indigo-600 shadow-2xl space-y-2.5 p-3.5"
                         style="width: 480px; max-width: min(560px, calc(100vw - 32px)); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.25), 0 10px 10px -5px rgba(0, 0, 0, 0.1);">
                        
                        <!-- Instant Search Input -->
                        <div class="relative">
                            <input type="text" 
                                   x-ref="searchInput" 
                                   x-model="search" 
                                   placeholder="Type name, phone (03xx...), or category..." 
                                   class="w-full pl-9 pr-8 py-2 text-xs font-bold text-slate-900 bg-slate-50 border border-slate-300 focus:bg-white focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 outline-hidden">
                            <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </div>
                            <button type="button" x-show="search.length > 0" @click="search = ''; $refs.searchInput.focus()" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-700 text-sm font-bold">
                                &times;
                            </button>
                        </div>

                        <!-- Category Quick Filter Pills -->
                        <div class="flex items-center gap-1.5 flex-wrap pb-1 border-b border-slate-100 text-[11px] font-bold">
                            <button type="button" @click="categoryTab = 'customer'" 
                                    :class="categoryTab === 'customer' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'" 
                                    class="px-2.5 py-1 transition-colors flex items-center gap-1 shrink-0 cursor-pointer">
                                <span>👤 Customers</span>
                                <span class="text-[9px] px-1 py-0.2 bg-black/20" x-text="counts.customer"></span>
                            </button>

                            <button type="button" @click="categoryTab = 'all'" 
                                    :class="categoryTab === 'all' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'" 
                                    class="px-2.5 py-1 transition-colors flex items-center gap-1 shrink-0 cursor-pointer">
                                <span>All Accounts</span>
                                <span class="text-[9px] px-1 py-0.2 bg-black/20" x-text="counts.all"></span>
                            </button>

                            <button type="button" @click="categoryTab = 'supplier'" 
                                    :class="categoryTab === 'supplier' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'" 
                                    class="px-2.5 py-1 transition-colors flex items-center gap-1 shrink-0 cursor-pointer">
                                <span>🏢 Suppliers</span>
                                <span class="text-[9px] px-1 py-0.2 bg-black/20" x-text="counts.supplier"></span>
                            </button>

                            <button type="button" @click="categoryTab = 'staff'" 
                                    :class="categoryTab === 'staff' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'" 
                                    class="px-2.5 py-1 transition-colors flex items-center gap-1 shrink-0 cursor-pointer">
                                <span>💇 Staff</span>
                                <span class="text-[9px] px-1 py-0.2 bg-black/20" x-text="counts.staff"></span>
                            </button>
                        </div>

                        <!-- Filtered Accounts Scrollable List -->
                        <div x-ref="dropdownList" class="max-h-64 overflow-y-auto divide-y divide-slate-100">
                            
                            <!-- "Show All Statements" Option -->
                            <div @click.stop="clearSelection()" 
                                 class="p-2.5 hover:bg-indigo-50 cursor-pointer transition-colors flex items-center justify-between"
                                 :class="!selectedAccount ? 'bg-indigo-50/70' : ''">
                                <div class="flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center text-xs font-black">📋</span>
                                    <div>
                                        <p class="text-xs font-black text-slate-900">All Accounts Statement</p>
                                        <p class="text-[10px] text-slate-400 font-medium">Clear filter and show complete general ledger</p>
                                    </div>
                                </div>
                                <template x-if="!selectedAccount">
                                    <span class="text-indigo-600 font-black text-xs">✓ Active</span>
                                </template>
                            </div>

                            <!-- List of Accounts -->
                            <template x-for="(acc, index) in filteredAccounts" :key="acc.id">
                                <div @click.stop="selectAccount(acc)" 
                                     @mouseenter="highlightedIndex = index"
                                     class="p-2.5 cursor-pointer transition-colors flex items-center justify-between gap-3"
                                     :class="{
                                         'bg-indigo-50': highlightedIndex === index || (selectedAccount && selectedAccount.id === acc.id),
                                         'hover:bg-indigo-50/60': highlightedIndex !== index
                                     }">
                                    <div class="space-y-0.5 overflow-hidden flex-1 min-w-0">
                                        <div class="flex items-center gap-1.5 truncate">
                                            <span class="text-xs font-black text-slate-900 truncate" x-text="acc.name"></span>
                                            <span class="px-1.5 py-0.2 text-[9px] font-bold uppercase shrink-0" 
                                                  :class="{
                                                      'bg-emerald-100 text-emerald-800 border border-emerald-300': acc.type === 'customer',
                                                      'bg-amber-100 text-amber-800 border border-amber-300': acc.type === 'supplier',
                                                      'bg-purple-100 text-purple-800 border border-purple-300': acc.type === 'staff',
                                                      'bg-slate-100 text-slate-700 border border-slate-300': acc.type === 'other'
                                                  }"
                                                  x-text="acc.category"></span>
                                        </div>
                                        <div class="flex items-center gap-2 text-[11px] text-slate-500 font-medium">
                                            <template x-if="acc.phone">
                                                <span class="flex items-center gap-0.5 font-mono">
                                                    <span>📞</span>
                                                    <span x-text="acc.phone"></span>
                                                </span>
                                            </template>
                                            <template x-if="!acc.phone">
                                                <span class="text-slate-400 italic">No phone attached</span>
                                            </template>
                                        </div>
                                    </div>

                                    <div class="text-right shrink-0">
                                        <span class="text-[10px] font-bold text-slate-400 uppercase block">Balance</span>
                                        <span class="text-xs font-black" 
                                              :class="acc.balance > 0 ? 'text-rose-600' : (acc.balance < 0 ? 'text-emerald-700' : 'text-slate-600')"
                                              x-text="'PKR ' + acc.balance.toLocaleString()"></span>
                                    </div>
                                </div>
                            </template>

                            <!-- Empty Search Result -->
                            <template x-if="filteredAccounts.length === 0">
                                <div class="py-8 text-center space-y-1 text-slate-400">
                                    <div class="text-xl">🔍</div>
                                    <p class="text-xs font-bold text-slate-700">No matching accounts found</p>
                                    <p class="text-[10px]">Try searching with another name or switch to 'All Accounts'.</p>
                                </div>
                            </template>
                        </div>

                        <!-- Dropdown Footer -->
                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400 font-medium">
                            <span>Use &uarr;&darr; keys to navigate, Enter to select</span>
                            <span x-text="filteredAccounts.length + ' accounts available'"></span>
                        </div>
                    </div>
                </div>

                <!-- 2. Transaction Type Filter -->
                <div style="flex: 2 1 180px; min-width: 150px;">
                    <label class="block text-[10px] font-black text-slate-700 uppercase tracking-wider mb-1 truncate">
                        Category / Type
                    </label>
                    <select name="type" onchange="document.getElementById('ledgerReportFilterForm').submit()" 
                            style="height: 40px;"
                            class="w-full px-2.5 bg-slate-50 hover:bg-white border border-slate-300 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:bg-white shadow-2xs">
                        <option value="">All Transaction Types</option>
                        <option value="sale" {{ (isset($type) && $type == 'sale') ? 'selected' : '' }}>Product / Service Sale</option>
                        <option value="receiving" {{ (isset($type) && $type == 'receiving') ? 'selected' : '' }}>Payment Receiving</option>
                        <option value="purchase" {{ (isset($type) && $type == 'purchase') ? 'selected' : '' }}>Supplier Purchase</option>
                        <option value="payment" {{ (isset($type) && $type == 'payment') ? 'selected' : '' }}>Supplier Payment</option>
                        <option value="commission" {{ (isset($type) && $type == 'commission') ? 'selected' : '' }}>Stylist Commission</option>
                    </select>
                </div>

                <!-- 3. From Date -->
                <div style="flex: 1.4 1 130px; min-width: 115px;">
                    <label class="block text-[10px] font-black text-slate-700 uppercase tracking-wider mb-1 truncate">
                        From Date
                    </label>
                    <input type="date" name="start_date" value="{{ $startDate }}" 
                           style="height: 40px;"
                           class="w-full px-2 bg-slate-50 border border-slate-300 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600 shadow-2xs">
                </div>

                <!-- 4. To Date -->
                <div style="flex: 1.4 1 130px; min-width: 115px;">
                    <label class="block text-[10px] font-black text-slate-700 uppercase tracking-wider mb-1 truncate">
                        To Date
                    </label>
                    <input type="date" name="end_date" value="{{ $endDate }}" 
                           style="height: 40px;"
                           class="w-full px-2 bg-slate-50 border border-slate-300 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600 shadow-2xs">
                </div>

                <!-- 5. Action Buttons -->
                <div style="flex: 0 0 auto; display: flex; gap: 6px; align-items: flex-end;">
                    <button type="submit" 
                            style="height: 40px;"
                            class="px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs transition-colors shadow-xs flex items-center gap-1 cursor-pointer shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                        <span>Filter</span>
                    </button>
                    @if($selectedAccount || (isset($type) && $type) || $startDate != now()->startOfMonth()->toDateString() || $endDate != now()->toDateString())
                        <a href="{{ route('manager.reports.ledger') }}" 
                           style="height: 40px;"
                           class="px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs flex items-center justify-center border border-slate-300 transition-colors shadow-2xs shrink-0" title="Reset all filters">
                            Reset
                        </a>
                    @endif
                </div>

            </div>
        </form>
    </div>

    <!-- Account Ledger Table -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between print:hidden">
            <h2 class="text-xs font-extrabold text-slate-700 uppercase tracking-wider">
                {{ $selectedAccount ? 'Statement for ' . $selectedAccount->name : 'General Ledger Statement (All Accounts & Clients)' }}
            </h2>
            <span class="text-xs font-bold text-slate-500">Date: {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} to {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs crystal-grid">
                <thead>
                    <tr class="bg-slate-100 text-slate-700 font-extrabold uppercase border-b tracking-wider">
                        <th class="py-2.5 px-3 w-12 text-center">Sr #</th>
                        <th class="py-2.5 px-4">Date</th>
                        @if(!$selectedAccount)
                            <th class="py-2.5 px-4">Account Name & Category</th>
                        @endif
                        <th class="py-2.5 px-3 text-center">Type</th>
                        <th class="py-2.5 px-4">Reference #</th>
                        <th class="py-2.5 px-4">Description</th>
                        <th class="py-2.5 px-3 text-right">Debit (+)</th>
                        <th class="py-2.5 px-3 text-right">Credit (-)</th>
                        <th class="py-2.5 px-4 text-right">Running Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @if($selectedAccount)
                    <!-- Opening Balance Row -->
                    <tr class="bg-slate-50 font-bold">
                        <td class="py-2 px-3 text-center font-mono text-[11px] text-slate-400">—</td>
                        <td class="py-2 px-4 text-slate-600 font-mono">{{ \Carbon\Carbon::parse($startDate)->format('d-M-Y') }}</td>
                        <td class="py-2 px-3 text-center uppercase font-bold text-[9.5pt] text-slate-600">OPENING</td>
                        <td class="py-2 px-4 text-slate-400 font-mono">—</td>
                        <td class="py-2 px-4 text-slate-600 italic">Opening balance carried forward prior to statement</td>
                        <td class="py-2 px-3 text-right text-slate-400">—</td>
                        <td class="py-2 px-3 text-right text-slate-400">—</td>
                        <td class="py-2 px-4 text-right font-black text-slate-900">{{ number_format($openingBalance, 2) }}</td>
                    </tr>
                    @endif

                    @forelse($ledgers as $entry)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="py-2 px-3 text-center font-mono text-[11px] text-slate-500">{{ $loop->iteration }}</td>
                        <td class="py-2 px-4 font-bold text-slate-700 font-mono">{{ $entry->date ? \Carbon\Carbon::parse($entry->date)->format('d-M-Y') : '—' }}</td>
                        @if(!$selectedAccount)
                            <td class="py-2 px-4">
                                <a href="{{ route('manager.reports.ledger', ['account_id' => $entry->account_id, 'start_date' => $startDate, 'end_date' => $endDate]) }}" 
                                   class="font-black text-slate-900 hover:text-indigo-600 transition-colors block text-xs"
                                   title="View this account statement">
                                    {{ $entry->account->name ?? 'General Account' }}
                                </a>
                                <div class="flex items-center gap-1 text-[10px] text-slate-400">
                                    <span>{{ $entry->account->category->title ?? 'General' }}</span>
                                    @if($entry->account && $entry->account->phone_no1)
                                        <span>&bull; {{ $entry->account->phone_no1 }}</span>
                                    @endif
                                </div>
                            </td>
                        @endif
                        <td class="py-2 px-3 text-center uppercase font-bold text-[9.5pt]">
                            @if($entry->type == 'purchase')
                                <span class="px-2 py-0.5 bg-amber-50 text-amber-800 border border-amber-200 text-[9px] font-black uppercase">Purchase</span>
                            @elseif($entry->type == 'payment')
                                <span class="px-2 py-0.5 bg-indigo-50 text-indigo-800 border border-indigo-200 text-[9px] font-black uppercase">Payment</span>
                            @elseif($entry->type == 'sale')
                                <span class="px-2 py-0.5 bg-emerald-50 text-emerald-800 border border-emerald-200 text-[9px] font-black uppercase">Sale</span>
                            @elseif($entry->type == 'receiving')
                                <span class="px-2 py-0.5 bg-purple-50 text-purple-800 border border-purple-200 text-[9px] font-black uppercase">Receiving</span>
                            @elseif($entry->type == 'commission')
                                <span class="px-2 py-0.5 bg-blue-50 text-blue-800 border border-blue-200 text-[9px] font-black uppercase">Commission</span>
                            @else
                                <span class="px-2 py-0.5 bg-slate-100 text-slate-700 border border-slate-200 text-[9px] font-black uppercase">{{ $entry->type }}</span>
                            @endif
                        </td>
                        <td class="py-2 px-4 font-mono font-bold text-indigo-700">{{ $entry->reference_no ?: '—' }}</td>
                        <td class="py-2 px-4 text-slate-700">{{ $entry->description ?: 'N/A' }}</td>
                        <td class="py-2 px-3 text-right font-bold text-indigo-700">{{ $entry->debit > 0 ? number_format($entry->debit, 2) : '—' }}</td>
                        <td class="py-2 px-3 text-right font-bold text-emerald-700">{{ $entry->credit > 0 ? number_format($entry->credit, 2) : '—' }}</td>
                        <td class="py-2 px-4 text-right font-black text-slate-900">{{ number_format($entry->running_balance, 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $selectedAccount ? 8 : 9 }}" class="py-12 text-center text-slate-400 font-semibold">
                            No ledger transactions recorded {{ $selectedAccount ? 'for ' . $selectedAccount->name : '' }} in this date range.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="font-black">
                        <td colspan="{{ $selectedAccount ? 5 : 6 }}" class="py-2 px-4 text-right uppercase">
                            {{ $selectedAccount ? 'Periodic Movement & Balance:' : 'Periodic Totals:' }}
                        </td>
                        <td class="py-2 px-3 text-right">{{ number_format($totalDebit, 2) }}</td>
                        <td class="py-2 px-3 text-right">{{ number_format($totalCredit, 2) }}</td>
                        <td class="py-2 px-4 text-right">
                            {{ $selectedAccount ? number_format($selectedAccount->balance, 2) : number_format($totalDebit - $totalCredit, 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if(is_object($ledgers) && method_exists($ledgers, 'links'))
        <div class="px-6 py-4 border-t border-slate-100 print:hidden">
            {{ $ledgers->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
