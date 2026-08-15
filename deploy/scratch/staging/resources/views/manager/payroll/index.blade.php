@extends('layouts.material')

@section('title', 'Employee Payroll Management')

@section('content')
<div x-data="{ 
    createModalOpen: false,
    editModalOpen: false,
    printModalOpen: false,
    selectedPayroll: null,
    employeesList: {{ json_encode($employees) }},
    employeeCommissions: {{ json_encode($employeeCommissions) }},
    currentMonthYear: '{{ $monthYear }}',
    isAdmin: {{ $isAdmin ? 'true' : 'false' }},

    // Create Form State
    createEmpId: '',
    createBaseSalary: 0,
    createAllowedLeaves: 2,
    createTakenLeaves: 0,
    createCommission: 0,
    createBonus: 0,
    createDeductions: 0,
    createStatus: 'draft',
    createPaymentDate: '{{ date('Y-m-d') }}',
    createNotes: '',

    // Edit Form State
    editPayrollId: null,
    editEmpName: '',
    editBaseSalary: 0,
    editAllowedLeaves: 2,
    editTakenLeaves: 0,
    editCommission: 0,
    editBonus: 0,
    editDeductions: 0,
    editStatus: 'draft',
    editPaymentDate: '',
    editNotes: '',

    onEmployeeSelect(empId) {
        if (!empId) return;
        const emp = this.employeesList.find(e => e.id == empId);
        if (emp) {
            this.createBaseSalary = parseFloat(emp.salary) || 0;
            this.createCommission = parseFloat(this.employeeCommissions[emp.id]) || 0;
        }
    },

    calculateLeaveDeduction(base, allowed, taken) {
        const salary = parseFloat(base) || 0;
        const allow = parseInt(allowed) || 0;
        const take = parseInt(taken) || 0;
        if (salary <= 0 || take <= allow) return 0;
        const perDay = salary / 30;
        const extra = take - allow;
        return (extra * perDay).toFixed(2);
    },

    calculateNetSalary(base, allowed, taken, comm, bonus, deduct) {
        const b = parseFloat(base) || 0;
        const c = parseFloat(comm) || 0;
        const bon = parseFloat(bonus) || 0;
        const d = parseFloat(deduct) || 0;
        const lDeduct = parseFloat(this.calculateLeaveDeduction(base, allowed, taken)) || 0;
        return Math.max(0, b - lDeduct + c + bon - d).toFixed(2);
    },

    openEditModal(p) {
        if (!this.isAdmin) {
            alert('⚠️ Access Restricted: Only System Admins are permitted to modify payroll records.');
            return;
        }
        this.editPayrollId = p.id;
        this.editEmpName = p.employee ? p.employee.name : 'Employee';
        this.editBaseSalary = parseFloat(p.base_salary) || 0;
        this.editAllowedLeaves = parseInt(p.allowed_leaves) || 2;
        this.editTakenLeaves = parseInt(p.taken_leaves) || 0;
        this.editCommission = parseFloat(p.total_commission) || 0;
        this.editBonus = parseFloat(p.bonus) || 0;
        this.editDeductions = parseFloat(p.deductions) || 0;
        this.editStatus = p.status || 'draft';
        this.editPaymentDate = p.payment_date ? p.payment_date.substring(0, 10) : '';
        this.editNotes = p.notes || '';
        this.editModalOpen = true;
    }
}" class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="p-2.5 bg-purple-50 text-purple-600">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Employee Payroll Management</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">Calculate staff monthly base salary, leave deductions, earned commissions, and print payslips.</p>
            </div>
        </div>

        <div>
            @if($isAdmin)
                <button @click="createModalOpen = true" 
                        class="inline-flex items-center gap-2 px-5 py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-sm transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    <span>+ Create Payroll Record</span>
                </button>
            @else
                <div class="px-4 py-2.5 bg-amber-50 border border-amber-200 text-amber-900 font-extrabold text-xs flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <span>View & Print Only (Payroll editing restricted to System Admin)</span>
                </div>
            @endif
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white p-4 border border-slate-200 shadow-sm">
        <form method="GET" action="{{ route('manager.payroll.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Payroll Month</label>
                <input type="month" name="month_year" value="{{ $monthYear }}" onchange="this.form.submit()" 
                       class="w-full px-4 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-purple-600">
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Search Employee</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by name or phone..." 
                       class="w-full px-4 py-2 bg-slate-50 border border-slate-200 text-xs font-medium focus:ring-2 focus:ring-purple-600">
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Status Filter</label>
                <select name="status" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-purple-600">
                    <option value="">All Statuses</option>
                    <option value="draft" {{ $status == 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="approved" {{ $status == 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="paid" {{ $status == 'paid' ? 'selected' : '' }}>Paid</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors">
                    Apply Filter
                </button>
                @if($search || $status || $monthYear != date('Y-m'))
                    <a href="{{ route('manager.payroll.index') }}" class="px-4 py-2 bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200 flex items-center justify-center">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Payroll Table -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[11px] font-extrabold uppercase tracking-wider">
                        <th class="py-4 px-4">Employee</th>
                        <th class="py-4 px-4">Month</th>
                        <th class="py-4 px-4">Base Salary</th>
                        <th class="py-4 px-4">Leaves (Taken / Allowed)</th>
                        <th class="py-4 px-4">Leave Deduct</th>
                        <th class="py-4 px-4">Earned Comm.</th>
                        <th class="py-4 px-4">Bonus / Deduct</th>
                        <th class="py-4 px-4">Calculated Net Salary</th>
                        <th class="py-4 px-4">Status</th>
                        <th class="py-4 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @forelse($payrolls as $p)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="py-4 px-4">
                            <p class="font-bold text-slate-900">{{ $p->employee->name ?? 'Employee' }}</p>
                            <span class="text-[10px] font-extrabold uppercase rounded px-1.5 py-0.5 {{ strtolower($p->employee->emp_type ?? '') === 'senior' ? 'bg-purple-100 text-purple-900' : 'bg-sky-100 text-sky-900' }}">
                                {{ ucfirst($p->employee->emp_type ?? 'Junior') }}
                            </span>
                        </td>
                        <td class="py-4 px-4 font-bold text-slate-700">
                            {{ date('M Y', strtotime($p->month_year . '-01')) }}
                        </td>
                        <td class="py-4 px-4 font-bold text-slate-800">
                            {{ number_format($p->base_salary, 2) }}
                        </td>
                        <td class="py-4 px-4 font-bold">
                            <span class="{{ $p->taken_leaves > $p->allowed_leaves ? 'text-rose-600 font-black' : 'text-slate-700' }}">
                                {{ $p->taken_leaves }} / {{ $p->allowed_leaves }} Days
                            </span>
                        </td>
                        <td class="py-4 px-4 font-bold text-rose-600">
                            -{{ number_format($p->leave_deduction, 2) }}
                        </td>
                        <td class="py-4 px-4 font-bold text-purple-700">
                            +{{ number_format($p->total_commission, 2) }}
                        </td>
                        <td class="py-4 px-4 font-medium text-slate-600">
                            <span class="text-emerald-600 font-bold">+{{ number_format($p->bonus, 2) }}</span> / 
                            <span class="text-rose-600 font-bold">-{{ number_format($p->deductions, 2) }}</span>
                        </td>
                        <td class="py-4 px-4 font-black text-sm text-emerald-700">
                            {{ number_format($p->net_salary, 2) }}
                        </td>
                        <td class="py-4 px-4">
                            @if($p->status === 'paid')
                                <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 font-extrabold text-[10px] uppercase rounded">
                                    🟢 Paid
                                </span>
                            @elseif($p->status === 'approved')
                                <span class="px-2.5 py-1 bg-blue-100 text-blue-800 font-extrabold text-[10px] uppercase rounded">
                                    🔵 Approved
                                </span>
                            @else
                                <span class="px-2.5 py-1 bg-amber-100 text-amber-800 font-extrabold text-[10px] uppercase rounded">
                                    🟡 Draft
                                </span>
                            @endif
                        </td>
                        <td class="py-4 px-4 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <!-- Print Payslip Button (ALL USERS) -->
                                <button @click="selectedPayroll = {{ json_encode($p->load('employee')) }}; printModalOpen = true" 
                                        class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-800 font-extrabold text-[11px] flex items-center gap-1 transition-colors" title="Print Official Payslip">
                                    <span>🖨️ Payslip</span>
                                </button>

                                <!-- Edit Button (ADMIN ONLY) -->
                                @if($isAdmin)
                                    <button @click="openEditModal({{ json_encode($p) }})" 
                                            class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-900 font-bold text-xs flex items-center gap-1 transition-colors" title="Edit Payroll Record">
                                        <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        <span>Edit</span>
                                    </button>

                                    <form method="POST" action="{{ route('manager.payroll.destroy', $p) }}" onsubmit="return confirm('Delete payroll for {{ addslashes($p->employee->name ?? '') }}?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1 text-slate-400 hover:text-rose-600" title="Delete Payroll">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </form>
                                @else
                                    <span class="text-[10px] text-slate-400 font-bold italic" title="Only Admin can edit payroll">Admin Edit Only</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="py-12 text-center text-slate-400">
                            <p class="text-sm font-semibold">No payroll records generated for {{ date('F Y', strtotime($monthYear . '-01')) }}.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-slate-100">
            {{ $payrolls->links() }}
        </div>
    </div>

    <!-- MODAL: CREATE PAYROLL (ADMIN ONLY) -->
    <div x-show="createModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="createModalOpen = false" class="bg-white max-w-2xl w-full p-6 shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 00-2 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 00-2 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    Generate Employee Monthly Payroll
                </h3>
                <button @click="createModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('manager.payroll.store') }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Select Employee</label>
                        <select name="account_id" x-model="createEmpId" @change="onEmployeeSelect(createEmpId)" required 
                                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-purple-600">
                            <option value="">Select Employee</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}">👤 {{ $emp->name }} (Salary: {{ number_format($emp->salary, 2) }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Payroll Month/Year</label>
                        <input type="month" name="month_year" x-model="currentMonthYear" required 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-purple-600">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Monthly Base Salary</label>
                        <input type="number" step="0.01" min="0" name="base_salary" x-model.number="createBaseSalary" required placeholder="50000.00" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-900 focus:ring-2 focus:ring-purple-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Allowed Paid Leaves</label>
                        <input type="number" min="0" name="allowed_leaves" x-model.number="createAllowedLeaves" required placeholder="2" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-purple-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Leaves Taken</label>
                        <input type="number" min="0" name="taken_leaves" x-model.number="createTakenLeaves" required placeholder="0" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-purple-600">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-purple-800 uppercase mb-1">Earned Commission</label>
                        <input type="number" step="0.01" min="0" name="total_commission" x-model.number="createCommission" placeholder="0.00" 
                               class="w-full px-4 py-2.5 bg-purple-50 border border-purple-200 text-sm font-bold text-purple-900 focus:ring-2 focus:ring-purple-600" title="Auto-fetched monthly service commissions">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-emerald-800 uppercase mb-1">Bonus / Allowance</label>
                        <input type="number" step="0.01" min="0" name="bonus" x-model.number="createBonus" placeholder="0.00" 
                               class="w-full px-4 py-2.5 bg-emerald-50 border border-emerald-200 text-sm font-bold text-emerald-900 focus:ring-2 focus:ring-emerald-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-rose-800 uppercase mb-1">Other Deductions</label>
                        <input type="number" step="0.01" min="0" name="deductions" x-model.number="createDeductions" placeholder="0.00" 
                               class="w-full px-4 py-2.5 bg-rose-50 border border-rose-200 text-sm font-bold text-rose-900 focus:ring-2 focus:ring-rose-600">
                    </div>
                </div>

                <!-- Live Calculated Net Salary Banner -->
                <div class="p-4 bg-slate-900 text-white flex items-center justify-between border border-slate-800 shadow-inner">
                    <div>
                        <p class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Leave Deduction (-<span x-text="calculateLeaveDeduction(createBaseSalary, createAllowedLeaves, createTakenLeaves)"></span>)</p>
                        <h4 class="text-xs font-bold text-slate-200">Calculated Net Salary</h4>
                    </div>
                    <div class="text-2xl font-black text-emerald-400" x-text="calculateNetSalary(createBaseSalary, createAllowedLeaves, createTakenLeaves, createCommission, createBonus, createDeductions)"></div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Payroll Status</label>
                        <select name="status" x-model="createStatus" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-purple-600">
                            <option value="draft">Draft</option>
                            <option value="approved">Approved</option>
                            <option value="paid">Paid (Synchronizes Employee Ledger)</option>
                        </select>
                    </div>

                    <div x-show="createStatus === 'paid'">
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Disbursement Date</label>
                        <input type="date" name="payment_date" x-model="createPaymentDate" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-purple-600">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Remarks / Notes</label>
                    <textarea name="notes" rows="2" x-model="createNotes" placeholder="Performance incentives, leave details..." 
                              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-purple-600"></textarea>
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="createModalOpen = false" class="px-4 py-2.5 text-slate-600 font-bold text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-md">
                        Save Payroll Record
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: EDIT PAYROLL (ADMIN ONLY) -->
    <div x-show="editModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="editModalOpen = false" class="bg-white max-w-2xl w-full p-6 shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Edit Payroll Record (<span x-text="editEmpName"></span>)
                </h3>
                <button @click="editModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form :action="'{{ url('manager/payroll') }}/' + editPayrollId" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Monthly Base Salary</label>
                        <input type="number" step="0.01" min="0" name="base_salary" x-model.number="editBaseSalary" required 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-900 focus:ring-2 focus:ring-purple-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Allowed Paid Leaves</label>
                        <input type="number" min="0" name="allowed_leaves" x-model.number="editAllowedLeaves" required 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-purple-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Leaves Taken</label>
                        <input type="number" min="0" name="taken_leaves" x-model.number="editTakenLeaves" required 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-purple-600">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-purple-800 uppercase mb-1">Earned Commission</label>
                        <input type="number" step="0.01" min="0" name="total_commission" x-model.number="editCommission" 
                               class="w-full px-4 py-2.5 bg-purple-50 border border-purple-200 text-sm font-bold text-purple-900 focus:ring-2 focus:ring-purple-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-emerald-800 uppercase mb-1">Bonus / Allowance</label>
                        <input type="number" step="0.01" min="0" name="bonus" x-model.number="editBonus" 
                               class="w-full px-4 py-2.5 bg-emerald-50 border border-emerald-200 text-sm font-bold text-emerald-900 focus:ring-2 focus:ring-emerald-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-rose-800 uppercase mb-1">Other Deductions</label>
                        <input type="number" step="0.01" min="0" name="deductions" x-model.number="editDeductions" 
                               class="w-full px-4 py-2.5 bg-rose-50 border border-rose-200 text-sm font-bold text-rose-900 focus:ring-2 focus:ring-rose-600">
                    </div>
                </div>

                <!-- Live Calculated Net Salary Banner -->
                <div class="p-4 bg-slate-900 text-white flex items-center justify-between border border-slate-800 shadow-inner">
                    <div>
                        <p class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Leave Deduction (-<span x-text="calculateLeaveDeduction(editBaseSalary, editAllowedLeaves, editTakenLeaves)"></span>)</p>
                        <h4 class="text-xs font-bold text-slate-200">Calculated Net Salary</h4>
                    </div>
                    <div class="text-2xl font-black text-emerald-400" x-text="calculateNetSalary(editBaseSalary, editAllowedLeaves, editTakenLeaves, editCommission, editBonus, editDeductions)"></div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Payroll Status</label>
                        <select name="status" x-model="editStatus" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-purple-600">
                            <option value="draft">Draft</option>
                            <option value="approved">Approved</option>
                            <option value="paid">Paid (Synchronizes Employee Ledger)</option>
                        </select>
                    </div>

                    <div x-show="editStatus === 'paid'">
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Disbursement Date</label>
                        <input type="date" name="payment_date" x-model="editPaymentDate" 
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-purple-600">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Remarks / Notes</label>
                    <textarea name="notes" rows="2" x-model="editNotes" 
                              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-sm font-medium focus:ring-2 focus:ring-purple-600"></textarea>
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="editModalOpen = false" class="px-4 py-2.5 text-slate-600 font-bold text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs shadow-md">
                        Update Payroll & Sync Ledger
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: PRINT OFFICIAL PAYSLIP (ALL USERS) -->
    <div x-show="printModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="printModalOpen = false" class="bg-white max-w-xl w-full p-6 shadow-2xl space-y-6" x-if="selectedPayroll">
            
            <div class="bg-white p-6 border border-slate-300 shadow-sm font-sans space-y-4" id="official-payslip-print">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-slate-900 pb-3">
                    <div>
                        <h2 class="text-lg font-black text-slate-900 uppercase tracking-tight">{{ $appSetting->brand_name }}</h2>
                        <p class="text-[10px] text-slate-500 font-bold uppercase">Official Employee Payroll Slip</p>
                    </div>
                    <div class="text-right">
                        <span class="px-2 py-1 bg-slate-900 text-white font-black text-[10px] uppercase tracking-wider rounded" x-text="selectedPayroll.month_year"></span>
                    </div>
                </div>

                <!-- Employee & Period Info Grid -->
                <div class="grid grid-cols-2 gap-3 p-3 bg-slate-50 border border-slate-200 text-xs">
                    <div>
                        <p class="text-[10px] uppercase font-bold text-slate-400">Employee Name</p>
                        <p class="font-extrabold text-slate-900" x-text="selectedPayroll.employee ? selectedPayroll.employee.name : 'Employee'"></p>
                        <p class="text-[10px] text-slate-500 font-semibold" x-text="'Phone: ' + (selectedPayroll.employee ? selectedPayroll.employee.phone_no1 : 'N/A')"></p>
                    </div>

                    <div>
                        <p class="text-[10px] uppercase font-bold text-slate-400">Designation / Level</p>
                        <p class="font-bold text-purple-900 uppercase" x-text="selectedPayroll.employee ? (selectedPayroll.employee.emp_type || 'Junior') : 'Staff'"></p>
                        <p class="text-[10px] text-slate-500 font-semibold" x-text="'Status: ' + (selectedPayroll.status ? selectedPayroll.status.toUpperCase() : 'DRAFT')"></p>
                    </div>
                </div>

                <!-- Salary Calculation Table -->
                <table class="w-full text-xs text-left border-collapse border border-slate-200">
                    <thead class="bg-slate-100 text-slate-700 font-bold uppercase text-[10px]">
                        <tr>
                            <th class="p-2 border">Earnings / Deductions Description</th>
                            <th class="p-2 border text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr>
                            <td class="p-2 font-bold text-slate-800">Basic Salary</td>
                            <td class="p-2 text-right font-bold text-slate-900" x-text="selectedPayroll.base_salary"></td>
                        </tr>
                        <tr>
                            <td class="p-2 text-slate-700">
                                Leave Deduction (<span x-text="selectedPayroll.taken_leaves"></span> Taken / <span x-text="selectedPayroll.allowed_leaves"></span> Allowed)
                            </td>
                            <td class="p-2 text-right font-bold text-rose-600" x-text="'-' + selectedPayroll.leave_deduction"></td>
                        </tr>
                        <tr>
                            <td class="p-2 text-slate-700">Monthly Treatment Commissions</td>
                            <td class="p-2 text-right font-bold text-purple-700" x-text="'+' + selectedPayroll.total_commission"></td>
                        </tr>
                        <tr>
                            <td class="p-2 text-slate-700">Performance Bonus & Allowances</td>
                            <td class="p-2 text-right font-bold text-emerald-600" x-text="'+' + selectedPayroll.bonus"></td>
                        </tr>
                        <tr>
                            <td class="p-2 text-slate-700">Other Penalties / Deductions</td>
                            <td class="p-2 text-right font-bold text-rose-600" x-text="'-' + selectedPayroll.deductions"></td>
                        </tr>
                        <tr class="bg-slate-900 text-white font-black">
                            <td class="p-2.5 text-sm uppercase">Net Payable Salary</td>
                            <td class="p-2.5 text-right text-base text-emerald-400" x-text="selectedPayroll.net_salary"></td>
                        </tr>
                    </tbody>
                </table>

                <template x-if="selectedPayroll.notes">
                    <p class="text-[11px] text-slate-500 italic bg-slate-50 p-2 border border-slate-200" x-text="'Remarks: ' + selectedPayroll.notes"></p>
                </template>

                <!-- Signatures -->
                <div class="pt-8 flex justify-between text-[10px] font-bold text-slate-500 uppercase border-t border-slate-200">
                    <div class="text-center">
                        <div class="w-32 border-b border-slate-400 mb-1 mx-auto"></div>
                        <span>Employee Signature</span>
                    </div>
                    <div class="text-center">
                        <div class="w-32 border-b border-slate-400 mb-1 mx-auto"></div>
                        <span>Authorized Admin Signature</span>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" @click="printModalOpen = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs">
                    Close
                </button>
                <button type="button" onclick="window.print()" class="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    <span>Print Official Payslip</span>
                </button>
            </div>
        </div>
    </div>

</div>
@endsection
