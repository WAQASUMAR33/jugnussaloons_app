@extends('layouts.material')

@section('title', 'Employee Payroll Management')

@section('content')
<div x-data="{ 
    batchModalOpen: false,
    createModalOpen: false,
    editModalOpen: false,
    printModalOpen: false,
    viewDeductionsModalOpen: false,
    viewAdvancesModalOpen: false,
    selectedPayroll: null,
    selectedEmployeeDeductions: [],
    selectedEmployeeAdvances: [],
    selectedEmployeeName: '',

    staffBatch: {{ json_encode($staffBatchPreview ?? []) }},
    employeesList: {{ json_encode($employees) }},
    employeeCommissions: {{ json_encode($employeeCommissions) }},
    employeeDeductionTotals: {{ json_encode($employeeDeductionTotals) }},
    employeeDeductionList: {{ json_encode($employeeDeductionList) }},
    employeeAdvanceTotals: {{ json_encode($employeeAdvanceTotals ?? []) }},
    employeeAdvanceList: {{ json_encode($employeeAdvanceList ?? []) }},
    totalAdvancesAmount: {{ $totalAdvancesAmount ?? 0 }},
    employeeAttendanceStats: {{ json_encode($employeeAttendanceStats) }},
    currentMonthYear: '{{ $monthYear }}',
    isAdmin: {{ $isAdmin ? 'true' : 'false' }},
    allMonthPayrolls: {{ json_encode($allMonthPayrolls ?? []) }},
    brandSettings: {
        name: '{{ addslashes($appSetting->brand_name ?? "Jugnu Saloon") }}',
        slogan: '{{ addslashes($appSetting->brand_slogan ?? "WELCOME TO JUGNU SALON PHALIA") }}',
        address: '{{ addslashes($appSetting->brand_address ?? "Phalia, Mandi Bahauddin") }}',
        phone1: '{{ addslashes($appSetting->brand_phone1 ?? "03194415757") }}',
        phone2: '{{ addslashes($appSetting->brand_phone2 ?? "") }}'
    },

    printDocumentViaIframe(htmlContent) {
        if (!htmlContent) return;
        let iframe = document.getElementById('payrollPrintIframe');
        if (!iframe) {
            iframe = document.createElement('iframe');
            iframe.id = 'payrollPrintIframe';
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
                console.warn('Iframe print fallback error:', err);
                window.print();
            }
        }, 350);
    },

    printThermalSlip(payroll) {
        if (!payroll) return;
        const docHtml = (typeof window.buildPayrollThermalSlipDocument === 'function') 
            ? window.buildPayrollThermalSlipDocument(payroll, this.brandSettings) 
            : '';
        this.printDocumentViaIframe(docHtml);
    },

    printA4Payslip(payroll) {
        if (!payroll) return;
        const docHtml = (typeof window.buildPayrollA4PayslipDocument === 'function') 
            ? window.buildPayrollA4PayslipDocument(payroll, this.brandSettings) 
            : '';
        this.printDocumentViaIframe(docHtml);
    },

    printMonthlyPayrollReport() {
        const docHtml = (typeof window.buildPayrollMonthlyReportDocument === 'function') 
            ? window.buildPayrollMonthlyReportDocument(this.allMonthPayrolls, this.staffBatch, this.currentMonthYear, this.brandSettings) 
            : '';
        this.printDocumentViaIframe(docHtml);
    },

    printEmployeeDeductions(empName, deductions) {
        const list = Array.isArray(deductions) ? deductions : [];
        const docHtml = (typeof window.buildEmployeeDeductionBreakdownDocument === 'function')
            ? window.buildEmployeeDeductionBreakdownDocument(list, empName, this.currentMonthYear, this.brandSettings)
            : ((typeof window.buildSalaryDeductionsReportDocument === 'function')
                ? window.buildSalaryDeductionsReportDocument(list, this.currentMonthYear, this.brandSettings, empName)
                : '');
        this.printDocumentViaIframe(docHtml);
    },

    getLeaveDeduction(row) {
        const salary = parseFloat(row.base_salary) || 0;
        const allow = parseInt(row.allowed_leaves) || 0;
        const take = parseInt(row.taken_leaves) || 0;
        if (salary <= 0 || take <= allow) return 0;
        const perDay = salary / 30;
        const extra = take - allow;
        return Math.round(extra * perDay * 100) / 100;
    },

    getNetSalary(row) {
        const base = parseFloat(row.base_salary) || 0;
        const leaveDed = this.getLeaveDeduction(row);
        const comm = parseFloat(row.commission) || 0;
        const bon = parseFloat(row.bonus) || 0;
        const ded = parseFloat(row.deductions) || 0;
        const adv = parseFloat(row.advance_salary) || 0;
        return Math.max(0, Math.round((base - leaveDed + comm + bon - ded - adv) * 100) / 100);
    },

    getTotalStaffCount() {
        return this.staffBatch.length;
    },

    getTotalBaseSalary() {
        return this.staffBatch.reduce((sum, r) => sum + (parseFloat(r.base_salary) || 0), 0);
    },

    getTotalCommissions() {
        return this.staffBatch.reduce((sum, r) => sum + (parseFloat(r.commission) || 0), 0);
    },

    getTotalDeductions() {
        return this.staffBatch.reduce((sum, r) => sum + (parseFloat(r.deductions) || 0), 0);
    },

    getTotalAdvances() {
        return this.staffBatch.reduce((sum, r) => sum + (parseFloat(r.advance_salary) || 0), 0);
    },

    getTotalNetPayout() {
        return this.staffBatch.reduce((sum, r) => sum + this.getNetSalary(r), 0);
    },

    // Create Form State
    createEmpId: '',
    createBaseSalary: 0,
    createAllowedLeaves: 2,
    createTakenLeaves: 0,
    createCommission: 0,
    createBonus: 0,
    createDeductions: 0,
    createAdvanceSalary: 0,
    createStatus: 'draft',
    createPaymentDate: '{{ date('Y-m-d') }}',
    createNotes: '',

    // Edit Form State
    editPayrollId: null,
    editEmpId: null,
    editEmpName: '',
    editBaseSalary: 0,
    editAllowedLeaves: 2,
    editTakenLeaves: 0,
    editCommission: 0,
    editBonus: 0,
    editDeductions: 0,
    editAdvanceSalary: 0,
    editStatus: 'draft',
    editPaymentDate: '',
    editNotes: '',
    editMonthYear: '',
    isLoadingStats: false,
    fetchStatusMessage: '',

    async fetchEmployeeStats(type = 'create') {
        const empId = type === 'create' ? this.createEmpId : this.editEmpId;
        const monthYear = type === 'create' ? this.currentMonthYear : (this.editMonthYear || this.currentMonthYear);
        if (!empId) return;

        this.isLoadingStats = true;
        this.fetchStatusMessage = 'Fetching live data from database...';

        try {
            const res = await fetch(`{{ route('manager.payroll.employee.data') }}?account_id=${empId}&month_year=${monthYear}`);
            const data = await res.json();
            if (data && data.account_id) {
                if (type === 'create') {
                    this.createBaseSalary = parseFloat(data.base_salary) || 0;
                    this.createCommission = parseFloat(data.total_commission) || 0;
                    this.createDeductions = parseFloat(data.deductions) || 0;
                    this.createAdvanceSalary = parseFloat(data.advance_salary) || 0;
                    this.createAllowedLeaves = parseInt(data.allowed_leaves) || 2;
                    this.createTakenLeaves = parseFloat(data.taken_leaves) || 0;
                    this.employeeAttendanceStats[empId] = data.attendance;
                    this.employeeDeductionList[empId] = data.deduction_items || [];
                    this.employeeAdvanceList[empId] = data.advance_items || [];
                } else {
                    this.editCommission = parseFloat(data.total_commission) || 0;
                    this.editDeductions = parseFloat(data.deductions) || 0;
                    this.editAdvanceSalary = parseFloat(data.advance_salary) || 0;
                    this.editTakenLeaves = parseFloat(data.taken_leaves) || 0;
                }
                this.fetchStatusMessage = `✓ Live DB: Comm PKR ${Number(data.total_commission).toLocaleString()}, Deduct PKR ${Number(data.deductions).toLocaleString()}, Adv PKR ${Number(data.advance_salary).toLocaleString()}`;
            }
        } catch (err) {
            console.error('Error fetching employee payroll stats:', err);
            this.fetchStatusMessage = '⚠️ Live DB fetch failed.';
        } finally {
            this.isLoadingStats = false;
        }
    },

    onEmployeeSelect(empId) {
        if (!empId) return;
        const emp = this.employeesList.find(e => e.id == empId);
        if (emp) {
            this.createBaseSalary = parseFloat(emp.salary) || 0;
            this.createCommission = parseFloat(this.employeeCommissions[emp.id]) || 0;
            this.createDeductions = parseFloat(this.employeeDeductionTotals[emp.id]) || 0;
            this.createAdvanceSalary = parseFloat(this.employeeAdvanceTotals[emp.id]) || 0;
            
            const att = this.employeeAttendanceStats[emp.id];
            if (att) {
                this.createTakenLeaves = att.calculated_taken_leaves || 0;
            } else {
                this.createTakenLeaves = 0;
            }

            this.fetchEmployeeStats('create');
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

    calculateNetSalary(base, allowed, taken, comm, bonus, deduct, adv = 0) {
        const b = parseFloat(base) || 0;
        const c = parseFloat(comm) || 0;
        const bon = parseFloat(bonus) || 0;
        const d = parseFloat(deduct) || 0;
        const a = parseFloat(adv) || 0;
        const lDeduct = parseFloat(this.calculateLeaveDeduction(base, allowed, taken)) || 0;
        return Math.max(0, b - lDeduct + c + bon - d - a).toFixed(2);
    },

    openDeductionsView(empId, empName) {
        this.selectedEmployeeName = empName;
        this.selectedEmployeeDeductions = this.employeeDeductionList[empId] || [];
        this.viewDeductionsModalOpen = true;
    },

    openAdvancesView(empId, empName) {
        this.selectedEmployeeName = empName;
        this.selectedEmployeeAdvances = this.employeeAdvanceList[empId] || [];
        this.viewAdvancesModalOpen = true;
    },

    openEditModal(p) {
        if (!this.isAdmin) {
            alert('⚠️ Access Restricted: Only System Admins are permitted to modify payroll records.');
            return;
        }
        this.editPayrollId = p.id;
        this.editEmpId = p.account_id;
        this.editEmpName = p.employee ? p.employee.name : 'Employee';
        this.editBaseSalary = parseFloat(p.base_salary) || 0;
        this.editAllowedLeaves = parseInt(p.allowed_leaves) || 2;
        this.editTakenLeaves = parseInt(p.taken_leaves) || 0;
        this.editCommission = parseFloat(p.total_commission) || 0;
        this.editBonus = parseFloat(p.bonus) || 0;
        this.editDeductions = parseFloat(p.deductions) || 0;
        this.editAdvanceSalary = parseFloat(p.advance_salary) || 0;
        this.editStatus = p.status || 'draft';
        this.editPaymentDate = p.payment_date ? p.payment_date.substring(0, 10) : '';
        this.editNotes = p.notes || '';
        this.editMonthYear = p.month_year || this.currentMonthYear;
        this.fetchStatusMessage = '';
        this.editModalOpen = true;
    }
}" class="space-y-6">

    <!-- Header & Main Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 border border-slate-200 shadow-sm rounded-none">
        <div class="flex items-center gap-3.5">
            <div class="p-3 bg-indigo-50 text-indigo-600 rounded-none">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </div>
            <div>
                <div class="flex items-center gap-2 text-xs font-bold text-slate-400 uppercase tracking-wider">
                    <span>Finance & Accounts</span>
                    <span>•</span>
                    <span class="text-indigo-600">Payroll Calculation</span>
                </div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight heading-font mt-0.5">Staff Payroll Management</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">Calculate monthly salaries, leave deductions, staff treatment commissions, and print official payslips.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <!-- Dedicated Salary Advances Page Link -->
            <a href="{{ route('manager.payroll.advances.index', ['month_year' => $monthYear]) }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-amber-50 hover:bg-amber-100 text-amber-800 font-bold text-xs border border-amber-200 rounded-none transition-colors">
                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>Advance Salary &rarr;</span>
            </a>

            <!-- Dedicated Salary Deductions Page Link -->
            <a href="{{ route('manager.payroll.deductions.index', ['month_year' => $monthYear]) }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-800 font-bold text-xs border border-rose-200 rounded-none transition-colors">
                <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4m16 0l-4-4m4 4l-4 4"></path></svg>
                <span>Salary Deductions &rarr;</span>
            </a>

            <!-- Print Monthly Payroll Report Button -->
            <button type="button" @click="printMonthlyPayrollReport()" 
                    style="background-color: #4338ca !important; color: #ffffff !important; border: 1px solid #3730a3 !important;"
                    class="inline-flex items-center gap-2 px-4 py-2.5 hover:opacity-95 font-black text-xs shadow-md transition-all rounded-none cursor-pointer" title="Print formal Monthly Payroll Report sheet (A4 Landscape)">
                <svg class="w-4 h-4" style="color: #fde047;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                <span style="color: #ffffff !important; font-weight: 900;">🖨️ Print Payroll Report</span>
            </button>

            @if($isAdmin)
                <!-- Primary: Batch Generate All Staff Payroll Modal Button -->
                <button type="button" @click="batchModalOpen = true" 
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs shadow-md transition-all rounded-none cursor-pointer" title="Generate payroll for all staff together with review sheet">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    <span>⚡ Generate Payroll for All Staff</span>
                </button>

                <!-- Quick 1-Click Auto DB Generate for All Staff -->
                <form method="POST" action="{{ route('manager.payroll.generate.all') }}" onsubmit="return confirm('Instantly generate and calculate monthly payroll for all staff members together for {{ date('F Y', strtotime($monthYear . '-01')) }} from database records?');" class="inline">
                    @csrf
                    <input type="hidden" name="month_year" value="{{ $monthYear }}">
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-sm transition-all rounded-none cursor-pointer" title="Direct 1-click database generation for all staff">
                        <svg class="w-3.5 h-3.5 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"></path></svg>
                        <span>1-Click Auto-Generate</span>
                    </button>
                </form>

                <!-- Discreet link for single employee override if ever needed -->
                <button type="button" @click="createModalOpen = true" 
                        class="text-xs text-slate-500 hover:text-indigo-600 font-semibold underline px-1 cursor-pointer">
                    + Single Override
                </button>
            @else
                <div class="px-4 py-2.5 bg-amber-50 border border-amber-200 text-amber-900 font-extrabold text-xs flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <span>View & Print Mode (Payroll editing restricted to Admin)</span>
                </div>
            @endif
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white p-4 border border-slate-200 shadow-sm rounded-none">
        <form method="GET" action="{{ route('manager.payroll.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Payroll Month</label>
                <input type="month" name="month_year" value="{{ $monthYear }}" onchange="this.form.submit()" 
                       class="w-full px-4 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600 rounded-none">
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Search Employee</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by name or phone..." 
                       class="w-full px-4 py-2 bg-slate-50 border border-slate-200 text-xs font-medium focus:ring-2 focus:ring-indigo-600 rounded-none">
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Status Filter</label>
                <select name="status" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-600 rounded-none">
                    <option value="">All Statuses</option>
                    <option value="draft" {{ $status == 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="approved" {{ $status == 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="paid" {{ $status == 'paid' ? 'selected' : '' }}>Paid</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2.5 bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors rounded-none cursor-pointer">
                    Apply Filter
                </button>
                @if($search || $status || $monthYear != date('Y-m'))
                    <a href="{{ route('manager.payroll.index') }}" class="px-4 py-2.5 bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200 flex items-center justify-center border border-slate-200 rounded-none">
                        Reset
                    </a>
                @endif
                <button type="button" @click="printMonthlyPayrollReport()" 
                        style="background-color: #4338ca !important; color: #ffffff !important; border: 1px solid #3730a3 !important;"
                        class="px-4 py-2.5 hover:opacity-95 font-black text-xs flex items-center gap-1.5 transition-all shadow-sm rounded-none cursor-pointer" title="Print Current Filtered Payroll Report">
                    <svg class="w-3.5 h-3.5" style="color: #fde047;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    <span style="color: #ffffff !important; font-weight: 900;">Print</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Main Payroll Table -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden rounded-none">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[11px] font-extrabold uppercase tracking-wider">
                        <th class="py-4 px-4">Employee</th>
                        <th class="py-4 px-4">Month</th>
                        <th class="py-4 px-4">Base Salary</th>
                        <th class="py-4 px-4">Leaves (Taken / Allow)</th>
                        <th class="py-4 px-4">Leave Deduct</th>
                        <th class="py-4 px-4">Commissions</th>
                        <th class="py-4 px-4">Bonus</th>
                        <th class="py-4 px-4">Deductions</th>
                        <th class="py-4 px-4">Advance Salary</th>
                        <th class="py-4 px-4">Net Salary</th>
                        <th class="py-4 px-4">Status</th>
                        <th class="py-4 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @forelse($payrolls as $p)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="py-4 px-4">
                            <p class="font-bold text-slate-900">{{ $p->employee->name ?? 'Employee' }}</p>
                            <span class="text-[10px] font-extrabold uppercase px-1.5 py-0.5 {{ strtolower($p->employee->emp_type ?? '') === 'senior' ? 'bg-purple-100 text-purple-900' : 'bg-sky-100 text-sky-900' }}">
                                {{ ucfirst($p->employee->emp_type ?? 'Junior') }}
                            </span>
                        </td>
                        <td class="py-4 px-4 font-bold text-slate-700">
                            {{ date('M Y', strtotime($p->month_year . '-01')) }}
                        </td>
                        <td class="py-4 px-4 font-bold text-slate-800">
                            PKR {{ number_format($p->base_salary, 2) }}
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
                        <td class="py-4 px-4 font-bold text-emerald-600">
                            +{{ number_format($p->bonus, 2) }}
                        </td>
                        <td class="py-4 px-4">
                            <div class="flex items-center gap-1.5">
                                <span class="font-black text-rose-600">-{{ number_format($p->deductions, 2) }}</span>
                                @php
                                    $itemCount = $p->deductionItems->count();
                                @endphp
                                @if($itemCount > 0)
                                    <button type="button" @click="openDeductionsView({{ $p->account_id }}, '{{ addslashes($p->employee->name ?? 'Employee') }}')" 
                                            class="px-1.5 py-0.5 bg-rose-100 text-rose-800 text-[10px] font-black hover:bg-rose-200 transition-colors cursor-pointer" title="View Itemized Deductions">
                                        {{ $itemCount }} &rarr;
                                    </button>
                                @endif
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            <div class="flex items-center gap-1.5">
                                <span class="{{ (float)$p->advance_salary > 0 ? 'font-black text-amber-600' : 'text-slate-400 font-normal' }}">
                                    {{ (float)$p->advance_salary > 0 ? ('-' . number_format($p->advance_salary, 2)) : 'PKR 0.00' }}
                                </span>
                                @php
                                    $advCount = $p->advanceItems ? $p->advanceItems->count() : 0;
                                @endphp
                                @if($advCount > 0)
                                    <button type="button" @click="openAdvancesView({{ $p->account_id }}, '{{ addslashes($p->employee->name ?? 'Employee') }}')" 
                                            class="px-1.5 py-0.5 bg-amber-100 text-amber-900 text-[10px] font-black hover:bg-amber-200 transition-colors cursor-pointer" title="View Itemized Advances">
                                        {{ $advCount }} &rarr;
                                    </button>
                                @endif
                            </div>
                        </td>
                        <td class="py-4 px-4 font-black text-sm text-emerald-700">
                            PKR {{ number_format($p->net_salary, 2) }}
                        </td>
                        <td class="py-4 px-4">
                            @if($p->status === 'paid')
                                <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 font-extrabold text-[10px] uppercase">
                                    🟢 Paid
                                </span>
                            @elseif($p->status === 'approved')
                                <span class="px-2.5 py-1 bg-blue-100 text-blue-800 font-extrabold text-[10px] uppercase">
                                    🔵 Approved
                                </span>
                            @else
                                <span class="px-2.5 py-1 bg-amber-100 text-amber-800 font-extrabold text-[10px] uppercase">
                                    🟡 Draft
                                </span>
                            @endif
                        </td>
                        <td class="py-4 px-4 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <!-- Quick 80mm Thermal Receipt Direct Print -->
                                <button type="button" @click="printThermalSlip({{ json_encode($p->load(['employee', 'deductionItems', 'advanceItems'])) }})" 
                                        style="background-color: #0f172a !important; color: #fbbf24 !important; border: 1px solid #0f172a !important;"
                                        class="px-2.5 py-1.5 hover:bg-black font-extrabold text-[11px] flex items-center gap-1 transition-colors shadow-sm rounded-none cursor-pointer" title="Quick Print 80mm Thermal Receipt Slip">
                                    <svg class="w-3.5 h-3.5" style="color: #fbbf24;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                    <span style="color: #fbbf24 !important; font-weight: 800;">80mm Slip</span>
                                </button>

                                <!-- View Payslip Modal & Print Options -->
                                <button type="button" @click="selectedPayroll = {{ json_encode($p->load(['employee', 'deductionItems', 'advanceItems'])) }}; printModalOpen = true" 
                                        style="background-color: #e0e7ff !important; color: #3730a3 !important; border: 1px solid #a5b4fc !important;"
                                        class="px-2.5 py-1.5 hover:bg-indigo-200 font-extrabold text-[11px] flex items-center gap-1 transition-colors rounded-none cursor-pointer" title="View Payslip Details & Print Formats">
                                    <span style="color: #3730a3 !important; font-weight: 900;">📄 Payslip</span>
                                </button>

                                <!-- Edit Button (ADMIN ONLY) -->
                                @if($isAdmin)
                                    <button @click="openEditModal({{ json_encode($p) }})" 
                                            class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs flex items-center gap-1 transition-colors border border-slate-200" title="Edit Payroll Record">
                                        <svg class="w-3.5 h-3.5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        <span>Edit</span>
                                    </button>

                                    <form method="POST" action="{{ route('manager.payroll.destroy', $p) }}" onsubmit="return confirm('Delete payroll for {{ addslashes($p->employee->name ?? '') }}?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600" title="Delete Payroll">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="12" class="py-14 text-center">
                            <div class="max-w-md mx-auto space-y-3">
                                <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                </div>
                                <h4 class="text-base font-black text-slate-900">No Payroll Generated for {{ date('F Y', strtotime($monthYear . '-01')) }} Yet</h4>
                                <p class="text-xs text-slate-500 font-medium">Generate monthly payroll for all active staff members together with live database commissions, attendance, advances, and deductions.</p>
                                @if($isAdmin)
                                <div class="pt-2 flex items-center justify-center gap-3">
                                    <button type="button" @click="batchModalOpen = true" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs shadow-md transition-all rounded-none cursor-pointer">
                                        ⚡ Generate Payroll for All Staff
                                    </button>
                                </div>
                                @endif
                            </div>
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

    <!-- MODAL 1: VIEW EMPLOYEE ITEMIZED DEDUCTIONS -->
    <div x-show="viewDeductionsModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="viewDeductionsModalOpen = false" class="bg-white max-w-lg w-full p-6 shadow-2xl space-y-4 border border-slate-200 rounded-none">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 heading-font">Itemized Deductions: <span x-text="selectedEmployeeName"></span></h3>
                    <p class="text-[11px] text-slate-400 font-semibold">Applied for {{ date('F Y', strtotime($monthYear . '-01')) }}</p>
                </div>
                <button @click="viewDeductionsModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="max-h-72 overflow-y-auto divide-y divide-slate-100 text-xs">
                <template x-for="d in selectedEmployeeDeductions" :key="d.id">
                    <div class="py-3 flex items-start justify-between gap-3">
                        <div>
                            <p class="font-black text-slate-900" x-text="d.reason"></p>
                            <p class="text-[11px] text-slate-400" x-text="'Date: ' + (d.deduction_date ? d.deduction_date.substring(0, 10) : 'N/A') + (d.notes ? ' • ' + d.notes : '')"></p>
                        </div>
                        <span class="font-black text-rose-600 shrink-0" x-text="'- PKR ' + Number(d.amount).toLocaleString()"></span>
                    </div>
                </template>
                <div x-show="selectedEmployeeDeductions.length === 0" class="py-8 text-center text-slate-400 font-semibold">
                    No specific itemized deductions recorded.
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                <a href="{{ route('manager.payroll.deductions.index', ['month_year' => $monthYear]) }}" class="text-xs font-bold text-rose-600 hover:underline">
                    Open Full Deductions Page &rarr;
                </a>
                <div class="flex items-center gap-2">
                    <button type="button" @click="printEmployeeDeductions(selectedEmployeeName, selectedEmployeeDeductions)" 
                            style="background-color: #0f172a !important; color: #fbbf24 !important; border: 1px solid #1e293b !important;"
                            class="px-3.5 py-2 font-extrabold text-xs hover:opacity-90 transition-colors rounded-none cursor-pointer shadow-sm"
                            x-show="selectedEmployeeDeductions.length > 0">
                        🖨️ Print Deduction Breakdown
                    </button>
                    <button type="button" @click="viewDeductionsModalOpen = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-none border border-slate-200">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL 1.5: VIEW EMPLOYEE ITEMIZED ADVANCE SALARY -->
    <div x-show="viewAdvancesModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="viewAdvancesModalOpen = false" class="bg-white max-w-lg w-full p-6 shadow-2xl space-y-4 border border-slate-200 rounded-none">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 heading-font">Salary Advances: <span x-text="selectedEmployeeName"></span></h3>
                    <p class="text-[11px] text-slate-400 font-semibold">Target Recovery Month: {{ date('F Y', strtotime($monthYear . '-01')) }}</p>
                </div>
                <button @click="viewAdvancesModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="max-h-72 overflow-y-auto divide-y divide-slate-100 text-xs">
                <template x-for="adv in selectedEmployeeAdvances" :key="adv.id">
                    <div class="py-3 flex items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-1.5">
                                <p class="font-black text-slate-900" x-text="adv.reason || 'Salary Advance'"></p>
                                <span class="px-1.5 py-0.2 text-[9px] font-black uppercase" :class="adv.status === 'recovered' ? 'bg-emerald-100 text-emerald-900' : 'bg-amber-100 text-amber-900'" x-text="adv.status === 'recovered' ? 'Recovered' : 'Pending'"></span>
                            </div>
                            <p class="text-[11px] text-slate-400" x-text="'Date: ' + (adv.advance_date ? adv.advance_date.substring(0, 10) : 'N/A') + ' • ' + adv.payment_mode + (adv.notes ? ' • ' + adv.notes : '')"></p>
                        </div>
                        <span class="font-black text-amber-600 shrink-0 font-mono" x-text="'- PKR ' + Number(adv.amount).toLocaleString()"></span>
                    </div>
                </template>
                <div x-show="selectedEmployeeAdvances.length === 0" class="py-8 text-center text-slate-400 font-semibold">
                    No specific advance salary records found for this month.
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                <a href="{{ route('manager.payroll.advances.index', ['month_year' => $monthYear]) }}" class="text-xs font-bold text-amber-600 hover:underline">
                    Open Full Advances Page &rarr;
                </a>
                <button type="button" @click="viewAdvancesModalOpen = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-none border border-slate-200">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL 1.8: BATCH GENERATE ALL STAFF PAYROLL (ADMIN ONLY) -->
    <div x-show="batchModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-slate-900/60 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="batchModalOpen = false" class="bg-white max-w-6xl w-full p-6 shadow-2xl space-y-5 max-h-[95vh] overflow-y-auto border border-slate-200 rounded-none">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-lg font-black text-slate-900 flex items-center gap-2.5 heading-font">
                        <span class="p-2 bg-emerald-100 text-emerald-800">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        </span>
                        <span>Generate Payroll for All Staff Together</span>
                    </h3>
                    <p class="text-xs font-semibold text-slate-500 mt-1">
                        Review and approve monthly payroll for all <span class="font-bold text-slate-800" x-text="staffBatch.length"></span> staff members together for <span class="font-bold text-indigo-700">{{ date('F Y', strtotime($monthYear . '-01')) }}</span>. Commissions, deductions, advance recovery, and attendance leaves are pre-fetched directly from the database.
                    </p>
                </div>
                <button @click="batchModalOpen = false" class="text-slate-400 hover:text-slate-600 p-2 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('manager.payroll.generate.all') }}" class="space-y-5">
                @csrf
                <input type="hidden" name="month_year" value="{{ $monthYear }}">

                <div class="overflow-x-auto border border-slate-200">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 text-[11px] font-black uppercase tracking-wider">
                                <th class="py-3 px-3">Employee</th>
                                <th class="py-3 px-3 w-28">Base Salary</th>
                                <th class="py-3 px-3 w-28">Leaves (Tk/Alw)</th>
                                <th class="py-3 px-3 w-24">Leave Ded</th>
                                <th class="py-3 px-3 w-28">Commission</th>
                                <th class="py-3 px-3 w-24">Bonus</th>
                                <th class="py-3 px-3 w-28">Deductions</th>
                                <th class="py-3 px-3 w-28">Advance Sal</th>
                                <th class="py-3 px-3 w-32">Net Payable</th>
                                <th class="py-3 px-3 w-24">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <template x-for="(row, idx) in staffBatch" :key="row.account_id">
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <td class="py-3 px-3 font-bold text-slate-900">
                                        <input type="hidden" :name="'staff_payroll[' + idx + '][account_id]'" :value="row.account_id">
                                        <div class="flex flex-col">
                                            <span class="font-extrabold text-slate-900" x-text="row.name"></span>
                                            <span class="text-[10px] uppercase font-bold text-slate-400" x-text="row.emp_type"></span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-3">
                                        <input type="number" step="0.01" min="0" 
                                               :name="'staff_payroll[' + idx + '][base_salary]'" 
                                               x-model.number="row.base_salary" 
                                               class="w-full px-2 py-1.5 bg-slate-50 border border-slate-200 font-bold text-slate-800 text-xs rounded-none">
                                    </td>
                                    <td class="py-3 px-3">
                                        <div class="flex items-center gap-1">
                                            <input type="number" min="0" 
                                                   :name="'staff_payroll[' + idx + '][taken_leaves]'" 
                                                   x-model.number="row.taken_leaves" 
                                                   title="Leaves Taken / Missed Days"
                                                   class="w-12 px-1.5 py-1.5 bg-slate-50 border border-slate-200 font-bold text-slate-800 text-xs text-center rounded-none">
                                            <span class="text-slate-400 font-bold">/</span>
                                            <input type="number" min="0" 
                                                   :name="'staff_payroll[' + idx + '][allowed_leaves]'" 
                                                   x-model.number="row.allowed_leaves" 
                                                   title="Allowed Paid Leaves"
                                                   class="w-12 px-1.5 py-1.5 bg-slate-50 border border-slate-200 font-bold text-slate-800 text-xs text-center rounded-none">
                                        </div>
                                    </td>
                                    <td class="py-3 px-3 font-bold text-rose-600">
                                        <span x-text="'- PKR ' + Number(getLeaveDeduction(row)).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                    </td>
                                    <td class="py-3 px-3">
                                        <input type="number" step="0.01" min="0" 
                                               :name="'staff_payroll[' + idx + '][commission]'" 
                                               x-model.number="row.commission" 
                                               class="w-full px-2 py-1.5 bg-purple-50/70 border border-purple-200 font-bold text-purple-900 text-xs rounded-none">
                                    </td>
                                    <td class="py-3 px-3">
                                        <input type="number" step="0.01" min="0" 
                                               :name="'staff_payroll[' + idx + '][bonus]'" 
                                               x-model.number="row.bonus" 
                                               placeholder="0.00"
                                               class="w-full px-2 py-1.5 bg-emerald-50/60 border border-emerald-200 font-bold text-emerald-900 text-xs rounded-none">
                                    </td>
                                    <td class="py-3 px-3">
                                        <input type="number" step="0.01" min="0" 
                                               :name="'staff_payroll[' + idx + '][deductions]'" 
                                               x-model.number="row.deductions" 
                                               class="w-full px-2 py-1.5 bg-rose-50/70 border border-rose-200 font-bold text-rose-900 text-xs rounded-none">
                                    </td>
                                    <td class="py-3 px-3">
                                        <input type="number" step="0.01" min="0" 
                                               :name="'staff_payroll[' + idx + '][advance_salary]'" 
                                               x-model.number="row.advance_salary" 
                                               class="w-full px-2 py-1.5 bg-amber-50/70 border border-amber-200 font-bold text-amber-900 text-xs rounded-none">
                                    </td>
                                    <td class="py-3 px-3 font-black text-sm text-emerald-700">
                                        <span x-text="'PKR ' + Number(getNetSalary(row)).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                    </td>
                                    <td class="py-3 px-3">
                                        <select :name="'staff_payroll[' + idx + '][status]'" x-model="row.status" 
                                                class="w-full px-2 py-1.5 bg-slate-50 border border-slate-200 font-bold text-xs rounded-none">
                                            <option value="draft">Draft</option>
                                            <option value="approved">Approved</option>
                                            <option value="paid">Paid</option>
                                        </select>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Aggregate Salon Summary Cards -->
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 p-4 bg-slate-50 border border-slate-200">
                    <div>
                        <p class="text-[10px] font-black uppercase text-slate-400">Total Staff</p>
                        <p class="text-base font-black text-slate-900 mt-0.5" x-text="getTotalStaffCount() + ' Members'"></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase text-slate-400">Gross Salaries</p>
                        <p class="text-base font-black text-slate-800 mt-0.5" x-text="'PKR ' + Number(getTotalBaseSalary()).toLocaleString()"></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase text-purple-600">Total Commissions</p>
                        <p class="text-base font-black text-purple-700 mt-0.5" x-text="'+ PKR ' + Number(getTotalCommissions()).toLocaleString()"></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase text-rose-600">Deductions & Advances</p>
                        <p class="text-base font-black text-rose-700 mt-0.5" x-text="'- PKR ' + Number(getTotalDeductions() + getTotalAdvances()).toLocaleString()"></p>
                    </div>
                    <div class="col-span-2 sm:col-span-1 bg-emerald-100/60 p-2.5 border border-emerald-300">
                        <p class="text-[10px] font-black uppercase text-emerald-800">Total Net Payout</p>
                        <p class="text-lg font-black text-emerald-900 mt-0.5" x-text="'PKR ' + Number(getTotalNetPayout()).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></p>
                    </div>
                </div>

                <!-- Actions Bar -->
                <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                    <p class="text-[11px] font-semibold text-slate-400">
                        ⚡ Saving will create or update payroll for all staff members together in a single action.
                    </p>

                    <div class="flex items-center gap-3">
                        <button type="button" @click="batchModalOpen = false" 
                                class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-none border border-slate-200 cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs shadow-md transition-all rounded-none cursor-pointer flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>⚡ Confirm & Save All Staff Payroll</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: CREATE PAYROLL (OPTIONAL SINGLE OVERRIDE) -->
    <div x-show="createModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="createModalOpen = false" class="bg-white max-w-2xl w-full p-6 shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto border border-slate-200 rounded-none">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2 heading-font">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    <span>Generate Employee Monthly Payroll</span>
                </h3>
                <button @click="createModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('manager.payroll.store') }}" class="space-y-4 text-xs font-bold text-slate-700">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-slate-600 font-bold uppercase text-[11px] mb-1">Select Employee *</label>
                        <select name="account_id" x-model="createEmpId" @change="onEmployeeSelect(createEmpId)" required 
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-900 focus:ring-2 focus:ring-indigo-600 rounded-none">
                            <option value="">Select Employee</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}">👤 {{ $emp->name }} (Base Salary: PKR {{ number_format($emp->salary, 2) }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-600 font-bold uppercase text-[11px] mb-1">Payroll Month/Year *</label>
                        <input type="month" name="month_year" x-model="currentMonthYear" @change="fetchEmployeeStats('create')" required 
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-900 focus:ring-2 focus:ring-indigo-600 rounded-none">
                    </div>
                </div>

                <!-- Live Monthly Attendance Summary for Selected Employee -->
                <template x-if="createEmpId && employeeAttendanceStats[createEmpId]">
                    <div class="p-3 bg-indigo-50/70 border border-indigo-200 flex items-center justify-between text-xs rounded-none">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-indigo-900 font-black">🗓️ Attendance Sheet:</span>
                            <span class="px-1.5 py-0.5 bg-emerald-100 text-emerald-900 font-black" x-text="employeeAttendanceStats[createEmpId].present + ' Present'"></span>
                            <span class="px-1.5 py-0.5 bg-rose-100 text-rose-900 font-black" x-text="employeeAttendanceStats[createEmpId].absent + ' Absent'"></span>
                            <span class="px-1.5 py-0.5 bg-amber-100 text-amber-900 font-bold" x-text="employeeAttendanceStats[createEmpId].late + ' Late'"></span>
                            <span class="px-1.5 py-0.5 bg-indigo-100 text-indigo-900 font-bold" x-text="employeeAttendanceStats[createEmpId].half_day + ' Half Day'"></span>
                            <span class="px-1.5 py-0.5 bg-purple-100 text-purple-900 font-bold" x-text="employeeAttendanceStats[createEmpId].leave + ' Leave'"></span>
                        </div>
                        <a :href="'/manager/attendance?month_year=' + currentMonthYear + '&tab=logs&employee_id=' + createEmpId" target="_blank" class="text-indigo-600 hover:underline font-bold text-[11px] shrink-0 ml-2">
                            View Logs &rarr;
                        </a>
                    </div>
                </template>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                    <div>
                        <label class="block text-slate-600 font-bold uppercase text-[11px] mb-1">Monthly Base Salary</label>
                        <input type="number" step="0.01" min="0" name="base_salary" x-model.number="createBaseSalary" required placeholder="50000.00" 
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 text-xs font-black text-slate-900 focus:ring-2 focus:ring-indigo-600 rounded-none">
                    </div>

                    <div>
                        <label class="block text-slate-600 font-bold uppercase text-[11px] mb-1">Allowed Paid Leaves</label>
                        <input type="number" min="0" name="allowed_leaves" x-model.number="createAllowedLeaves" required placeholder="2" 
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600 rounded-none">
                    </div>

                    <div>
                        <label class="block text-slate-600 font-bold uppercase text-[11px] mb-1">Leaves Taken</label>
                        <input type="number" min="0" name="taken_leaves" x-model.number="createTakenLeaves" required placeholder="0" 
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600 rounded-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-purple-900 font-bold uppercase text-[11px] mb-1">Commission</label>
                        <input type="number" step="0.01" min="0" name="total_commission" x-model.number="createCommission" placeholder="0.00" 
                               class="w-full px-3 py-2 bg-purple-50 border border-purple-200 text-xs font-black text-purple-900 focus:ring-2 focus:ring-purple-600 rounded-none">
                        <p class="text-[9px] font-bold text-purple-700 mt-1">✨ Auto-fetched from Commissions + Appointments</p>
                    </div>

                    <div>
                        <label class="block text-emerald-900 font-bold uppercase text-[11px] mb-1">Bonus</label>
                        <input type="number" step="0.01" min="0" name="bonus" x-model.number="createBonus" placeholder="0.00" 
                               class="w-full px-3 py-2 bg-emerald-50 border border-emerald-200 text-xs font-black text-emerald-900 focus:ring-2 focus:ring-emerald-600 rounded-none">
                    </div>

                    <div>
                        <label class="block text-rose-900 font-bold uppercase text-[11px] mb-1">Deductions</label>
                        <input type="number" step="0.01" min="0" name="deductions" x-model.number="createDeductions" placeholder="0.00" 
                               class="w-full px-3 py-2 bg-rose-50 border border-rose-200 text-xs font-black text-rose-900 focus:ring-2 focus:ring-rose-600 rounded-none" title="Auto-fetched from reason-based deductions">
                        <p class="text-[9px] font-bold text-rose-700 mt-1">✨ Auto-fetched from Salary Deductions table</p>
                    </div>

                    <div>
                        <label class="block text-amber-900 font-bold uppercase text-[11px] mb-1">Advance Salary</label>
                        <input type="number" step="0.01" min="0" name="advance_salary" x-model.number="createAdvanceSalary" placeholder="0.00" 
                               class="w-full px-3 py-2 bg-amber-50 border border-amber-200 text-xs font-black text-amber-900 focus:ring-2 focus:ring-amber-600 rounded-none" title="Auto-fetched from staff salary advance records">
                        <p class="text-[9px] font-bold text-amber-700 mt-1">✨ Auto-fetched from Salary Advances table</p>
                    </div>
                </div>

                <!-- Live Calculated Net Salary Banner -->
                <div class="p-4 bg-slate-900 text-white flex items-center justify-between border border-slate-800 shadow-inner rounded-none">
                    <div>
                        <p class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Leave Deduction (-<span x-text="calculateLeaveDeduction(createBaseSalary, createAllowedLeaves, createTakenLeaves)"></span>) • Advance (-<span x-text="Number(createAdvanceSalary || 0).toLocaleString()"></span>)</p>
                        <h4 class="text-xs font-bold text-slate-200">Calculated Net Payable Salary</h4>
                    </div>
                    <div class="text-2xl font-black text-emerald-400">PKR <span x-text="calculateNetSalary(createBaseSalary, createAllowedLeaves, createTakenLeaves, createCommission, createBonus, createDeductions, createAdvanceSalary)"></span></div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-slate-600 font-bold uppercase text-[11px] mb-1">Payroll Status</label>
                        <select name="status" x-model="createStatus" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600 rounded-none">
                            <option value="draft">Draft</option>
                            <option value="approved">Approved</option>
                            <option value="paid">Paid (Synchronizes Employee Ledger)</option>
                        </select>
                    </div>

                    <div x-show="createStatus === 'paid'">
                        <label class="block text-slate-600 font-bold uppercase text-[11px] mb-1">Disbursement Date</label>
                        <input type="date" name="payment_date" x-model="createPaymentDate" 
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 text-xs font-medium focus:ring-2 focus:ring-indigo-600 rounded-none">
                    </div>
                </div>

                <div>
                    <label class="block text-slate-600 font-bold uppercase text-[11px] mb-1">Remarks / Notes</label>
                    <textarea name="notes" rows="2" x-model="createNotes" placeholder="Performance incentives, deductions summary..." 
                              class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 text-xs font-medium focus:ring-2 focus:ring-indigo-600 rounded-none resize-none"></textarea>
                </div>

                <div class="pt-3 flex justify-end gap-2.5 border-t border-slate-100">
                    <button type="button" @click="createModalOpen = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs border border-slate-200 rounded-none">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm rounded-none">
                        Save Payroll Record
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 3: EDIT PAYROLL (ADMIN ONLY) -->
    <div x-show="editModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="editModalOpen = false" class="bg-white max-w-2xl w-full p-6 shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto border border-slate-200 rounded-none">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2 heading-font">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    <span>Edit Payroll Record (<span x-text="editEmpName"></span>)</span>
                </h3>
                <button @click="editModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form :action="'{{ url('manager/payroll') }}/' + editPayrollId" method="POST" class="space-y-4 text-xs font-bold text-slate-700">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                    <div>
                        <label class="block text-slate-600 font-bold uppercase text-[11px] mb-1">Monthly Base Salary</label>
                        <input type="number" step="0.01" min="0" name="base_salary" x-model.number="editBaseSalary" required 
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 text-xs font-black text-slate-900 focus:ring-2 focus:ring-indigo-600 rounded-none">
                    </div>

                    <div>
                        <label class="block text-slate-600 font-bold uppercase text-[11px] mb-1">Allowed Paid Leaves</label>
                        <input type="number" min="0" name="allowed_leaves" x-model.number="editAllowedLeaves" required 
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600 rounded-none">
                    </div>

                    <div>
                        <label class="block text-slate-600 font-bold uppercase text-[11px] mb-1">Leaves Taken</label>
                        <input type="number" min="0" name="taken_leaves" x-model.number="editTakenLeaves" required 
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600 rounded-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-purple-900 font-bold uppercase text-[11px] mb-1">Commission</label>
                        <input type="number" step="0.01" min="0" name="total_commission" x-model.number="editCommission" 
                               class="w-full px-3 py-2 bg-purple-50 border border-purple-200 text-xs font-black text-purple-900 focus:ring-2 focus:ring-purple-600 rounded-none">
                        <p class="text-[9px] font-bold text-purple-700 mt-1">✨ Commissions from database + Appointments</p>
                    </div>

                    <div>
                        <label class="block text-emerald-900 font-bold uppercase text-[11px] mb-1">Bonus</label>
                        <input type="number" step="0.01" min="0" name="bonus" x-model.number="editBonus" 
                               class="w-full px-3 py-2 bg-emerald-50 border border-emerald-200 text-xs font-black text-emerald-900 focus:ring-2 focus:ring-emerald-600 rounded-none">
                    </div>

                    <div>
                        <label class="block text-rose-900 font-bold uppercase text-[11px] mb-1">Deductions</label>
                        <input type="number" step="0.01" min="0" name="deductions" x-model.number="editDeductions" 
                               class="w-full px-3 py-2 bg-rose-50 border border-rose-200 text-xs font-black text-rose-900 focus:ring-2 focus:ring-rose-600 rounded-none">
                        <p class="text-[9px] font-bold text-rose-700 mt-1">✨ Deductions from Salary Deductions table</p>
                    </div>

                    <div>
                        <label class="block text-amber-900 font-bold uppercase text-[11px] mb-1">Advance Salary</label>
                        <input type="number" step="0.01" min="0" name="advance_salary" x-model.number="editAdvanceSalary" placeholder="0.00" 
                               class="w-full px-3 py-2 bg-amber-50 border border-amber-200 text-xs font-black text-amber-900 focus:ring-2 focus:ring-amber-600 rounded-none">
                        <p class="text-[9px] font-bold text-amber-700 mt-1">✨ Advances from Salary Advances table</p>
                    </div>
                </div>

                <!-- Live Calculated Net Salary Banner -->
                <div class="p-4 bg-slate-900 text-white flex items-center justify-between border border-slate-800 shadow-inner rounded-none">
                    <div>
                        <p class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Leave Deduction (-<span x-text="calculateLeaveDeduction(editBaseSalary, editAllowedLeaves, editTakenLeaves)"></span>) • Advance (-<span x-text="Number(editAdvanceSalary || 0).toLocaleString()"></span>)</p>
                        <h4 class="text-xs font-bold text-slate-200">Calculated Net Payable Salary</h4>
                    </div>
                    <div class="text-2xl font-black text-emerald-400">PKR <span x-text="calculateNetSalary(editBaseSalary, editAllowedLeaves, editTakenLeaves, editCommission, editBonus, editDeductions, editAdvanceSalary)"></span></div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-slate-600 font-bold uppercase text-[11px] mb-1">Payroll Status</label>
                        <select name="status" x-model="editStatus" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600 rounded-none">
                            <option value="draft">Draft</option>
                            <option value="approved">Approved</option>
                            <option value="paid">Paid (Synchronizes Employee Ledger)</option>
                        </select>
                    </div>

                    <div x-show="editStatus === 'paid'">
                        <label class="block text-slate-600 font-bold uppercase text-[11px] mb-1">Disbursement Date</label>
                        <input type="date" name="payment_date" x-model="editPaymentDate" 
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 text-xs font-medium focus:ring-2 focus:ring-indigo-600 rounded-none">
                    </div>
                </div>

                <div>
                    <label class="block text-slate-600 font-bold uppercase text-[11px] mb-1">Remarks / Notes</label>
                    <textarea name="notes" rows="2" x-model="editNotes" 
                              class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 text-xs font-medium focus:ring-2 focus:ring-indigo-600 rounded-none resize-none"></textarea>
                </div>

                <div class="pt-3 flex justify-end gap-2.5 border-t border-slate-100">
                    <button type="button" @click="editModalOpen = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs border border-slate-200 rounded-none">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm rounded-none">
                        Update Payroll & Sync Ledger
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 4: PRINT OFFICIAL PAYSLIP WITH ITEMIZED DEDUCTIONS -->
    <div x-show="printModalOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm"
         x-cloak>
        <div @click.outside="printModalOpen = false" class="bg-white max-w-xl w-full p-6 shadow-2xl space-y-6 border border-slate-200 rounded-none" x-if="selectedPayroll">
            
            <div class="bg-white p-6 border border-slate-300 shadow-sm font-sans space-y-4" id="official-payslip-print">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-slate-900 pb-3">
                    <div>
                        <h2 class="text-lg font-black text-slate-900 uppercase tracking-tight">{{ $appSetting->brand_name }}</h2>
                        <p class="text-[10px] text-slate-500 font-bold uppercase">Official Employee Payroll Slip</p>
                    </div>
                    <div class="text-right">
                        <span class="px-2 py-1 bg-slate-900 text-white font-black text-[10px] uppercase tracking-wider" x-text="selectedPayroll.month_year"></span>
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
                            <th class="p-2 border text-right">Amount (PKR)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 font-semibold">
                        <tr>
                            <td class="p-2 font-bold text-slate-900">Basic Salary</td>
                            <td class="p-2 text-right font-black text-slate-900" x-text="selectedPayroll.base_salary"></td>
                        </tr>
                        <tr>
                            <td class="p-2 text-slate-700">
                                Leave Deduction (<span x-text="selectedPayroll.taken_leaves"></span> Taken / <span x-text="selectedPayroll.allowed_leaves"></span> Allowed)
                            </td>
                            <td class="p-2 text-right font-bold text-rose-600" x-text="'- ' + selectedPayroll.leave_deduction"></td>
                        </tr>
                        <tr>
                            <td class="p-2 text-slate-700">Monthly Treatment Commissions</td>
                            <td class="p-2 text-right font-bold text-purple-700" x-text="'+ ' + selectedPayroll.total_commission"></td>
                        </tr>
                        <tr>
                            <td class="p-2 text-slate-700">Performance Bonus & Allowances</td>
                            <td class="p-2 text-right font-bold text-emerald-600" x-text="'+ ' + selectedPayroll.bonus"></td>
                        </tr>
                        <tr>
                            <td class="p-2 text-slate-700">
                                <div>Total Other Deductions</div>
                                <template x-if="selectedPayroll.deduction_items && selectedPayroll.deduction_items.length > 0">
                                    <div class="mt-1 space-y-0.5 pl-2 border-l-2 border-rose-300 text-[10px] text-slate-500 font-normal">
                                        <template x-for="item in selectedPayroll.deduction_items" :key="item.id">
                                            <p>• <span x-text="item.reason"></span> (<span x-text="item.deduction_date ? item.deduction_date.substring(0, 10) : ''"></span>): -PKR <span x-text="item.amount"></span></p>
                                        </template>
                                    </div>
                                </template>
                            </td>
                            <td class="p-2 text-right font-bold text-rose-600" x-text="'- ' + selectedPayroll.deductions"></td>
                        </tr>
                        <tr x-show="parseFloat(selectedPayroll.advance_salary || 0) > 0">
                            <td class="p-2 text-slate-700">
                                <div>Advance Salary Deducted</div>
                                <template x-if="selectedPayroll.advance_items && selectedPayroll.advance_items.length > 0">
                                    <div class="mt-1 space-y-0.5 pl-2 border-l-2 border-amber-300 text-[10px] text-slate-500 font-normal">
                                        <template x-for="adv in selectedPayroll.advance_items" :key="adv.id">
                                            <p>• <span x-text="adv.reason || 'Salary Advance'"></span> (<span x-text="adv.advance_date ? adv.advance_date.substring(0, 10) : ''"></span> - <span x-text="adv.payment_mode"></span>): -PKR <span x-text="adv.amount"></span></p>
                                        </template>
                                    </div>
                                </template>
                            </td>
                            <td class="p-2 text-right font-bold text-amber-700" x-text="'- ' + selectedPayroll.advance_salary"></td>
                        </tr>
                        <tr class="bg-slate-900 text-white font-black">
                            <td class="p-2.5 text-sm uppercase">Net Payable Salary</td>
                            <td class="p-2.5 text-right text-base text-emerald-400" x-text="'PKR ' + selectedPayroll.net_salary"></td>
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

            <div class="flex flex-wrap items-center justify-between gap-2 pt-3 border-t border-slate-200">
                <button type="button" @click="printModalOpen = false" 
                        style="background-color: #f1f5f9 !important; color: #334155 !important; border: 1px solid #cbd5e1 !important;"
                        class="px-4 py-2 hover:bg-slate-200 font-bold text-xs rounded-none cursor-pointer">
                    <span style="color: #334155 !important; font-weight: 700;">Close</span>
                </button>
                <div class="flex items-center gap-2">
                    <button type="button" @click="printThermalSlip(selectedPayroll)" 
                            style="background-color: #0f172a !important; color: #fbbf24 !important; border: 1px solid #0f172a !important;"
                            class="px-4 py-2 hover:bg-black font-extrabold text-xs flex items-center gap-1.5 shadow-sm rounded-none cursor-pointer" title="Print for 80mm POS Thermal Receipt Printer">
                        <svg class="w-4 h-4" style="color: #fbbf24;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        <span style="color: #fbbf24 !important; font-weight: 800;">🖨️ Print 80mm Receipt Slip</span>
                    </button>
                    <button type="button" @click="printA4Payslip(selectedPayroll)" 
                            style="background-color: #4338ca !important; color: #ffffff !important; border: 1px solid #3730a3 !important;"
                            class="px-4 py-2 hover:opacity-95 font-bold text-xs flex items-center gap-1.5 shadow-sm rounded-none cursor-pointer" title="Print Full Page A4 Official Payslip">
                        <svg class="w-4 h-4" style="color: #ffffff;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span style="color: #ffffff !important; font-weight: 800;">📄 Print A4 Formal Payslip</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
window.formatPayrollMoney = function(val) {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

window.formatPayrollMonth = function(monthStr) {
    if (!monthStr) return '';
    const parts = monthStr.split('-');
    if (parts.length >= 2) {
        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        const y = parts[0];
        const m = parseInt(parts[1], 10) - 1;
        return (monthNames[m] || '') + ' ' + y;
    }
    return monthStr;
};

window.buildPayrollThermalSlipDocument = function(payroll, brand) {
    if (!payroll) return '';
    brand = brand || {};
    const emp = payroll.employee || {};
    const empName = emp.name || payroll.name || 'Employee';
    const empType = (emp.emp_type || payroll.emp_type || 'Staff').toUpperCase();
    const empPhone = emp.phone_no1 || payroll.phone || '';
    const monthYear = payroll.month_year || '{{ $monthYear }}';
    const monthYearText = window.formatPayrollMonth(monthYear);
    const dateNow = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

    const baseSalary = parseFloat(payroll.base_salary) || 0;
    const takenLeaves = parseFloat(payroll.taken_leaves) || 0;
    const allowedLeaves = parseFloat(payroll.allowed_leaves) || 0;
    const leaveDeduction = parseFloat(payroll.leave_deduction) || 0;
    const commission = parseFloat(payroll.total_commission !== undefined ? payroll.total_commission : payroll.commission) || 0;
    const bonus = parseFloat(payroll.bonus) || 0;
    const deductions = parseFloat(payroll.deductions) || 0;
    const advanceSalary = parseFloat(payroll.advance_salary) || 0;
    const netSalary = parseFloat(payroll.net_salary) || Math.max(0, baseSalary - leaveDeduction + commission + bonus - deductions - advanceSalary);
    const status = (payroll.status || 'draft').toUpperCase();

    let deductionsBreakdown = '';
    if (payroll.deduction_items && payroll.deduction_items.length > 0) {
        payroll.deduction_items.forEach(d => {
            const dateStr = d.deduction_date ? ` (${d.deduction_date.substring(0, 10)})` : '';
            deductionsBreakdown += `
                <div class="row sub-row">
                    <span>• ${d.reason || 'Deduction'}${dateStr}</span>
                    <span>-${window.formatPayrollMoney(d.amount)}</span>
                </div>
            `;
        });
    }

    let advancesBreakdown = '';
    if (payroll.advance_items && payroll.advance_items.length > 0) {
        payroll.advance_items.forEach(a => {
            const mode = a.payment_mode ? ` - ${a.payment_mode}` : '';
            const dateStr = a.advance_date ? ` (${a.advance_date.substring(0, 10)})` : '';
            advancesBreakdown += `
                <div class="row sub-row">
                    <span>• ${a.reason || 'Advance'}${dateStr}${mode}</span>
                    <span>-${window.formatPayrollMoney(a.amount)}</span>
                </div>
            `;
        });
    }

    const slogan = brand.slogan ? `<div style="font-size: 11px; font-weight: 600; text-transform: uppercase; margin-top: 2px;">${brand.slogan}</div>` : '';
    const address = brand.address ? `<div style="font-size: 11px; margin-top: 2px;">${brand.address}</div>` : '';
    const phone = (brand.phone1 || brand.phone2) ? `<div style="font-size: 11px; margin-top: 2px;">Tel: ${brand.phone1 || ''} ${brand.phone2 ? ' / ' + brand.phone2 : ''}</div>` : '';

    return `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Salary Slip - ${empName} - ${monthYearText}</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 0;
        }
        @media print {
            html, body {
                width: 76mm !important;
                margin: 0 auto !important;
                padding: 3mm 3mm !important;
            }
        }
        html, body {
            width: 76mm;
            margin: 0 auto;
            padding: 3mm 3mm;
            font-family: Arial, Helvetica, 'Segoe UI', sans-serif;
            font-size: 13px;
            line-height: 1.4;
            color: #000;
            background: #fff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            -webkit-text-size-adjust: 100%;
            box-sizing: border-box;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        .dashed { border-bottom: 2px dashed #000; margin: 6px 0; }
        .solid  { border-bottom: 2px solid #000; margin: 6px 0; }
        .double { border-top: 2px solid #000; border-bottom: 2px solid #000; margin: 6px 0; padding: 3px 0; }
        .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 12px;
        }
        .row span:last-child { font-weight: 700; text-align: right; }
        .sub-row { font-size: 11px; padding-left: 4px; color: #222; }
        .sub-row span:last-child { font-weight: 600; }
        .bold { font-weight: 700; }
        .black { font-weight: 900; }
        .center { text-align: center; }
        .uppercase { text-transform: uppercase; }
        .section-title {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 4px;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <div class="center" style="margin-bottom: 6px;">
        <div style="font-size: 16px; font-weight: 900; text-transform: uppercase; letter-spacing: 1px;">${brand.name || 'JUGNU SALOON'}</div>
        ${slogan}
        ${address}
        ${phone}
    </div>

    <div class="double center bold uppercase" style="font-size: 13px; letter-spacing: 0.5px;">
        STAFF SALARY PAYMENT SLIP
    </div>

    <div style="margin-bottom: 6px;">
        <div class="row">
            <span>Slip Ref:</span>
            <span>#PAY-${payroll.id || 'TEMP'}</span>
        </div>
        <div class="row">
            <span>Month:</span>
            <span>${monthYearText}</span>
        </div>
        <div class="row">
            <span>Issue Date:</span>
            <span>${dateNow}</span>
        </div>
        <div class="dashed"></div>
        <div class="row">
            <span>Staff Name:</span>
            <span>${empName}</span>
        </div>
        <div class="row">
            <span>Designation:</span>
            <span class="uppercase">${empType}</span>
        </div>
        ${empPhone ? `
        <div class="row">
            <span>Contact:</span>
            <span>${empPhone}</span>
        </div>` : ''}
        <div class="row">
            <span>Status:</span>
            <span class="uppercase">${status}</span>
        </div>
    </div>

    <div class="solid"></div>
    <div class="section-title">EARNINGS (PKR)</div>
    <div>
        <div class="row">
            <span>Basic Salary:</span>
            <span>${window.formatPayrollMoney(baseSalary)}</span>
        </div>
        <div class="row">
            <span>Commissions:</span>
            <span>+${window.formatPayrollMoney(commission)}</span>
        </div>
        <div class="row">
            <span>Bonus & Allowances:</span>
            <span>+${window.formatPayrollMoney(bonus)}</span>
        </div>
    </div>

    <div class="dashed"></div>
    <div class="section-title">DEDUCTIONS (PKR)</div>
    <div>
        <div class="row">
            <span>Leaves (${takenLeaves}/${allowedLeaves} d):</span>
            <span>-${window.formatPayrollMoney(leaveDeduction)}</span>
        </div>
        <div class="row">
            <span>Advance Salary:</span>
            <span>-${window.formatPayrollMoney(advanceSalary)}</span>
        </div>
        ${advancesBreakdown}
        <div class="row">
            <span>Other Deductions:</span>
            <span>-${window.formatPayrollMoney(deductions)}</span>
        </div>
        ${deductionsBreakdown}
    </div>

    <div class="double" style="margin: 8px 0; padding: 5px 0;">
        <div style="display: flex; justify-content: space-between; font-size: 15px; font-weight: 900;">
            <span>NET SALARY PAYABLE:</span>
            <span>PKR ${window.formatPayrollMoney(netSalary)}</span>
        </div>
    </div>

    ${payroll.notes ? `
    <div style="font-size: 11px; margin-top: 4px;">
        <strong>Remarks:</strong> ${payroll.notes}
    </div>
    <div class="dashed"></div>` : ''}

    <div style="margin-top: 24px; font-size: 11px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-end;">
            <div class="center" style="width: 45%;">
                <div style="border-bottom: 1px solid #000; height: 22px; margin-bottom: 4px;"></div>
                <span>Staff Signature</span>
            </div>
            <div class="center" style="width: 45%;">
                <div style="border-bottom: 1px solid #000; height: 22px; margin-bottom: 4px;"></div>
                <span>Authorized Sign</span>
            </div>
        </div>
    </div>

    <div class="center" style="margin-top: 16px; font-size: 10px; color: #222;">
        *** Thank You - Jugnu's Saloon ***<br>
        Computer Generated Official Receipt
    </div>
</body>
</html>`;
};

window.buildPayrollA4PayslipDocument = function(payroll, brand) {
    if (!payroll) return '';
    brand = brand || {};
    const emp = payroll.employee || {};
    const empName = emp.name || payroll.name || 'Employee';
    const empType = (emp.emp_type || payroll.emp_type || 'Staff').toUpperCase();
    const empPhone = emp.phone_no1 || payroll.phone || 'N/A';
    const monthYear = payroll.month_year || '{{ $monthYear }}';
    const monthYearText = window.formatPayrollMonth(monthYear);
    const dateNow = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

    const baseSalary = parseFloat(payroll.base_salary) || 0;
    const takenLeaves = parseFloat(payroll.taken_leaves) || 0;
    const allowedLeaves = parseFloat(payroll.allowed_leaves) || 0;
    const leaveDeduction = parseFloat(payroll.leave_deduction) || 0;
    const commission = parseFloat(payroll.total_commission !== undefined ? payroll.total_commission : payroll.commission) || 0;
    const bonus = parseFloat(payroll.bonus) || 0;
    const deductions = parseFloat(payroll.deductions) || 0;
    const advanceSalary = parseFloat(payroll.advance_salary) || 0;
    const netSalary = parseFloat(payroll.net_salary) || Math.max(0, baseSalary - leaveDeduction + commission + bonus - deductions - advanceSalary);
    const status = (payroll.status || 'draft').toUpperCase();

    let deductionsBreakdown = '';
    if (payroll.deduction_items && payroll.deduction_items.length > 0) {
        payroll.deduction_items.forEach(d => {
            const dateStr = d.deduction_date ? ` (${d.deduction_date.substring(0, 10)})` : '';
            deductionsBreakdown += `
                <div style="font-size: 10px; color: #475569; margin-top: 2px; padding-left: 10px;">
                    • ${d.reason || 'Deduction'}${dateStr}: -PKR ${window.formatPayrollMoney(d.amount)}
                </div>
            `;
        });
    }

    let advancesBreakdown = '';
    if (payroll.advance_items && payroll.advance_items.length > 0) {
        payroll.advance_items.forEach(a => {
            const mode = a.payment_mode ? ` - ${a.payment_mode}` : '';
            const dateStr = a.advance_date ? ` (${a.advance_date.substring(0, 10)})` : '';
            advancesBreakdown += `
                <div style="font-size: 10px; color: #475569; margin-top: 2px; padding-left: 10px;">
                    • ${a.reason || 'Advance'}${dateStr}${mode}: -PKR ${window.formatPayrollMoney(a.amount)}
                </div>
            `;
        });
    }

    const slogan = brand.slogan ? `<div style="font-size: 11px; color: #475569; font-weight: 600; text-transform: uppercase;">${brand.slogan}</div>` : '';
    const address = brand.address ? `<div style="font-size: 11px; color: #475569;">📍 ${brand.address}</div>` : '';
    const phone = (brand.phone1 || brand.phone2) ? `<div style="font-size: 11px; color: #475569;">📞 Tel: ${brand.phone1 || ''} ${brand.phone2 ? '/ ' + brand.phone2 : ''}</div>` : '';

    return `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip - ${empName} - ${monthYearText}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 15mm;
        }
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #0f172a;
            background: #fff;
            padding: 20px;
            font-size: 12px;
            line-height: 1.4;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .brand-name {
            font-size: 22px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .doc-title-box {
            text-align: right;
        }
        .badge {
            background: #0f172a;
            color: #fff;
            padding: 4px 12px;
            font-weight: 900;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            margin-bottom: 4px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 12px 16px;
            margin-bottom: 15px;
        }
        .info-item-label {
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 700;
            color: #64748b;
        }
        .info-item-val {
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
        }
        table.slip-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table.slip-table th {
            background: #f1f5f9;
            color: #1e293b;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 11px;
            border: 1px solid #cbd5e1;
            padding: 8px 12px;
            text-align: left;
        }
        table.slip-table td {
            border: 1px solid #cbd5e1;
            padding: 8px 12px;
            font-size: 12px;
        }
        .net-row td {
            background: #0f172a !important;
            color: #fff !important;
            font-weight: 900 !important;
            padding: 10px 12px !important;
            font-size: 15px !important;
        }
        .net-amount {
            color: #34d399 !important;
            text-align: right;
        }
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
            padding-top: 15px;
        }
        .sig-box {
            width: 38%;
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #475569;
        }
        .sig-line {
            border-bottom: 1px solid #94a3b8;
            height: 35px;
            margin-bottom: 6px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="brand-name">${brand.name || 'JUGNU SALOON'}</div>
            ${slogan}
            ${address}
            ${phone}
        </div>
        <div class="doc-title-box">
            <div class="badge">OFFICIAL PAYSLIP</div>
            <div style="font-size: 11px; color: #475569;">Period: <strong>${monthYearText}</strong></div>
            <div style="font-size: 11px; color: #475569;">Slip Ref: <strong>#PAY-${payroll.id || 'NEW'}</strong></div>
        </div>
    </div>

    <div class="info-grid">
        <div>
            <div class="info-item-label">Employee Name</div>
            <div class="info-item-val">${empName}</div>
            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">Phone: ${empPhone}</div>
        </div>
        <div>
            <div class="info-item-label">Designation & Status</div>
            <div class="info-item-val" style="color: #7c3aed;">${empType}</div>
            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">Payment Status: <strong>${status}</strong></div>
        </div>
    </div>

    <table class="slip-table">
        <thead>
            <tr>
                <th>Earnings & Deductions Description</th>
                <th style="text-align: right; width: 140px;">Amount (PKR)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="font-weight: 700;">Basic Salary</td>
                <td style="text-align: right; font-weight: 800;">${window.formatPayrollMoney(baseSalary)}</td>
            </tr>
            <tr>
                <td>Leave Deduction (${takenLeaves} Taken / ${allowedLeaves} Allowed days)</td>
                <td style="text-align: right; font-weight: 700; color: #dc2626;">-${window.formatPayrollMoney(leaveDeduction)}</td>
            </tr>
            <tr>
                <td>Monthly Treatment Commissions</td>
                <td style="text-align: right; font-weight: 700; color: #7c3aed;">+${window.formatPayrollMoney(commission)}</td>
            </tr>
            <tr>
                <td>Performance Bonus & Allowances</td>
                <td style="text-align: right; font-weight: 700; color: #059669;">+${window.formatPayrollMoney(bonus)}</td>
            </tr>
            <tr>
                <td>
                    <div>Advance Salary Deductions</div>
                    ${advancesBreakdown}
                </td>
                <td style="text-align: right; font-weight: 700; color: #d97706;">-${window.formatPayrollMoney(advanceSalary)}</td>
            </tr>
            <tr>
                <td>
                    <div>Other Itemized Deductions</div>
                    ${deductionsBreakdown}
                </td>
                <td style="text-align: right; font-weight: 700; color: #dc2626;">-${window.formatPayrollMoney(deductions)}</td>
            </tr>
            <tr class="net-row">
                <td>NET PAYABLE SALARY</td>
                <td class="net-amount">PKR ${window.formatPayrollMoney(netSalary)}</td>
            </tr>
        </tbody>
    </table>

    ${payroll.notes ? `
    <div style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 8px 12px; margin-bottom: 20px; font-size: 11px; font-style: italic; color: #475569;">
        <strong>Remarks / Notes:</strong> ${payroll.notes}
    </div>` : ''}

    <div class="signatures">
        <div class="sig-box">
            <div class="sig-line"></div>
            <span>Employee Signature</span>
        </div>
        <div class="sig-box">
            <div class="sig-line"></div>
            <span>Authorized Administrator Signature</span>
        </div>
    </div>

    <div style="text-align: center; margin-top: 40px; font-size: 10px; color: #94a3b8;">
        This is a system-generated official salary slip issued by ${brand.name || 'Jugnu Saloon'}.
    </div>
</body>
</html>`;
};

window.buildPayrollMonthlyReportDocument = function(payrolls, batch, monthYear, brand) {
    brand = brand || {};
    let items = Array.isArray(payrolls) && payrolls.length > 0 ? payrolls : (Array.isArray(batch) ? batch : []);
    const monthYearText = window.formatPayrollMonth(monthYear);
    const dateNow = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

    let totalStaff = items.length;
    let totalBase = 0;
    let totalTakenLeaves = 0;
    let totalLeaveDeduct = 0;
    let totalComm = 0;
    let totalBonus = 0;
    let totalDeductions = 0;
    let totalAdvance = 0;
    let totalNet = 0;

    let rowsHtml = '';
    items.forEach((p, idx) => {
        const emp = p.employee || {};
        const empName = emp.name || p.name || 'Employee';
        const empType = emp.emp_type || p.emp_type || 'Staff';
        const base = parseFloat(p.base_salary) || 0;
        const taken = parseFloat(p.taken_leaves) || 0;
        const allowed = parseFloat(p.allowed_leaves) || 0;
        const leaveDed = parseFloat(p.leave_deduction) || 0;
        const comm = parseFloat(p.total_commission !== undefined ? p.total_commission : p.commission) || 0;
        const bonus = parseFloat(p.bonus) || 0;
        const ded = parseFloat(p.deductions) || 0;
        const adv = parseFloat(p.advance_salary) || 0;
        const net = parseFloat(p.net_salary) || Math.max(0, base - leaveDed + comm + bonus - ded - adv);
        const status = (p.status || 'draft').toUpperCase();

        totalBase += base;
        totalTakenLeaves += taken;
        totalLeaveDeduct += leaveDed;
        totalComm += comm;
        totalBonus += bonus;
        totalDeductions += ded;
        totalAdvance += adv;
        totalNet += net;

        const statusClass = status === 'PAID' ? 'status-paid' : (status === 'APPROVED' ? 'status-approved' : 'status-draft');

        rowsHtml += `
            <tr>
                <td style="text-align: center; font-weight: bold;">${idx + 1}</td>
                <td>
                    <div style="font-weight: 800; color: #0f172a;">${empName}</div>
                    <div style="font-size: 8px; color: #64748b; text-transform: uppercase;">${empType}</div>
                </td>
                <td style="text-align: right; font-weight: 600;">${window.formatPayrollMoney(base)}</td>
                <td style="text-align: center;">${taken} / ${allowed}</td>
                <td style="text-align: right; color: #dc2626;">-${window.formatPayrollMoney(leaveDed)}</td>
                <td style="text-align: right; color: #7c3aed; font-weight: 600;">+${window.formatPayrollMoney(comm)}</td>
                <td style="text-align: right; color: #059669;">+${window.formatPayrollMoney(bonus)}</td>
                <td style="text-align: right; color: #dc2626;">-${window.formatPayrollMoney(ded)}</td>
                <td style="text-align: right; color: #d97706; font-weight: 600;">-${window.formatPayrollMoney(adv)}</td>
                <td style="text-align: right; font-weight: 900; color: #047857; font-size: 11px;">${window.formatPayrollMoney(net)}</td>
                <td style="text-align: center;"><span class="status-badge ${statusClass}">${status}</span></td>
                <td style="border-bottom: 1px dashed #cbd5e1; width: 100px;"></td>
            </tr>
        `;
    });

    const slogan = brand.slogan ? `<div class="brand-subtitle">${brand.slogan}</div>` : '';
    const address = brand.address ? `<span style="margin-right: 12px;">📍 ${brand.address}</span>` : '';
    const phone = (brand.phone1 || brand.phone2) ? `<span>📞 ${brand.phone1 || ''} ${brand.phone2 ? '/ ' + brand.phone2 : ''}</span>` : '';

    return `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Monthly Payroll Report - ${monthYearText}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm 10mm;
        }
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #1e293b;
            background: #fff;
            padding: 10px;
            font-size: 11px;
            line-height: 1.3;
        }
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        .brand-title {
            font-size: 18px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0f172a;
        }
        .brand-subtitle {
            font-size: 9px;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 1px;
        }
        .brand-contact {
            font-size: 9px;
            color: #475569;
            margin-top: 2px;
        }
        .report-badge {
            background: #0f172a;
            color: #fff;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            margin-bottom: 3px;
        }
        .report-meta {
            text-align: right;
            font-size: 9px;
            color: #64748b;
        }
        .report-meta strong {
            color: #0f172a;
        }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 6px;
            margin-bottom: 10px;
        }
        .kpi-card {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 5px 8px;
        }
        .kpi-label {
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 1px;
        }
        .kpi-value {
            font-size: 12px;
            font-weight: 900;
            color: #0f172a;
        }
        .kpi-card.highlight {
            background: #ecfdf5;
            border-color: #10b981;
        }
        .kpi-card.highlight .kpi-value {
            color: #047857;
        }
        table.payroll-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin-bottom: 12px;
        }
        table.payroll-table th {
            background: #f1f5f9;
            color: #334155;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 9px;
            border: 1px solid #cbd5e1;
            padding: 5px 4px;
            text-align: left;
        }
        table.payroll-table td {
            border: 1px solid #cbd5e1;
            padding: 4px 5px;
            vertical-align: middle;
        }
        table.payroll-table tr:nth-child(even) {
            background: #f8fafc;
        }
        table.payroll-table tfoot td {
            background: #0f172a;
            color: #fff;
            font-weight: 800;
            border: 1px solid #0f172a;
            padding: 5px 5px;
        }
        .status-badge {
            display: inline-block;
            padding: 1px 4px;
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
            border-radius: 2px;
        }
        .status-paid { background: #dcfce7; color: #15803d; }
        .status-approved { background: #dbeafe; color: #1d4ed8; }
        .status-draft { background: #fef3c7; color: #b45309; }
        .signatures-grid {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            padding-top: 5px;
        }
        .signature-col {
            width: 28%;
            text-align: center;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            color: #475569;
        }
        .signature-line {
            border-bottom: 1px solid #94a3b8;
            height: 22px;
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
    <div class="header-container">
        <div>
            <div class="brand-title">${brand.name || 'JUGNU SALOON'}</div>
            ${slogan}
            <div class="brand-contact">${address} ${phone}</div>
        </div>
        <div style="text-align: right;">
            <div class="report-badge">STAFF MONTHLY PAYROLL REPORT</div>
            <div class="report-meta">
                <div>Period: <strong>${monthYearText}</strong></div>
                <div>Generated: <strong>${dateNow}</strong></div>
            </div>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label">Total Staff</div>
            <div class="kpi-value">${totalStaff} Employees</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Gross Base Salary</div>
            <div class="kpi-value">PKR ${window.formatPayrollMoney(totalBase)}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Total Commissions</div>
            <div class="kpi-value" style="color: #7c3aed;">+PKR ${window.formatPayrollMoney(totalComm)}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Total Bonuses</div>
            <div class="kpi-value" style="color: #059669;">+PKR ${window.formatPayrollMoney(totalBonus)}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Deductions & Advances</div>
            <div class="kpi-value" style="color: #dc2626;">-PKR ${window.formatPayrollMoney(totalDeductions + totalAdvance + totalLeaveDeduct)}</div>
        </div>
        <div class="kpi-card highlight">
            <div class="kpi-label" style="color: #047857;">Total Net Payout</div>
            <div class="kpi-value">PKR ${window.formatPayrollMoney(totalNet)}</div>
        </div>
    </div>

    <table class="payroll-table">
        <thead>
            <tr>
                <th style="width: 25px; text-align: center;">#</th>
                <th>Employee / Role</th>
                <th style="text-align: right;">Base (PKR)</th>
                <th style="text-align: center;">Leaves</th>
                <th style="text-align: right;">Leave Ded.</th>
                <th style="text-align: right;">Comm.</th>
                <th style="text-align: right;">Bonus</th>
                <th style="text-align: right;">Deduct.</th>
                <th style="text-align: right;">Advance</th>
                <th style="text-align: right; color: #047857;">Net Payout</th>
                <th style="text-align: center; width: 60px;">Status</th>
                <th style="text-align: center; width: 100px;">Signature / Remarks</th>
            </tr>
        </thead>
        <tbody>
            ${rowsHtml}
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" style="text-align: right; text-transform: uppercase;">Grand Totals:</td>
                <td style="text-align: right;">${window.formatPayrollMoney(totalBase)}</td>
                <td style="text-align: center;">${totalTakenLeaves} d</td>
                <td style="text-align: right;">-${window.formatPayrollMoney(totalLeaveDeduct)}</td>
                <td style="text-align: right;">+${window.formatPayrollMoney(totalComm)}</td>
                <td style="text-align: right;">+${window.formatPayrollMoney(totalBonus)}</td>
                <td style="text-align: right;">-${window.formatPayrollMoney(totalDeductions)}</td>
                <td style="text-align: right;">-${window.formatPayrollMoney(totalAdvance)}</td>
                <td style="text-align: right; font-size: 11px; color: #34d399;">PKR ${window.formatPayrollMoney(totalNet)}</td>
                <td colspan="2" style="text-align: center; font-size: 8px;">DISBURSEMENT TOTAL</td>
            </tr>
        </tfoot>
    </table>

    <div class="signatures-grid">
        <div class="signature-col">
            <div class="signature-line"></div>
            <span>Prepared By (HR / Accountant)</span>
        </div>
        <div class="signature-col">
            <div class="signature-line"></div>
            <span>Checked & Verified By</span>
        </div>
        <div class="signature-col">
            <div class="signature-line"></div>
            <span>Authorized Administrator Signature</span>
        </div>
    </div>
</body>
</html>`;
};

window.buildEmployeeDeductionBreakdownDocument = function(deductions, empName, monthYear, brand, empPhone) {
    const list = Array.isArray(deductions) ? deductions : [];
    const totalAmount = list.reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0);
    
    let periodLabel = window.formatPayrollMonth ? window.formatPayrollMonth(monthYear) : (monthYear || 'Current Month');
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
            const amt = window.formatPayrollMoney ? window.formatPayrollMoney(item.amount) : Number(item.amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
                                PKR ${window.formatPayrollMoney ? window.formatPayrollMoney(reasonSummary[r].total) : Number(reasonSummary[r].total).toLocaleString()}
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
                PKR ${window.formatPayrollMoney ? window.formatPayrollMoney(totalAmount) : Number(totalAmount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
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
                    PKR ${window.formatPayrollMoney ? window.formatPayrollMoney(totalAmount) : Number(totalAmount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
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

window.buildSalaryDeductionsReportDocument = function(deductions, monthYear, brand, employeeName) {
    const list = Array.isArray(deductions) ? deductions : [];
    const totalAmount = list.reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0);
    const periodLabel = window.formatPayrollMonth ? window.formatPayrollMonth(monthYear) : (monthYear || 'Current Month');
    const now = new Date();
    const printedAt = now.toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

    let rowsHtml = '';
    if (list.length === 0) {
        rowsHtml = `<tr><td colspan="6" style="padding: 24px; text-align: center; color: #94a3b8; font-style: italic;">No deduction records found for this employee.</td></tr>`;
    } else {
        list.forEach((item, idx) => {
            const dateStr = item.deduction_date ? String(item.deduction_date).substring(0, 10) : '—';
            const reason = item.reason || '—';
            const amt = window.formatPayrollMoney ? window.formatPayrollMoney(item.amount) : Number(item.amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
            const notes = item.notes ? item.notes : '—';
            const bgClass = idx % 2 === 0 ? '#ffffff' : '#f8fafc';

            rowsHtml += `
                <tr style="background-color: ${bgClass}; border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 9px 10px; font-weight: 700; color: #64748b; font-size: 11px; text-align: center;">${idx + 1}</td>
                    <td style="padding: 9px 10px; font-weight: 700; color: #1e293b; font-size: 11px; white-space: nowrap;">${dateStr}</td>
                    <td style="padding: 9px 10px; font-size: 11px;">
                        <span style="display: inline-block; background-color: #ffe4e6; color: #9f1239; font-weight: 700; padding: 2px 8px; border-radius: 2px; font-size: 10.5px; border: 1px solid #fecdd3;">
                            ${reason}
                        </span>
                    </td>
                    <td style="padding: 9px 10px; font-weight: 800; color: #be123c; font-size: 11.5px; text-align: right; white-space: nowrap;">
                        PKR ${amt}
                    </td>
                    <td style="padding: 9px 10px; font-size: 10.5px; color: #475569;">
                        ${notes}
                    </td>
                    <td style="padding: 9px 10px; font-size: 10px; color: #64748b;">
                        Approved Deduction
                    </td>
                </tr>
            `;
        });
    }

    return `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Salary Deductions - ${employeeName || 'Staff'}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 14mm; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #0f172a; margin: 0; padding: 0; font-size: 12px; }
        .header-table { width: 100%; border-collapse: collapse; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 16px; }
        .report-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .report-table th { background-color: #0f172a; color: #ffffff; font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 9px 10px; border: 1px solid #0f172a; }
        .report-table td { border: 1px solid #e2e8f0; padding: 8px 10px; }
        .signatures { margin-top: 48px; display: flex; justify-content: space-between; page-break-inside: avoid; }
        .sign-block { width: 30%; border-top: 1px dashed #475569; padding-top: 6px; text-align: center; font-size: 11px; font-weight: 700; color: #334155; }
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
                    ${brand.address ? brand.address : ''} ${brand.phone1 ? ` | Tel: ${brand.phone1}` : ''}
                </div>
            </td>
            <td style="width: 35%; text-align: right;">
                <div style="display: inline-block; background: #0f172a; color: #fbbf24; padding: 6px 14px; font-weight: 900; font-size: 13px; text-transform: uppercase;">
                    DEDUCTION STATEMENT
                </div>
                <div style="font-size: 11px; color: #64748b; margin-top: 4px;">
                    Printed: ${printedAt}
                </div>
            </td>
        </tr>
    </table>

    <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 4px; margin-bottom: 16px; display: flex; justify-content: space-between;">
        <div>
            <span style="font-size: 10px; font-weight: 800; text-transform: uppercase; color: #64748b;">Employee Name:</span>
            <strong style="font-size: 14px; color: #0f172a; margin-left: 6px;">${employeeName || 'Staff Member'}</strong>
        </div>
        <div>
            <span style="font-size: 10px; font-weight: 800; text-transform: uppercase; color: #64748b;">Month Period:</span>
            <strong style="font-size: 13px; color: #0f172a; margin-left: 6px;">${periodLabel}</strong>
        </div>
        <div>
            <span style="font-size: 10px; font-weight: 800; text-transform: uppercase; color: #64748b;">Total Deduction:</span>
            <strong style="font-size: 14px; color: #be123c; margin-left: 6px;">PKR ${window.formatPayrollMoney ? window.formatPayrollMoney(totalAmount) : Number(totalAmount).toLocaleString()}</strong>
        </div>
    </div>

    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 35px; text-align: center;">#</th>
                <th style="width: 90px;">Date</th>
                <th>Deduction Reason</th>
                <th style="width: 120px; text-align: right;">Amount (PKR)</th>
                <th>Remarks / Details</th>
                <th style="width: 120px;">Status</th>
            </tr>
        </thead>
        <tbody>
            ${rowsHtml}
        </tbody>
        <tfoot>
            <tr style="background-color: #0f172a; color: #ffffff; font-weight: 900;">
                <td colspan="3" style="padding: 10px; text-transform: uppercase; font-size: 12px;">Total Deductions Subtracted from Payroll</td>
                <td style="padding: 10px; font-size: 13px; text-align: right; color: #fbbf24;">
                    PKR ${window.formatPayrollMoney ? window.formatPayrollMoney(totalAmount) : Number(totalAmount).toLocaleString()}
                </td>
                <td colspan="2" style="padding: 10px; font-size: 10px; color: #cbd5e1;">
                    ${list.length} item(s) logged
                </td>
            </tr>
        </tfoot>
    </table>

    <div class="signatures">
        <div class="sign-block">Employee Signature / Acknowledgment</div>
        <div class="sign-block">Accounts Officer</div>
        <div class="sign-block">Branch Manager / Approval</div>
    </div>
</body>
</html>`;
};
</script>
@endsection
