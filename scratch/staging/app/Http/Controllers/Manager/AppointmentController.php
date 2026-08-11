<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountLedger;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\SaloonService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    /**
     * Display service appointments listing & booking interface.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $date = $request->input('date');

        $query = Appointment::with(['customer', 'employee', 'items.service']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('booking_no', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('employee', function ($eq) use ($search) {
                      $eq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($date) {
            $query->whereDate('appointment_date', $date);
        }

        $appointments = $query->orderBy('appointment_date', 'desc')->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        // 1. Fetch Employee accounts strictly filtered to Employee/Staff category
        $employees = Account::whereHas('category', function ($q) {
            $q->where('title', 'like', '%Employee%')
              ->orWhere('title', 'like', '%Staff%');
        })->orderBy('name')->get();

        // Fallback: If no account is categorized as Employee yet, list all non-supplier accounts
        if ($employees->isEmpty()) {
            $employees = Account::whereHas('category', function ($q) {
                $q->where('title', 'not like', '%Supplier%')
                  ->where('title', 'not like', '%Vendor%');
            })->orWhereDoesntHave('category')->orderBy('name')->get();
        }

        // 2. Fetch Customer accounts
        $customers = Account::whereHas('category', function ($q) {
            $q->where('title', 'not like', '%Supplier%')
              ->where('title', 'not like', '%Vendor%');
        })->orWhereDoesntHave('category')->orderBy('name')->get();

        // 3. Fetch Saloon Services for the filterable services catalog
        $saloonServices = SaloonService::orderBy('title')->get();

        return view('manager.appointments.index', compact(
            'appointments', 
            'employees', 
            'customers', 
            'saloonServices', 
            'search', 
            'status', 
            'date'
        ));
    }

    /**
     * Store a newly created service appointment booking.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'employee_id' => ['required', 'exists:accounts,id'],
            'appointment_date' => ['required', 'date'],
            'start_time' => ['nullable', 'string'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'receipt_image' => ['nullable', 'file', 'image', 'max:5120'],
            'payment_mode' => ['nullable', 'string', 'in:Cash,Bank'],
            'extra_amount' => ['nullable', 'numeric', 'min:0'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['required', 'exists:services,id'],
        ]);

        $receiptPath = null;
        if ($request->hasFile('receipt_image')) {
            $file = $request->file('receipt_image');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/receipts'), $filename);
            $receiptPath = 'uploads/receipts/' . $filename;
        }

        DB::transaction(function () use ($validated, $receiptPath) {
            $customer = Account::findOrFail($validated['account_id']);
            $employee = Account::findOrFail($validated['employee_id']);
            $appointmentDate = $validated['appointment_date'];
            $paidAmount = (float) $validated['paid_amount'];
            $billDiscount = (float) ($validated['discount'] ?? 0);
            $paymentMode = $validated['payment_mode'] ?? 'Cash';
            $extraAmount = ($paymentMode === 'Bank') ? (float) ($validated['extra_amount'] ?? 0) : 0.00;

            // Auto Generate Booking Number
            $bookingNo = 'APT-' . date('Ym') . '-' . str_pad(Appointment::count() + 1, 4, '0', STR_PAD_LEFT);

            // Calculate service totals and commissions based on employee designation (Junior vs Senior)
            $services = SaloonService::whereIn('id', $validated['service_ids'])->get();
            $isSenior = strtolower($employee->emp_type ?? '') === 'senior';
            $grossTotal = 0;
            $totalCommission = 0;
            $serviceCommissions = [];

            foreach ($services as $srv) {
                $grossTotal += (float) $srv->discounted_price;
                $comm = $isSenior
                    ? (float) ($srv->senior_commission ?? $srv->commission ?? 0)
                    : (float) ($srv->junior_commission ?? $srv->commission ?? 0);
                $serviceCommissions[$srv->id] = $comm;
                $totalCommission += $comm;
            }

            $netAmount = max(0, round($grossTotal - $billDiscount + $extraAmount, 2));
            $balanceDue = round($netAmount - $paidAmount, 2);

            // 1. Create Appointment Record
            $appointment = Appointment::create([
                'booking_no' => $bookingNo,
                'account_id' => $customer->id,
                'employee_id' => $employee->id,
                'appointment_date' => $appointmentDate,
                'start_time' => $validated['start_time'] ?? null,
                'total_amount' => $grossTotal,
                'discount' => $billDiscount,
                'net_amount' => $netAmount,
                'paid_amount' => $paidAmount,
                'balance_due' => $balanceDue,
                'total_commission' => $totalCommission,
                'status' => 'confirmed',
                'notes' => $validated['notes'] ?? null,
                'receipt_image' => $receiptPath,
                'payment_mode' => $paymentMode,
                'extra_amount' => $extraAmount,
            ]);

            // 2. Attach Appointment Services
            foreach ($services as $srv) {
                AppointmentService::create([
                    'appointment_id' => $appointment->id,
                    'saloon_service_id' => $srv->id,
                    'price' => $srv->price,
                    'discount' => $srv->discount,
                    'discounted_price' => $srv->discounted_price,
                    'commission' => $serviceCommissions[$srv->id] ?? 0,
                ]);
            }

            // 3. Write Customer Ledger Entries
            $newCustBalance = $customer->balance + $netAmount - $paidAmount;

            AccountLedger::create([
                'account_id' => $customer->id,
                'date' => $appointmentDate,
                'type' => 'sale',
                'reference_no' => $bookingNo,
                'description' => "Saloon Service Booking #{$bookingNo} (" . count($services) . " services - Stylist: {$employee->name})",
                'debit' => $netAmount,
                'credit' => 0.00,
                'running_balance' => $customer->balance + $netAmount,
            ]);

            if ($paidAmount > 0) {
                AccountLedger::create([
                    'account_id' => $customer->id,
                    'date' => $appointmentDate,
                    'type' => 'receiving',
                    'reference_no' => $bookingNo . '-REC',
                    'description' => "Payment received for Service Booking #{$bookingNo}",
                    'debit' => 0.00,
                    'credit' => $paidAmount,
                    'running_balance' => $newCustBalance,
                ]);
            }

            $customer->update(['balance' => $newCustBalance]);

            // 4. Write Employee Stylist Commission Ledger Entry
            if ($totalCommission > 0) {
                $newEmpBalance = $employee->balance + $totalCommission;
                AccountLedger::create([
                    'account_id' => $employee->id,
                    'date' => $appointmentDate,
                    'type' => 'commission',
                    'reference_no' => $bookingNo . '-COMM',
                    'description' => "Stylist Commission for Service Booking #{$bookingNo} (Client: {$customer->name})",
                    'debit' => 0.00,
                    'credit' => $totalCommission,
                    'running_balance' => $newEmpBalance,
                ]);
                $employee->update(['balance' => $newEmpBalance]);
            }
        });

        return redirect()->route('manager.appointments.index')
            ->with('success', 'Service appointment booked successfully! Customer and Employee ledgers updated.');
    }

    /**
     * Update an existing service appointment booking record & synchronize ledgers.
     */
    public function update(Request $request, Appointment $appointment)
    {
        $validated = $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'employee_id' => ['required', 'exists:accounts,id'],
            'appointment_date' => ['required', 'date'],
            'start_time' => ['nullable', 'string'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:pending,confirmed,completed,cancelled'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'receipt_image' => ['nullable', 'file', 'image', 'max:5120'],
            'payment_mode' => ['nullable', 'string', 'in:Cash,Bank'],
            'extra_amount' => ['nullable', 'numeric', 'min:0'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['required', 'exists:services,id'],
        ]);

        $receiptPath = $appointment->receipt_image;
        if ($request->hasFile('receipt_image')) {
            $file = $request->file('receipt_image');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/receipts'), $filename);
            $receiptPath = 'uploads/receipts/' . $filename;
        }

        DB::transaction(function () use ($validated, $appointment, $receiptPath) {
            $bookingNo = $appointment->booking_no;

            // 1. Reverse old Customer and Employee Ledgers
            $oldCustomer = Account::find($appointment->account_id);
            $oldEmployee = Account::find($appointment->employee_id);

            AccountLedger::where('reference_no', 'like', "%{$bookingNo}%")->delete();

            if ($oldCustomer) {
                $lastCustLedger = AccountLedger::where('account_id', $oldCustomer->id)->orderBy('id', 'desc')->first();
                $oldCustomer->update(['balance' => $lastCustLedger ? $lastCustLedger->running_balance : 0.00]);
            }

            if ($oldEmployee) {
                $lastEmpLedger = AccountLedger::where('account_id', $oldEmployee->id)->orderBy('id', 'desc')->first();
                $oldEmployee->update(['balance' => $lastEmpLedger ? $lastEmpLedger->running_balance : 0.00]);
            }

            // Remove old line items
            $appointment->items()->delete();

            // 2. Fetch target customer and employee
            $customer = Account::findOrFail($validated['account_id']);
            $employee = Account::findOrFail($validated['employee_id']);
            $appointmentDate = $validated['appointment_date'];
            $paidAmount = (float) $validated['paid_amount'];
            $billDiscount = (float) ($validated['discount'] ?? 0);
            $paymentMode = $validated['payment_mode'] ?? 'Cash';
            $extraAmount = ($paymentMode === 'Bank') ? (float) ($validated['extra_amount'] ?? 0) : 0.00;

            $services = SaloonService::whereIn('id', $validated['service_ids'])->get();
            $isSenior = strtolower($employee->emp_type ?? '') === 'senior';
            $grossTotal = 0;
            $totalCommission = 0;
            $serviceCommissions = [];

            foreach ($services as $srv) {
                $grossTotal += (float) $srv->discounted_price;
                $comm = $isSenior
                    ? (float) ($srv->senior_commission ?? $srv->commission ?? 0)
                    : (float) ($srv->junior_commission ?? $srv->commission ?? 0);
                $serviceCommissions[$srv->id] = $comm;
                $totalCommission += $comm;
            }

            $netAmount = max(0, round($grossTotal - $billDiscount + $extraAmount, 2));
            $balanceDue = round($netAmount - $paidAmount, 2);

            // 3. Update Appointment Record
            $appointment->update([
                'account_id' => $customer->id,
                'employee_id' => $employee->id,
                'appointment_date' => $appointmentDate,
                'start_time' => $validated['start_time'] ?? null,
                'total_amount' => $grossTotal,
                'discount' => $billDiscount,
                'net_amount' => $netAmount,
                'paid_amount' => $paidAmount,
                'balance_due' => $balanceDue,
                'total_commission' => $totalCommission,
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
                'receipt_image' => $receiptPath,
                'payment_mode' => $paymentMode,
                'extra_amount' => $extraAmount,
            ]);

            // 4. Create new line items
            foreach ($services as $srv) {
                AppointmentService::create([
                    'appointment_id' => $appointment->id,
                    'saloon_service_id' => $srv->id,
                    'price' => $srv->price,
                    'discount' => $srv->discount,
                    'discounted_price' => $srv->discounted_price,
                    'commission' => $serviceCommissions[$srv->id] ?? 0,
                ]);
            }

            // 5. Create New Customer Ledgers
            $newCustBalance = $customer->balance + $netAmount - $paidAmount;

            AccountLedger::create([
                'account_id' => $customer->id,
                'date' => $appointmentDate,
                'type' => 'sale',
                'reference_no' => $bookingNo,
                'description' => "Saloon Service Booking #{$bookingNo} (" . count($services) . " services - Stylist: {$employee->name})",
                'debit' => $netAmount,
                'credit' => 0.00,
                'running_balance' => $customer->balance + $netAmount,
            ]);

            if ($paidAmount > 0) {
                AccountLedger::create([
                    'account_id' => $customer->id,
                    'date' => $appointmentDate,
                    'type' => 'receiving',
                    'reference_no' => $bookingNo . '-REC',
                    'description' => "Payment received for Service Booking #{$bookingNo}",
                    'debit' => 0.00,
                    'credit' => $paidAmount,
                    'running_balance' => $newCustBalance,
                ]);
            }

            $customer->update(['balance' => $newCustBalance]);

            // 6. Create New Employee Stylist Commission Ledger Entry
            if ($totalCommission > 0) {
                $newEmpBalance = $employee->balance + $totalCommission;
                AccountLedger::create([
                    'account_id' => $employee->id,
                    'date' => $appointmentDate,
                    'type' => 'commission',
                    'reference_no' => $bookingNo . '-COMM',
                    'description' => "Stylist Commission for Service Booking #{$bookingNo} (Client: {$customer->name})",
                    'debit' => 0.00,
                    'credit' => $totalCommission,
                    'running_balance' => $newEmpBalance,
                ]);
                $employee->update(['balance' => $newEmpBalance]);
            }
        });

        return redirect()->route('manager.appointments.index')
            ->with('success', "Service appointment #{$appointment->booking_no} updated successfully! Customer and Employee ledgers synchronized.");
    }

    /**
     * Update appointment booking status.
     */
    public function updateStatus(Request $request, Appointment $appointment)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,confirmed,completed,cancelled'],
        ]);

        $appointment->update(['status' => $validated['status']]);

        return redirect()->route('manager.appointments.index')
            ->with('success', "Appointment #{$appointment->booking_no} status updated to " . ucfirst($validated['status']) . "!");
    }
}
