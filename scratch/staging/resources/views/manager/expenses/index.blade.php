@extends('layouts.material')

@section('title', 'Expense Management')

@section('content')
<div x-data="{ 
    createModalOpen: false,
    editModalOpen: false,
    editExpense: { exp_id: null, exp_title: '', exp_category_id: '', amount: 0, description: '', created_at: '' }
}" class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="p-2.5 bg-rose-50 text-rose-600">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Expense Management</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">Track, categorize, analyze, and manage operational salon expenditures.</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('manager.expense-categories.index') }}" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs flex items-center gap-1.5">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                <span>Manage Categories</span>
            </a>
            <button @click="createModalOpen = true" 
                    class="inline-flex items-center gap-2 px-5 py-3 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-sm transition-all">
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

        <!-- Metric 2: This Month Total -->
        <div class="bg-white p-5 border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-[10px] font-extrabold uppercase text-slate-400 tracking-wider">This Month's Total</p>
                <h3 class="text-2xl font-black text-slate-900 mt-1">{{ number_format($thisMonthTotal, 2) }}</h3>
                <p class="text-[11px] font-semibold text-emerald-600 mt-1">{{ date('F Y') }}</p>
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

        <!-- Metric 4: Total Categories Count -->
        <div class="bg-white p-5 border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-[10px] font-extrabold uppercase text-slate-400 tracking-wider">Active Categories</p>
                <h3 class="text-2xl font-black text-slate-900 mt-1">{{ count($categories) }}</h3>
                <p class="text-[11px] font-semibold text-slate-500 mt-1">Configured Types</p>
            </div>
            <div class="p-3 bg-purple-50 text-purple-600 rounded-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
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
                    $percentage = $totalExpensesAmount > 0 ? round(($catItem->total_amount / $totalExpensesAmount) * 100, 1) : 0;
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
                                <button @click="editExpense = { 
                                            exp_id: {{ $exp->exp_id }}, 
                                            exp_title: '{{ addslashes($exp->exp_title) }}', 
                                            exp_category_id: {{ $exp->exp_category_id }}, 
                                            amount: {{ $exp->amount }}, 
                                            description: '{{ addslashes($exp->description ?? '') }}',
                                            created_at: '{{ $exp->created_at ? $exp->created_at->format('Y-m-d') : '' }}'
                                        }; editModalOpen = true" 
                                        class="p-1.5 text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition-colors" title="Edit Expense">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </button>
                                <form method="POST" action="{{ route('manager.expenses.destroy', $exp) }}" onsubmit="return confirm('Delete expense entry #EXP-{{ $exp->exp_id }} ({{ addslashes($exp->exp_title) }})?');" class="inline">
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

            <form method="POST" action="{{ route('manager.expenses.store') }}" class="space-y-4">
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
                    <button type="submit" class="px-6 py-2.5 bg-rose-600 text-white font-bold text-xs shadow-md">
                        Save Expense
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

            <form :action="'{{ url('manager/expenses') }}/' + editExpense.exp_id" method="POST" class="space-y-4">
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
                    <button type="submit" class="px-6 py-2.5 bg-rose-600 text-white font-bold text-xs shadow-md">
                        Update Expense
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
