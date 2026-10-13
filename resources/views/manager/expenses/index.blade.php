@extends('layouts.material')

@section('title', 'Expense Management')

@section('content')
<div x-data="{ 
    createModalOpen: false,
    editModalOpen: false,
    isSubmitting: false,
    editExpense: { exp_id: null, exp_title: '', exp_category_id: '', amount: 0, description: '', created_at: '' },
    openEditModal(exp) {
        this.editExpense = {
            exp_id: exp.exp_id,
            exp_title: exp.exp_title || '',
            exp_category_id: exp.exp_category_id || '',
            amount: Number(exp.amount) || 0,
            description: exp.description || '',
            created_at: exp.created_at || ''
        };
        this.editModalOpen = true;
    },
    allExpenses: {{ json_encode($allExpenses ?? []) }},
    dateFrom: '{{ $dateFrom }}',
    dateTo: '{{ $dateTo }}',
    categoryId: '{{ $categoryId }}',
    brandSettings: {
        name: '{{ addslashes($appSetting->brand_name ?? "Jugnu Saloon") }}',
        slogan: '{{ addslashes($appSetting->brand_slogan ?? "WELCOME TO JUGNU SALON PHALIA") }}',
        address: '{{ addslashes($appSetting->brand_address ?? "Phalia, Mandi Bahauddin") }}',
        phone1: '{{ addslashes($appSetting->brand_phone1 ?? "03194415757") }}',
        phone2: '{{ addslashes($appSetting->brand_phone2 ?? "") }}'
    },

    printDocumentViaIframe(htmlContent) {
        if (!htmlContent) return;
        let iframe = document.getElementById('expensePrintIframe');
        if (!iframe) {
            iframe = document.createElement('iframe');
            iframe.id = 'expensePrintIframe';
            iframe.style.position = 'fixed';
            iframe.style.right = '0';
            iframe.style.bottom = '0';
            iframe.style.width = '0';
            iframe.style.height = '0';
            iframe.style.border = '0';
            iframe.style.visibility = 'hidden';
            document.body.appendChild(iframe);
        }

        const doc = iframe.contentWindow.document;
        doc.open();
        doc.write(htmlContent);
        doc.close();

        setTimeout(() => {
            try {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
            } catch (err) {
                console.warn('Print iframe error:', err);
                window.print();
            }
        }, 350);
    },

    printExpenseReport() {
        let catTitle = '';
        if (this.categoryId) {
            const sel = document.querySelector('select[name=\'category_id\']');
            if (sel && sel.selectedIndex > 0) {
                catTitle = sel.options[sel.selectedIndex].text.trim();
            }
        }
        const docHtml = (typeof window.buildDatewiseExpenseReportDocument === 'function')
            ? window.buildDatewiseExpenseReportDocument(this.allExpenses, this.dateFrom, this.dateTo, catTitle, this.brandSettings)
            : '';
        this.printDocumentViaIframe(docHtml);
    }
}" class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-4 sm:p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="p-2.5 bg-rose-50 text-rose-600 shrink-0">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Expense Management</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">Track, categorize, analyze, and manage operational salon expenditures.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <button type="button" @click="printExpenseReport()" 
                    style="background-color: #0f172a !important; color: #fbbf24 !important; border: 1px solid #1e293b !important;"
                    class="inline-flex items-center gap-2 px-4 py-2.5 font-extrabold text-xs shadow-sm hover:opacity-90 transition-all rounded-none cursor-pointer">
                <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                <span>🖨️ Print Expense Report</span>
            </button>

            <a href="{{ route('manager.expense-categories.index') }}" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs flex items-center gap-1.5 border border-slate-200">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                <span>Manage Categories</span>
            </a>

            <button @click="createModalOpen = true" 
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-sm transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                <span>Record New Expense</span>
            </button>
        </div>
    </div>

    <!-- Alert Notifications -->
    @if(session('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
            <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- ANALYTICS SUMMARY CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Metric 1: Total Filtered Expenses -->
        <div class="bg-white p-5 border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-[10px] font-extrabold uppercase text-slate-400 tracking-wider">Filtered Expenses Total</p>
                <h3 class="text-2xl font-black text-slate-900 mt-1">{{ number_format($totalExpensesAmount, 2) }}</h3>
                <p class="text-[11px] font-semibold text-slate-500 mt-1">{{ $totalExpensesCount }} {{ Str::plural('record', $totalExpensesCount) }}</p>
            </div>
            <div class="p-3 bg-rose-50 text-rose-600 rounded-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>

        <!-- Metric 2: This Month Total / Selected Dates Total -->
        <div class="bg-white p-5 border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-[10px] font-extrabold uppercase text-slate-400 tracking-wider">
                    {{ ($dateFrom || $dateTo) ? 'Selected Dates Total' : "This Month's Total" }}
                </p>
                <h3 class="text-2xl font-black text-slate-900 mt-1">{{ number_format($monthOrPeriodTotal ?? $thisMonthTotal, 2) }}</h3>
                <p class="text-[11px] font-semibold text-emerald-600 mt-1">
                    @if($dateFrom && $dateTo)
                        {{ \Carbon\Carbon::parse($dateFrom)->format('d M') }} — {{ \Carbon\Carbon::parse($dateTo)->format('d M, Y') }}
                    @elseif($dateFrom)
                        From {{ \Carbon\Carbon::parse($dateFrom)->format('d M, Y') }}
                    @elseif($dateTo)
                        Until {{ \Carbon\Carbon::parse($dateTo)->format('d M, Y') }}
                    @else
                        {{ date('F Y') }}
                    @endif
                </p>
            </div>
            <div class="p-3 bg-indigo-50 text-indigo-600 rounded-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </div>
        </div>

        <!-- Metric 3: Top Expense Category -->
        <div class="bg-white p-5 border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-[10px] font-extrabold uppercase text-slate-400 tracking-wider">Highest Category</p>
                <h3 class="text-lg font-black text-slate-900 mt-1 truncate max-w-[150px]">
                    {{ $topCategory ? $topCategory->category->title : 'N/A' }}
                </h3>
                <p class="text-[11px] font-bold text-rose-600 mt-1">
                    {{ $topCategory ? number_format($topCategory->total_amount, 2) : 'No Data' }}
                </p>
            </div>
            <div class="p-3 bg-amber-50 text-amber-600 rounded-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
            </div>
        </div>

        <!-- Metric 4: Aesthetic Expense -->
        <div class="bg-white p-5 border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-[10px] font-extrabold uppercase text-slate-400 tracking-wider">Aesthetic Expense</p>
                <h3 class="text-2xl font-black text-slate-900 mt-1">{{ number_format($aestheticTotal, 2) }}</h3>
                <p class="text-[11px] font-semibold text-slate-500 mt-1">
                    {{ $aestheticCount }} {{ Str::plural('record', $aestheticCount) }}
                    @if($totalExpensesAmount > 0)
                        <span class="text-purple-600 font-bold ml-1">({{ round(($aestheticTotal / $totalExpensesAmount) * 100, 1) }}%)</span>
                    @endif
                </p>
            </div>
            <div class="p-3 bg-purple-50 text-purple-600 rounded-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- CATEGORY-WISE EXPENSE DISTRIBUTION BAR -->
    @if(count($categoryBreakdown) > 0)
    <div class="bg-white p-5 border border-slate-200 shadow-sm space-y-3">
        <h4 class="text-xs font-black uppercase text-slate-700 tracking-wider">Category Expenditure Breakdown</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
            @foreach($categoryBreakdown as $catItem)
                @php
                    $percentage = $totalExpensesAmount > 0 ? min(100, round(($catItem->total_amount / $totalExpensesAmount) * 100, 1)) : 0;
                @endphp
                <div class="p-3 bg-slate-50 border border-slate-200/80 rounded space-y-1">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-slate-800 truncate max-w-[120px]">{{ $catItem->category->title ?? 'Uncategorized' }}</span>
                        <span class="font-black text-rose-600">{{ number_format($catItem->total_amount, 2) }}</span>
                    </div>
                    <div class="w-full bg-slate-200 h-1.5 rounded-full overflow-hidden">
                        <div class="bg-rose-500 h-full rounded-full" style="width: {{ $percentage }}%"></div>
                    </div>
                    <div class="flex items-center justify-between text-[10px] text-slate-400 font-semibold">
                        <span>{{ $catItem->count }} {{ Str::plural('item', $catItem->count) }}</span>
                        <span>{{ $percentage }}%</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- FILTER & SEARCH BAR -->
    <div class="bg-white p-4 border border-slate-200 shadow-sm">
        <form method="GET" action="{{ route('manager.expenses.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <!-- Title Search -->
            <div>
                <label class="block text-[10px] font-extrabold uppercase text-slate-500 mb-1">Search Title</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </span>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search title..." 
                           class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 text-xs font-medium focus:ring-2 focus:ring-rose-600 focus:bg-white transition-all">
                </div>
            </div>

            <!-- Category Filter -->
            <div>
                <label class="block text-[10px] font-extrabold uppercase text-slate-500 mb-1">Category</label>
                <select name="category_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-rose-600">
                    <option value="">All Categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ $categoryId == $category->id ? 'selected' : '' }}>
                            {{ $category->title }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Date From -->
            <div>
                <label class="block text-[10px] font-extrabold uppercase text-slate-500 mb-1">Date From</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}" 
                       class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-medium focus:ring-2 focus:ring-rose-600">
            </div>

            <!-- Date To -->
            <div>
                <label class="block text-[10px] font-extrabold uppercase text-slate-500 mb-1">Date To</label>
                <input type="date" name="date_to" value="{{ $dateTo }}" 
                       class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs font-medium focus:ring-2 focus:ring-rose-600">
            </div>

            <!-- Actions -->
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 px-4 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs transition-colors h-[38px] flex items-center justify-center">
                    Filter Results
                </button>
                <button type="button" @click="printExpenseReport()" 
                        style="background-color: #0f172a !important; color: #fbbf24 !important;"
                        class="py-2 px-3 font-bold text-xs hover:opacity-90 h-[38px] flex items-center justify-center transition-colors cursor-pointer"
                        title="Print Filtered Datewise Expense Report">
                    🖨️ Print
                </button>
                @if($search || $categoryId || $dateFrom || $dateTo)
                    <a href="{{ route('manager.expenses.index') }}" class="py-2 px-3 bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200 h-[38px] flex items-center justify-center">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- EXPENSES DATATABLE -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[11px] font-extrabold uppercase tracking-wider">
                        <th class="py-4 px-6">Expense ID</th>
                        <th class="py-4 px-6">Expense Title</th>
                        <th class="py-4 px-6">Category</th>
                        <th class="py-4 px-6">Amount</th>
                        <th class="py-4 px-6">Description</th>
                        <th class="py-4 px-6">Added By</th>
                        <th class="py-4 px-6">Date</th>
                        <th class="py-4 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($expenses as $exp)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="py-4 px-6 font-bold text-xs text-slate-400">#EXP-{{ $exp->exp_id }}</td>
                        <td class="py-4 px-6">
                            <p class="font-bold text-slate-900 leading-tight">{{ $exp->exp_title }}</p>
                        </td>
                        <td class="py-4 px-6">
                            <span class="px-2.5 py-1 bg-slate-100 text-slate-800 border border-slate-200 font-extrabold text-[11px] rounded">
                                {{ $exp->category->title ?? 'General' }}
                            </span>
                        </td>
                        <td class="py-4 px-6 font-black text-rose-600 text-sm">
                            {{ number_format($exp->amount, 2) }}
                        </td>
                        <td class="py-4 px-6 text-xs text-slate-500 max-w-xs truncate">
                            {{ $exp->description ?: '—' }}
                        </td>
                        <td class="py-4 px-6 text-xs text-slate-600 font-semibold">
                            {{ $exp->creator->name ?? 'System User' }}
                        </td>
                        <td class="py-4 px-6 text-xs text-slate-500">
                            {{ $exp->created_at ? $exp->created_at->format('M d, Y h:i A') : '—' }}
                        </td>
                        <td class="py-4 px-6 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @php
                                    $expPayload = [
                                        'exp_id' => $exp->exp_id,
                                        'exp_title' => (string) $exp->exp_title,
                                        'exp_category_id' => (string) $exp->exp_category_id,
                                        'amount' => (float) $exp->amount,
                                        'description' => (string) ($exp->description ?? ''),
                                        'created_at' => $exp->created_at ? $exp->created_at->format('Y-m-d') : '',
                                    ];
                                @endphp
                                <button type="button" 
                                        @click="openEditModal({{ Js::from($expPayload) }})" 
                                        class="p-1.5 text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition-colors" title="Edit Expense">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </button>
                                <form method="POST" action="{{ route('manager.expenses.destroy', $exp) }}" onsubmit="return confirm({{ Js::from('Delete expense entry #EXP-' . $exp->exp_id . ' (' . $exp->exp_title . ')?') }});" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 text-slate-500 hover:text-red-600 hover:bg-red-50 transition-colors" title="Delete Expense">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-12 text-center text-slate-400">
                            <p class="text-sm font-semibold">No expense records found.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-slate-100">
            {{ $expenses->links() }}
        </div>
    </div>

    <!-- MODAL: CREATE EXPENSE -->
    <div x-show="createModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="createModalOpen = false" class="bg-white max-w-lg w-full p-6 shadow-2xl space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Record Expense
                </h3>
                <button @click="createModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('manager.expenses.store') }}" @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Expense Title</label>
                    <input type="text" name="exp_title" required placeholder="e.g. Monthly Electricity Bill, Cleaning Supplies" 
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-rose-600">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Category</label>
                        <select name="exp_category_id" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-rose-600">
                            <option value="">Select Category</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Amount</label>
                        <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-bold text-rose-600 focus:ring-2 focus:ring-rose-600">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Expense Date</label>
                    <input type="date" name="created_at" value="{{ date('Y-m-d') }}" 
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-rose-600">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Description / Notes (Optional)</label>
                    <textarea name="description" rows="3" placeholder="Vendor details, receipt references..." 
                              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-rose-600"></textarea>
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="createModalOpen = false" class="px-4 py-2.5 text-slate-600 font-bold text-xs">
                        Cancel
                    </button>
                    <button type="submit" :disabled="isSubmitting" class="px-6 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-md transition-all flex items-center gap-1.5 disabled:opacity-70 disabled:cursor-not-allowed">
                        <template x-if="isSubmitting">
                            <svg class="animate-spin w-3.5 h-3.5 text-white shrink-0" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                        </template>
                        <span x-text="isSubmitting ? 'Saving Expense...' : 'Save Expense'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: EDIT EXPENSE -->
    <div x-show="editModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="editModalOpen = false" class="bg-white max-w-lg w-full p-6 shadow-2xl space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Edit Expense Entry
                </h3>
                <button @click="editModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form :action="'{{ url('manager/expenses') }}/' + editExpense.exp_id" method="POST" @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Expense Title</label>
                    <input type="text" name="exp_title" x-model="editExpense.exp_title" required 
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-rose-600">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Category</label>
                        <select name="exp_category_id" x-model="editExpense.exp_category_id" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-rose-600">
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Amount</label>
                        <input type="number" step="0.01" min="0.01" name="amount" x-model.number="editExpense.amount" required 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-bold text-rose-600 focus:ring-2 focus:ring-rose-600">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Expense Date</label>
                    <input type="date" name="created_at" x-model="editExpense.created_at" 
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-rose-600">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Description / Notes (Optional)</label>
                    <textarea name="description" rows="3" x-model="editExpense.description" 
                              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-rose-600"></textarea>
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="editModalOpen = false" class="px-4 py-2.5 text-slate-600 font-bold text-xs">
                        Cancel
                    </button>
                    <button type="submit" :disabled="isSubmitting" class="px-6 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-md transition-all flex items-center gap-1.5 disabled:opacity-70 disabled:cursor-not-allowed">
                        <template x-if="isSubmitting">
                            <svg class="animate-spin w-3.5 h-3.5 text-white shrink-0" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                        </template>
                        <span x-text="isSubmitting ? 'Updating...' : 'Update Expense'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
window.formatExpenseMoney = function(num) {
    return Number(num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

window.buildDatewiseExpenseReportDocument = function(expenses, dateFrom, dateTo, categoryTitle, brand) {
    const list = Array.isArray(expenses) ? [...expenses] : [];
    
    // Sort chronologically ascending by created_at
    list.sort((a, b) => new Date(a.created_at || 0) - new Date(b.created_at || 0));

    const totalAmount = list.reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0);
    const now = new Date();
    const printedAt = now.toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

    let periodLabel = 'All Recorded History';
    if (dateFrom && dateTo) {
        periodLabel = `${dateFrom} to ${dateTo}`;
    } else if (dateFrom) {
        periodLabel = `From ${dateFrom} onwards`;
    } else if (dateTo) {
        periodLabel = `Up to ${dateTo}`;
    }

    // Group items by date (YYYY-MM-DD)
    const grouped = {};
    const categoryTotals = {};

    list.forEach(item => {
        const rawDate = item.created_at ? String(item.created_at).substring(0, 10) : 'General / Other';
        if (!grouped[rawDate]) {
            grouped[rawDate] = [];
        }
        grouped[rawDate].push(item);

        const catName = (item.category && item.category.title) ? item.category.title : 'General';
        categoryTotals[catName] = (categoryTotals[catName] || 0) + (parseFloat(item.amount) || 0);
    });

    const dates = Object.keys(grouped);
    let datewiseSectionsHtml = '';

    if (dates.length === 0) {
        datewiseSectionsHtml = `
            <div style="padding: 36px; text-align: center; color: #94a3b8; font-size: 13px; font-style: italic; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 4px;">
                No expense entries found matching the active filter criteria.
            </div>
        `;
    } else {
        dates.forEach(dateKey => {
            const dayItems = grouped[dateKey];
            const daySubtotal = dayItems.reduce((s, it) => s + (parseFloat(it.amount) || 0), 0);
            let formattedDayHeader = dateKey;
            try {
                if (dateKey !== 'General / Other') {
                    const dObj = new Date(dateKey + 'T00:00:00');
                    formattedDayHeader = dObj.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                }
            } catch(e) {}

            let rowsHtml = '';
            dayItems.forEach((it, idx) => {
                const expId = it.exp_id ? `#EXP-${it.exp_id}` : `—`;
                const title = it.exp_title || 'Expense';
                const cat = (it.category && it.category.title) ? it.category.title : 'General';
                const amt = window.formatExpenseMoney(it.amount);
                const desc = it.description ? it.description : '—';
                const creator = (it.creator && it.creator.name) ? it.creator.name : 'System User';
                let timeStr = '';
                if (it.created_at && it.created_at.length > 10) {
                    try {
                        const tObj = new Date(it.created_at);
                        timeStr = tObj.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                    } catch(e) {}
                }

                rowsHtml += `
                    <tr style="border-bottom: 1px solid #e2e8f0; font-size: 11px; background: ${idx % 2 === 0 ? '#ffffff' : '#fcfcfd'};">
                        <td style="padding: 7px 10px; color: #64748b; font-weight: 700; width: 75px; text-align: center;">${expId}</td>
                        <td style="padding: 7px 10px; font-weight: 800; color: #0f172a;">
                            ${title}
                            ${timeStr ? `<span style="font-size: 9.5px; color: #94a3b8; font-weight: normal; margin-left: 6px;">(${timeStr})</span>` : ''}
                        </td>
                        <td style="padding: 7px 10px; width: 130px;">
                            <span style="display: inline-block; background-color: #f1f5f9; color: #334155; font-weight: 700; padding: 2px 7px; border-radius: 2px; font-size: 10px; border: 1px solid #cbd5e1;">
                                ${cat}
                            </span>
                        </td>
                        <td style="padding: 7px 10px; color: #475569; font-size: 10.5px;">${desc}</td>
                        <td style="padding: 7px 10px; color: #64748b; width: 95px; font-size: 10.5px;">${creator}</td>
                        <td style="padding: 7px 10px; font-weight: 900; color: #be123c; text-align: right; width: 115px; white-space: nowrap;">
                            PKR ${amt}
                        </td>
                    </tr>
                `;
            });

            datewiseSectionsHtml += `
                <div style="margin-bottom: 16px; border: 1px solid #cbd5e1; border-radius: 4px; overflow: hidden; page-break-inside: avoid;">
                    <div style="background-color: #f8fafc; border-bottom: 1px solid #cbd5e1; padding: 8px 12px; display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 12px; font-weight: 900; color: #0f172a;">📅 ${formattedDayHeader}</span>
                            <span style="font-size: 10px; font-weight: 800; color: #475569; background: #e2e8f0; padding: 1px 7px; border-radius: 2px;">
                                ${dayItems.length} ${dayItems.length === 1 ? 'Transaction' : 'Transactions'}
                            </span>
                        </div>
                        <div style="font-size: 12px; font-weight: 900; color: #be123c;">
                            Day Total: PKR ${window.formatExpenseMoney(daySubtotal)}
                        </div>
                    </div>
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #0f172a; color: #ffffff; font-size: 9.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; text-align: left;">
                                <th style="padding: 6px 10px; text-align: center; width: 75px;">ID</th>
                                <th style="padding: 6px 10px;">Expense Title</th>
                                <th style="padding: 6px 10px; width: 130px;">Category</th>
                                <th style="padding: 6px 10px;">Description / Remarks</th>
                                <th style="padding: 6px 10px; width: 95px;">Recorded By</th>
                                <th style="padding: 6px 10px; text-align: right; width: 115px;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rowsHtml}
                        </tbody>
                        <tfoot>
                            <tr style="background: #f1f5f9; border-top: 1px solid #cbd5e1;">
                                <td colspan="5" style="padding: 6px 10px; text-align: right; font-weight: 800; font-size: 10.5px; color: #334155; text-transform: uppercase;">
                                    Subtotal for ${dateKey}:
                                </td>
                                <td style="padding: 6px 10px; text-align: right; font-weight: 900; font-size: 11.5px; color: #be123c;">
                                    PKR ${window.formatExpenseMoney(daySubtotal)}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            `;
        });
    }

    // Category breakdown summary block
    let catSummaryHtml = '';
    const catKeys = Object.keys(categoryTotals);
    if (catKeys.length > 0) {
        catSummaryHtml = `
            <div style="margin-top: 20px; padding: 12px; background: #fff1f2; border: 1px solid #fecdd3; border-radius: 4px; page-break-inside: avoid;">
                <div style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #9f1239; margin-bottom: 8px;">
                    Category-Wise Expenditure Breakdown
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    ${catKeys.map(k => {
                        const amt = categoryTotals[k];
                        const pct = totalAmount > 0 ? ((amt / totalAmount) * 100).toFixed(1) : 0;
                        return `
                            <div style="background: #ffffff; border: 1px solid #fbcfe8; padding: 6px 10px; border-radius: 4px; font-size: 11px; min-width: 140px;">
                                <div style="font-weight: 700; color: #0f172a;">${k}</div>
                                <div style="font-weight: 900; color: #be123c; margin-top: 2px;">
                                    PKR ${window.formatExpenseMoney(amt)}
                                    <span style="font-size: 9.5px; color: #64748b; font-weight: normal; margin-left: 3px;">(${pct}%)</span>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
    }

    return `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Datewise Expense Report - ${periodLabel}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm 12mm 12mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #0f172a;
            background: #ffffff;
            margin: 0;
            padding: 0;
            font-size: 12px;
            line-height: 1.4;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }
        .header-table td {
            vertical-align: top;
        }
        .meta-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 10px 14px;
            margin-bottom: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .kpi-cards {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
        }
        .kpi-card {
            flex: 1;
            padding: 10px 14px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
        }
        .kpi-card.highlight {
            background: #fff1f2;
            border-color: #fecdd3;
        }
        .kpi-title {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
        }
        .kpi-value {
            font-size: 18px;
            font-weight: 900;
            color: #0f172a;
            margin-top: 2px;
        }
        .signatures {
            margin-top: 36px;
            display: flex;
            justify-content: space-between;
            page-break-inside: avoid;
        }
        .sign-block {
            width: 28%;
            border-top: 1px dashed #475569;
            padding-top: 6px;
            text-align: center;
            font-size: 10.5px;
            font-weight: 700;
            color: #334155;
        }
        .footer-note {
            margin-top: 24px;
            border-top: 1px solid #cbd5e1;
            padding-top: 8px;
            display: flex;
            justify-content: space-between;
            font-size: 9.5px;
            color: #64748b;
        }
        @media print {
            body { margin: 0; padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 65%;">
                <div style="font-size: 22px; font-weight: 900; letter-spacing: -0.5px; color: #0f172a; text-transform: uppercase;">
                    ${brand.name || 'JUGNU SALOON'}
                </div>
                <div style="font-size: 11px; font-weight: 700; color: #be123c; margin-top: 2px;">
                    ${brand.slogan || 'LUXURY SALON & SPA EXPERIENCE'}
                </div>
                <div style="font-size: 10.5px; color: #475569; margin-top: 4px;">
                    ${brand.address ? `${brand.address}` : ''}
                    ${brand.phone1 ? ` | <strong>Tel:</strong> ${brand.phone1}` : ''}
                    ${brand.phone2 ? ` / ${brand.phone2}` : ''}
                </div>
            </td>
            <td style="width: 35%; text-align: right;">
                <div style="display: inline-block; background: #0f172a; color: #fbbf24; padding: 6px 14px; font-weight: 900; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">
                    FINANCIAL AUDIT
                </div>
                <div style="font-size: 14px; font-weight: 800; color: #be123c; margin-top: 6px;">
                    DATEWISE EXPENSE REPORT
                </div>
                <div style="font-size: 10px; color: #64748b; margin-top: 2px;">
                    Generated: ${printedAt}
                </div>
            </td>
        </tr>
    </table>

    <div class="meta-box">
        <div>
            <span style="font-size: 10px; font-weight: 800; text-transform: uppercase; color: #64748b;">Report Range:</span>
            <strong style="font-size: 13px; color: #0f172a; margin-left: 6px;">${periodLabel}</strong>
        </div>
        <div>
            <span style="font-size: 10px; font-weight: 800; text-transform: uppercase; color: #64748b;">Category:</span>
            <strong style="font-size: 12px; color: #0f172a; margin-left: 6px;">${categoryTitle ? categoryTitle : 'All Categories'}</strong>
        </div>
        <div>
            <span style="font-size: 10px; font-weight: 800; text-transform: uppercase; color: #64748b;">Total Days Active:</span>
            <strong style="font-size: 13px; color: #0f172a; margin-left: 6px;">${dates.length} Days</strong>
        </div>
    </div>

    <div class="kpi-cards">
        <div class="kpi-card highlight">
            <div class="kpi-title" style="color: #9f1239;">Total Filtered Expenditure</div>
            <div class="kpi-value" style="color: #be123c;">PKR ${window.formatExpenseMoney(totalAmount)}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-title">Total Vouchers Logged</div>
            <div class="kpi-value">${list.length} Records</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-title">Daily Average</div>
            <div class="kpi-value">
                PKR ${window.formatExpenseMoney(dates.length > 0 ? (totalAmount / dates.length) : 0)}
            </div>
        </div>
    </div>

    ${datewiseSectionsHtml}

    <!-- GRAND SUMMARY ROW -->
    <div style="background-color: #0f172a; color: #ffffff; padding: 12px 16px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; margin-top: 16px;">
        <div style="font-size: 13px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px;">
            GRAND TOTAL EXPENDITURE (${periodLabel})
        </div>
        <div style="font-size: 16px; font-weight: 900; color: #fbbf24;">
            PKR ${window.formatExpenseMoney(totalAmount)}
        </div>
    </div>

    ${catSummaryHtml}

    <div class="signatures">
        <div class="sign-block">
            Prepared By (Accountant / Cashier)
        </div>
        <div class="sign-block">
            Auditor / Finance Reviewer
        </div>
        <div class="sign-block">
            Authorized Salon Director / Seal
        </div>
    </div>

    <div class="footer-note">
        <div>Official Datewise Expenditure Statement • ${brand.name || 'Jugnu Saloon'} Management System</div>
        <div>Page 1 of 1</div>
    </div>
</body>
</html>`;
};
</script>
@endsection
