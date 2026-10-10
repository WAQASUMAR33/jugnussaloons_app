@extends('layouts.material')

@section('title', 'Staff Advance Salary Management')

@section('content')
<div x-data="{ 
    advanceModalOpen: false,
    isSubmitting: false,
    advEmpId: '',
    advAmount: '',
    advDate: '{{ date('Y-m-d') }}',
    advMonthYear: '{{ $monthYear }}',
    advPaymentMode: 'Cash',
    advBankAccountId: '',
    advReason: '',
    advNotes: '',
    advRecordLedger: true,
    isAdmin: {{ $isAdmin ? 'true' : 'false' }},

    setQuickReason(reason) {
        this.advReason = reason;
    }
}" class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 border border-slate-200 shadow-sm rounded-none">
        <div class="flex items-center gap-3.5">
            <div class="p-3 bg-amber-50 text-amber-600 rounded-none">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <div class="flex items-center gap-2 text-xs font-bold text-slate-400 uppercase tracking-wider">
                    <span>Finance & Accounts</span>
                    <span>•</span>
                    <span class="text-amber-600">Advance Salary</span>
                </div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight heading-font mt-0.5">Staff Advance Salary System</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">Issue, manage, and track employee salary advances with automated monthly payroll deductions and ledger syncing.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="{{ route('manager.payroll.index', ['month_year' => $monthYear]) }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs border border-slate-200 rounded-none transition-colors">
                <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                <span>Go to Staff Payroll</span>
            </a>

            <a href="{{ route('manager.payroll.deductions.index', ['month_year' => $monthYear]) }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-800 font-bold text-xs border border-rose-200 rounded-none transition-colors">
                <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4m16 0l-4-4m4 4l-4 4"></path></svg>
                <span>Salary Deductions</span>
            </a>

            @if($isAdmin)
                <button type="button" @click="advanceModalOpen = true" 
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs shadow-sm transition-all rounded-none cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    <span>+ Issue Advance Salary</span>
                </button>
            @endif
        </div>
    </div>

    <!-- Monthly Summary KPI Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 border border-slate-200 shadow-sm rounded-none">
            <p class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Total Advances ({{ date('F Y', strtotime($monthYear . '-01')) }})</p>
            <h3 class="text-2xl font-black text-amber-600 mt-1">PKR {{ number_format($totalAdvancesAmount, 2) }}</h3>
            <p class="text-[11px] text-slate-500 font-semibold mt-0.5">{{ $totalEntriesCount }} advances scheduled</p>
        </div>

        <div class="bg-white p-5 border border-slate-200 shadow-sm rounded-none">
            <p class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Pending Recovery</p>
            <h3 class="text-2xl font-black text-rose-600 mt-1">PKR {{ number_format($totalPendingAmount, 2) }}</h3>
            <p class="text-[11px] text-slate-500 font-semibold mt-0.5">To be deducted from payroll</p>
        </div>

        <div class="bg-white p-5 border border-slate-200 shadow-sm rounded-none">
            <p class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Recovered in Payroll</p>
            <h3 class="text-2xl font-black text-emerald-600 mt-1">PKR {{ number_format($totalRecoveredAmount, 2) }}</h3>
            <p class="text-[11px] text-slate-500 font-semibold mt-0.5">Settled via salary disbursement</p>
        </div>

        <div class="bg-white p-5 border border-slate-200 shadow-sm rounded-none">
            <p class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Staff Beneficiaries</p>
            <h3 class="text-2xl font-black text-slate-900 mt-1">{{ $affectedStaffCount }} Employees</h3>
            <p class="text-[11px] text-slate-500 font-semibold mt-0.5">Received advances this month</p>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white p-4 border border-slate-200 shadow-sm rounded-none">
        <form method="GET" action="{{ route('manager.payroll.advances.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Target Recovery Month</label>
                <input type="month" name="month_year" value="{{ $monthYear }}" onchange="this.form.submit()" 
                       class="w-full px-4 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-amber-500 rounded-none">
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Filter by Staff Member</label>
                <select name="employee_id" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-amber-500 rounded-none">
                    <option value="">All Staff Members</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" {{ (string)$employeeId === (string)$emp->id ? 'selected' : '' }}>👤 {{ $emp->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Status Filter</label>
                <select name="status" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-amber-500 rounded-none">
                    <option value="">All Statuses</option>
                    <option value="pending_recovery" {{ $status === 'pending_recovery' ? 'selected' : '' }}>⏳ Pending Recovery</option>
                    <option value="recovered" {{ $status === 'recovered' ? 'selected' : '' }}>✓ Recovered (Cleared)</option>
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Search Reason / Notes</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search reason, staff, mode..." 
                       class="w-full px-4 py-2 bg-slate-50 border border-slate-200 text-xs font-medium focus:ring-2 focus:ring-amber-500 rounded-none">
            </div>

            <div class="col-span-1 sm:col-span-2 lg:col-span-1 flex items-end gap-2">
                <button type="submit" class="flex-1 py-2.5 bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors rounded-none cursor-pointer">
                    Apply Filter
                </button>
                @if($search || $employeeId || $status || $monthYear != date('Y-m'))
                    <a href="{{ route('manager.payroll.advances.index') }}" class="px-4 py-2.5 bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200 flex items-center justify-center border border-slate-200 rounded-none">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Advances Data Table -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden rounded-none">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[11px] font-extrabold uppercase tracking-wider">
                        <th class="py-4 px-4">Employee</th>
                        <th class="py-4 px-4">Advance Amount</th>
                        <th class="py-4 px-4">Disbursement Date</th>
                        <th class="py-4 px-4">Recovery Month</th>
                        <th class="py-4 px-4">Payment Mode</th>
                        <th class="py-4 px-4">Purpose / Reason</th>
                        <th class="py-4 px-4">Status</th>
                        <th class="py-4 px-4">Recorded By</th>
                        @if($isAdmin)
                            <th class="py-4 px-4 text-right">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @forelse($advances as $adv)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="py-4 px-4">
                            <p class="font-bold text-slate-900">{{ $adv->employee->name ?? 'Employee' }}</p>
                            <p class="text-[10px] text-slate-400 font-medium">{{ $adv->employee->phone_no1 ?? '' }}</p>
                        </td>
                        <td class="py-4 px-4 font-black text-amber-600 font-mono text-sm">
                            PKR {{ number_format($adv->amount, 2) }}
                        </td>
                        <td class="py-4 px-4 font-semibold text-slate-700">
                            {{ $adv->advance_date ? $adv->advance_date->format('M d, Y') : 'N/A' }}
                        </td>
                        <td class="py-4 px-4 font-bold text-indigo-700">
                            {{ date('F Y', strtotime($adv->month_year . '-01')) }}
                        </td>
                        <td class="py-4 px-4">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-black uppercase rounded-none border
                                {{ $adv->payment_mode === 'Cash' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-indigo-50 text-indigo-800 border-indigo-200' }}">
                                {{ $adv->payment_mode }}
                                @if($adv->bankAccount)
                                    <span class="font-normal text-slate-500">({{ $adv->bankAccount->bank_name }})</span>
                                @endif
                            </span>
                        </td>
                        <td class="py-4 px-4">
                            <p class="font-bold text-slate-800">{{ $adv->reason ?: 'Salary Advance' }}</p>
                            @if($adv->notes)
                                <p class="text-[10px] text-slate-400 font-normal italic mt-0.5">{{ $adv->notes }}</p>
                            @endif
                        </td>
                        <td class="py-4 px-4">
                            @if($adv->status === 'recovered')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-black uppercase bg-emerald-100 text-emerald-900 border border-emerald-300">
                                    ✓ Recovered in Payroll
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-black uppercase bg-amber-100 text-amber-900 border border-amber-300">
                                    ⏳ Pending Recovery
                                </span>
                            @endif
                        </td>
                        <td class="py-4 px-4 text-slate-500 font-medium">
                            <p class="font-semibold text-slate-800">{{ $adv->creator->name ?? 'Admin' }}</p>
                            <p class="text-[10px] text-slate-400">{{ $adv->created_at ? $adv->created_at->format('M d, H:i') : '' }}</p>
                        </td>
                        @if($isAdmin)
                        <td class="py-4 px-4 text-right">
                            <form method="POST" action="{{ route('manager.payroll.advances.destroy', $adv) }}" 
                                  onsubmit="return confirm('Are you sure you want to remove this advance salary of PKR {{ number_format($adv->amount, 2) }} for {{ addslashes($adv->employee->name ?? '') }}?');" 
                                  class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 transition-colors cursor-pointer" title="Delete Advance Record">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </form>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $isAdmin ? '9' : '8' }}" class="py-12 text-center text-slate-400">
                            <div class="space-y-1">
                                <p class="text-sm font-semibold">No advance salary records found for {{ date('F Y', strtotime($monthYear . '-01')) }}.</p>
                                <p class="text-xs text-slate-400">Click "+ Issue Advance Salary" to record a new staff disbursement.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-slate-100">
            {{ $advances->links() }}
        </div>
    </div>

    <!-- MODAL: ISSUE SALARY ADVANCE (ADMIN ONLY) -->
    @if($isAdmin)
    <div x-show="advanceModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="advanceModalOpen = false" class="bg-white max-w-lg w-full p-6 shadow-2xl space-y-5 border border-slate-200 rounded-none max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2 heading-font">
                    <span class="p-1.5 bg-amber-50 text-amber-600 rounded-none">💰</span>
                    <span>Issue Employee Salary Advance</span>
                </h3>
                <button @click="advanceModalOpen = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('manager.payroll.advances.store') }}" @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true" class="space-y-4 text-xs font-bold text-slate-700">
                @csrf
                <div>
                    <label class="block text-slate-600 font-bold uppercase text-[11px] mb-1">Select Employee *</label>
                    <select name="account_id" x-model="advEmpId" required 
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-900 focus:ring-2 focus:ring-amber-500 rounded-none">
                        <option value="">Select Employee</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}">👤 {{ $emp->name }} (Monthly Base Salary: PKR {{ number_format($emp->salary, 2) }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="block text-slate-600 font-bold uppercase text-[11px] mb-1">Advance Amount (PKR) *</label>
                        <input type="number" step="0.01" min="0.01" name="amount" x-model="advAmount" required placeholder="10000.00" 
                               class="w-full px-3.5 py-2.5 bg-white border border-slate-300 text-xs font-black text-slate-900 focus:ring-2 focus:ring-amber-500 rounded-none">
                    </div>

                    <div>
                        <label class="block text-slate-600 font-bold uppercase text-[11px] mb-1">Disbursement Date *</label>
                        <input type="date" name="advance_date" x-model="advDate" required 
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-900 focus:ring-2 focus:ring-amber-500 rounded-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="block text-slate-600 font-bold uppercase text-[11px] mb-1">Target Recovery Month *</label>
                        <input type="month" name="month_year" x-model="advMonthYear" required 
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-900 focus:ring-2 focus:ring-amber-500 rounded-none"
                               title="Month whose payroll will deduct this advance">
                    </div>

                    <div>
                        <label class="block text-slate-600 font-bold uppercase text-[11px] mb-1">Disbursed Via (Payment Mode) *</label>
                        <select name="payment_mode" x-model="advPaymentMode" required 
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-900 focus:ring-2 focus:ring-amber-500 rounded-none">
                            <option value="Cash">Cash In Hand</option>
                            <option value="Bank Transfer">Bank Transfer / Online</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <!-- Bank account selector if bank transfer -->
                <div x-show="advPaymentMode === 'Bank Transfer'">
                    <label class="block text-slate-600 font-bold uppercase text-[11px] mb-1">Bank Account</label>
                    <select name="bank_account_id" x-model="advBankAccountId" 
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-amber-500 rounded-none">
                        <option value="">Select Company Bank Account</option>
                        @foreach($bankAccounts as $ba)
                            <option value="{{ $ba->bankid }}">{{ $ba->bank_name }} - {{ $ba->account_title }} ({{ $ba->account_no }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- Purpose / Reason with Quick Select Pills -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-slate-600 font-bold uppercase text-[11px]">Reason / Purpose</label>
                        <span class="text-[10px] text-slate-400 font-semibold">Click quick reason below:</span>
                    </div>
                    <input type="text" name="reason" x-model="advReason" placeholder="e.g. Family Emergency, Home Rent..." 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 text-xs font-semibold focus:ring-2 focus:ring-amber-500 rounded-none">
                    
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        <button type="button" @click="setQuickReason('Family Emergency')" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-[10px] font-bold text-slate-700 rounded-none">
                            Family Emergency
                        </button>
                        <button type="button" @click="setQuickReason('Medical / Health Expense')" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-[10px] font-bold text-slate-700 rounded-none">
                            Medical / Health
                        </button>
                        <button type="button" @click="setQuickReason('Home Rent / Utility Bills')" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-[10px] font-bold text-slate-700 rounded-none">
                            Home Rent / Bills
                        </button>
                        <button type="button" @click="setQuickReason('Eid / Festival Expense')" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-[10px] font-bold text-slate-700 rounded-none">
                            Eid / Festival
                        </button>
                        <button type="button" @click="setQuickReason('Personal Urgent Need')" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-[10px] font-bold text-slate-700 rounded-none">
                            Personal Urgent Need
                        </button>
                    </div>
                </div>

                <!-- Sync with Ledger Checkbox -->
                <div class="p-3 bg-slate-50 border border-slate-200 flex items-center gap-2 rounded-none">
                    <input type="checkbox" name="record_ledger" value="1" x-model="advRecordLedger" id="advRecordLedger"
                           class="w-4 h-4 text-amber-600 border-slate-300 rounded-none focus:ring-amber-500">
                    <label for="advRecordLedger" class="text-xs font-bold text-slate-800 cursor-pointer">
                        Sync with Staff Ledger <span class="font-normal text-slate-500">(Records immediate debit payout entry in employee account)</span>
                    </label>
                </div>

                <div>
                    <label class="block text-slate-600 font-bold uppercase text-[11px] mb-1">Additional Notes (Optional)</label>
                    <textarea name="notes" rows="2" x-model="advNotes" placeholder="Approved by manager, repayment schedule notes..." 
                              class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 text-xs font-medium focus:ring-2 focus:ring-amber-500 rounded-none resize-none"></textarea>
                </div>

                <div class="pt-3 flex justify-end gap-2.5 border-t border-slate-100">
                    <button type="button" @click="advanceModalOpen = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs border border-slate-200 rounded-none cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" :disabled="isSubmitting" class="px-6 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs shadow-sm rounded-none cursor-pointer flex items-center gap-1.5 disabled:opacity-70 disabled:cursor-not-allowed">
                        <template x-if="isSubmitting">
                            <svg class="animate-spin w-3.5 h-3.5 text-white shrink-0" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                        </template>
                        <span x-text="isSubmitting ? 'Issuing Advance...' : 'Confirm & Issue Advance'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

</div>
@endsection
