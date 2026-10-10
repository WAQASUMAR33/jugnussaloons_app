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
    currentMonthYear: '{{ $monthYear }}',
    selectedEmployeeId: '{{ $employeeId }}',
    allDeductions: {{ json_encode($allDeductions ?? []) }},
    brandSettings: {
        name: '{{ addslashes($appSetting->brand_name ?? "Jugnu Saloon") }}',
        slogan: '{{ addslashes($appSetting->brand_slogan ?? "WELCOME TO JUGNU SALON PHALIA") }}',
        address: '{{ addslashes($appSetting->brand_address ?? "Phalia, Mandi Bahauddin") }}',
        phone1: '{{ addslashes($appSetting->brand_phone1 ?? "03194415757") }}',
        phone2: '{{ addslashes($appSetting->brand_phone2 ?? "") }}'
    },

    setQuickReason(reason) {
        this.deductReason = reason;
    },

    printDocumentViaIframe(htmlContent) {
        if (!htmlContent) return;
        let iframe = document.getElementById('deductionPrintIframe');
        if (!iframe) {
            iframe = document.createElement('iframe');
            iframe.id = 'deductionPrintIframe';
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

    printDeductionsReport() {
        let empName = '';
        if (this.selectedEmployeeId) {
            const sel = document.querySelector('select[name=\'employee_id\']');
            if (sel && sel.selectedIndex > 0) {
                empName = sel.options[sel.selectedIndex].text.replace('👤', '').trim();
            }
        }
        const docHtml = (typeof window.buildSalaryDeductionsReportDocument === 'function')
            ? window.buildSalaryDeductionsReportDocument(this.allDeductions, this.currentMonthYear, this.brandSettings, empName)
            : '';
        this.printDocumentViaIframe(docHtml);
    },

    printEmployeeDeductionBreakdown(empId, empName, currentItem = null) {
        let empDeductions = this.allDeductions.filter(item => {
            const accId = item.account_id || (item.employee ? item.employee.id : null);
            return String(accId) === String(empId);
        });
        if (empDeductions.length === 0 && currentItem) {
            empDeductions = [currentItem];
        }
        const docHtml = (typeof window.buildEmployeeDeductionBreakdownDocument === 'function')
            ? window.buildEmployeeDeductionBreakdownDocument(empDeductions, empName, this.currentMonthYear, this.brandSettings)
            : '';
        this.printDocumentViaIframe(docHtml);
    },

    printSingleDeductionSlip(d) {
        // Also include all deductions for this employee to show full breakdown on slip
        const empDeductions = this.allDeductions.filter(item => {
            const accId = item.account_id || (item.employee ? item.employee.id : null);
            return String(accId) === String(d.account_id || d.id);
        });
        const docHtml = (typeof window.buildSingleDeductionSlipDocument === 'function')
            ? window.buildSingleDeductionSlipDocument(d, this.brandSettings, empDeductions.length > 0 ? empDeductions : [d])
            : '';
        this.printDocumentViaIframe(docHtml);
    }
}" class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-4 sm:p-6 border border-slate-200 shadow-sm rounded-none">
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
            <button type="button" @click="printDeductionsReport()" 
                    style="background-color: #0f172a !important; color: #fbbf24 !important; border: 1px solid #1e293b !important;"
                    class="inline-flex items-center gap-2 px-4 py-2.5 font-extrabold text-xs shadow-sm hover:opacity-90 transition-all rounded-none cursor-pointer">
                <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                <span>🖨️ Print Deduction Report</span>
            </button>

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
        <form method="GET" action="{{ route('manager.payroll.deductions.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
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
                <button type="button" @click="printDeductionsReport()" 
                        style="background-color: #0f172a !important; color: #fbbf24 !important;"
                        class="px-3 py-2.5 font-bold text-xs hover:opacity-90 flex items-center justify-center transition-colors rounded-none cursor-pointer"
                        title="Print Filtered Deductions Report">
                    🖨️ Print
                </button>
                @if($search || $employeeId || $monthYear != date('Y-m'))
                    <a href="{{ route('manager.payroll.deductions.index') }}" class="px-3 py-2.5 bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200 flex items-center justify-center border border-slate-200 rounded-none">
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
                            <div class="flex items-center justify-end gap-1.5">
                                <!-- Print Employee Deduction Breakdown -->
                                <button type="button" 
                                        @click="printEmployeeDeductionBreakdown({{ $d->account_id }}, '{{ addslashes($d->employee->name ?? 'Staff') }}', {
                                            id: {{ $d->id }},
                                            account_id: {{ $d->account_id }},
                                            employee_name: '{{ addslashes($d->employee->name ?? 'Staff') }}',
                                            employee_phone: '{{ addslashes($d->employee->phone_no1 ?? '') }}',
                                            amount: {{ (float)$d->amount }},
                                            deduction_date: '{{ $d->deduction_date ? $d->deduction_date->format('Y-m-d') : '' }}',
                                            reason: '{{ addslashes($d->reason) }}',
                                            notes: '{{ addslashes($d->notes ?? '') }}',
                                            creator_name: '{{ addslashes($d->creator->name ?? 'Management') }}'
                                        })"
                                        style="background-color: #0f172a !important; color: #fbbf24 !important; border: 1px solid #1e293b !important;"
                                        class="px-2.5 py-1.5 font-black text-xs hover:opacity-90 transition-colors rounded-none cursor-pointer shadow-sm" 
                                        title="Print Staff Deduction Breakdown Statement">
                                    🖨️ Breakdown
                                </button>
                                <button type="button" 
                                        @click="printSingleDeductionSlip({
                                            id: {{ $d->id }},
                                            account_id: {{ $d->account_id }},
                                            employee_name: '{{ addslashes($d->employee->name ?? 'Staff') }}',
                                            employee_phone: '{{ addslashes($d->employee->phone_no1 ?? '') }}',
                                            amount: {{ (float)$d->amount }},
                                            deduction_date: '{{ $d->deduction_date ? $d->deduction_date->format('Y-m-d') : '' }}',
                                            reason: '{{ addslashes($d->reason) }}',
                                            notes: '{{ addslashes($d->notes ?? '') }}',
                                            creator_name: '{{ addslashes($d->creator->name ?? 'Management') }}'
                                        })"
                                        style="background-color: #f8fafc !important; color: #0f172a !important; border: 1px solid #cbd5e1 !important;"
                                        class="px-2 py-1.5 font-bold text-xs hover:bg-slate-100 transition-colors rounded-none cursor-pointer" 
                                        title="Print Deduction Voucher">
                                    Slip
                                </button>
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
                            </div>
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

<script>
window.formatDeductionMoney = function(num) {
    return Number(num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

window.formatDeductionMonth = function(mStr) {
    if (!mStr) return 'All Periods';
    try {
        const parts = mStr.split('-');
        if (parts.length >= 2) {
            const date = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, 1);
            return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        }
    } catch(e) {}
    return mStr;
};

window.buildSalaryDeductionsReportDocument = function(deductions, monthYear, brand, employeeName) {
    const list = Array.isArray(deductions) ? deductions : [];
    const totalAmount = list.reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0);
    const periodLabel = window.formatDeductionMonth(monthYear);
    const now = new Date();
    const printedAt = now.toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

    // Grouping by reason for category summary
    const reasonTotals = {};
    list.forEach(item => {
        const r = item.reason || 'General / Other';
        reasonTotals[r] = (reasonTotals[r] || 0) + (parseFloat(item.amount) || 0);
    });

    let rowsHtml = '';
    if (list.length === 0) {
        rowsHtml = `<tr><td colspan="7" style="padding: 24px; text-align: center; color: #94a3b8; font-style: italic;">No deduction records found for this period.</td></tr>`;
    } else {
        list.forEach((item, idx) => {
            const dateStr = item.deduction_date ? String(item.deduction_date).substring(0, 10) : '—';
            const emp = item.employee ? item.employee.name : (item.employee_name || 'Staff');
            const phone = (item.employee && item.employee.phone_no1) ? item.employee.phone_no1 : (item.employee_phone || '');
            const reason = item.reason || '—';
            const amt = window.formatDeductionMoney(item.amount);
            const notes = item.notes ? item.notes : '—';
            const creator = item.creator ? item.creator.name : (item.creator_name || 'Admin');
            const bgClass = idx % 2 === 0 ? '#ffffff' : '#f8fafc';

            rowsHtml += `
                <tr style="background-color: ${bgClass}; border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 9px 10px; font-weight: 700; color: #64748b; font-size: 11px; text-align: center;">${idx + 1}</td>
                    <td style="padding: 9px 10px; font-weight: 700; color: #1e293b; font-size: 11px; white-space: nowrap;">${dateStr}</td>
                    <td style="padding: 9px 10px; font-size: 11px;">
                        <strong style="color: #0f172a; display: block;">${emp}</strong>
                        ${phone ? `<span style="font-size: 10px; color: #64748b;">${phone}</span>` : ''}
                    </td>
                    <td style="padding: 9px 10px; font-size: 11px;">
                        <span style="display: inline-block; background-color: #ffe4e6; color: #9f1239; font-weight: 700; padding: 2px 8px; border-radius: 2px; font-size: 10.5px; border: 1px solid #fecdd3;">
                            ${reason}
                        </span>
                    </td>
                    <td style="padding: 9px 10px; font-weight: 800; color: #be123c; font-size: 11.5px; text-align: right; white-space: nowrap;">
                        PKR ${amt}
                    </td>
                    <td style="padding: 9px 10px; font-size: 10.5px; color: #475569; max-width: 200px;">
                        ${notes}
                    </td>
                    <td style="padding: 9px 10px; font-size: 10.5px; color: #64748b;">
                        ${creator}
                    </td>
                </tr>
            `;
        });
    }

    let reasonSummaryHtml = '';
    const reasons = Object.keys(reasonTotals);
    if (reasons.length > 0) {
        reasonSummaryHtml = `
            <div style="margin-top: 20px; padding: 12px; background: #fff1f2; border: 1px solid #fecdd3; border-radius: 4px;">
                <div style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #9f1239; margin-bottom: 8px;">
                    Deduction Reason Breakdown
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 12px;">
                    ${reasons.map(r => `
                        <div style="background: #ffffff; border: 1px solid #fbcfe8; padding: 6px 10px; border-radius: 4px; font-size: 11px;">
                            <span style="color: #475569; font-weight: 600;">${r}:</span>
                            <span style="color: #be123c; font-weight: 800; margin-left: 4px;">PKR ${window.formatDeductionMoney(reasonTotals[r])}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }

    return `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Salary Deductions Report - ${periodLabel}</title>
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
            margin-bottom: 16px;
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
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .report-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 9px 10px;
            border: 1px solid #0f172a;
        }
        .report-table td {
            border: 1px solid #e2e8f0;
            padding: 8px 10px;
        }
        .signatures {
            margin-top: 40px;
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
                    OFFICIAL REPORT
                </div>
                <div style="font-size: 14px; font-weight: 800; color: #be123c; margin-top: 6px;">
                    SALARY DEDUCTIONS REPORT
                </div>
                <div style="font-size: 10px; color: #64748b; margin-top: 2px;">
                    Generated: ${printedAt}
                </div>
            </td>
        </tr>
    </table>

    <div class="meta-box">
        <div>
            <span style="font-size: 10px; font-weight: 800; text-transform: uppercase; color: #64748b;">Report Period:</span>
            <strong style="font-size: 13px; color: #0f172a; margin-left: 6px;">${periodLabel}</strong>
        </div>
        <div>
            <span style="font-size: 10px; font-weight: 800; text-transform: uppercase; color: #64748b;">Staff Filter:</span>
            <strong style="font-size: 12px; color: #0f172a; margin-left: 6px;">${employeeName ? employeeName : 'All Employees'}</strong>
        </div>
        <div>
            <span style="font-size: 10px; font-weight: 800; text-transform: uppercase; color: #64748b;">Deductions Found:</span>
            <strong style="font-size: 13px; color: #be123c; margin-left: 6px;">${list.length} Records</strong>
        </div>
    </div>

    <div class="kpi-cards">
        <div class="kpi-card highlight">
            <div class="kpi-title" style="color: #9f1239;">Total Salary Deductions</div>
            <div class="kpi-value" style="color: #be123c;">PKR ${window.formatDeductionMoney(totalAmount)}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-title">Total Incident Logs</div>
            <div class="kpi-value">${list.length} Entries</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-title">Report Scope</div>
            <div class="kpi-value" style="font-size: 14px; line-height: 24px;">${employeeName ? employeeName : 'Full Staff Payroll'}</div>
        </div>
    </div>

    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 32px; text-align: center;">#</th>
                <th style="width: 80px;">Date</th>
                <th>Employee / Staff</th>
                <th style="width: 140px;">Deduction Reason</th>
                <th style="width: 110px; text-align: right;">Amount (PKR)</th>
                <th>Remarks / Notes</th>
                <th style="width: 85px;">Recorded By</th>
            </tr>
        </thead>
        <tbody>
            ${rowsHtml}
        </tbody>
        <tfoot>
            <tr style="background-color: #0f172a; color: #ffffff; font-weight: 900;">
                <td colspan="4" style="padding: 10px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; border-color: #0f172a;">
                    GRAND TOTAL DEDUCTIONS
                </td>
                <td style="padding: 10px; font-size: 13px; text-align: right; color: #fbbf24; border-color: #0f172a; white-space: nowrap;">
                    PKR ${window.formatDeductionMoney(totalAmount)}
                </td>
                <td colspan="2" style="padding: 10px; font-size: 10.5px; color: #cbd5e1; border-color: #0f172a;">
                    ${list.length} Total Incidents Logged
                </td>
            </tr>
        </tfoot>
    </table>

    ${reasonSummaryHtml}

    <div class="signatures">
        <div class="sign-block">
            Prepared By (HR / Accounts)
        </div>
        <div class="sign-block">
            Auditor / Finance Officer
        </div>
        <div class="sign-block">
            Salon Manager / Authorized Seal
        </div>
    </div>

    <div class="footer-note">
        <div>System-generated official deductions report • ${brand.name || 'Jugnu Saloon'} Management System</div>
        <div>Page 1 of 1</div>
    </div>
</body>
</html>`;
};

window.buildEmployeeDeductionBreakdownDocument = function(deductions, empName, monthYear, brand, empPhone) {
    const list = Array.isArray(deductions) ? deductions : [];
    const totalAmount = list.reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0);
    
    let periodLabel = monthYear || 'Current Month';
    try {
        if (monthYear && monthYear.indexOf('-') !== -1) {
            const parts = monthYear.split('-');
            const d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, 1);
            periodLabel = d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        }
    } catch(e) {}

    const now = new Date();
    const printedAt = now.toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

    let phoneStr = empPhone || '';
    if (!phoneStr && list.length > 0) {
        for (const item of list) {
            if (item.employee && item.employee.phone_no1) {
                phoneStr = item.employee.phone_no1;
                break;
            } else if (item.employee_phone) {
                phoneStr = item.employee_phone;
                break;
            }
        }
    }

    const reasonSummary = {};
    list.forEach(item => {
        const r = item.reason || 'General Deduction';
        if (!reasonSummary[r]) {
            reasonSummary[r] = { count: 0, total: 0 };
        }
        reasonSummary[r].count += 1;
        reasonSummary[r].total += (parseFloat(item.amount) || 0);
    });

    let rowsHtml = '';
    if (list.length === 0) {
        rowsHtml = `<tr><td colspan="7" style="padding: 24px; text-align: center; color: #94a3b8; font-style: italic;">No deduction breakdown items recorded for this staff member.</td></tr>`;
    } else {
        list.forEach((item, idx) => {
            const ref = item.id ? `#DED-${item.id}` : `—`;
            const dateStr = item.deduction_date ? String(item.deduction_date).substring(0, 10) : '—';
            const reason = item.reason || 'General';
            const amt = Number(item.amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const notes = item.notes ? item.notes : '—';
            const creator = item.creator ? item.creator.name : (item.creator_name || 'Management');
            const bg = idx % 2 === 0 ? '#ffffff' : '#f8fafc';

            rowsHtml += `
                <tr style="background-color: ${bg}; border-bottom: 1px solid #e2e8f0; font-size: 11.5px;">
                    <td style="padding: 9px 10px; font-weight: 700; color: #64748b; text-align: center; width: 35px;">${idx + 1}</td>
                    <td style="padding: 9px 10px; font-weight: 800; color: #475569; width: 85px; text-align: center;">${ref}</td>
                    <td style="padding: 9px 10px; font-weight: 700; color: #0f172a; width: 95px; white-space: nowrap;">${dateStr}</td>
                    <td style="padding: 9px 10px;">
                        <span style="display: inline-block; background-color: #fff1f2; color: #be123c; font-weight: 800; padding: 3px 8px; border-radius: 3px; font-size: 11px; border: 1px solid #fecdd3;">
                            ${reason}
                        </span>
                    </td>
                    <td style="padding: 9px 10px; color: #334155; font-size: 11px;">
                        ${notes}
                    </td>
                    <td style="padding: 9px 10px; color: #64748b; width: 110px; font-size: 10.5px;">
                        ${creator}
                    </td>
                    <td style="padding: 9px 10px; font-weight: 900; color: #be123c; text-align: right; width: 125px; white-space: nowrap;">
                        PKR ${amt}
                    </td>
                </tr>
            `;
        });
    }

    let reasonCardsHtml = '';
    const reasons = Object.keys(reasonSummary);
    if (reasons.length > 0) {
        reasonCardsHtml = `
            <div style="margin-top: 18px; padding: 12px 14px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; page-break-inside: avoid;">
                <div style="font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #475569; margin-bottom: 8px;">
                    Deduction Reason Breakdown
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    ${reasons.map(r => `
                        <div style="background: #ffffff; border: 1px solid #e2e8f0; padding: 6px 12px; border-radius: 4px; font-size: 11px;">
                            <div style="font-weight: 700; color: #0f172a;">${r}</div>
                            <div style="margin-top: 2px; font-size: 11px; color: #be123c; font-weight: 900;">
                                PKR ${Number(reasonSummary[r].total).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                <span style="color: #64748b; font-weight: normal; font-size: 10px;">(${reasonSummary[r].count} ${reasonSummary[r].count === 1 ? 'incident' : 'incidents'})</span>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }

    return `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Salary Deduction Breakdown - ${empName || 'Staff'} - ${periodLabel}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 15mm 12mm 15mm;
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
        .employee-card {
            background: #f8fafc;
            border: 1.5px solid #cbd5e1;
            border-radius: 4px;
            padding: 12px 16px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .report-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 9px 10px;
            border: 1px solid #0f172a;
            text-align: left;
        }
        .report-table td {
            border: 1px solid #e2e8f0;
            padding: 8px 10px;
        }
        .signatures {
            margin-top: 45px;
            display: flex;
            justify-content: space-between;
            page-break-inside: avoid;
        }
        .sign-block {
            width: 29%;
            border-top: 1px dashed #475569;
            padding-top: 6px;
            text-align: center;
            font-size: 11px;
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
            <td style="width: 62%;">
                <div style="font-size: 22px; font-weight: 900; letter-spacing: -0.5px; color: #0f172a; text-transform: uppercase;">
                    ${brand.name || "JUGNU'S SALOON PHALIA"}
                </div>
                <div style="font-size: 11px; font-weight: 700; color: #be123c; margin-top: 2px;">
                    ${brand.slogan || "WELCOME TO JUGNU SALON PHALIA"}
                </div>
                <div style="font-size: 10.5px; color: #475569; margin-top: 4px;">
                    ${brand.address ? `${brand.address}` : 'Phalia, Mandi Bahauddin'}
                    ${brand.phone1 ? ` | <strong>Tel:</strong> ${brand.phone1}` : ''}
                    ${brand.phone2 ? ` / ${brand.phone2}` : ''}
                </div>
            </td>
            <td style="width: 38%; text-align: right;">
                <div style="display: inline-block; background: #0f172a; color: #fbbf24; padding: 6px 14px; font-weight: 900; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                    OFFICIAL DEDUCTION STATEMENT
                </div>
                <div style="font-size: 14px; font-weight: 800; color: #be123c; margin-top: 6px;">
                    SALARY DEDUCTIONS BREAKDOWN
                </div>
                <div style="font-size: 10px; color: #64748b; margin-top: 2px;">
                    Printed: ${printedAt}
                </div>
            </td>
        </tr>
    </table>

    <div class="employee-card">
        <div>
            <div style="font-size: 10px; font-weight: 800; text-transform: uppercase; color: #64748b;">Staff Member</div>
            <div style="font-size: 16px; font-weight: 900; color: #0f172a; margin-top: 2px;">${empName || 'Staff Member'}</div>
            ${phoneStr ? `<div style="font-size: 11px; color: #475569; font-weight: 600; margin-top: 1px;">Phone: ${phoneStr}</div>` : ''}
        </div>
        <div>
            <div style="font-size: 10px; font-weight: 800; text-transform: uppercase; color: #64748b;">Payroll Period</div>
            <div style="font-size: 14px; font-weight: 800; color: #0f172a; margin-top: 2px;">${periodLabel}</div>
            <div style="font-size: 11px; color: #64748b; margin-top: 1px;">${list.length} ${list.length === 1 ? 'Incident' : 'Incidents'} Logged</div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 10px; font-weight: 800; text-transform: uppercase; color: #9f1239;">Total Deducted Amount</div>
            <div style="font-size: 20px; font-weight: 900; color: #be123c; margin-top: 2px;">
                PKR ${Number(totalAmount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </div>
            <div style="font-size: 9.5px; color: #9f1239; font-weight: 700;">Subtracted from monthly payout</div>
        </div>
    </div>

    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 35px; text-align: center;">#</th>
                <th style="width: 85px; text-align: center;">Voucher</th>
                <th style="width: 95px;">Incident Date</th>
                <th style="width: 170px;">Deduction Reason</th>
                <th>Remarks / Incident Details</th>
                <th style="width: 110px;">Recorded By</th>
                <th style="width: 125px; text-align: right;">Amount (PKR)</th>
            </tr>
        </thead>
        <tbody>
            ${rowsHtml}
        </tbody>
        <tfoot>
            <tr style="background-color: #0f172a; color: #ffffff; font-weight: 900;">
                <td colspan="4" style="padding: 10px; font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.5px; border-color: #0f172a;">
                    TOTAL SALARY DEDUCTIONS BREAKDOWN
                </td>
                <td colspan="2" style="padding: 10px; font-size: 10.5px; color: #cbd5e1; border-color: #0f172a;">
                    ${list.length} Itemized Incident(s) Applied
                </td>
                <td style="padding: 10px; font-size: 13px; text-align: right; color: #fbbf24; border-color: #0f172a; white-space: nowrap;">
                    PKR ${Number(totalAmount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                </td>
            </tr>
        </tfoot>
    </table>

    ${reasonCardsHtml}

    <div class="signatures">
        <div class="sign-block">
            Staff Signature / Acknowledgment
        </div>
        <div class="sign-block">
            Accounts / HR Officer
        </div>
        <div class="sign-block">
            Salon Manager / Authorized Seal
        </div>
    </div>

    <div class="footer-note">
        <div>Official staff salary deduction breakdown statement • ${brand.name || "Jugnu's Saloon Phalia"} Management System</div>
        <div>Page 1 of 1</div>
    </div>
</body>
</html>`;
};

window.buildSingleDeductionSlipDocument = function(d, brand, empDeductions = null) {
    const list = Array.isArray(empDeductions) && empDeductions.length > 0 ? empDeductions : [d];
    const totalAmount = list.reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0);
    const amt = window.formatDeductionMoney(d.amount);
    const now = new Date();
    const printedAt = now.toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

    let breakdownRowsHtml = '';
    list.forEach((item, idx) => {
        const ref = item.id ? `#DED-${item.id}` : `—`;
        const dt = item.deduction_date ? String(item.deduction_date).substring(0, 10) : '—';
        const rsn = item.reason || 'Deduction';
        const nts = item.notes || '—';
        const itemAmt = window.formatDeductionMoney(item.amount);

        breakdownRowsHtml += `
            <tr style="border-bottom: 1px solid #e2e8f0; font-size: 11px;">
                <td style="padding: 6px 8px; text-align: center; color: #64748b; font-weight: 700;">${idx + 1}</td>
                <td style="padding: 6px 8px; font-weight: 800; color: #475569;">${ref}</td>
                <td style="padding: 6px 8px; color: #0f172a; white-space: nowrap;">${dt}</td>
                <td style="padding: 6px 8px; font-weight: 700; color: #be123c;">${rsn}</td>
                <td style="padding: 6px 8px; color: #475569; font-size: 10.5px;">${nts}</td>
                <td style="padding: 6px 8px; text-align: right; font-weight: 900; color: #be123c; white-space: nowrap;">PKR ${itemAmt}</td>
            </tr>
        `;
    });

    return `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Deduction Voucher - #${d.id || ''}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 15mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #0f172a;
            margin: 0;
            padding: 0;
            font-size: 12px;
            line-height: 1.4;
        }
        .center { text-align: center; }
        .double-divider {
            border-top: 2px solid #0f172a;
            margin: 10px 0;
        }
        .box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 10px 14px;
            border-radius: 4px;
            margin: 10px 0;
            display: flex;
            justify-content: space-between;
        }
        .table-breakdown {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
        }
        .table-breakdown th {
            background: #0f172a;
            color: #ffffff;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 7px 8px;
            border: 1px solid #0f172a;
            text-align: left;
        }
        .table-breakdown td {
            border: 1px solid #e2e8f0;
            padding: 7px 8px;
        }
        .amount-banner {
            background: #fff1f2;
            border: 1.5px solid #fecdd3;
            border-radius: 4px;
            padding: 10px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 12px 0;
        }
        .signatures {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            page-break-inside: avoid;
        }
        .sign-block {
            width: 35%;
            border-top: 1px dashed #64748b;
            padding-top: 6px;
            text-align: center;
            font-size: 10.5px;
            font-weight: 700;
            color: #334155;
        }
    </style>
</head>
<body>
    <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0f172a; padding-bottom: 10px; margin-bottom: 12px;">
        <div>
            <div style="font-size: 20px; font-weight: 900; text-transform: uppercase; letter-spacing: -0.3px;">
                ${brand.name || "JUGNU'S SALOON PHALIA"}
            </div>
            <div style="font-size: 10.5px; font-weight: 700; color: #be123c;">
                ${brand.slogan || "WELCOME TO JUGNU SALON PHALIA"}
            </div>
            <div style="font-size: 10px; color: #475569; margin-top: 2px;">
                ${brand.address || 'Phalia, Mandi Bahauddin'} ${brand.phone1 ? ` | Tel: ${brand.phone1}` : ''}
            </div>
        </div>
        <div style="text-align: right;">
            <div style="display: inline-block; background: #0f172a; color: #fbbf24; font-size: 11px; font-weight: 900; padding: 4px 10px; text-transform: uppercase; letter-spacing: 0.5px;">
                DEDUCTION VOUCHER & BREAKDOWN
            </div>
            <div style="font-size: 10px; color: #64748b; margin-top: 4px;">
                Ref: <strong>#DED-${d.id || 'N/A'}</strong> • Printed: ${printedAt}
            </div>
        </div>
    </div>

    <div class="box">
        <div>
            <div style="font-size: 9.5px; font-weight: 700; text-transform: uppercase; color: #64748b;">Staff Member</div>
            <div style="font-size: 15px; font-weight: 900; color: #0f172a; margin-top: 2px;">${d.employee_name || 'Staff Member'}</div>
            ${d.employee_phone ? `<div style="font-size: 10.5px; color: #475569; margin-top: 2px;">Phone: ${d.employee_phone}</div>` : ''}
        </div>
        <div>
            <div style="font-size: 9.5px; font-weight: 700; text-transform: uppercase; color: #64748b;">Voucher Date</div>
            <div style="font-size: 13px; font-weight: 800; color: #0f172a; margin-top: 2px;">${d.deduction_date || '—'}</div>
            <div style="font-size: 10px; color: #64748b; margin-top: 2px;">Recorded By: ${d.creator_name || 'Management'}</div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 9.5px; font-weight: 700; text-transform: uppercase; color: #9f1239;">Voucher Amount</div>
            <div style="font-size: 18px; font-weight: 900; color: #be123c; margin-top: 2px;">PKR ${amt}</div>
        </div>
    </div>

    <div style="margin: 12px 0 4px 0;">
        <div style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #0f172a;">
            Itemized Deductions Breakdown For ${d.employee_name || 'Staff'}
        </div>
    </div>

    <table class="table-breakdown">
        <thead>
            <tr>
                <th style="width: 30px; text-align: center;">#</th>
                <th style="width: 75px;">Ref</th>
                <th style="width: 85px;">Date</th>
                <th style="width: 150px;">Deduction Reason</th>
                <th>Remarks / Notes</th>
                <th style="width: 110px; text-align: right;">Amount (PKR)</th>
            </tr>
        </thead>
        <tbody>
            ${breakdownRowsHtml}
        </tbody>
        <tfoot>
            <tr style="background: #0f172a; color: #ffffff; font-weight: 900;">
                <td colspan="4" style="padding: 8px; font-size: 11px; text-transform: uppercase;">Total Salary Deductions Breakdown</td>
                <td colspan="2" style="padding: 8px; font-size: 12px; text-align: right; color: #fbbf24;">
                    PKR ${Number(totalAmount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                </td>
            </tr>
        </tfoot>
    </table>

    <div class="signatures">
        <div class="sign-block">
            Staff Signature / Acknowledgment
        </div>
        <div class="sign-block">
            Authorized Manager / Cashier
        </div>
    </div>

    <div style="margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 6px; text-align: center; font-size: 9px; color: #94a3b8;">
        Official Salary Deduction Slip & Breakdown • ${brand.name || "Jugnu's Saloon Phalia"} Management System
    </div>
</body>
</html>`;
};
</script>
@endsection
