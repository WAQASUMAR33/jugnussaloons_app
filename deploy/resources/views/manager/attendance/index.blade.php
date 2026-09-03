@extends('layouts.material')

@section('title', 'Staff Attendance Management')

@section('content')
<div x-data="{
    activeTab: '{{ $activeTab }}',
    selectedDate: '{{ $selectedDate }}',
    dailySheet: {{ json_encode($dailySheet) }},
    
    markAll(statusValue) {
        this.dailySheet.forEach(item => {
            item.status = statusValue;
        });
    },

    setAllCheckIn(timeVal) {
        this.dailySheet.forEach(item => {
            if (item.status === 'present' || item.status === 'late' || item.status === 'half_day') {
                item.check_in = timeVal;
            }
        });
    }
}" class="space-y-6">

    <!-- Top Action Bar & Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-none border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold text-slate-400 uppercase tracking-wider">
                <span>Staff & HR Operations</span>
                <span>•</span>
                <span class="text-indigo-600">Finance & Accounts</span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight heading-font mt-0.5">
                Staff Attendance Management
            </h1>
        </div>

        <!-- Mode Switcher Tabs -->
        <div class="inline-flex p-1 bg-slate-100 rounded-none border border-slate-200 shrink-0">
            <button type="button" @click="activeTab = 'daily'" 
                    :class="activeTab === 'daily' ? 'bg-white text-indigo-700 shadow-sm font-black' : 'text-slate-600 hover:text-slate-900 font-bold'"
                    class="px-4 py-2 text-xs rounded-none transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                <span>Daily Attendance Sheet</span>
            </button>
            <button type="button" @click="activeTab = 'logs'" 
                    :class="activeTab === 'logs' ? 'bg-white text-indigo-700 shadow-sm font-black' : 'text-slate-600 hover:text-slate-900 font-bold'"
                    class="px-4 py-2 text-xs rounded-none transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <span>Monthly Attendance Logs</span>
            </button>
        </div>
    </div>

    <!-- Top KPI Summary Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        <!-- Total Staff -->
        <div class="bg-white p-4 border border-slate-200 rounded-none shadow-sm flex flex-col justify-between">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Staff</span>
            <div class="flex items-baseline justify-between mt-1">
                <span class="text-2xl font-black text-slate-900">{{ $totalStaff }}</span>
                <span class="text-xs font-bold text-slate-400">Members</span>
            </div>
        </div>

        <!-- Present Today -->
        <div class="bg-white p-4 border border-emerald-200 rounded-none shadow-sm bg-emerald-50/20 flex flex-col justify-between">
            <span class="text-[10px] font-black text-emerald-800 uppercase tracking-wider">Present</span>
            <div class="flex items-baseline justify-between mt-1">
                <span class="text-2xl font-black text-emerald-700">{{ $presentCount }}</span>
                <span class="text-xs font-bold text-emerald-600">On Duty</span>
            </div>
        </div>

        <!-- Absent Today -->
        <div class="bg-white p-4 border border-rose-200 rounded-none shadow-sm bg-rose-50/20 flex flex-col justify-between">
            <span class="text-[10px] font-black text-rose-800 uppercase tracking-wider">Absent</span>
            <div class="flex items-baseline justify-between mt-1">
                <span class="text-2xl font-black text-rose-700">{{ $absentCount }}</span>
                <span class="text-xs font-bold text-rose-600">Unexcused</span>
            </div>
        </div>

        <!-- Late / Half Day -->
        <div class="bg-white p-4 border border-amber-200 rounded-none shadow-sm bg-amber-50/20 flex flex-col justify-between">
            <span class="text-[10px] font-black text-amber-800 uppercase tracking-wider">Late / Half Day</span>
            <div class="flex items-baseline justify-between mt-1">
                <span class="text-2xl font-black text-amber-700">{{ $lateCount + $halfDayCount }}</span>
                <span class="text-xs font-bold text-amber-600">Partial</span>
            </div>
        </div>

        <!-- Approved Leave -->
        <div class="bg-white p-4 border border-indigo-200 rounded-none shadow-sm bg-indigo-50/20 flex flex-col justify-between col-span-2 sm:col-span-1">
            <span class="text-[10px] font-black text-indigo-800 uppercase tracking-wider">On Leave</span>
            <div class="flex items-baseline justify-between mt-1">
                <span class="text-2xl font-black text-indigo-700">{{ $leaveCount }}</span>
                <span class="text-xs font-bold text-indigo-600">Approved</span>
            </div>
        </div>
    </div>

    <!-- TAB 1: DAILY ATTENDANCE SHEET -->
    <div x-show="activeTab === 'daily'" class="space-y-4">
        
        <!-- Date Selector & Quick Bulk Actions Bar -->
        <div class="bg-white p-4 border border-slate-200 rounded-none shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            
            <!-- Date Filter Form -->
            <form method="GET" action="{{ route('manager.attendance.index') }}" class="flex items-center gap-2">
                <input type="hidden" name="tab" value="daily">
                <label class="text-xs font-bold text-slate-700 whitespace-nowrap">Attendance Date:</label>
                <input type="date" name="date" value="{{ $selectedDate }}" onchange="this.form.submit()"
                       class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-none text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600">
                <a href="{{ route('manager.attendance.index', ['date' => date('Y-m-d'), 'tab' => 'daily']) }}" 
                   class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-none border border-slate-200 transition-colors">
                    Today
                </a>
            </form>

            <!-- 1-Click Quick Bulk Actions -->
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider mr-1">Quick Apply:</span>
                <button type="button" @click="markAll('present')" 
                        class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 font-black text-xs rounded-none border border-emerald-300 transition-colors flex items-center gap-1.5">
                    <span>⚡ Mark All Present</span>
                </button>
                <button type="button" @click="markAll('absent')" 
                        class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-800 font-black text-xs rounded-none border border-rose-300 transition-colors flex items-center gap-1.5">
                    <span>Mark All Absent</span>
                </button>
            </div>
        </div>

        <!-- Attendance Sheet Form & Table -->
        <form method="POST" action="{{ route('manager.attendance.bulkStore') }}">
            @csrf
            <input type="hidden" name="date" value="{{ $selectedDate }}">

            <div class="bg-white border border-slate-200 rounded-none shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-400 text-[10px] font-black uppercase tracking-wider">
                                <th class="py-3.5 px-4 w-12 text-center">#</th>
                                <th class="py-3.5 px-4">Staff Member</th>
                                <th class="py-3.5 px-4">Designation</th>
                                <th class="py-3.5 px-4 min-w-[340px]">Attendance Status</th>
                                <th class="py-3.5 px-4 w-32">Check In</th>
                                <th class="py-3.5 px-4 w-32">Check Out</th>
                                <th class="py-3.5 px-4 min-w-[200px]">Remarks / Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                            <template x-for="(item, index) in dailySheet" :key="item.employee_id">
                                <tr class="hover:bg-slate-50/60 transition-colors">
                                    
                                    <!-- Hidden Inputs for Form Submission -->
                                    <input type="hidden" :name="'attendances[' + index + '][account_id]'" :value="item.employee_id">
                                    <input type="hidden" :name="'attendances[' + index + '][status]'" :value="item.status">
                                    <input type="hidden" :name="'attendances[' + index + '][check_in]'" :value="item.check_in">
                                    <input type="hidden" :name="'attendances[' + index + '][check_out]'" :value="item.check_out">
                                    <input type="hidden" :name="'attendances[' + index + '][notes]'" :value="item.notes">

                                    <td class="py-3.5 px-4 text-center font-bold text-slate-400" x-text="index + 1"></td>
                                    
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-none bg-indigo-600 text-white font-black text-xs flex items-center justify-center shrink-0">
                                                <span x-text="item.name.substring(0, 2).toUpperCase()"></span>
                                            </div>
                                            <div>
                                                <h4 class="font-extrabold text-slate-900" x-text="item.name">Staff Name</h4>
                                                <p class="text-[10px] text-slate-400 font-normal" x-text="item.phone || 'No phone'"></p>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="py-3.5 px-4">
                                        <span class="px-2 py-0.5 bg-slate-100 text-slate-700 font-bold text-[11px] rounded-none border border-slate-200" x-text="item.emp_type">
                                            Staff
                                        </span>
                                    </td>

                                    <!-- Status Selector Buttons -->
                                    <td class="py-3.5 px-4">
                                        <div class="inline-flex rounded-none border border-slate-200 p-0.5 bg-slate-100 gap-1 flex-wrap">
                                            <!-- Present -->
                                            <button type="button" @click="item.status = 'present'" 
                                                    :class="item.status === 'present' ? 'bg-emerald-600 text-white font-black shadow-xs' : 'text-slate-600 hover:bg-slate-200 font-bold'"
                                                    class="px-2.5 py-1 text-[11px] rounded-none transition-all">
                                                ✓ Present
                                            </button>
                                            <!-- Late -->
                                            <button type="button" @click="item.status = 'late'" 
                                                    :class="item.status === 'late' ? 'bg-amber-500 text-white font-black shadow-xs' : 'text-slate-600 hover:bg-slate-200 font-bold'"
                                                    class="px-2.5 py-1 text-[11px] rounded-none transition-all">
                                                ⏳ Late
                                            </button>
                                            <!-- Half Day -->
                                            <button type="button" @click="item.status = 'half_day'" 
                                                    :class="item.status === 'half_day' ? 'bg-indigo-600 text-white font-black shadow-xs' : 'text-slate-600 hover:bg-slate-200 font-bold'"
                                                    class="px-2.5 py-1 text-[11px] rounded-none transition-all">
                                                ½ Half Day
                                            </button>
                                            <!-- Leave -->
                                            <button type="button" @click="item.status = 'leave'" 
                                                    :class="item.status === 'leave' ? 'bg-purple-600 text-white font-black shadow-xs' : 'text-slate-600 hover:bg-slate-200 font-bold'"
                                                    class="px-2.5 py-1 text-[11px] rounded-none transition-all">
                                                🏖️ Leave
                                            </button>
                                            <!-- Absent -->
                                            <button type="button" @click="item.status = 'absent'" 
                                                    :class="item.status === 'absent' ? 'bg-rose-600 text-white font-black shadow-xs' : 'text-slate-600 hover:bg-slate-200 font-bold'"
                                                    class="px-2.5 py-1 text-[11px] rounded-none transition-all">
                                                ✕ Absent
                                            </button>
                                        </div>
                                    </td>

                                    <!-- Check In Time -->
                                    <td class="py-3.5 px-4">
                                        <input type="time" x-model="item.check_in" 
                                               :disabled="item.status === 'absent' || item.status === 'leave'"
                                               class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-none text-xs font-bold text-slate-800 disabled:opacity-40 focus:ring-2 focus:ring-indigo-600">
                                    </td>

                                    <!-- Check Out Time -->
                                    <td class="py-3.5 px-4">
                                        <input type="time" x-model="item.check_out" 
                                               :disabled="item.status === 'absent' || item.status === 'leave'"
                                               class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-none text-xs font-bold text-slate-800 disabled:opacity-40 focus:ring-2 focus:ring-indigo-600">
                                    </td>

                                    <!-- Remarks / Notes -->
                                    <td class="py-3.5 px-4">
                                        <input type="text" x-model="item.notes" placeholder="Remarks (e.g. sick leave, late 20m)..." 
                                               class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-none text-xs font-medium text-slate-800 focus:ring-2 focus:ring-indigo-600">
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="dailySheet.length === 0">
                                <td colspan="7" class="py-8 text-center text-slate-400 font-semibold">
                                    No staff / employee accounts found in the system.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer Save Action Bar -->
                <div class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                    <p class="text-xs font-bold text-slate-500">
                        Attendance will be saved for date: <span class="text-slate-900 font-black" x-text="selectedDate"></span>
                    </p>
                    <button type="submit" 
                            class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs rounded-none shadow-sm flex items-center gap-2 transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        <span>Save & Update Attendance Sheet</span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- TAB 2: MONTHLY ATTENDANCE LOGS & HISTORY -->
    <div x-show="activeTab === 'logs'" class="space-y-4" x-cloak>
        
        <!-- Filter Bar -->
        <div class="bg-white p-4 border border-slate-200 rounded-none shadow-sm">
            <form method="GET" action="{{ route('manager.attendance.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <input type="hidden" name="tab" value="logs">

                <!-- Month Picker -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Select Month</label>
                    <input type="month" name="month_year" value="{{ $monthYear }}" 
                           class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-none text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-600">
                </div>

                <!-- Staff Member Filter -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Staff Member</label>
                    <select name="employee_id" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-none text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-600">
                        <option value="">All Staff Members</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}" {{ $employeeId == $emp->id ? 'selected' : '' }}>
                                {{ $emp->name }} ({{ $emp->emp_type ?? 'Staff' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Attendance Status</label>
                    <select name="status" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-none text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-600">
                        <option value="">All Statuses</option>
                        <option value="present" {{ $status == 'present' ? 'selected' : '' }}>Present</option>
                        <option value="late" {{ $status == 'late' ? 'selected' : '' }}>Late</option>
                        <option value="half_day" {{ $status == 'half_day' ? 'selected' : '' }}>Half Day</option>
                        <option value="leave" {{ $status == 'leave' ? 'selected' : '' }}>On Leave</option>
                        <option value="absent" {{ $status == 'absent' ? 'selected' : '' }}>Absent</option>
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 py-2 px-4 bg-indigo-600 text-white font-bold text-xs rounded-none hover:bg-indigo-700 transition-colors shadow-xs">
                        Filter Logs
                    </button>
                    @if($employeeId || $status || $monthYear !== date('Y-m'))
                        <a href="{{ route('manager.attendance.index', ['tab' => 'logs']) }}" class="px-3 py-2 bg-slate-100 text-slate-600 font-bold text-xs rounded-none hover:bg-slate-200 border border-slate-200">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Monthly Logs Table -->
        <div class="bg-white border border-slate-200 rounded-none shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-400 text-[10px] font-black uppercase tracking-wider">
                            <th class="py-3.5 px-4">Date</th>
                            <th class="py-3.5 px-4">Staff Member</th>
                            <th class="py-3.5 px-4">Designation</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-4">Check In</th>
                            <th class="py-3.5 px-4">Check Out</th>
                            <th class="py-3.5 px-4">Remarks</th>
                            <th class="py-3.5 px-4">Logged By</th>
                            <th class="py-3.5 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                        @forelse($monthlyLogs as $log)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-3.5 px-4 font-black text-slate-900">
                                {{ $log->date ? $log->date->format('M d, Y') : '—' }}
                            </td>
                            <td class="py-3.5 px-4 font-bold text-slate-900">
                                {{ $log->employee->name ?? 'Staff' }}
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2 py-0.5 bg-slate-100 text-slate-700 font-bold text-[11px] rounded-none border border-slate-200">
                                    {{ $log->employee->emp_type ?? 'Staff' }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4">
                                @if($log->status === 'present')
                                    <span class="px-2.5 py-1 text-[10px] font-black uppercase bg-emerald-100 text-emerald-900 border border-emerald-300">
                                        ✓ Present
                                    </span>
                                @elseif($log->status === 'late')
                                    <span class="px-2.5 py-1 text-[10px] font-black uppercase bg-amber-100 text-amber-900 border border-amber-300">
                                        ⏳ Late
                                    </span>
                                @elseif($log->status === 'half_day')
                                    <span class="px-2.5 py-1 text-[10px] font-black uppercase bg-indigo-100 text-indigo-900 border border-indigo-300">
                                        ½ Half Day
                                    </span>
                                @elseif($log->status === 'leave')
                                    <span class="px-2.5 py-1 text-[10px] font-black uppercase bg-purple-100 text-purple-900 border border-purple-300">
                                        🏖️ Leave
                                    </span>
                                @elseif($log->status === 'absent')
                                    <span class="px-2.5 py-1 text-[10px] font-black uppercase bg-rose-100 text-rose-900 border border-rose-300">
                                        ✕ Absent
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 text-[10px] font-black uppercase bg-slate-100 text-slate-700 border border-slate-200">
                                        {{ $log->status }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 font-mono font-bold text-slate-800">
                                {{ $log->check_in ? substr($log->check_in, 0, 5) : '—' }}
                            </td>
                            <td class="py-3.5 px-4 font-mono font-bold text-slate-800">
                                {{ $log->check_out ? substr($log->check_out, 0, 5) : '—' }}
                            </td>
                            <td class="py-3.5 px-4 text-slate-600">
                                {{ $log->notes ?: '—' }}
                            </td>
                            <td class="py-3.5 px-4 text-[11px] text-slate-400">
                                {{ $log->creator->name ?? 'System' }}
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <form method="POST" action="{{ route('manager.attendance.destroy', $log->id) }}" onsubmit="return confirm('Delete this attendance entry?');" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 text-rose-400 hover:text-rose-600 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-slate-400 font-semibold">
                                No attendance records found for the selected month and filters.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($monthlyLogs->hasPages())
                <div class="p-4 border-t border-slate-200 bg-slate-50">
                    {{ $monthlyLogs->links() }}
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
