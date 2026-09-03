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
        $orderType = $request->input('order_type');
        $employeeId = $request->input('employee_id');
        $ranking = $request->input('ranking');

        $query = Appointment::with(['customer', 'employee.category', 'rankedBy', 'items.service']);

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

        if ($orderType) {
            $query->where('order_type', $orderType);
        }

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        if ($ranking) {
            if ($ranking === 'unranked') {
                $query->whereNull('ranking');
            } else {
                $query->where('ranking', (int)$ranking);
            }
        }

        $appointments = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();

        // Metrics for Online vs On-Site Bookings
        $onlineBookingsCount = Appointment::where('order_type', 'Online')->count();
        $onSiteBookingsCount = Appointment::where(function($q) {
            $q->where('order_type', 'On Site')->orWhereNull('order_type');
        })->count();

        // Ranking Metrics
        $totalRankedCount = Appointment::whereNotNull('ranking')->count();
        $avgSaloonRanking = round((float) (Appointment::whereNotNull('ranking')->avg('ranking') ?: 0), 1);
        $fiveStarCount = Appointment::where('ranking', 5)->count();

        // 1. Fetch Customer accounts & Ensure Walk-in Customer is default
        $customerCategory = \App\Models\AccountCategory::where('title', 'like', '%Customer%')
            ->orWhere('title', 'like', '%Client%')
            ->first();

        if (!$customerCategory) {
            $customerCategory = \App\Models\AccountCategory::firstOrCreate(['title' => 'Walk-in Client']);
        }

        $walkinCustomer = Account::where('name', 'like', '%Walk-in%')
            ->orWhere('name', 'like', '%Walk in%')
            ->orWhere('name', 'Walkin Customer')
            ->first();

        if (!$walkinCustomer) {
            $walkinCustomer = Account::create([
                'name' => 'Walk-in Customer',
                'account_category_id' => $customerCategory->id,
                'phone_no1' => '0300-0000000',
                'balance' => 0.00,
            ]);
        }

        $customers = Account::where(function($q) {
            $q->whereHas('category', function ($cq) {
                $cq->where('title', 'like', '%Customer%')
                   ->orWhere('title', 'like', '%Client%')
                   ->orWhere('title', 'like', '%Member%');
            })->orWhereDoesntHave('category');
        })->where(function($q) {
            $q->whereNull('emp_type')->orWhere('emp_type', '');
        })->whereDoesntHave('category', function($q) {
            $q->where('title', 'like', '%Employee%')
              ->orWhere('title', 'like', '%Staff%')
              ->orWhere('title', 'like', '%Supplier%')
              ->orWhere('title', 'like', '%Vendor%');
        })->get();

        // Ensure Walk-in Customer is always the very first item
        $customers = $customers->reject(function ($c) use ($walkinCustomer) {
            return $c->id == $walkinCustomer->id;
        })->sortBy('name')->values();
        $customers->prepend($walkinCustomer);

        $defaultCustomer = $walkinCustomer;

        // Fetch Staff / Employee Accounts for Assignment and Ranking
        $employees = Account::where(function($q) {
            $q->whereHas('category', function ($cq) {
                $cq->where('title', 'like', '%Employee%')
                   ->orWhere('title', 'like', '%Staff%')
                   ->orWhere('title', 'like', '%Stylist%')
                   ->orWhere('title', 'like', '%Barber%');
            })->orWhere(function($sq) {
                $sq->whereNotNull('emp_type')->where('emp_type', '!=', '');
            });
        })->with('category')->orderBy('name')->get();

        if ($employees->isEmpty()) {
            $employees = Account::whereHas('category', function ($q) {
                $q->where('title', 'not like', '%Supplier%')
                  ->where('title', 'not like', '%Vendor%');
            })->orWhereDoesntHave('category')->with('category')->orderBy('name')->get();
        }

        // 2. Fetch Saloon Services & Categories for the filterable services catalog
        $saloonServices = SaloonService::with('category')->orderBy('title')->get();
        $serviceCategories = \App\Models\ServiceCategory::with('services')->orderBy('title')->get();

        return view('manager.appointments.index', compact(
            'appointments', 
            'customers', 
            'employees',
            'defaultCustomer',
            'saloonServices', 
            'serviceCategories',
            'search', 
            'status', 
            'date',
            'orderType',
            'employeeId',
            'ranking',
            'onlineBookingsCount',
            'onSiteBookingsCount',
            'totalRankedCount',
            'avgSaloonRanking',
            'fiveStarCount'
        ));
    }

    /**
     * Store a newly created service appointment booking.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_type' => ['nullable', 'string', 'in:On Site,Online'],
            'account_id' => ['required', 'exists:accounts,id'],
            'employee_id' => ['nullable', 'exists:accounts,id'],
            'appointment_date' => ['required', 'date'],
            'start_time' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:pending,confirmed,completed,cancelled'],
            'ranking' => ['nullable', 'integer', 'min:1', 'max:5'],
            'ranking_notes' => ['nullable', 'string', 'max:1000'],
            'discount_type' => ['nullable', 'string', 'in:fixed,percentage'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'receipt_image' => ['nullable', 'file', 'image', 'max:5120'],
            'payment_mode' => ['nullable', 'string', 'in:Cash,Card,Bank,Other'],
            'extra_amount' => ['nullable', 'numeric', 'min:0'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['required', 'exists:services,id'],
            'service_quantities' => ['nullable', 'array'],
            'service_quantities.*' => ['nullable', 'numeric', 'min:1'],
            'service_custom_titles' => ['nullable', 'array'],
            'service_prices' => ['nullable', 'array'],
            'admin_password' => ['nullable', 'string'],
        ]);

        $receiptPath = null;
        if ($request->hasFile('receipt_image')) {
            $file = $request->file('receipt_image');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/receipts'), $filename);
            $receiptPath = 'uploads/receipts/' . $filename;
        }

        $requiresAdminApproval = false;

        DB::transaction(function () use ($validated, $receiptPath, $request, &$requiresAdminApproval) {
            $customer = Account::findOrFail($validated['account_id']);
            $employeeId = !empty($validated['employee_id']) ? (int) $validated['employee_id'] : null;
            $ranking = !empty($validated['ranking']) ? (int) $validated['ranking'] : null;
            $rankingNotes = $validated['ranking_notes'] ?? null;
            $appointmentDate = $validated['appointment_date'];
            $paidAmount = (float) $validated['paid_amount'];
            $paymentMode = $validated['payment_mode'] ?? 'Cash';
            $extraAmount = in_array($paymentMode, ['Card', 'Bank']) ? (float) ($validated['extra_amount'] ?? 0) : 0.00;
            $appointmentStatus = $validated['status'] ?? 'confirmed';

            // Auto Generate Booking Number
            $bookingNo = 'APT-' . date('Ym') . '-' . str_pad(Appointment::count() + 1, 4, '0', STR_PAD_LEFT);

            // Calculate service totals, custom titles, and multi-quantities
            $serviceIds = $request->input('service_ids', []);
            $serviceQuantities = $request->input('service_quantities', []);
            $serviceCustomTitles = $request->input('service_custom_titles', []);
            $servicePrices = $request->input('service_prices', []);

            $services = SaloonService::whereIn('id', $serviceIds)->get()->keyBy('id');
            $grossTotal = 0;
            $processedLineItems = [];

            foreach ($serviceIds as $index => $sId) {
                if (!isset($services[$sId])) continue;
                $srv = $services[$sId];

                // Quantity (default 1)
                $qty = (isset($serviceQuantities[$index]) && is_numeric($serviceQuantities[$index]) && (int)$serviceQuantities[$index] > 0)
                    ? (int)$serviceQuantities[$index]
                    : 1;

                // Custom or Standard Unit Price
                $unitPrice = (isset($servicePrices[$index]) && is_numeric($servicePrices[$index]))
                    ? max(0, (float)$servicePrices[$index])
                    : (float)($srv->discounted_price ?: $srv->price ?: 0);

                $lineTotal = round($unitPrice * $qty, 2);

                // Custom Title if passed (e.g. for Ad Ons)
                $customTitle = isset($serviceCustomTitles[$index]) && trim($serviceCustomTitles[$index]) !== ''
                    ? trim($serviceCustomTitles[$index])
                    : null;

                $grossTotal += $lineTotal;

                $processedLineItems[] = [
                    'saloon_service_id' => $srv->id,
                    'custom_title' => $customTitle,
                    'quantity' => $qty,
                    'price' => $unitPrice,
                    'discount' => 0.00,
                    'discounted_price' => $lineTotal,
                    'commission' => 0.00,
                ];
            }

            $discountType = $validated['discount_type'] ?? 'fixed';
            $rawDiscountVal = (float) ($validated['discount'] ?? 0);
            $rawDiscountPct = (float) ($validated['discount_percentage'] ?? 0);

            if ($discountType === 'percentage') {
                $discountPercentage = min(100, max(0, $rawDiscountPct));
                $billDiscount = round(($grossTotal * $discountPercentage) / 100, 2);
            } else {
                $billDiscount = min($grossTotal, max(0, $rawDiscountVal));
                $discountPercentage = $grossTotal > 0 ? round(($billDiscount / $grossTotal) * 100, 2) : 0.00;
            }

            // Check Permissions
            $isAdmin = auth()->user()->hasRole('admin');
            $adminAuthorized = false;

            if ($request->filled('admin_password')) {
                $adminPassword = $request->input('admin_password');
                $adminUsers = \App\Models\User::whereHas('roles', function ($q) {
                    $q->where('name', 'admin');
                })->get();
                foreach ($adminUsers as $adminUser) {
                    if (\Illuminate\Support\Facades\Hash::check($adminPassword, $adminUser->password)) {
                        $adminAuthorized = true;
                        break;
                    }
                }
            }

            $hasDiscountPermission = $isAdmin || auth()->user()->hasPermission('allow-bill-discount');

            if ($discountPercentage <= 10.00) {
                // Up to 10% discount: Allowed for every user unconditionally
                $discountStatus = 'approved';
                $discountApprovedBy = auth()->id();
            } else {
                // Greater than 10%: Allowed only for users with allow-bill-discount permission or admin (or authorized via admin password)
                if ($hasDiscountPermission || $adminAuthorized) {
                    $discountStatus = 'approved';
                    $discountApprovedBy = auth()->id();
                } else {
                    $discountStatus = 'pending_approval';
                    $requiresAdminApproval = true;
                    $discountApprovedBy = null;
                }
            }

            $netAmount = max(0, round($grossTotal - $billDiscount + $extraAmount, 2));
            $balanceDue = max(0, round($netAmount - $paidAmount, 2));

            // 1. Create Appointment Record
            $appointment = Appointment::create([
                'booking_no' => $bookingNo,
                'order_type' => $validated['order_type'] ?? 'On Site',
                'account_id' => $customer->id,
                'employee_id' => $employeeId,
                'appointment_date' => $appointmentDate,
                'start_time' => $validated['start_time'] ?? null,
                'total_amount' => $grossTotal,
                'discount' => $billDiscount,
                'discount_type' => $discountType,
                'discount_percentage' => $discountPercentage,
                'discount_status' => $discountStatus,
                'discount_approved_by' => $discountApprovedBy,
                'net_amount' => $netAmount,
                'paid_amount' => $paidAmount,
                'balance_due' => $balanceDue,
                'total_commission' => 0.00,
                'status' => $appointmentStatus,
                'ranking' => $ranking,
                'ranking_notes' => $rankingNotes,
                'ranked_by' => $ranking ? auth()->id() : null,
                'ranked_at' => $ranking ? now() : null,
                'notes' => $validated['notes'] ?? null,
                'receipt_image' => $receiptPath,
                'payment_mode' => $paymentMode,
                'extra_amount' => $extraAmount,
            ]);

            // If discount percentage > 10% and not authorized, generate DiscountRequest notification for Admin
            if ($discountPercentage > 10.00 && !$hasDiscountPermission && !$adminAuthorized) {
                \App\Models\DiscountRequest::create([
                    'appointment_id' => $appointment->id,
                    'requested_by_user_id' => auth()->id(),
                    'gross_amount' => $grossTotal,
                    'discount_amount' => $billDiscount,
                    'discount_percentage' => $discountPercentage,
                    'status' => 'pending',
                    'notes' => "Service appointment booking discount request of {$discountPercentage}% (PKR {$billDiscount}) on total PKR {$grossTotal}.",
                ]);
            }

            // 2. Attach Appointment Services (with custom titles & custom prices)
            foreach ($processedLineItems as $item) {
                AppointmentService::create(array_merge($item, [
                    'appointment_id' => $appointment->id,
                ]));
            }

            // 3. Write Customer Ledger Entries
            $newCustBalance = $customer->balance + $netAmount - $paidAmount;

            AccountLedger::create([
                'account_id' => $customer->id,
                'date' => $appointmentDate,
                'type' => 'sale',
                'reference_no' => $bookingNo,
                'description' => "Saloon Service Booking #{$bookingNo} (" . count($processedLineItems) . " services)",
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
        });

        $msg = 'Service appointment booked successfully!';
        if ($requiresAdminApproval) {
            $msg .= ' ⚠️ Note: Discount > 10% requires Admin Approval. A notification has been sent to Admin.';
        }

        return redirect()->route('manager.appointments.index')
            ->with($requiresAdminApproval ? 'warning' : 'success', $msg);
    }

    /**
     * Update an existing service appointment booking record & synchronize ledgers.
     */
    public function update(Request $request, Appointment $appointment)
    {
        $validated = $request->validate([
            'order_type' => ['nullable', 'string', 'in:On Site,Online'],
            'account_id' => ['required', 'exists:accounts,id'],
            'appointment_date' => ['required', 'date'],
            'start_time' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:pending,confirmed,completed,cancelled'],
            'ranking' => ['nullable', 'integer', 'min:1', 'max:5'],
            'ranking_notes' => ['nullable', 'string', 'max:1000'],
            'discount_type' => ['nullable', 'string', 'in:fixed,percentage'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'receipt_image' => ['nullable', 'file', 'image', 'max:5120'],
            'payment_mode' => ['nullable', 'string', 'in:Cash,Card,Bank,Other'],
            'extra_amount' => ['nullable', 'numeric', 'min:0'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['required', 'exists:services,id'],
            'service_quantities' => ['nullable', 'array'],
            'service_quantities.*' => ['nullable', 'numeric', 'min:1'],
            'service_custom_titles' => ['nullable', 'array'],
            'service_prices' => ['nullable', 'array'],
            'admin_password' => ['nullable', 'string'],
        ]);

        $receiptPath = $appointment->receipt_image;
        if ($request->hasFile('receipt_image')) {
            $file = $request->file('receipt_image');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/receipts'), $filename);
            $receiptPath = 'uploads/receipts/' . $filename;
        }

        $requiresAdminApproval = false;

        DB::transaction(function () use ($validated, $appointment, $receiptPath, $request, &$requiresAdminApproval) {
            $bookingNo = $appointment->booking_no;

            // 1. Reverse old Customer Ledgers
            $oldCustomer = Account::find($appointment->account_id);

            AccountLedger::where('reference_no', 'like', "%{$bookingNo}%")->delete();

            if ($oldCustomer) {
                $lastCustLedger = AccountLedger::where('account_id', $oldCustomer->id)->orderBy('id', 'desc')->first();
                $oldCustomer->update(['balance' => $lastCustLedger ? $lastCustLedger->running_balance : 0.00]);
            }

            // Remove old line items
            $appointment->items()->delete();

            // 2. Fetch target customer
            $customer = Account::findOrFail($validated['account_id']);
            $employeeId = array_key_exists('employee_id', $validated) ? (!empty($validated['employee_id']) ? (int)$validated['employee_id'] : null) : $appointment->employee_id;
            $ranking = array_key_exists('ranking', $validated) ? (!empty($validated['ranking']) ? (int)$validated['ranking'] : null) : $appointment->ranking;
            $rankingNotes = array_key_exists('ranking_notes', $validated) ? $validated['ranking_notes'] : $appointment->ranking_notes;
            $rankedBy = $ranking ? ($appointment->ranked_by ?: auth()->id()) : null;
            $rankedAt = $ranking ? ($appointment->ranked_at ?: now()) : null;

            $appointmentDate = $validated['appointment_date'];
            $paidAmount = (float) $validated['paid_amount'];
            $paymentMode = $validated['payment_mode'] ?? 'Cash';
            $extraAmount = in_array($paymentMode, ['Card', 'Bank']) ? (float) ($validated['extra_amount'] ?? 0) : 0.00;
            $appointmentStatus = $validated['status'] ?? $appointment->status ?? 'confirmed';

            // Calculate service totals, custom titles, and multi-quantities
            $serviceIds = $request->input('service_ids', []);
            $serviceQuantities = $request->input('service_quantities', []);
            $serviceCustomTitles = $request->input('service_custom_titles', []);
            $servicePrices = $request->input('service_prices', []);

            $services = SaloonService::whereIn('id', $serviceIds)->get()->keyBy('id');
            $grossTotal = 0;
            $processedLineItems = [];

            foreach ($serviceIds as $index => $sId) {
                if (!isset($services[$sId])) continue;
                $srv = $services[$sId];

                // Quantity (default 1)
                $qty = (isset($serviceQuantities[$index]) && is_numeric($serviceQuantities[$index]) && (int)$serviceQuantities[$index] > 0)
                    ? (int)$serviceQuantities[$index]
                    : 1;

                // Custom Price if passed and valid
                $unitPrice = (isset($servicePrices[$index]) && is_numeric($servicePrices[$index]))
                    ? max(0, (float)$servicePrices[$index])
                    : (float)($srv->discounted_price ?: $srv->price ?: 0);

                $lineTotal = round($unitPrice * $qty, 2);

                // Custom Title if passed
                $customTitle = isset($serviceCustomTitles[$index]) && trim($serviceCustomTitles[$index]) !== ''
                    ? trim($serviceCustomTitles[$index])
                    : null;

                $grossTotal += $lineTotal;

                $processedLineItems[] = [
                    'saloon_service_id' => $srv->id,
                    'custom_title' => $customTitle,
                    'quantity' => $qty,
                    'price' => $unitPrice,
                    'discount' => 0.00,
                    'discounted_price' => $lineTotal,
                    'commission' => 0.00,
                ];
            }

            $discountType = $validated['discount_type'] ?? 'fixed';
            $rawDiscountVal = (float) ($validated['discount'] ?? 0);
            $rawDiscountPct = (float) ($validated['discount_percentage'] ?? 0);

            if ($discountType === 'percentage') {
                $discountPercentage = min(100, max(0, $rawDiscountPct));
                $billDiscount = round($grossTotal * ($discountPercentage / 100), 2);
            } else {
                $billDiscount = max(0, $rawDiscountVal);
                $discountPercentage = ($grossTotal > 0) ? round(($billDiscount / $grossTotal) * 100, 2) : 0.00;
            }

            $isAdmin = auth()->user()->hasRole('admin');
            $adminPassword = $request->input('admin_password');
            $adminAuthorized = false;

            if ($adminPassword) {
                $adminUsers = \App\Models\User::whereHas('roles', function($q) {
                    $q->where('name', 'admin');
                })->get();
                foreach ($adminUsers as $adminUser) {
                    if (\Illuminate\Support\Facades\Hash::check($adminPassword, $adminUser->password)) {
                        $adminAuthorized = true;
                        break;
                    }
                }
            }

            $hasDiscountPermission = $isAdmin || auth()->user()->hasPermission('allow-bill-discount');

            if ($discountPercentage <= 10.00) {
                // Up to 10% discount: Allowed for every user unconditionally
                $discountStatus = 'approved';
                $discountApprovedBy = auth()->id();
            } else {
                // Greater than 10%: Allowed only for users with allow-bill-discount permission or admin (or authorized via admin password)
                if ($hasDiscountPermission || $adminAuthorized) {
                    $discountStatus = 'approved';
                    $discountApprovedBy = auth()->id();
                } else {
                    $discountStatus = 'pending_approval';
                    $requiresAdminApproval = true;
                    $discountApprovedBy = null;
                }
            }

            $netAmount = max(0, round($grossTotal - $billDiscount + $extraAmount, 2));
            $balanceDue = max(0, round($netAmount - $paidAmount, 2));

            // 3. Update Appointment Record
            $appointment->update([
                'order_type' => $validated['order_type'] ?? $appointment->order_type ?? 'On Site',
                'account_id' => $customer->id,
                'employee_id' => $employeeId,
                'appointment_date' => $appointmentDate,
                'start_time' => $validated['start_time'] ?? null,
                'total_amount' => $grossTotal,
                'discount' => $billDiscount,
                'discount_type' => $discountType,
                'discount_percentage' => $discountPercentage,
                'discount_status' => $discountStatus,
                'discount_approved_by' => $discountApprovedBy,
                'net_amount' => $netAmount,
                'paid_amount' => $paidAmount,
                'balance_due' => $balanceDue,
                'total_commission' => 0.00,
                'status' => $appointmentStatus,
                'ranking' => $ranking,
                'ranking_notes' => $rankingNotes,
                'ranked_by' => $rankedBy,
                'ranked_at' => $rankedAt,
                'notes' => $validated['notes'] ?? null,
                'receipt_image' => $receiptPath,
                'payment_mode' => $paymentMode,
                'extra_amount' => $extraAmount,
            ]);

            if ($discountPercentage > 10.00 && !$hasDiscountPermission && !$adminAuthorized) {
                \App\Models\DiscountRequest::create([
                    'appointment_id' => $appointment->id,
                    'requested_by_user_id' => auth()->id(),
                    'gross_amount' => $grossTotal,
                    'discount_amount' => $billDiscount,
                    'discount_percentage' => $discountPercentage,
                    'status' => 'pending',
                    'notes' => "Service appointment booking discount edit request of {$discountPercentage}% (PKR {$billDiscount}) on total PKR {$grossTotal}.",
                ]);
            }

            // 4. Create new line items
            foreach ($processedLineItems as $item) {
                AppointmentService::create(array_merge($item, [
                    'appointment_id' => $appointment->id,
                ]));
            }

            // 5. Create New Customer Ledgers
            $newCustBalance = $customer->balance + $netAmount - $paidAmount;

            AccountLedger::create([
                'account_id' => $customer->id,
                'date' => $appointmentDate,
                'type' => 'sale',
                'reference_no' => $bookingNo,
                'description' => "Saloon Service Booking #{$bookingNo} (" . count($processedLineItems) . " services)",
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
        });

        $msg = "Service appointment #{$appointment->booking_no} updated successfully!";
        if ($requiresAdminApproval) {
            $msg .= ' ⚠️ Note: Updated discount > 10% requires Admin Approval.';
        }

        return redirect()->route('manager.appointments.index')
            ->with($requiresAdminApproval ? 'warning' : 'success', $msg);
    }

    /**
     * Rate / Rank employee for an appointment.
     */
    public function rateEmployee(Request $request, Appointment $appointment)
    {
        $validated = $request->validate([
            'employee_id' => ['nullable', 'exists:accounts,id'],
            'ranking' => ['required', 'integer', 'min:1', 'max:5'],
            'ranking_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $appointment->update([
            'employee_id' => !empty($validated['employee_id']) ? (int)$validated['employee_id'] : $appointment->employee_id,
            'ranking' => (int) $validated['ranking'],
            'ranking_notes' => $validated['ranking_notes'] ?? null,
            'ranked_by' => auth()->id(),
            'ranked_at' => now(),
        ]);

        return redirect()->back()
            ->with('success', "Employee ranking for Appointment #{$appointment->booking_no} recorded successfully!");
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
