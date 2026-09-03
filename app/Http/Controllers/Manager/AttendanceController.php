<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\StaffAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    /**
     * Display staff attendance daily sheet, monthly logs, and KPI metrics.
     */
    public function index(Request $request)
    {
        $selectedDate = $request->input('date', date('Y-m-d'));
        $monthYear = $request->input('month_year', date('Y-m'));
        $employeeId = $request->input('employee_id');
        $status = $request->input('status');
        $activeTab = $request->input('tab', 'daily');

        // Fetch Employee Accounts
        $employees = Account::whereHas('category', function ($q) {
            $q->where('title', 'like', '%Employee%')
              ->orWhere('title', 'like', '%Staff%')
              ->orWhere('title', 'like', '%Barber%')
              ->orWhere('title', 'like', '%Stylist%');
        })->orderBy('name')->get();

        if ($employees->isEmpty()) {
            $employees = Account::whereHas('category', function ($q) {
                $q->where('title', 'not like', '%Supplier%')
                  ->where('title', 'not like', '%Vendor%');
            })->orWhereDoesntHave('category')->orderBy('name')->get();
        }

        // Daily Sheet Attendances for the selected date
        $dailyAttendances = StaffAttendance::whereDate('date', $selectedDate)
            ->get()
            ->keyBy('account_id');

        // Pre-build daily data array for all employees on selected date
        $dailySheet = [];
        foreach ($employees as $emp) {
            $att = $dailyAttendances->get($emp->id);
            $dailySheet[] = [
                'employee_id' => $emp->id,
                'name' => $emp->name,
                'emp_type' => $emp->emp_type ?? 'Staff',
                'phone' => $emp->phone_no1,
                'attendance_id' => $att ? $att->id : null,
                'status' => $att ? $att->status : 'present',
                'check_in' => $att && $att->check_in ? substr($att->check_in, 0, 5) : '09:00',
                'check_out' => $att && $att->check_out ? substr($att->check_out, 0, 5) : '18:00',
                'notes' => $att ? $att->notes : '',
            ];
        }

        // Selected Date KPI Statistics
        $totalStaff = count($employees);
        $presentCount = StaffAttendance::whereDate('date', $selectedDate)->where('status', 'present')->count();
        $absentCount = StaffAttendance::whereDate('date', $selectedDate)->where('status', 'absent')->count();
        $lateCount = StaffAttendance::whereDate('date', $selectedDate)->where('status', 'late')->count();
        $halfDayCount = StaffAttendance::whereDate('date', $selectedDate)->where('status', 'half_day')->count();
        $leaveCount = StaffAttendance::whereDate('date', $selectedDate)->where('status', 'leave')->count();

        // Monthly Logs & History Query
        $parts = explode('-', $monthYear);
        $year = (int) ($parts[0] ?? date('Y'));
        $month = (int) ($parts[1] ?? date('m'));

        $logsQuery = StaffAttendance::with(['employee', 'creator'])
            ->whereYear('date', $year)
            ->whereMonth('date', $month);

        if ($employeeId) {
            $logsQuery->where('account_id', $employeeId);
        }

        if ($status) {
            $logsQuery->where('status', $status);
        }

        $monthlyLogs = $logsQuery->orderBy('date', 'desc')->orderBy('id', 'desc')->paginate(20)->withQueryString();

        return view('manager.attendance.index', compact(
            'employees',
            'dailySheet',
            'selectedDate',
            'monthYear',
            'employeeId',
            'status',
            'activeTab',
            'totalStaff',
            'presentCount',
            'absentCount',
            'lateCount',
            'halfDayCount',
            'leaveCount',
            'monthlyLogs'
        ));
    }

    /**
     * Bulk save or update daily staff attendance sheet.
     */
    public function bulkStore(Request $request)
    {
        if ($request->isMethod('get')) {
            return redirect()->route('manager.attendance.index', $request->query());
        }

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'attendances' => ['required', 'array'],
            'attendances.*.account_id' => ['required', 'exists:accounts,id'],
            'attendances.*.status' => ['required', 'in:present,absent,half_day,leave,late,holiday'],
            'attendances.*.check_in' => ['nullable', 'string'],
            'attendances.*.check_out' => ['nullable', 'string'],
            'attendances.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $date = $validated['date'];
        $userId = Auth::id();

        DB::transaction(function () use ($validated, $date, $userId) {
            foreach ($validated['attendances'] as $attData) {
                StaffAttendance::updateOrCreate(
                    [
                        'account_id' => $attData['account_id'],
                        'date' => $date,
                    ],
                    [
                        'status' => $attData['status'],
                        'check_in' => !empty($attData['check_in']) ? $attData['check_in'] : null,
                        'check_out' => !empty($attData['check_out']) ? $attData['check_out'] : null,
                        'notes' => $attData['notes'] ?? null,
                        'created_by' => $userId,
                    ]
                );
            }
        });

        return redirect()->route('manager.attendance.index', ['date' => $date, 'tab' => 'daily'])
            ->with('success', "Staff attendance for {$date} saved successfully!");
    }

    /**
     * Store or update a single employee attendance record.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'date' => ['required', 'date'],
            'status' => ['required', 'in:present,absent,half_day,leave,late,holiday'],
            'check_in' => ['nullable', 'string'],
            'check_out' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        StaffAttendance::updateOrCreate(
            [
                'account_id' => $validated['account_id'],
                'date' => $validated['date'],
            ],
            [
                'status' => $validated['status'],
                'check_in' => !empty($validated['check_in']) ? $validated['check_in'] : null,
                'check_out' => !empty($validated['check_out']) ? $validated['check_out'] : null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]
        );

        return redirect()->back()->with('success', 'Staff attendance updated successfully!');
    }

    /**
     * Delete an attendance record.
     */
    public function destroy(StaffAttendance $attendance)
    {
        $attendance->delete();

        return redirect()->back()->with('success', 'Attendance record deleted successfully!');
    }
}
