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
use App\Services\ImageUploadService;

class AppointmentController extends Controller
{
    /**
     * Display service appointments booking history.
     */
    public function index(Request $request)
    {
        if ($request->query('tab') === 'pos' || $request->query('mode') === 'new') {
            return redirect()->route('manager.appointments.create');
        }

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
            $query->where(function($q) use ($date) {
                $q->whereDate('appointment_date', $date)
                  ->orWhereDate('created_at', $date);
            });
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
        $totalBookingsCount = Appointment::count();
        $onlineBookingsCount = Appointment::where('order_type', 'Online')->count();
        $onSiteBookingsCount = Appointment::where(function($q) {
            $q->where('order_type', 'On Site')->orWhereNull('order_type');
        })->count();

        // Ranking Metrics
        $totalRankedCount = Appointment::whereNotNull('ranking')->count();
        $avgSaloonRanking = round((float) (Appointment::whereNotNull('ranking')->avg('ranking') ?: 0), 1);
        $fiveStarCount = Appointment::where('ranking', 5)->count();

        // Fetch Staff / Employee Accounts for Filter Dropdown and Quick Rating
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

        $brandSettings = \App\Models\Setting::getSettings();

        return view('manager.appointments.index', compact(
            'appointments', 
            'employees',
            'brandSettings',
            'search', 
            'status', 
            'date',
            'orderType',
            'employeeId',
            'ranking',
            'totalBookingsCount',
            'onlineBookingsCount',
            'onSiteBookingsCount',
            'totalRankedCount',
            'avgSaloonRanking',
            'fiveStarCount'
        ));
    }

    /**
     * Show the dedicated New Bill / Service POS checkout terminal.
     */
    public function create(Request $request)
    {
        $data = $this->getBookingFormData();
        $editingAppointment = null;
        if ($request->filled('edit')) {
            $editingAppointment = Appointment::with(['customer', 'employee', 'items.service'])->find($request->input('edit'));
        }
        $data['editingAppointment'] = $editingAppointment;

        return view('manager.appointments.create', $data);
    }

    /**
     * Edit an existing service appointment in the dedicated checkout terminal.
     */
    public function edit(Appointment $appointment)
    {
        $data = $this->getBookingFormData();
        $data['editingAppointment'] = $appointment->load(['customer', 'employee', 'items.service']);

        return view('manager.appointments.create', $data);
    }

    /**
     * Get shared datasets needed for the billing & POS checkout terminal.
     */
    private function getBookingFormData()
    {
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

        $saloonServices = SaloonService::with('category')->orderBy('title')->get();
        $serviceCategories = \App\Models\ServiceCategory::with('services')->orderBy('title')->get();
        $brandSettings = \App\Models\Setting::getSettings();
        $totalBookingsCount = Appointment::count();

        return compact(
            'customers',
            'walkinCustomer',
            'defaultCustomer',
            'employees',
            'saloonServices',
            'serviceCategories',
            'brandSettings',
            'totalBookingsCount'
        );
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
            'discount_type' => ['nullable', 'string', 'in:fixed,percentage'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'receipt_image' => ['nullable', 'file', 'image', 'max:5120'],
            'payment_mode' => ['nullable', 'string', 'in:Cash,Card,Bank,Other,Split'],
            'split_payments' => ['nullable'],
            'extra_amount' => ['nullable', 'numeric', 'min:0'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['required', 'exists:services,id'],
            'service_quantities' => ['nullable', 'array'],
            'service_quantities.*' => ['nullable', 'numeric', 'min:1'],
            'service_custom_titles' => ['nullable', 'array'],
            'service_prices' => ['nullable', 'array'],
            'service_discounts' => ['nullable', 'array'],
            'service_discount_types' => ['nullable', 'array'],
            'admin_password' => ['nullable', 'string'],
        ]);

        $receiptPath = null;
        if ($request->hasFile('receipt_image')) {
            $receiptPath = ImageUploadService::upload($request->file('receipt_image'), 'receipts', 'receipt');
        }

        DB::transaction(function () use ($validated, $receiptPath, $request) {
            $customer = Account::findOrFail($validated['account_id']);
            $employeeId = !empty($validated['employee_id']) ? (int) $validated['employee_id'] : null;
            $appointmentDate = $validated['appointment_date'];
            $paidAmount = (float) $validated['paid_amount'];
            $paymentMode = $validated['payment_mode'] ?? 'Cash';
            $extraAmount = (in_array($paymentMode, ['Card', 'Bank']) || $paymentMode === 'Split') ? (float) ($validated['extra_amount'] ?? 0) : 0.00;
            $appointmentStatus = $validated['status'] ?? 'confirmed';

            // Process split payments breakdown if mode is Split
            $splitPayments = null;
            if ($paymentMode === 'Split') {
                $rawSplits = $request->input('split_payments');
                if (is_string($rawSplits)) {
                    $splitPayments = json_decode($rawSplits, true);
                } elseif (is_array($rawSplits)) {
                    $splitPayments = $rawSplits;
                }
                if (is_array($splitPayments)) {
                    $splitPayments = [
                        'Cash' => max(0, (float)($splitPayments['Cash'] ?? 0)),
                        'Card' => max(0, (float)($splitPayments['Card'] ?? 0)),
                        'Bank' => max(0, (float)($splitPayments['Bank'] ?? 0)),
                        'Other' => max(0, (float)($splitPayments['Other'] ?? 0)),
                    ];
                    $paidAmount = array_sum($splitPayments);
                }
            }

            // Auto Generate Booking Number
            $bookingNo = 'APT-' . date('Ym') . '-' . str_pad(Appointment::count() + 1, 4, '0', STR_PAD_LEFT);

            // Calculate service totals, custom titles, multi-quantities, and service-level discounts
            $serviceIds = $request->input('service_ids', []);
            $serviceQuantities = $request->input('service_quantities', []);
            $serviceCustomTitles = $request->input('service_custom_titles', []);
            $servicePrices = $request->input('service_prices', []);
            $serviceDiscounts = $request->input('service_discounts', []);
            $serviceDiscountTypes = $request->input('service_discount_types', []);

            $services = SaloonService::with('category')->whereIn('id', $serviceIds)->get()->keyBy('id');
            $grossTotal = 0;
            $processedLineItems = [];

            foreach ($serviceIds as $index => $sId) {
                if (!isset($services[$sId])) continue;
                $srv = $services[$sId];

                // Quantity (default 1)
                $qty = (isset($serviceQuantities[$index]) && is_numeric($serviceQuantities[$index]) && (int)$serviceQuantities[$index] > 0)
                    ? (int)$serviceQuantities[$index]
                    : 1;

                // Base catalog price or custom price
                $basePrice = (isset($servicePrices[$index]) && is_numeric($servicePrices[$index]))
                    ? max(0, (float)$servicePrices[$index])
                    : (float)($srv->price ?: $srv->discounted_price ?: 0);

                // Service-level discount
                $rawDiscount = (isset($serviceDiscounts[$index]) && is_numeric($serviceDiscounts[$index]))
                    ? max(0, (float)$serviceDiscounts[$index])
                    : 0.00;

                $discType = (isset($serviceDiscountTypes[$index]) && in_array($serviceDiscountTypes[$index], ['percentage', 'fixed']))
                    ? $serviceDiscountTypes[$index]
                    : 'percentage';

                if ($discType === 'percentage') {
                    $discPct = min(100, max(0, $rawDiscount));
                    $itemDiscountPerUnit = round($basePrice * ($discPct / 100), 2);
                } else {
                    $itemDiscountPerUnit = min($basePrice, max(0, $rawDiscount));
                }

                $unitNetPrice = max(0, round($basePrice - $itemDiscountPerUnit, 2));
                $lineTotal = round($unitNetPrice * $qty, 2);

                // Custom Title if passed (e.g. for Ad Ons)
                $customTitle = isset($serviceCustomTitles[$index]) && trim($serviceCustomTitles[$index]) !== ''
                    ? trim($serviceCustomTitles[$index])
                    : $srv->title;

                $processedLineItems[] = [
                    'saloon_service_id' => $srv->id,
                    'custom_title' => $customTitle,
                    'quantity' => $qty,
                    'price' => $basePrice,
                    'discount' => round($itemDiscountPerUnit * $qty, 2),
                    'discounted_price' => $lineTotal,
                    'commission' => 0.00,
                ];

                $grossTotal += $lineTotal;
            }

            // Customer Card Tier Discount Rules:
            // 1. Silver Card: Fixed 5%
            // 2. Gold Card: Fixed 15%
            // 3. Platinum Card: Fixed 20%
            // No Card: Normal flow (Permission allows any number, no permission allows max 10%)
            $cardTypeLower = strtolower(trim($customer->card_type ?? ''));
            $isCardFixed = false;
            $cardFixedPct = null;

            if (str_contains($cardTypeLower, 'silver')) {
                $isCardFixed = true;
                $cardFixedPct = 5.00;
            } elseif (str_contains($cardTypeLower, 'gold')) {
                $isCardFixed = true;
                $cardFixedPct = 15.00;
            } elseif (str_contains($cardTypeLower, 'platinum')) {
                $isCardFixed = true;
                $cardFixedPct = 20.00;
            }

            $isAdmin = auth()->user()->hasRole('admin');
            $hasDiscountPermission = $isAdmin || auth()->user()->canGiveDiscount();
            $adminAuthorized = false;

            if ($request->filled('admin_password')) {
                $adminPassword = $request->input('admin_password');
                $adminUsers = \App\Models\User::whereHas('roles', function ($q) {
                    $q->where('slug', 'admin')->orWhere('name', 'admin')->orWhere('name', 'Administrator');
                })->get();
                foreach ($adminUsers as $adminUser) {
                    if (\Illuminate\Support\Facades\Hash::check($adminPassword, $adminUser->password)) {
                        $adminAuthorized = true;
                        break;
                    }
                }
            }

            // Separate items into:
            // 1. Non-discounted, non-addon items => eligible for bill discount
            // 2. Add-On services => NEVER receive bill discount (even if not individually discounted)
            // 3. Already-individually-discounted items => also not eligible for bill discount
            $nonDiscountedTotal = 0.00;
            $discountedTotal = 0.00;
            $hasDiscountedItem = false;
            foreach ($processedLineItems as $item) {
                $srv = $services[$item['saloon_service_id']] ?? null;
                $isItemDiscounted = false;
                $isAddonService = $this->isAddOnService($srv);
                if ($srv) {
                    $origPrice = (float) ($srv->price ?? 0);
                    $discPct = (float) ($srv->discount ?? 0);
                    $discPrice = (float) ($srv->discounted_price ?? 0);
                    $unitPrice = (float) ($item['price'] ?? 0);
                    $itemDiscAmt = (float) ($item['discount'] ?? 0);
                    if ($itemDiscAmt > 0 || $discPct > 0 || ($discPrice > 0 && $discPrice < $origPrice) || ($origPrice > 0 && $unitPrice < $origPrice)) {
                        $isItemDiscounted = true;
                    }
                }

                if ($isItemDiscounted || $isAddonService) {
                    // Add-ons and individually-discounted items are NOT eligible for bill discount
                    $hasDiscountedItem = $hasDiscountedItem || $isItemDiscounted;
                    $discountedTotal += (float) $item['discounted_price'];
                } else {
                    $nonDiscountedTotal += (float) $item['discounted_price'];
                }
            }

            // The discount base is ONLY the sum of non-discounted items
            $eligibleDiscountBase = $nonDiscountedTotal;

            if ($eligibleDiscountBase <= 0) {
                // All items in cart are already discounted: no bill or card discount allowed
                $discountType = 'percentage';
                $discountPercentage = 0.00;
                $billDiscount = 0.00;
                $discountStatus = 'approved';
                $discountApprovedBy = auth()->id();
            } elseif ($isCardFixed) {
                // Fixed cardholder discount: applies ONLY to the sum of non-discounted items
                $discountType = 'percentage';
                $discountPercentage = $cardFixedPct;
                $billDiscount = round(($eligibleDiscountBase * $discountPercentage) / 100, 2);
                $discountStatus = 'approved';
                $discountApprovedBy = auth()->id();
            } else {
                // Normal flow: No Card - discount applies ONLY to the sum of non-discounted items
                $discountType = $validated['discount_type'] ?? 'fixed';
                $rawDiscountVal = (float) ($validated['discount'] ?? 0);
                $rawDiscountPct = (float) ($validated['discount_percentage'] ?? 0);

                if ($discountType === 'percentage') {
                    $discountPercentage = min(100, max(0, $rawDiscountPct));
                    $billDiscount = round(($eligibleDiscountBase * $discountPercentage) / 100, 2);
                } else {
                    $billDiscount = min($eligibleDiscountBase, max(0, $rawDiscountVal));
                    $discountPercentage = $eligibleDiscountBase > 0 ? round(($billDiscount / $eligibleDiscountBase) * 100, 2) : 0.00;
                }

                if ($billDiscount > 0) {
                    if ($hasDiscountPermission || $isAdmin) {
                        // User has permission: can allow at any number
                        $discountStatus = 'approved';
                        $discountApprovedBy = auth()->id();
                    } else {
                        // Not allowed permission: just only for 10% and less
                        if ($discountPercentage <= 10.00 || $adminAuthorized) {
                            $discountStatus = 'approved';
                            $discountApprovedBy = auth()->id();
                        } else {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'discount' => ["A discount of {$discountPercentage}% exceeds your allowed limit of 10%. Administrator authorization password is required."]
                            ]);
                        }
                    }
                } else {
                    $discountStatus = 'approved';
                    $discountApprovedBy = auth()->id();
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
                'notes' => $validated['notes'] ?? null,
                'receipt_image' => $receiptPath,
                'payment_mode' => $paymentMode,
                'split_payments' => $splitPayments,
                'extra_amount' => $extraAmount,
            ]);

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
                $receivingDesc = "Payment received for Service Booking #{$bookingNo}";
                if ($paymentMode === 'Split' && is_array($splitPayments)) {
                    $parts = [];
                    foreach ($splitPayments as $m => $val) {
                        if ($val > 0) $parts[] = "{$m}: PKR " . number_format($val, 2);
                    }
                    if (!empty($parts)) {
                        $receivingDesc .= " (Split: " . implode(', ', $parts) . ")";
                    }
                } elseif (!empty($paymentMode)) {
                    $receivingDesc .= " ({$paymentMode})";
                }

                AccountLedger::create([
                    'account_id' => $customer->id,
                    'date' => $appointmentDate,
                    'type' => 'receiving',
                    'reference_no' => $bookingNo . '-REC',
                    'description' => $receivingDesc,
                    'debit' => 0.00,
                    'credit' => $paidAmount,
                    'running_balance' => $newCustBalance,
                ]);
            }

            $customer->update(['balance' => $newCustBalance]);
        });

        return redirect()->route('manager.appointments.index')
            ->with('success', 'Service appointment booked successfully!');
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
            'discount_type' => ['nullable', 'string', 'in:fixed,percentage'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'receipt_image' => ['nullable', 'file', 'image', 'max:5120'],
            'payment_mode' => ['nullable', 'string', 'in:Cash,Card,Bank,Other,Split'],
            'split_payments' => ['nullable'],
            'extra_amount' => ['nullable', 'numeric', 'min:0'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['required', 'exists:services,id'],
            'service_quantities' => ['nullable', 'array'],
            'service_quantities.*' => ['nullable', 'numeric', 'min:1'],
            'service_custom_titles' => ['nullable', 'array'],
            'service_prices' => ['nullable', 'array'],
            'service_discounts' => ['nullable', 'array'],
            'service_discount_types' => ['nullable', 'array'],
            'admin_password' => ['nullable', 'string'],
        ]);

        $receiptPath = $appointment->receipt_image;
        if ($request->hasFile('receipt_image')) {
            ImageUploadService::delete($appointment->receipt_image);
            $receiptPath = ImageUploadService::upload($request->file('receipt_image'), 'receipts', 'receipt');
        }

        DB::transaction(function () use ($validated, $appointment, $receiptPath, $request) {
            $bookingNo = $appointment->booking_no;

            // 1. Reverse old Customer Ledgers
            $oldCustomer = Account::find($appointment->account_id);

            AccountLedger::where('reference_no', 'like', "%{$bookingNo}%")->delete();

            if ($oldCustomer) {
                $lastCustLedger = AccountLedger::where('account_id', $oldCustomer->id)->orderBy('id', 'desc')->first();
                $oldCustomer->update(['balance' => $lastCustLedger ? $lastCustLedger->running_balance : 0.00]);
            }

            // 1. Verify Confirmed Booking Protection
            // If booking is already confirmed, only Admin (or with Admin authorization) can remove services or decrease service quantities
            $isConfirmed = ($appointment->status === 'confirmed');
            $isAdmin = auth()->user()->hasRole('admin');
            $adminAuthorized = false;

            if ($request->filled('admin_password')) {
                $adminPassword = $request->input('admin_password');
                $adminUsers = \App\Models\User::whereHas('roles', function ($q) {
                    $q->where('slug', 'admin')->orWhere('name', 'admin')->orWhere('name', 'Administrator');
                })->get();
                foreach ($adminUsers as $adminUser) {
                    if (\Illuminate\Support\Facades\Hash::check($adminPassword, $adminUser->password)) {
                        $adminAuthorized = true;
                        break;
                    }
                }
            }

            if ($isConfirmed && !$isAdmin && !$adminAuthorized) {
                $originalItems = $appointment->items()->get();
                $incomingQuantities = [];
                $serviceIds = $request->input('service_ids', []);
                $serviceQuantities = $request->input('service_quantities', []);

                foreach ($serviceIds as $idx => $sId) {
                    $qty = (isset($serviceQuantities[$idx]) && is_numeric($serviceQuantities[$idx]) && (int)$serviceQuantities[$idx] > 0)
                        ? (int)$serviceQuantities[$idx]
                        : 1;
                    $incomingQuantities[$sId] = ($incomingQuantities[$sId] ?? 0) + $qty;
                }

                foreach ($originalItems as $origItem) {
                    $sId = $origItem->saloon_service_id;
                    $origQty = (int) $origItem->quantity;
                    $newQty = $incomingQuantities[$sId] ?? 0;

                    if ($newQty < $origQty) {
                        $serviceName = $origItem->service ? $origItem->service->title : 'Service #' . $sId;
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'service_ids' => [
                                "The booking '{$appointment->booking_no}' is already Confirmed. Decreasing quantity or removing '{$serviceName}' requires Administrator authorization."
                            ]
                        ]);
                    }
                }
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
            $extraAmount = (in_array($paymentMode, ['Card', 'Bank']) || $paymentMode === 'Split') ? (float) ($validated['extra_amount'] ?? 0) : 0.00;
            $appointmentStatus = $validated['status'] ?? $appointment->status ?? 'confirmed';

            // Process split payments breakdown if mode is Split
            $splitPayments = null;
            if ($paymentMode === 'Split') {
                $rawSplits = $request->input('split_payments');
                if (is_string($rawSplits)) {
                    $splitPayments = json_decode($rawSplits, true);
                } elseif (is_array($rawSplits)) {
                    $splitPayments = $rawSplits;
                }
                if (is_array($splitPayments)) {
                    $splitPayments = [
                        'Cash' => max(0, (float)($splitPayments['Cash'] ?? 0)),
                        'Card' => max(0, (float)($splitPayments['Card'] ?? 0)),
                        'Bank' => max(0, (float)($splitPayments['Bank'] ?? 0)),
                        'Other' => max(0, (float)($splitPayments['Other'] ?? 0)),
                    ];
                    $paidAmount = array_sum($splitPayments);
                }
            }

            // Calculate service totals, custom titles, multi-quantities, and service-level discounts
            $serviceIds = $request->input('service_ids', []);
            $serviceQuantities = $request->input('service_quantities', []);
            $serviceCustomTitles = $request->input('service_custom_titles', []);
            $servicePrices = $request->input('service_prices', []);
            $serviceDiscounts = $request->input('service_discounts', []);
            $serviceDiscountTypes = $request->input('service_discount_types', []);

            $services = SaloonService::with('category')->whereIn('id', $serviceIds)->get()->keyBy('id');
            $grossTotal = 0;
            $processedLineItems = [];

            foreach ($serviceIds as $index => $sId) {
                if (!isset($services[$sId])) continue;
                $srv = $services[$sId];

                // Quantity (default 1)
                $qty = (isset($serviceQuantities[$index]) && is_numeric($serviceQuantities[$index]) && (int)$serviceQuantities[$index] > 0)
                    ? (int)$serviceQuantities[$index]
                    : 1;

                // Base catalog price or custom price
                $basePrice = (isset($servicePrices[$index]) && is_numeric($servicePrices[$index]))
                    ? max(0, (float)$servicePrices[$index])
                    : (float)($srv->price ?: $srv->discounted_price ?: 0);

                // Service-level discount
                $rawDiscount = (isset($serviceDiscounts[$index]) && is_numeric($serviceDiscounts[$index]))
                    ? max(0, (float)$serviceDiscounts[$index])
                    : 0.00;

                $discType = (isset($serviceDiscountTypes[$index]) && in_array($serviceDiscountTypes[$index], ['percentage', 'fixed']))
                    ? $serviceDiscountTypes[$index]
                    : 'percentage';

                if ($discType === 'percentage') {
                    $discPct = min(100, max(0, $rawDiscount));
                    $itemDiscountPerUnit = round($basePrice * ($discPct / 100), 2);
                } else {
                    $itemDiscountPerUnit = min($basePrice, max(0, $rawDiscount));
                }

                $unitNetPrice = max(0, round($basePrice - $itemDiscountPerUnit, 2));
                $lineTotal = round($unitNetPrice * $qty, 2);

                // Custom Title if passed
                $customTitle = isset($serviceCustomTitles[$index]) && trim($serviceCustomTitles[$index]) !== ''
                    ? trim($serviceCustomTitles[$index])
                    : null;

                $grossTotal += $lineTotal;

                $processedLineItems[] = [
                    'saloon_service_id' => $srv->id,
                    'custom_title' => $customTitle,
                    'quantity' => $qty,
                    'price' => $basePrice,
                    'discount' => round($itemDiscountPerUnit * $qty, 2),
                    'discounted_price' => $lineTotal,
                    'commission' => 0.00,
                ];
            }

            // Customer Card Tier Discount Rules:
            // 1. Silver Card: Fixed 5%
            // 2. Gold Card: Fixed 15%
            // 3. Platinum Card: Fixed 20%
            // No Card: Normal flow (Permission allows any number, no permission allows max 10%)
            $cardTypeLower = strtolower(trim($customer->card_type ?? ''));
            $isCardFixed = false;
            $cardFixedPct = null;

            if (str_contains($cardTypeLower, 'silver')) {
                $isCardFixed = true;
                $cardFixedPct = 5.00;
            } elseif (str_contains($cardTypeLower, 'gold')) {
                $isCardFixed = true;
                $cardFixedPct = 15.00;
            } elseif (str_contains($cardTypeLower, 'platinum')) {
                $isCardFixed = true;
                $cardFixedPct = 20.00;
            }

            $isAdmin = auth()->user()->hasRole('admin');
            $hasDiscountPermission = $isAdmin || auth()->user()->canGiveDiscount();
            $adminAuthorized = false;

            if ($request->filled('admin_password')) {
                $adminPassword = $request->input('admin_password');
                $adminUsers = \App\Models\User::whereHas('roles', function ($q) {
                    $q->where('slug', 'admin')->orWhere('name', 'admin')->orWhere('name', 'Administrator');
                })->get();
                foreach ($adminUsers as $adminUser) {
                    if (\Illuminate\Support\Facades\Hash::check($adminPassword, $adminUser->password)) {
                        $adminAuthorized = true;
                        break;
                    }
                }
            }

            // Separate items into:
            // 1. Non-discounted, non-addon items => eligible for bill discount
            // 2. Add-On services => NEVER receive bill discount (even if not individually discounted)
            // 3. Already-individually-discounted items => also not eligible for bill discount
            $nonDiscountedTotal = 0.00;
            $discountedTotal = 0.00;
            $hasDiscountedItem = false;
            foreach ($processedLineItems as $item) {
                $srv = $services[$item['saloon_service_id']] ?? null;
                $isItemDiscounted = false;
                $isAddonService = $this->isAddOnService($srv);
                if ($srv) {
                    $origPrice = (float) ($srv->price ?? 0);
                    $discPct = (float) ($srv->discount ?? 0);
                    $discPrice = (float) ($srv->discounted_price ?? 0);
                    $unitPrice = (float) ($item['price'] ?? 0);
                    $itemDiscAmt = (float) ($item['discount'] ?? 0);
                    if ($itemDiscAmt > 0 || $discPct > 0 || ($discPrice > 0 && $discPrice < $origPrice) || ($origPrice > 0 && $unitPrice < $origPrice)) {
                        $isItemDiscounted = true;
                    }
                }

                if ($isItemDiscounted || $isAddonService) {
                    // Add-ons and individually-discounted items are NOT eligible for bill discount
                    $hasDiscountedItem = $hasDiscountedItem || $isItemDiscounted;
                    $discountedTotal += (float) $item['discounted_price'];
                } else {
                    $nonDiscountedTotal += (float) $item['discounted_price'];
                }
            }

            // The discount base is ONLY the sum of non-discounted items
            $eligibleDiscountBase = $nonDiscountedTotal;

            if ($eligibleDiscountBase <= 0) {
                // All items in cart are already discounted: no bill or card discount allowed
                $discountType = 'percentage';
                $discountPercentage = 0.00;
                $billDiscount = 0.00;
                $discountStatus = 'approved';
                $discountApprovedBy = auth()->id();
            } elseif ($isCardFixed) {
                // Fixed cardholder discount: applies ONLY to the sum of non-discounted items
                $discountType = 'percentage';
                $discountPercentage = $cardFixedPct;
                $billDiscount = round(($eligibleDiscountBase * $discountPercentage) / 100, 2);
                $discountStatus = 'approved';
                $discountApprovedBy = auth()->id();
            } else {
                // Normal flow: No Card - discount applies ONLY to the sum of non-discounted items
                $discountType = $validated['discount_type'] ?? 'fixed';
                $rawDiscountVal = (float) ($validated['discount'] ?? 0);
                $rawDiscountPct = (float) ($validated['discount_percentage'] ?? 0);

                if ($discountType === 'percentage') {
                    $discountPercentage = min(100, max(0, $rawDiscountPct));
                    $billDiscount = round(($eligibleDiscountBase * $discountPercentage) / 100, 2);
                } else {
                    $billDiscount = min($eligibleDiscountBase, max(0, $rawDiscountVal));
                    $discountPercentage = $eligibleDiscountBase > 0 ? round(($billDiscount / $eligibleDiscountBase) * 100, 2) : 0.00;
                }

                if ($billDiscount > 0) {
                    if ($hasDiscountPermission || $isAdmin) {
                        // User has permission: can allow at any number
                        $discountStatus = 'approved';
                        $discountApprovedBy = auth()->id();
                    } else {
                        // Not allowed permission: just only for 10% and less
                        if ($discountPercentage <= 10.00 || $adminAuthorized) {
                            $discountStatus = 'approved';
                            $discountApprovedBy = auth()->id();
                        } else {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'discount' => ["A discount of {$discountPercentage}% exceeds your allowed limit of 10%. Administrator authorization password is required."]
                            ]);
                        }
                    }
                } else {
                    $discountStatus = 'approved';
                    $discountApprovedBy = auth()->id();
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
                'split_payments' => $splitPayments,
                'extra_amount' => $extraAmount,
            ]);

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
                $receivingDesc = "Payment received for Service Booking #{$bookingNo}";
                if ($paymentMode === 'Split' && is_array($splitPayments)) {
                    $parts = [];
                    foreach ($splitPayments as $m => $val) {
                        if ($val > 0) $parts[] = "{$m}: PKR " . number_format($val, 2);
                    }
                    if (!empty($parts)) {
                        $receivingDesc .= " (Split: " . implode(', ', $parts) . ")";
                    }
                } elseif (!empty($paymentMode)) {
                    $receivingDesc .= " ({$paymentMode})";
                }

                AccountLedger::create([
                    'account_id' => $customer->id,
                    'date' => $appointmentDate,
                    'type' => 'receiving',
                    'reference_no' => $bookingNo . '-REC',
                    'description' => $receivingDesc,
                    'debit' => 0.00,
                    'credit' => $paidAmount,
                    'running_balance' => $newCustBalance,
                ]);
            }

            $customer->update(['balance' => $newCustBalance]);
        });

        return redirect()->route('manager.appointments.index')
            ->with('success', "Service appointment #{$appointment->booking_no} updated successfully!");
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
     * Verify administrator password via AJAX.
     */
    public function verifyAdminPassword(Request $request)
    {
        $password = $request->input('password');
        if (empty($password)) {
            return response()->json(['valid' => false, 'message' => 'Password is required.'], 422);
        }

        $adminUsers = \App\Models\User::whereHas('roles', function ($q) {
            $q->where('slug', 'admin')->orWhere('name', 'admin')->orWhere('name', 'Administrator');
        })->get();

        foreach ($adminUsers as $adminUser) {
            if (\Illuminate\Support\Facades\Hash::check($password, $adminUser->password)) {
                return response()->json(['valid' => true]);
            }
        }

        return response()->json(['valid' => false, 'message' => 'Incorrect administrator password.'], 403);
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

    /**
     * Upload or replace customer payment proof / slip for an appointment.
     */
    public function uploadReceipt(Request $request, Appointment $appointment)
    {
        $request->validate([
            'receipt_image' => ['required', 'file', 'mimes:jpeg,png,jpg,webp,pdf', 'max:10240'],
        ]);

        if ($request->hasFile('receipt_image')) {
            if (!empty($appointment->receipt_image) && !str_starts_with($appointment->receipt_image, 'http')) {
                ImageUploadService::delete($appointment->receipt_image);
            }
            $receiptPath = ImageUploadService::upload($request->file('receipt_image'), 'receipts', 'receipt');
            $appointment->update(['receipt_image' => $receiptPath]);
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Payment proof slip updated successfully for #{$appointment->booking_no}",
                'receipt_url' => asset($appointment->receipt_image),
            ]);
        }

        return redirect()->back()
            ->with('success', "Payment proof slip updated successfully for #{$appointment->booking_no}!");
    }

    /**
     * Detect if a SaloonService is an Add-On service by its title or category name.
     * Add-On services are NEVER eligible for bill-level discounts.
     * Mirrors the JS isAddOn() detection in the appointments view.
     */
    private function isAddOnService(?\App\Models\SaloonService $srv): bool
    {
        if (!$srv) return false;

        $catTitle = strtolower(trim($srv->category->title ?? ''));
        $srvTitle = strtolower(trim($srv->title ?? ''));

        $addonKeywords = ['ad on', 'add on', 'adon', 'addon'];
        foreach ($addonKeywords as $kw) {
            if (str_contains($catTitle, $kw) || str_contains($srvTitle, $kw)) {
                return true;
            }
        }

        return false;
    }
}
