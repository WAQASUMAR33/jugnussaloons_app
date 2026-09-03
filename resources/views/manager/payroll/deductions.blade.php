@extends('layouts.material')

@section('title', 'Staff Salary Deductions')

@section('content')
<div x-data="{ 
    deductionModalOpen: false,
    deductEmpId: '',
    deductAmount: '',
    deductReason: '',
    deductDate: '{{ date('Y-m-d') }}',
    deductNotes: '',
    isAdmin: {{ $isAdmin ? 'true' : 'false' }},

    setQuickReason(reason) {
        this.deductReason = reason;
    }
}" class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 border border-slate-200 shadow-sm rounded-none">
        <div class="flex items-center gap-3.5">
            <div class="p-3 bg-rose-50 text-rose-600 rounded-none">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4m16 0l-4-4m4 4l-4 4"></path></svg>
            </div>
            <div>
                <div class="flex items-center gap-2 text-xs font-bold text-slate-400 uppercase tracking-wider">
                    <span>Finance & Accounts</span>
                    <span>•</span>
                    <span class="text-rose-600">Staff Deductions</span>
                </div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight heading-font mt-0.5">Staff Salary Deductions</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">Record and monitor reason-based staff salary deductions (late arrival, damage, advance salary, penalties).</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="{{ route('manager.payroll.index', ['month_year' => $monthYear]) }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs border border-slate-200 rounded-none transition-colors">
                <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                <span>Go to Staff Payroll</span>
            </a>

            @if($isAdmin)
                <button type="button" @click="deductionModalOpen = true" 
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-sm transition-all rounded-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    <span>+ Record New Deduction</span>
                </button>
            @endif
        </div>
    </div>

    <!-- Monthly Summary KPI Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-5 border border-slate-200 shadow-sm rounded-none">
            <p class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Total Deductions ({{ date('F Y', strtotime($monthYear . '-01')) }})</p>
            <h3 class="text-2xl font-black text-rose-600 mt-1">PKR {{ number_format($totalDeductionsAmount, 2) }}</h3>
            <p class="text-[11px] text-slate-500 font-semibold mt-0.5">Applied to monthly staff payrolls</p>
        </div>

        <div class="bg-white p-5 border border-slate-200 shadow-sm rounded-none">
            <p class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Affected Staff Members</p>
            <h3 class="text-2xl font-black text-slate-900 mt-1">{{ $affectedStaffCount }} Staff</h3>
            <p class="text-[11px] text-slate-500 font-semibold mt-0.5">Employees with recorded deductions</p>
        </div>

        <div class="bg-white p-5 border border-slate-200 shadow-sm rounded-none">
            <p class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Total Logged Entries</p>
            <h3 class="text-2xl font-black text-slate-900 mt-1">{{ $totalEntriesCount }} Entries</h3>
            <p class="text-[11px] text-slate-500 font-semibold mt-0.5">Logged incidents for this month</p>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white p-4 border border-slate-200 shadow-sm rounded-none">
        <form method="GET" action="{{ route('manager.payroll.deductions.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Month Period</label>
                <input type="month" name="month_year" value="{{ $monthYear }}" onchange="this.form.submit()" 
                       class="w-full px-4 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-rose-500 rounded-none">
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Filter by Staff Member</label>
                <select name="employee_id" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-rose-500 rounded-none">
                    <option value="">All Staff Members</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" {{ $employeeId == $emp->id ? 'selected' : '' }}>👤 {{ $emp->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Search Reason / Notes</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search reason, employee, notes..." 
                       class="w-full px-4 py-2 bg-slate-50 border border-slate-200 text-xs font-medium focus:ring-2 focus:ring-rose-500 rounded-none">
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2.5 bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors rounded-none">
                    Filter Logs
                </button>
                @if($search || $employeeId || $monthYear != date('Y-m'))
                    <a href="{{ route('manager.payroll.deductions.index') }}" class="px-4 py-2.5 bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200 flex items-center justify-center border border-slate-200 rounded-none">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Deductions Table -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden rounded-none">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[11px] font-extrabold uppercase tracking-wider">
                        <th class="py-4 px-4">Date</th>
                        <th class="py-4 px-4">Employee / Staff</th>
                        <th class="py-4 px-4">Deduction Reason</th>
                        <th class="py-4 px-4">Amount</th>
                        <th class="py-4 px-4">Remarks / Details</th>
                        <th class="py-4 px-4">Recorded By</th>
                        <th class="py-4 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @forelse($deductions as $d)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="py-4 px-4 font-bold text-slate-700">
                            {{ $d->deduction_date ? $d->deduction_date->format('M d, Y') : '—' }}
                        </td>
                        <td class="py-4 px-4">
                            <p class="font-bold text-slate-900">{{ $d->employee->name ?? 'Staff' }}</p>
                            <span class="text-[10px] font-bold text-slate-400">{{ $d->employee->phone_no1 ?? '' }}</span>
                        </td>
                        <td class="py-4 px-4">
                            <span class="px-2.5 py-1 bg-rose-50 text-rose-800 font-bold text-[11px] border border-rose-200 inline-block">
                                {{ $d->reason }}
                            </span>
                        </td>
                        <td class="py-4 px-4 font-black text-rose-600 text-sm">
                            PKR {{ number_format($d->amount, 2) }}
                        </td>
                        <td class="py-4 px-4 font-medium text-slate-600">
                            {{ $d->notes ?: '—' }}
                        </td>
                        <td class="py-4 px-4 font-semibold text-slate-500">
                            {{ $d->creator->name ?? 'System' }}
                        </td>
                        <td class="py-4 px-4 text-right">
                            @if($isAdmin)
                                <form method="POST" action="{{ route('manager.payroll.deductions.destroy', $d) }}" onsubmit="return confirm('Are you sure you want to delete this deduction of PKR {{ $d->amount }}?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-xs border border-rose-200 rounded-none transition-colors" title="Delete Deduction">
                                        Delete
                                    </button>
                                </form>
                            @else
                                <span class="text-[10px] text-slate-400 font-bold italic">Admin Only</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-12 text-center text-slate-400">
                            <p class="text-sm font-semibold">No salary deduction records found for {{ date('F Y', strtotime($monthYear . '-01')) }}.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-slate-100">
            {{ $deductions->links() }}
        </div>
    </div>

    <!-- MODAL: RECORD SALARY DEDUCTION -->
    <div x-show="deductionModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="deductionModalOpen = false" class="bg-white max-w-lg w-full p-6 shadow-2xl space-y-5 border border-slate-200 rounded-none">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2 heading-font">
                    <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4m16 0l-4-4m4 4l-4 4"></path></svg>
                    <span>Record Staff Salary Deduction</span>
                </h3>
                <button @click="deductionModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('manager.payroll.deductions.store') }}" class="space-y-4 text-xs font-bold text-slate-700">
                @csrf
                
                <div class="space-y-1">
                    <label class="block text-slate-600 font-bold uppercase text-[11px]">Select Staff Member *</label>
                    <select name="account_id" x-model="deductEmpId" required 
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-900 focus:ring-2 focus:ring-rose-500 rounded-none">
                        <option value="">-- Choose Employee --</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}">👤 {{ $emp->name }} ({{ ucfirst($emp->emp_type ?? 'Staff') }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div class="space-y-1">
                        <label class="block text-slate-600 font-bold uppercase text-[11px]">Deduction Amount (PKR) *</label>
                        <input type="number" step="0.01" min="1" name="amount" x-model="deductAmount" required placeholder="e.g. 500" 
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 text-xs font-black text-rose-600 focus:ring-2 focus:ring-rose-500 rounded-none">
                    </div>

                    <div class="space-y-1">
                        <label class="block text-slate-600 font-bold uppercase text-[11px]">Deduction Date *</label>
                        <input type="date" name="deduction_date" x-model="deductDate" required 
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-rose-500 rounded-none">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-slate-600 font-bold uppercase text-[11px]">Deduction Reason *</label>
                    <div class="flex flex-wrap gap-1.5 pb-1">
                        <button type="button" @click="setQuickReason('Late Arrival / Shift Delay')" class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] font-bold border border-slate-200">
                            ⏱️ Late Arrival
                        </button>
                        <button type="button" @click="setQuickReason('Equipment / Tool Damage')" class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] font-bold border border-slate-200">
                            ✂️ Tool Damage
                        </button>
                        <button type="button" @click="setQuickReason('Advance Salary Adjustment')" class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] font-bold border border-slate-200">
                            💵 Advance Salary
                        </button>
                        <button type="button" @click="setQuickReason('Uniform / Salon Kit Expense')" class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] font-bold border border-slate-200">
                            👔 Uniform / Kit
                        </button>
                        <button type="button" @click="setQuickReason('Disciplinary Penalty')" class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] font-bold border border-slate-200">
                            ⚠️ Disciplinary
                        </button>
                    </div>
                    <input type="text" name="reason" x-model="deductReason" required placeholder="Type reason or click a preset tag above..." 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-900 focus:ring-2 focus:ring-rose-500 rounded-none">
                </div>

                <div class="space-y-1">
                    <label class="block text-slate-600 font-bold uppercase text-[11px]">Additional Remarks / Notes</label>
                    <textarea name="notes" rows="2" x-model="deductNotes" placeholder="Optional details..." 
                              class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 text-xs font-medium focus:ring-2 focus:ring-rose-500 rounded-none resize-none"></textarea>
                </div>

                <div class="pt-3 flex justify-end gap-2.5 border-t border-slate-100">
                    <button type="button" @click="deductionModalOpen = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs border border-slate-200 rounded-none">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white font-black text-xs shadow-sm rounded-none">
                        Save Deduction Entry
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
