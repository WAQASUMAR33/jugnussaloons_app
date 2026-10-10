@extends('layouts.material')

@section('title', 'General Ledger & Customer Statements')

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

            const form = document.getElementById('ledgerFilterForm');
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

            const form = document.getElementById('ledgerFilterForm');
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

    <!-- Header & Summary Metrics -->
    <div class="bg-white p-6 border border-slate-200 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="p-3 bg-blue-50 text-blue-700 border border-blue-200">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight heading-font">General Account Ledger</h1>
                    <p class="text-xs font-semibold text-slate-500 mt-0.5">Audit complete transaction histories for customers, suppliers, staff, and general accounts.</p>
                </div>
            </div>

            @if($selectedAccount)
                <div class="flex items-center gap-3">
                    <div class="p-3 bg-slate-900 text-white text-right border border-slate-800 shadow-xs">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-300 block">Current Account Balance</span>
                        <p class="text-xl font-black {{ $selectedAccount->balance > 0 ? 'text-rose-400' : 'text-emerald-400' }}">
                            PKR {{ number_format(abs($selectedAccount->balance), 2) }}
                            <span class="text-xs font-bold text-slate-300">{{ $selectedAccount->balance > 0 ? '(Receivable / Due)' : ($selectedAccount->balance < 0 ? '(Advance / Credit)' : '(Settled)') }}</span>
                        </p>
                    </div>
                </div>
            @endif
        </div>

        <!-- Ledger Overview KPI Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2 border-t border-slate-100 text-xs">
            <div class="p-3 bg-slate-50 border border-slate-200">
                <span class="text-[10px] font-bold text-slate-500 uppercase block">Total Debits (+)</span>
                <span class="text-base font-black text-slate-900 mt-0.5 block">PKR {{ number_format($totalDebit, 2) }}</span>
            </div>
            <div class="p-3 bg-slate-50 border border-slate-200">
                <span class="text-[10px] font-bold text-slate-500 uppercase block">Total Credits (-)</span>
                <span class="text-base font-black text-emerald-700 mt-0.5 block">PKR {{ number_format($totalCredit, 2) }}</span>
            </div>
            <div class="p-3 bg-slate-50 border border-slate-200">
                <span class="text-[10px] font-bold text-slate-500 uppercase block">Total Statements</span>
                <span class="text-base font-black text-indigo-700 mt-0.5 block">{{ $ledgers->total() }} entries</span>
            </div>
            <div class="p-3 bg-slate-50 border border-slate-200">
                <span class="text-[10px] font-bold text-slate-500 uppercase block">Active Filter</span>
                <span class="text-xs font-black text-slate-800 mt-1 block truncate">
                    {{ $selectedAccount ? $selectedAccount->name : 'All Accounts' }}
                </span>
            </div>
        </div>
    </div>

    <!-- Smart Filter & Search Bar: All Filter Components in a Single Line / Row -->
    <div class="bg-white p-4 border border-slate-200 shadow-xs">
        <form id="ledgerFilterForm" method="GET" action="{{ route('manager.ledger.index') }}">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-12 gap-3 items-end w-full">
                
                <!-- 1. Customer / Account Searchable Combobox -->
                <div x-data="ledgerAccountPicker(window.ledgerAccountsData, window.ledgerInitialSelected)" 
                     @click.outside="closeDropdown()" 
                     @keydown="onKeyDown($event)"
                     class="relative col-span-1 sm:col-span-2 md:col-span-3 xl:col-span-4">
                    
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
                         class="absolute z-50 left-0 mt-1 bg-white border-2 border-indigo-600 shadow-2xl space-y-2.5 p-3.5 w-[calc(100vw-2.5rem)] max-w-lg sm:w-[480px]"
                         style="box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.25), 0 10px 10px -5px rgba(0, 0, 0, 0.1);">
                        
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
                <div class="col-span-1 sm:col-span-1 md:col-span-1 xl:col-span-2">
                    <label class="block text-[10px] font-black text-slate-700 uppercase tracking-wider mb-1 truncate">
                        Category / Type
                    </label>
                    <select name="type" onchange="document.getElementById('ledgerFilterForm').submit()" 
                            style="height: 40px;"
                            class="w-full px-2.5 bg-slate-50 hover:bg-white border border-slate-300 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:bg-white shadow-2xs">
                        <option value="">All Transaction Types</option>
                        <option value="sale" {{ $type == 'sale' ? 'selected' : '' }}>Product / Service Sale</option>
                        <option value="receiving" {{ $type == 'receiving' ? 'selected' : '' }}>Payment Receiving</option>
                        <option value="purchase" {{ $type == 'purchase' ? 'selected' : '' }}>Supplier Purchase</option>
                        <option value="payment" {{ $type == 'payment' ? 'selected' : '' }}>Supplier Payment</option>
                        <option value="commission" {{ $type == 'commission' ? 'selected' : '' }}>Stylist Commission</option>
                    </select>
                </div>

                <!-- 3. From Date -->
                <div class="col-span-1 sm:col-span-1 md:col-span-1 xl:col-span-2">
                    <label class="block text-[10px] font-black text-slate-700 uppercase tracking-wider mb-1 truncate">
                        From Date
                    </label>
                    <input type="date" name="start_date" value="{{ $startDate }}" 
                           style="height: 40px;"
                           class="w-full px-2 bg-slate-50 border border-slate-300 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600 shadow-2xs">
                </div>

                <!-- 4. To Date -->
                <div class="col-span-1 sm:col-span-1 md:col-span-1 xl:col-span-2">
                    <label class="block text-[10px] font-black text-slate-700 uppercase tracking-wider mb-1 truncate">
                        To Date
                    </label>
                    <input type="date" name="end_date" value="{{ $endDate }}" 
                           style="height: 40px;"
                           class="w-full px-2 bg-slate-50 border border-slate-300 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600 shadow-2xs">
                </div>

                <!-- 5. Action Buttons -->
                <div class="col-span-1 sm:col-span-2 md:col-span-3 xl:col-span-2 flex items-center gap-2">
                    <button type="submit" 
                            style="height: 40px;"
                            class="flex-1 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs transition-colors shadow-xs flex items-center justify-center gap-1 cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                        <span>Filter</span>
                    </button>
                    @if($accountId || $type || $startDate || $endDate)
                        <a href="{{ route('manager.ledger.index') }}" 
                           style="height: 40px;"
                           class="px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs flex items-center justify-center border border-slate-300 transition-colors shadow-2xs shrink-0" title="Reset all filters">
                            Reset
                        </a>
                    @endif
                </div>

            </div>
        </form>
    </div>

    <!-- Ledger Statements Table -->
    <div class="bg-white border border-slate-200 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[11px] font-extrabold uppercase tracking-wider">
                        <th class="py-4 px-6">ID</th>
                        <th class="py-4 px-6">Date</th>
                        <th class="py-4 px-6">Account Name & Category</th>
                        <th class="py-4 px-6">Type</th>
                        <th class="py-4 px-6">Reference #</th>
                        <th class="py-4 px-6">Description</th>
                        <th class="py-4 px-6 text-right">Debit (+)</th>
                        <th class="py-4 px-6 text-right">Credit (-)</th>
                        <th class="py-4 px-6 text-right">Running Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                    @forelse($ledgers as $ledger)
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <td class="py-4 px-6 font-mono text-slate-400">#{{ $ledger->id }}</td>
                        <td class="py-4 px-6 text-slate-700 font-bold">
                            {{ $ledger->date ? $ledger->date->format('M d, Y') : '—' }}
                        </td>
                        <td class="py-4 px-6">
                            <div class="space-y-0.5">
                                <a href="{{ route('manager.ledger.index', ['account_id' => $ledger->account_id]) }}" 
                                   class="font-black text-slate-900 hover:text-indigo-600 transition-colors block text-sm"
                                   title="Filter ledger for this account">
                                    {{ $ledger->account->name ?? 'Unknown Account' }}
                                </a>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase">
                                        {{ $ledger->account->category->title ?? 'General' }}
                                    </span>
                                    @if($ledger->account && $ledger->account->phone_no1)
                                        <span class="text-[10px] text-slate-400 font-mono">&bull; {{ $ledger->account->phone_no1 }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-6">
                            @if($ledger->type == 'purchase')
                                <span class="px-2.5 py-1 bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-black uppercase">Purchase</span>
                            @elseif($ledger->type == 'payment')
                                <span class="px-2.5 py-1 bg-purple-50 text-purple-800 border border-purple-200 text-[10px] font-black uppercase">Payment</span>
                            @elseif($ledger->type == 'sale')
                                <span class="px-2.5 py-1 bg-indigo-50 text-indigo-800 border border-indigo-200 text-[10px] font-black uppercase">Sale</span>
                            @elseif($ledger->type == 'receiving')
                                <span class="px-2.5 py-1 bg-emerald-50 text-emerald-800 border border-emerald-200 text-[10px] font-black uppercase">Receiving</span>
                            @elseif($ledger->type == 'commission')
                                <span class="px-2.5 py-1 bg-blue-50 text-blue-800 border border-blue-200 text-[10px] font-black uppercase">Commission</span>
                            @else
                                <span class="px-2.5 py-1 bg-slate-100 text-slate-800 border border-slate-200 text-[10px] font-black uppercase">{{ ucfirst($ledger->type) }}</span>
                            @endif
                        </td>
                        <td class="py-4 px-6 font-mono text-xs font-bold text-slate-800">
                            {{ $ledger->reference_no ?: '—' }}
                        </td>
                        <td class="py-4 px-6 text-xs text-slate-600 max-w-xs">
                            {{ $ledger->description }}
                        </td>
                        <td class="py-4 px-6 font-bold text-slate-900 text-right">
                            {{ $ledger->debit > 0 ? number_format($ledger->debit, 2) : '—' }}
                        </td>
                        <td class="py-4 px-6 font-bold text-emerald-700 text-right">
                            {{ $ledger->credit > 0 ? number_format($ledger->credit, 2) : '—' }}
                        </td>
                        <td class="py-4 px-6 font-black text-slate-900 text-right">
                            PKR {{ number_format($ledger->running_balance, 2) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-16 text-center text-slate-400 space-y-2">
                            <div class="text-3xl">📋</div>
                            <p class="text-sm font-bold text-slate-700">No ledger transactions found</p>
                            <p class="text-xs text-slate-400">Try adjusting your account filter, transaction type, or date range.</p>
                            @if($accountId || $type || $startDate || $endDate)
                                <a href="{{ route('manager.ledger.index') }}" class="inline-block mt-2 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs border border-slate-300">
                                    Reset Filters & Show All
                                </a>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50">
            {{ $ledgers->links() }}
        </div>
    </div>

</div>

@endsection
