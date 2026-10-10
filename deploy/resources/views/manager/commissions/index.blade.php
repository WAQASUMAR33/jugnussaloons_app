@extends('layouts.material')

@section('title', 'Commission Management')

@section('content')
<style>
@keyframes indeterminate-bar {
    0% { left: -35%; right: 100%; }
    60% { left: 100%; right: -90%; }
    100% { left: 100%; right: -90%; }
}
@keyframes indeterminate-short-bar {
    0% { left: -200%; right: 100%; }
    60% { left: 107%; right: -8%; }
    100% { left: 107%; right: -8%; }
}
.progress-bar-loading {
    position: relative;
    overflow: hidden;
}
.progress-bar-loading::before {
    content: '';
    position: absolute;
    background-color: #4f46e5;
    top: 0; left: 0; bottom: 0;
    will-change: left, right;
    animation: indeterminate-bar 2.1s cubic-bezier(0.65, 0.815, 0.735, 0.395) infinite;
}
.progress-bar-loading::after {
    content: '';
    position: absolute;
    background-color: #818cf8;
    top: 0; left: 0; bottom: 0;
    will-change: left, right;
    animation: indeterminate-short-bar 2.1s cubic-bezier(0.165, 0.84, 0.44, 1) infinite;
    animation-delay: 1.15s;
}
</style>

<div class="space-y-6" 
     x-data="{
        modalOpen: {{ (!empty($errors) && $errors->any()) ? 'true' : 'false' }},
        isEdit: false,
        isSubmitting: false,
        employeeSearch: '',
        commissionPercentage: 2,
        employeesList: {{ json_encode($employees) }},
        individualPrintModalOpen: false,
        individualPrintEmployeeId: '{{ $employeeId ?: '' }}',
        individualPrintFromDate: '{{ $fromDate ?: date('Y-m-01') }}',
        individualPrintToDate: '{{ $toDate ?: date('Y-m-d') }}',
        openIndividualPrintModal(empId = null) {
            if (empId) {
                this.individualPrintEmployeeId = empId;
            }
            this.individualPrintModalOpen = true;
        },
        submitIndividualPrint() {
            if (!this.individualPrintEmployeeId) {
                alert('Please select an employee');
                return;
            }
            const params = new URLSearchParams({
                employee_id: this.individualPrintEmployeeId,
                from_date: this.individualPrintFromDate || '',
                to_date: this.individualPrintToDate || ''
            });
            window.open('{{ route('manager.commissions.print') }}?' + params.toString(), '_blank');
            this.individualPrintModalOpen = false;
        },
        form: {
            id: null,
            employee_id: '{{ old('employee_id', '') }}',
            amount_of_work: '{{ old('amount_of_work', '') }}',
            total_amount: '{{ old('total_amount', '') }}',
            date: '{{ old('date', date('Y-m-d')) }}',
            description: '{{ old('description', '') }}'
        },

        get filteredEmployees() {
            if (!this.employeeSearch || this.employeeSearch.trim() === '') {
                return this.employeesList;
            }
            const q = this.employeeSearch.toLowerCase();
            return this.employeesList.filter(e => {
                const name = (e.name || '').toLowerCase();
                const phone = (e.phone_no1 || '').toLowerCase();
                const card = (e.card_no || '').toLowerCase();
                return name.includes(q) || phone.includes(q) || card.includes(q);
            });
        },

        openCreateModal() {
            this.isEdit = false;
            this.isSubmitting = false;
            this.employeeSearch = '';
            this.commissionPercentage = 2;
            this.form = {
                id: null,
                employee_id: '',
                amount_of_work: '',
                total_amount: '',
                date: '{{ date('Y-m-d') }}',
                description: ''
            };
            this.modalOpen = true;
        },

        openEditModal(item) {
            this.isEdit = true;
            this.isSubmitting = false;
            this.employeeSearch = '';
            const work = parseFloat(item.amount_of_work) || 0;
            const total = parseFloat(item.total_amount) || 0;
            const pct = work > 0 ? ((total / work) * 100).toFixed(2) : 2;
            this.commissionPercentage = parseFloat(pct);

            this.form = {
                id: item.id,
                employee_id: item.employee_id,
                amount_of_work: item.amount_of_work,
                total_amount: item.total_amount,
                date: item.date ? item.date.split('T')[0] : '{{ date('Y-m-d') }}',
                description: item.description || ''
            };
            this.modalOpen = true;
        },

        onWorkAmountChange() {
            const work = parseFloat(this.form.amount_of_work) || 0;
            const pct = parseFloat(this.commissionPercentage) || 0;
            if (work > 0 && pct >= 0) {
                this.form.total_amount = ((work * pct) / 100).toFixed(2);
            } else if (work === 0) {
                this.form.total_amount = '0.00';
            }
        },

        onPercentageChange() {
            const work = parseFloat(this.form.amount_of_work) || 0;
            const pct = parseFloat(this.commissionPercentage) || 0;
            if (work > 0 && pct >= 0) {
                this.form.total_amount = ((work * pct) / 100).toFixed(2);
            }
        },

        applyPercentage(pct) {
            this.commissionPercentage = pct;
            this.onPercentageChange();
        },

        onTotalCommissionChange() {
            const work = parseFloat(this.form.amount_of_work) || 0;
            const total = parseFloat(this.form.total_amount) || 0;
            if (work > 0 && total >= 0) {
                this.commissionPercentage = parseFloat(((total / work) * 100).toFixed(2));
            }
        }
     }">

    <!-- Page Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-none border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold text-slate-400 uppercase tracking-wider">
                <span>Payroll & Staff Operations</span>
                <span>•</span>
                <span class="text-indigo-600">Commission Management</span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight heading-font mt-0.5">
                Staff Commission Management
            </h1>
            <p class="text-xs text-slate-500 mt-1">
                Record and track employee commission payouts, work volume, and service performance.
            </p>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
            <!-- Print Consolidated Official Report Button -->
            <a href="{{ route('manager.commissions.print', request()->all()) }}" 
               target="_blank"
               class="px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-xs transition-all flex items-center gap-2 shadow-xs hover:shadow cursor-pointer">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                <span>Print Official Report</span>
            </a>

            <!-- Print Individual Employee Statement Button -->
            <button type="button" 
                    @click="openIndividualPrintModal()"
                    class="px-4 py-2.5 bg-white hover:bg-indigo-50/50 text-indigo-700 border border-indigo-200 font-extrabold text-xs transition-all flex items-center gap-2 shadow-xs hover:shadow cursor-pointer">
                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                <span>Print Employee Statement</span>
            </button>

            <!-- Add Commission Button -->
            <button type="button" 
                    @click="openCreateModal()" 
                    style="background-color: #4f46e5; color: #ffffff;"
                    class="px-5 py-2.5 hover:bg-indigo-700 text-white font-extrabold text-xs rounded-none transition-all flex items-center gap-2 shadow-sm hover:shadow cursor-pointer">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                <span>Record New Commission</span>
            </button>
        </div>
    </div>

    <!-- Alert Notifications -->
    @if(session('success'))
    <div class="p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 text-xs font-bold flex items-center justify-between shadow-2xs">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <span>{{ session('success') }}</span>
        </div>
        <button type="button" @click="$el.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900 font-bold">&times;</button>
    </div>
    @endif

    @if(session('error'))
    <div class="p-4 bg-rose-50 border-l-4 border-rose-500 text-rose-800 text-xs font-bold flex items-center justify-between shadow-2xs">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            <span>{{ session('error') }}</span>
        </div>
        <button type="button" @click="$el.parentElement.remove()" class="text-rose-600 hover:text-rose-900 font-bold">&times;</button>
    </div>
    @endif

    @if(!empty($errors) && $errors->any())
    <div class="p-4 bg-rose-50 border-l-4 border-rose-500 text-rose-800 text-xs font-bold space-y-1 shadow-2xs">
        <div class="flex items-center justify-between">
            <span class="font-black text-rose-900">Please correct the following errors:</span>
            <button type="button" @click="$el.parentElement.remove()" class="text-rose-600 hover:text-rose-900">&times;</button>
        </div>
        @foreach($errors->all() as $error)
            <div class="flex items-center gap-2 font-semibold">
                <span>&bull;</span>
                <span>{{ $error }}</span>
            </div>
        @endforeach
    </div>
    @endif

    <!-- ========================================== -->
    <!-- STATISTICS SUMMARY SECTION (TOP) -->
    <!-- ========================================== -->
    <div class="space-y-3">
        <div class="flex items-center gap-2 border-b border-slate-200 pb-2">
            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            <h2 class="text-base font-black text-slate-900 tracking-tight">Commission Summary Statistics</h2>
            <span class="text-xs font-bold text-slate-500">(Calculated on current filtered period)</span>
        </div>

        <!-- Summary KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Total Work Value Card -->
            <div class="bg-white p-5 border border-slate-200 rounded-none shadow-sm flex items-center justify-between">
                <div class="space-y-1">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Work Volume</span>
                    <h3 class="text-2xl font-black text-slate-900 font-mono">
                        PKR {{ number_format($totalWorkAmount, 2) }}
                    </h3>
                    <p class="text-[10px] text-slate-500 font-semibold">Total revenue / work generated</p>
                </div>
                <div class="w-12 h-12 bg-slate-100 text-slate-700 flex items-center justify-center rounded-none shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                </div>
            </div>

            <!-- Total Commission Payable Card -->
            <div class="bg-white p-5 border border-indigo-200 rounded-none shadow-sm flex items-center justify-between bg-indigo-50/20">
                <div class="space-y-1">
                    <span class="text-[10px] font-black uppercase tracking-wider text-indigo-700">Total Commission Paid</span>
                    <h3 class="text-2xl font-black text-indigo-700 font-mono">
                        PKR {{ number_format($totalCommissionAmount, 2) }}
                    </h3>
                    <p class="text-[10px] text-indigo-600 font-semibold">Net commission earnings</p>
                </div>
                <div class="w-12 h-12 bg-indigo-100 text-indigo-700 flex items-center justify-center rounded-none shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>

            <!-- Average Commission Rate % -->
            <div class="bg-white p-5 border border-emerald-200 rounded-none shadow-sm flex items-center justify-between bg-emerald-50/20">
                <div class="space-y-1">
                    <span class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Avg. Commission Yield</span>
                    <h3 class="text-2xl font-black text-emerald-700 font-mono">
                        {{ $avgCommissionRate }}%
                    </h3>
                    <p class="text-[10px] text-emerald-600 font-semibold">Average commission % of work</p>
                </div>
                <div class="w-12 h-12 bg-emerald-100 text-emerald-700 flex items-center justify-center rounded-none shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"></path></svg>
                </div>
            </div>

            <!-- Total Transactions Count -->
            <div class="bg-white p-5 border border-slate-200 rounded-none shadow-sm flex items-center justify-between">
                <div class="space-y-1">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Entries Logged</span>
                    <h3 class="text-2xl font-black text-slate-900 font-mono">
                        {{ $totalRecords }}
                    </h3>
                    <p class="text-[10px] text-slate-500 font-semibold">Matched transaction records</p>
                </div>
                <div class="w-12 h-12 bg-slate-100 text-slate-700 flex items-center justify-center rounded-none shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                </div>
            </div>
        </div>
    </div>

    @if(isset($employeeBreakdown) && $employeeBreakdown->isNotEmpty())
    <!-- ========================================== -->
    <!-- STAFF PERFORMANCE & QUICK STATEMENTS PANEL -->
    <!-- ========================================== -->
    <div class="bg-white border border-slate-200 rounded-none shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 bg-slate-50/70">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">
                    Active Staff Commission Breakdown ({{ $employeeBreakdown->count() }} Stylists)
                </h3>
                <span class="text-[11px] text-slate-500 font-medium">
                    ({{ $fromDate ? \Carbon\Carbon::parse($fromDate)->format('d M') : 'Period Start' }} &mdash; {{ $toDate ? \Carbon\Carbon::parse($toDate)->format('d M, Y') : 'Current' }})
                </span>
            </div>
            <div class="flex items-center gap-2 text-[11px]">
                <button type="button" 
                        @click="openIndividualPrintModal()" 
                        class="text-indigo-600 hover:text-indigo-800 font-bold flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    <span>Print Custom Staff Statement</span>
                </button>
            </div>
        </div>

        <div class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3.5">
            @foreach($employeeBreakdown as $breakdown)
            @php
                $emp = $breakdown->employee;
                $effectiveRate = ($breakdown->total_work > 0) ? round(($breakdown->total_commission / $breakdown->total_work) * 100, 1) : 0;
            @endphp
            <div class="border border-slate-200 bg-slate-50/40 hover:bg-white hover:border-indigo-300 hover:shadow-xs p-3.5 transition-all flex flex-col justify-between space-y-3 group">
                <div>
                    <!-- Employee Top Info -->
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-8 h-8 bg-indigo-100 text-indigo-700 font-black text-xs flex items-center justify-center rounded-none shrink-0 group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                                {{ strtoupper(substr($emp ? $emp->name : 'ST', 0, 2)) }}
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-xs font-black text-slate-900 truncate" title="{{ $emp ? $emp->name : 'N/A' }}">
                                    {{ $emp ? $emp->name : 'N/A' }}
                                </h4>
                                <div class="flex items-center gap-1.5 text-[10px] text-slate-500 mt-0.5">
                                    @if($emp && $emp->phone_no1)
                                    <span>{{ $emp->phone_no1 }}</span>
                                    <span>&bull;</span>
                                    @endif
                                    <span class="text-indigo-600 font-bold">{{ $breakdown->total_entries }} entries</span>
                                </div>
                            </div>
                        </div>

                        @if($emp && $emp->category)
                        <span class="px-1.5 py-0.5 bg-slate-200/70 text-slate-700 font-black text-[9px] uppercase tracking-wider shrink-0">
                            {{ $emp->category->title }}
                        </span>
                        @endif
                    </div>

                    <!-- Metrics Grid -->
                    <div class="grid grid-cols-2 gap-2 mt-3 pt-2.5 border-t border-slate-200/70">
                        <div>
                            <span class="text-[9px] font-black uppercase text-slate-400 block">Work Volume</span>
                            <span class="text-xs font-black font-mono text-slate-800">
                                PKR {{ number_format($breakdown->total_work, 0) }}
                            </span>
                        </div>
                        <div class="text-right">
                            <span class="text-[9px] font-black uppercase text-indigo-500 block">Commission ({{ $effectiveRate }}%)</span>
                            <span class="text-xs font-black font-mono text-indigo-700">
                                PKR {{ number_format($breakdown->total_commission, 0) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Action Links -->
                <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-1">
                    <a href="{{ route('manager.commissions.index', ['employee_id' => $breakdown->employee_id, 'from_date' => $fromDate, 'to_date' => $toDate]) }}" 
                       class="text-[10px] font-bold text-slate-500 hover:text-slate-800 transition-colors">
                        View Records &rarr;
                    </a>

                    <a href="{{ route('manager.commissions.print', ['employee_id' => $breakdown->employee_id, 'from_date' => $fromDate, 'to_date' => $toDate]) }}" 
                       target="_blank"
                       title="Print Statement for {{ $emp ? $emp->name : 'Staff' }}"
                       class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white font-extrabold text-[10px] transition-colors flex items-center gap-1 border border-indigo-200 hover:border-transparent">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        <span>Print Statement</span>
                    </a>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- FILTER BAR -->
    <div class="bg-white p-5 rounded-none border border-slate-200 shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-700 flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                <span>Filter & Search Commissions</span>
            </h3>
            
            <!-- Quick Date Filter Presets -->
            <div class="hidden sm:flex items-center gap-1 text-[11px] font-bold text-slate-500">
                <span>Quick Range:</span>
                <a href="{{ route('manager.commissions.index', ['date' => date('Y-m-d')]) }}" 
                   class="px-2 py-0.5 {{ request('date') === date('Y-m-d') ? 'bg-indigo-100 text-indigo-700 font-black' : 'hover:bg-slate-100' }}">Today</a>
                <span>&bull;</span>
                <a href="{{ route('manager.commissions.index', ['from_date' => date('Y-m-01'), 'to_date' => date('Y-m-t')]) }}" 
                   class="px-2 py-0.5 {{ request('from_date') === date('Y-m-01') ? 'bg-indigo-100 text-indigo-700 font-black' : 'hover:bg-slate-100' }}">This Month</a>
                <span>&bull;</span>
                <a href="{{ route('manager.commissions.index') }}" 
                   class="px-2 py-0.5 {{ !request()->hasAny(['search', 'employee_id', 'from_date', 'to_date', 'date']) ? 'bg-indigo-100 text-indigo-700 font-black' : 'hover:bg-slate-100' }}">All Records</a>
            </div>
        </div>

        <form action="{{ route('manager.commissions.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3.5">
            <!-- Search Keyword Input -->
            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Keyword Search</label>
                <div class="relative">
                    <input type="text" 
                           name="search" 
                           value="{{ $search }}" 
                           placeholder="Description, employee name, phone..." 
                           class="w-full text-xs font-semibold pl-8 pr-3 py-2 bg-slate-50 border border-slate-300 focus:bg-white focus:border-indigo-600 focus:ring-0 rounded-none transition-colors">
                    <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
            </div>

            <!-- Employee Dropdown Filter -->
            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Filter By Employee</label>
                <select name="employee_id" 
                        class="w-full text-xs font-semibold py-2 px-3 bg-slate-50 border border-slate-300 focus:bg-white focus:border-indigo-600 focus:ring-0 rounded-none transition-colors">
                    <option value="">All Employees / Stylists</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" {{ (string)$employeeId === (string)$emp->id ? 'selected' : '' }}>
                            {{ $emp->name }} ({{ $emp->phone_no1 ?: 'Staff' }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Date Range From -->
            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">From Date</label>
                <input type="date" 
                       name="from_date" 
                       value="{{ $fromDate }}" 
                       class="w-full text-xs font-semibold py-2 px-3 bg-slate-50 border border-slate-300 focus:bg-white focus:border-indigo-600 focus:ring-0 rounded-none transition-colors">
            </div>

            <!-- Date Range To & Submit Buttons -->
            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">To Date</label>
                <div class="flex items-center gap-1.5">
                    <input type="date" 
                           name="to_date" 
                           value="{{ $toDate }}" 
                           class="w-full text-xs font-semibold py-2 px-3 bg-slate-50 border border-slate-300 focus:bg-white focus:border-indigo-600 focus:ring-0 rounded-none transition-colors">
                    
                    <button type="submit" 
                            class="px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-none transition-colors shrink-0 cursor-pointer">
                        Filter
                    </button>
                    <a href="{{ route('manager.commissions.print', request()->all()) }}" 
                       target="_blank"
                       title="Print Filtered Commission Report"
                       class="px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-none shrink-0 transition-colors flex items-center gap-1.5 shadow-2xs">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        <span>Print</span>
                    </a>
                    @if(request()->hasAny(['search', 'employee_id', 'from_date', 'to_date', 'date']))
                    <a href="{{ route('manager.commissions.index') }}" 
                       title="Reset Filters"
                       class="px-2.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-none shrink-0 transition-colors">
                        ✕
                    </a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    <!-- MAIN COMMISSION TABLE -->
    <div class="bg-white border border-slate-200 rounded-none shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 bg-slate-50/50">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-700">Commission Logs</span>
                <span class="px-2 py-0.5 bg-indigo-50 text-indigo-700 text-[10px] font-black border border-indigo-200">
                    {{ $commissions->total() }} Records Found
                </span>
                <a href="{{ route('manager.commissions.print', request()->all()) }}" 
                   target="_blank"
                   class="ml-2 text-[11px] font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    <span>Print Report Statement</span>
                </a>
            </div>
            <div class="text-[11px] font-semibold text-slate-500">
                Showing {{ $commissions->firstItem() ?? 0 }} to {{ $commissions->lastItem() ?? 0 }} of {{ $commissions->total() }} entries
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-100/80 border-b border-slate-200 text-[11px] font-black uppercase text-slate-700 tracking-wider">
                        <th class="py-3 px-4 w-14"># ID</th>
                        <th class="py-3 px-4">Employee / Stylist</th>
                        <th class="py-3 px-4 text-right">Amount of Work</th>
                        <th class="py-3 px-4 text-right">Total Commission</th>
                        <th class="py-3 px-4 text-center w-28">Commission %</th>
                        <th class="py-3 px-4">Date</th>
                        <th class="py-3 px-4">Description / Notes</th>
                        <th class="py-3 px-4 text-right w-24">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 text-slate-800">
                    @forelse($commissions as $item)
                    <tr class="hover:bg-slate-50/80 transition-colors group">
                        <!-- ID -->
                        <td class="py-3.5 px-4 font-mono font-bold text-slate-500">
                            #{{ $item->id }}
                        </td>

                        <!-- Employee -->
                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-1.5 font-extrabold text-slate-900">
                                <span>{{ $item->employee ? $item->employee->name : 'N/A' }}</span>
                                @if($item->employee_id)
                                <a href="{{ route('manager.commissions.print', ['employee_id' => $item->employee_id, 'from_date' => $fromDate, 'to_date' => $toDate]) }}" 
                                   target="_blank" 
                                   title="Print Statement for {{ $item->employee ? $item->employee->name : 'Staff' }}" 
                                   class="text-slate-400 hover:text-indigo-600 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                </a>
                                @endif
                            </div>
                            <div class="text-[10px] text-slate-500 font-medium flex items-center gap-1.5 mt-0.5">
                                @if($item->employee && $item->employee->phone_no1)
                                    <span>{{ $item->employee->phone_no1 }}</span>
                                @endif
                                @if($item->employee && $item->employee->category)
                                    <span class="px-1.5 py-0.2 bg-slate-100 text-slate-600 font-bold text-[9px] border border-slate-200">
                                        {{ $item->employee->category->title }}
                                    </span>
                                @endif
                            </div>
                        </td>

                        <!-- Amount of Work -->
                        <td class="py-3.5 px-4 text-right font-mono font-bold text-slate-700">
                            PKR {{ number_format($item->amount_of_work, 2) }}
                        </td>

                        <!-- Total Commission -->
                        <td class="py-3.5 px-4 text-right font-mono font-black text-indigo-700 text-sm">
                            PKR {{ number_format($item->total_amount, 2) }}
                        </td>

                        <!-- Commission % Rate -->
                        <td class="py-3.5 px-4 text-center">
                            <span class="px-2.5 py-0.5 bg-emerald-50 text-emerald-800 border border-emerald-300 font-black text-[10px]">
                                {{ $item->commission_percentage }}%
                            </span>
                        </td>

                        <!-- Date -->
                        <td class="py-3.5 px-4 font-mono text-slate-700 font-semibold whitespace-nowrap">
                            {{ \Carbon\Carbon::parse($item->date)->format('d M, Y') }}
                        </td>

                        <!-- Description -->
                        <td class="py-3.5 px-4 text-slate-600 max-w-xs truncate" title="{{ $item->description }}">
                            {{ $item->description ?: '—' }}
                        </td>

                        <!-- Actions -->
                        <td class="py-3.5 px-4 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <!-- Print Employee Statement -->
                                @if($item->employee_id)
                                <a href="{{ route('manager.commissions.print', ['employee_id' => $item->employee_id, 'from_date' => $fromDate, 'to_date' => $toDate]) }}" 
                                   target="_blank"
                                   class="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-none transition-colors cursor-pointer"
                                   title="Print Statement for {{ $item->employee ? $item->employee->name : 'Staff' }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                </a>
                                @endif

                                <!-- Edit Button -->
                                <button type="button" 
                                        @click="openEditModal({{ json_encode($item) }})" 
                                        class="p-1.5 text-slate-600 hover:text-indigo-600 hover:bg-slate-100 rounded-none transition-colors cursor-pointer"
                                        title="Edit Entry">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </button>

                                <!-- Delete Form -->
                                <form action="{{ route('manager.commissions.destroy', $item->id) }}" 
                                      method="POST" 
                                      onsubmit="return confirm('Are you sure you want to delete commission record #{{ $item->id }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" 
                                            class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-none transition-colors cursor-pointer"
                                            title="Delete Entry">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-12 text-center text-slate-400 font-semibold">
                            <div class="flex flex-col items-center justify-center space-y-2">
                                <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <span>No commission records found matching your query.</span>
                                <button type="button" @click="openCreateModal()" class="text-xs text-indigo-600 font-black hover:underline mt-1 cursor-pointer">
                                    + Record First Commission
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($commissions->hasPages())
        <div class="p-4 border-t border-slate-200 bg-slate-50">
            {{ $commissions->links() }}
        </div>
        @endif
    </div>

    <!-- ========================================== -->
    <!-- ADD / EDIT COMMISSION MODAL -->
    <!-- ========================================== -->
    <div x-show="modalOpen" 
         @click.self="if(!isSubmitting) modalOpen = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs" 
         x-cloak>
        <div class="bg-white max-w-lg w-full p-6 shadow-2xl rounded-none border border-slate-300 space-y-4 max-h-[92vh] overflow-y-auto relative">
            
            <!-- Loading Progress Bar on Submit -->
            <div x-show="isSubmitting" class="w-full bg-indigo-100 h-1.5 progress-bar-loading -mt-6 -mx-6 mb-3"></div>

            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <div>
                    <h3 class="text-base font-black text-slate-900 heading-font" x-text="isEdit ? 'Edit Commission Entry #' + form.id : 'Record New Commission'"></h3>
                    <p class="text-xs text-slate-500 font-medium mt-0.5">Select the employee, record work done, and specify commission rate.</p>
                </div>
                <button type="button" 
                        @click="if(!isSubmitting) modalOpen = false" 
                        :disabled="isSubmitting"
                        :class="isSubmitting ? 'opacity-40 cursor-not-allowed' : 'text-slate-400 hover:text-slate-600'"
                        class="text-xl font-bold p-1 leading-none cursor-pointer">&times;</button>
            </div>

            <form :action="isEdit ? ('/manager/commissions/' + form.id) : '{{ route('manager.commissions.store') }}'" 
                  method="POST" 
                  @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true;"
                  class="space-y-4">
                @csrf
                <template x-if="isEdit">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <!-- Employee Selection with Live Search (Type Employee) -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-black text-slate-700 uppercase tracking-wider">
                        Employee / Stylist <span class="text-rose-600">*</span>
                    </label>
                    
                    <!-- Search Employee Filter Box -->
                    <div class="relative">
                        <input type="text" 
                               x-model="employeeSearch" 
                               placeholder="Type to search staff by name, phone..." 
                               class="w-full text-xs font-semibold pl-8 pr-3 py-2 bg-slate-50 border border-slate-300 focus:bg-white focus:border-indigo-600 focus:ring-0 rounded-none transition-colors">
                        <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>

                    <!-- Dropdown Select -->
                    <select name="employee_id" 
                            x-model="form.employee_id" 
                            required 
                            class="w-full text-xs font-semibold py-2 px-3 bg-white border border-slate-300 focus:border-indigo-600 focus:ring-0 rounded-none">
                        <option value="">-- Choose Employee (Staff Account) --</option>
                        <template x-for="emp in filteredEmployees" :key="emp.id">
                            <option :value="emp.id" x-text="emp.name + (emp.phone_no1 ? ' (' + emp.phone_no1 + ')' : '') + (emp.category ? ' • ' + emp.category.title : '')"></option>
                        </template>
                    </select>
                    @error('employee_id')
                        <p class="text-rose-600 text-[11px] font-bold mt-0.5">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Work Amount & Commission Rate Section -->
                <div class="p-3.5 bg-slate-50 border border-slate-200 space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- Amount of Work -->
                        <div class="space-y-1">
                            <label class="block text-xs font-black text-slate-700 uppercase tracking-wider">
                                Amount of Work (PKR) <span class="text-rose-600">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-xs font-black text-slate-400">PKR</span>
                                <input type="number" 
                                       step="0.01" 
                                       min="0" 
                                       name="amount_of_work" 
                                       x-model="form.amount_of_work" 
                                       @input="onWorkAmountChange()" 
                                       required 
                                       placeholder="5000.00"
                                       class="w-full text-xs font-mono font-bold pl-12 pr-3 py-2 bg-white border border-slate-300 focus:border-indigo-600 focus:ring-0 rounded-none">
                            </div>
                            @error('amount_of_work')
                                <p class="text-rose-600 text-[11px] font-bold mt-0.5">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Commission Rate % -->
                        <div class="space-y-1">
                            <label class="block text-xs font-black text-slate-700 uppercase tracking-wider">
                                Commission Rate (%) <span class="text-rose-600">*</span>
                            </label>
                            <div class="relative">
                                <input type="number" 
                                       step="0.01" 
                                       min="0" 
                                       max="100" 
                                       x-model="commissionPercentage" 
                                       @input="onPercentageChange()" 
                                       placeholder="2"
                                       class="w-full text-xs font-mono font-bold pr-8 pl-3 py-2 bg-white border border-slate-300 focus:border-indigo-600 focus:ring-0 rounded-none">
                                <span class="absolute right-3 top-2.5 text-xs font-black text-slate-400">%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Calculated Total Commission Amount (PKR) -->
                    <div class="pt-2 border-t border-slate-200 space-y-1">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-black text-indigo-900 uppercase tracking-wider">
                                Total Commission Amount (PKR) <span class="text-rose-600">*</span>
                            </label>
                            <template x-if="form.amount_of_work && commissionPercentage">
                                <span class="text-[10px] font-bold text-indigo-600 font-mono">
                                    <span x-text="commissionPercentage + '%'"></span> of PKR <span x-text="form.amount_of_work"></span>
                                </span>
                            </template>
                        </div>
                        <div class="relative">
                            <span class="absolute left-3 top-2.5 text-xs font-black text-indigo-500">PKR</span>
                            <input type="number" 
                                   step="0.01" 
                                   min="0" 
                                   name="total_amount" 
                                   x-model="form.total_amount" 
                                   @input="onTotalCommissionChange()"
                                   required 
                                   placeholder="0.00"
                                   class="w-full text-xs font-mono font-black text-indigo-700 pl-12 pr-3 py-2 bg-white border border-indigo-300 focus:border-indigo-600 focus:ring-0 rounded-none">
                        </div>
                        <p class="text-[10px] text-slate-400 font-medium">
                            Automatically computed based on commission rate. You can also directly type a custom commission amount here.
                        </p>
                        @error('total_amount')
                            <p class="text-rose-600 text-[11px] font-bold mt-0.5">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Date & Description -->
                <div class="space-y-3 pt-1">
                    <div>
                        <label class="block text-xs font-black text-slate-700 uppercase tracking-wider mb-1">
                            Date <span class="text-rose-600">*</span>
                        </label>
                        <input type="date" 
                               name="date" 
                               x-model="form.date" 
                               required 
                               class="w-full text-xs font-semibold py-2 px-3 bg-slate-50 border border-slate-300 focus:bg-white focus:border-indigo-600 focus:ring-0 rounded-none">
                        @error('date')
                            <p class="text-rose-600 text-[11px] font-bold mt-0.5">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-black text-slate-700 uppercase tracking-wider mb-1">
                            Description / Service Notes
                        </label>
                        <textarea name="description" 
                                  x-model="form.description" 
                                  rows="2" 
                                  placeholder="e.g. Bridal makeup services, Hair spa package, Client invoice ref..." 
                                  class="w-full text-xs font-medium p-3 bg-slate-50 border border-slate-300 focus:bg-white focus:border-indigo-600 focus:ring-0 rounded-none"></textarea>
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="flex items-center justify-between pt-3 border-t border-slate-200">
                    <div class="text-[11px] text-slate-500 font-semibold">
                        <span x-show="isSubmitting" class="text-indigo-700 font-bold inline-flex items-center gap-1.5 animate-pulse">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Saving commission entry...
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" 
                                @click="if(!isSubmitting) modalOpen = false" 
                                :disabled="isSubmitting"
                                :class="isSubmitting ? 'opacity-50 cursor-not-allowed pointer-events-none' : 'hover:bg-slate-200'"
                                class="px-4 py-2 bg-slate-100 text-slate-700 font-bold text-xs rounded-none transition-colors cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" 
                                :disabled="isSubmitting"
                                :class="isSubmitting ? 'opacity-70 cursor-not-allowed pointer-events-none' : 'hover:opacity-90'"
                                style="background-color: #4f46e5; color: #ffffff;"
                                class="inline-flex items-center justify-center gap-2 min-w-[140px] px-5 py-2 text-white font-black text-xs rounded-none transition-all shadow-sm cursor-pointer">
                            <span x-show="!isSubmitting" class="inline-flex items-center gap-1.5 text-white">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                <span x-text="isEdit ? 'Update Commission' : 'Save Commission'"></span>
                            </span>
                            <span x-show="isSubmitting" class="inline-flex items-center gap-2 text-white">
                                <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>Processing...</span>
                            </span>
                        </button>
                    </div>
                </div>
            </form>

        </div>
    </div>

    <!-- ========================================== -->
    <!-- INDIVIDUAL EMPLOYEE PRINT STATEMENT MODAL -->
    <!-- ========================================== -->
    <div x-show="individualPrintModalOpen" 
         @click.self="individualPrintModalOpen = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs" 
         x-cloak>
        <div class="bg-white max-w-md w-full p-6 shadow-2xl rounded-none border border-slate-300 space-y-4 relative">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-indigo-100 text-indigo-700 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-900 heading-font">Print Employee Statement</h3>
                        <p class="text-[11px] text-slate-500 font-medium">Individual commission breakdown report</p>
                    </div>
                </div>
                <button type="button" @click="individualPrintModalOpen = false" class="text-xl font-bold p-1 text-slate-400 hover:text-slate-600 leading-none cursor-pointer">&times;</button>
            </div>

            <div class="space-y-3.5">
                <!-- Select Employee -->
                <div class="space-y-1">
                    <label class="block text-xs font-black text-slate-700 uppercase tracking-wider">
                        Select Employee / Stylist <span class="text-rose-600">*</span>
                    </label>
                    <select x-model="individualPrintEmployeeId" 
                            class="w-full text-xs font-semibold py-2 px-3 bg-white border border-slate-300 focus:border-indigo-600 focus:ring-0 rounded-none">
                        <option value="">-- Choose Employee --</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}">
                                {{ $emp->name }} ({{ $emp->phone_no1 ?: 'Staff' }}){{ $emp->category ? ' • ' . $emp->category->title : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Date Range -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-black uppercase text-slate-600 mb-1">From Date</label>
                        <input type="date" 
                               x-model="individualPrintFromDate" 
                               class="w-full text-xs font-semibold py-2 px-3 bg-slate-50 border border-slate-300 focus:bg-white focus:border-indigo-600 focus:ring-0 rounded-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase text-slate-600 mb-1">To Date</label>
                        <input type="date" 
                               x-model="individualPrintToDate" 
                               class="w-full text-xs font-semibold py-2 px-3 bg-slate-50 border border-slate-300 focus:bg-white focus:border-indigo-600 focus:ring-0 rounded-none">
                    </div>
                </div>

                <div class="p-3 bg-indigo-50/60 border border-indigo-100 text-[11px] text-indigo-900 space-y-1">
                    <div class="font-bold flex items-center gap-1.5 text-indigo-800">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Individual Statement Features:</span>
                    </div>
                    <p class="text-slate-600 pl-5">
                        Prints an official statement detailing the employee profile, period totals, day-by-day sub-totals, itemized chronological work ledger with running progressive balance, and acknowledgment signatures.
                    </p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                <button type="button" 
                        @click="individualPrintModalOpen = false" 
                        class="px-4 py-2 bg-slate-100 text-slate-700 font-bold text-xs rounded-none hover:bg-slate-200 transition-colors cursor-pointer">
                    Cancel
                </button>
                <button type="button" 
                        @click="submitIndividualPrint()" 
                        style="background-color: #4f46e5; color: #ffffff;"
                        class="px-5 py-2 font-black text-xs rounded-none transition-all flex items-center gap-2 shadow-xs hover:shadow cursor-pointer">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    <span>Generate & Print Report</span>
                </button>
            </div>
        </div>
    </div>

</div>
@endsection
