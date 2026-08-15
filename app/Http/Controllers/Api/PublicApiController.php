<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\ContactMessage;
use App\Models\Gallery;
use App\Models\Product;
use App\Models\SaloonService;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PublicApiController extends Controller
{
    /**
     * Fetch products for the public front-end catalog.
     */
    public function products(Request $request)
    {
        $search = $request->query('search');

        $query = Product::query();

        if ($search) {
            $query->where('title', 'like', "%{$search}%");
        }

        $products = $query->orderBy('created_at', 'desc')->get()->map(function ($product) {
            return [
                'id' => $product->id,
                'title' => $product->title,
                'price' => (float) $product->price,
                'discount' => (float) $product->discount,
                'discounted_price' => (float) ($product->discounted_price ?? $product->calculateDiscountedPrice()),
                'stock' => (int) $product->stock,
                'image_url' => $product->image ? url($product->image) : null,
                'created_at' => $product->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * Fetch services for the public front-end catalog.
     */
    public function services(Request $request)
    {
        $search = $request->query('search');
        $categoryId = $request->query('category_id');

        $query = SaloonService::with('category');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($categoryId) {
            $query->where('service_category_id', $categoryId);
        }

        $services = $query->orderBy('title', 'asc')->get()->map(function ($service) {
            return [
                'id' => $service->id,
                'title' => $service->title,
                'description' => $service->description,
                'price' => (float) $service->price,
                'discount' => (float) $service->discount,
                'discounted_price' => (float) ($service->discounted_price ?? $service->calculateDiscountedPrice()),
                'category' => $service->category ? [
                    'id' => $service->category->id,
                    'title' => $service->category->title,
                ] : null,
                'image_url' => $service->image ? url($service->image) : null,
                'created_at' => $service->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $services,
        ]);
    }

    /**
     * Fetch service categories for filtering.
     */
    public function serviceCategories()
    {
        $categories = ServiceCategory::orderBy('title')->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Fetch photo gallery showcase items for the public front-end.
     */
    public function galleries(Request $request)
    {
        $category = $request->query('category');
        $search = $request->query('search');

        $query = Gallery::where('is_active', true);

        if ($category) {
            $query->where('category', $category);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $galleries = $query->orderBy('sort_order', 'asc')
                           ->orderBy('created_at', 'desc')
                           ->get()
                           ->map(function ($item) {
                               return [
                                   'id' => $item->id,
                                   'title' => $item->title,
                                   'category' => $item->category,
                                   'image_path' => $item->image_path,
                                   'image_url' => $item->url,
                                   'file_name' => $item->file_name,
                                   'file_size' => (int) $item->file_size,
                                   'formatted_size' => $item->formatted_size,
                                   'sort_order' => (int) $item->sort_order,
                                   'created_at' => $item->created_at,
                               ];
                           });

        return response()->json([
            'success' => true,
            'data' => $galleries,
        ]);
    }

    /**
     * Book a public appointment from the front-end.
     */
    public function bookAppointment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'string'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['required', 'exists:services,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'receipt_image' => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Check if requested time slot is already reserved for the date
        if (!empty($validated['start_time']) && !empty($validated['appointment_date'])) {
            $isReserved = Appointment::where('appointment_date', $validated['appointment_date'])
                ->where('start_time', $validated['start_time'])
                ->where('status', '!=', 'cancelled')
                ->exists();

            if ($isReserved) {
                return response()->json([
                    'success' => false,
                    'message' => 'The selected time slot is no longer available.',
                    'errors' => [
                        'start_time' => ['Selected time slot is already reserved.']
                    ]
                ], 422);
            }
        }

        $receiptPath = null;
        if ($request->hasFile('receipt_image')) {
            $file = $request->file('receipt_image');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/receipts'), $filename);
            $receiptPath = 'uploads/receipts/' . $filename;
        }

        $appointment = DB::transaction(function () use ($validated, $receiptPath) {
            $customerCategory = \App\Models\AccountCategory::where('title', 'like', '%Customer%')->first() 
                ?? \App\Models\AccountCategory::first();

            $customer = Account::where('phone_no1', $validated['customer_phone'])->first();

            if (!$customer) {
                $customer = Account::create([
                    'name' => $validated['customer_name'],
                    'phone_no1' => $validated['customer_phone'],
                    'account_category_id' => $customerCategory ? $customerCategory->id : 1,
                    'balance' => 0,
                ]);
            }

            // Fetch default employee / staff account if exists
            $employee = Account::whereHas('category', function ($q) {
                $q->where('title', 'like', '%Employee%')
                  ->orWhere('title', 'like', '%Staff%');
            })->first();

            $employeeId = $employee ? $employee->id : $customer->id;

            $services = SaloonService::whereIn('id', $validated['service_ids'])->get();
            $isSenior = $employee && strtolower($employee->emp_type ?? '') === 'senior';
            $grossTotal = 0;
            $totalCommission = 0;
            $serviceCommissions = [];

            foreach ($services as $srv) {
                $grossTotal += (float) ($srv->discounted_price ?? $srv->calculateDiscountedPrice());
                $comm = $isSenior
                    ? (float) ($srv->senior_commission ?? $srv->commission ?? 0)
                    : (float) ($srv->junior_commission ?? $srv->commission ?? 0);
                $serviceCommissions[$srv->id] = $comm;
                $totalCommission += $comm;
            }

            $bookingNo = 'APT-' . date('Ym') . '-' . str_pad(Appointment::count() + 1, 4, '0', STR_PAD_LEFT);

            $appointment = Appointment::create([
                'booking_no' => $bookingNo,
                'account_id' => $customer->id,
                'employee_id' => $employeeId,
                'appointment_date' => $validated['appointment_date'],
                'start_time' => $validated['start_time'] ?? null,
                'total_amount' => $grossTotal,
                'discount' => 0,
                'net_amount' => $grossTotal,
                'paid_amount' => 0,
                'balance_due' => $grossTotal,
                'total_commission' => $totalCommission,
                'status' => 'pending',
                'notes' => ($validated['notes'] ?? '') . " [Front-end online booking: {$validated['customer_name']} - Phone: {$validated['customer_phone']}]",
                'receipt_image' => $receiptPath ? url($receiptPath) : null,
            ]);

            foreach ($services as $srv) {
                AppointmentService::create([
                    'appointment_id' => $appointment->id,
                    'saloon_service_id' => $srv->id,
                    'price' => $srv->price,
                    'discount' => $srv->discount,
                    'discounted_price' => $srv->discounted_price ?? $srv->calculateDiscountedPrice(),
                    'commission' => $serviceCommissions[$srv->id] ?? 0,
                ]);
            }

            return $appointment;
        });

        $formattedDate = is_string($appointment->appointment_date)
            ? $appointment->appointment_date
            : $appointment->appointment_date->format('Y-m-d');

        return response()->json([
            'success' => true,
            'message' => 'Appointment created successfully',
            'data' => [
                'booking_no' => $appointment->booking_no,
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'appointment_date' => $formattedDate,
                'start_time' => $appointment->start_time,
                'net_amount' => (float) $appointment->net_amount,
                'status' => $appointment->status,
            ],
        ], 201);
    }

    /**
     * Submit contact form message.
     */
    public function submitContact(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $contact = ContactMessage::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Thank you for reaching out! Your message has been received.',
            'data' => [
                'id' => $contact->id,
                'created_at' => $contact->created_at,
            ],
        ], 201);
    }

    /**
     * Customer Sign Up API for front-end website.
     */
    public function customerSignup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'phone_no1' => ['required', 'string', 'max:50'],
            'username' => ['required', 'string', 'max:255', 'unique:accounts,username'],
            'password' => ['required', 'string', 'min:6'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'date_of_birth' => ['nullable', 'date'],
            'date_of_anniversary' => ['nullable', 'date'],
            'card_type' => ['nullable', 'string', 'in:No Card,Silver,Gold,Platinum'],
            'card_no' => ['nullable', 'string', 'max:50'],
            'phone_no2' => ['nullable', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $customerCategory = \App\Models\AccountCategory::where('title', 'like', '%Customer%')->first() 
            ?? \App\Models\AccountCategory::first();

        $existingCustomer = Account::where('phone_no1', $validated['phone_no1'])->first();

        if ($existingCustomer) {
            if (!empty($existingCustomer->username)) {
                return response()->json([
                    'success' => false,
                    'message' => 'An account with this phone number already exists.',
                    'errors' => ['phone_no1' => ['Phone number already registered.']]
                ], 422);
            }

            // Update existing guest/customer record with full account login credentials
            $existingCustomer->update([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
                'father_name' => $validated['father_name'] ?? $existingCustomer->father_name,
                'address' => $validated['address'] ?? $existingCustomer->address,
                'date_of_birth' => $validated['date_of_birth'] ?? $existingCustomer->date_of_birth,
                'date_of_anniversary' => $validated['date_of_anniversary'] ?? $existingCustomer->date_of_anniversary,
                'card_type' => $validated['card_type'] ?? $existingCustomer->card_type,
                'card_no' => $validated['card_no'] ?? $existingCustomer->card_no,
                'phone_no2' => $validated['phone_no2'] ?? $existingCustomer->phone_no2,
            ]);

            $customer = $existingCustomer;
        } else {
            $customer = Account::create([
                'account_category_id' => $customerCategory ? $customerCategory->id : 1,
                'name' => $validated['name'],
                'phone_no1' => $validated['phone_no1'],
                'username' => $validated['username'],
                'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
                'father_name' => $validated['father_name'] ?? null,
                'address' => $validated['address'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'date_of_anniversary' => $validated['date_of_anniversary'] ?? null,
                'card_type' => $validated['card_type'] ?? null,
                'card_no' => $validated['card_no'] ?? null,
                'phone_no2' => $validated['phone_no2'] ?? null,
                'balance' => 0.00,
            ]);
        }

        $dob = $customer->date_of_birth ? (is_string($customer->date_of_birth) ? $customer->date_of_birth : $customer->date_of_birth->format('Y-m-d')) : null;
        $anniv = $customer->date_of_anniversary ? (is_string($customer->date_of_anniversary) ? $customer->date_of_anniversary : $customer->date_of_anniversary->format('Y-m-d')) : null;

        return response()->json([
            'success' => true,
            'message' => 'Customer registered successfully',
            'data' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'username' => $customer->username,
                'phone_no1' => $customer->phone_no1,
                'phone_no2' => $customer->phone_no2,
                'father_name' => $customer->father_name,
                'address' => $customer->address,
                'card_type' => $customer->card_type,
                'card_no' => $customer->card_no,
                'date_of_birth' => $dob,
                'date_of_anniversary' => $anniv,
                'balance' => (float) $customer->balance,
                'created_at' => $customer->created_at,
            ],
        ], 201);
    }

    /**
     * Customer Login API for front-end website.
     */
    public function customerLogin(Request $request)
    {
        $loginKey = $request->has('login') ? 'login' : ($request->has('username') ? 'username' : 'phone_no1');
        
        $validator = Validator::make($request->all(), [
            $loginKey => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $loginVal = $request->input($loginKey);
        $password = $request->input('password');

        $customer = Account::where(function ($q) use ($loginVal) {
            $q->where('username', $loginVal)
              ->orWhere('phone_no1', $loginVal);
        })->whereHas('category', function ($q) {
            $q->where('title', 'like', '%Customer%')
              ->orWhere('title', 'like', '%Client%')
              ->orWhere('title', 'like', '%VIP%')
              ->orWhere('title', 'like', '%Member%');
        })->first();

        // Also fallback to search by username/phone if category check is permissive
        if (!$customer) {
            $customer = Account::where(function ($q) use ($loginVal) {
                $q->where('username', $loginVal)
                  ->orWhere('phone_no1', $loginVal);
            })->whereNotNull('password')->first();
        }

        if (!$customer || !$customer->password || !\Illuminate\Support\Facades\Hash::check($password, $customer->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid username/phone or password.',
            ], 401);
        }

        $dob = $customer->date_of_birth ? (is_string($customer->date_of_birth) ? $customer->date_of_birth : $customer->date_of_birth->format('Y-m-d')) : null;
        $anniv = $customer->date_of_anniversary ? (is_string($customer->date_of_anniversary) ? $customer->date_of_anniversary : $customer->date_of_anniversary->format('Y-m-d')) : null;

        return response()->json([
            'success' => true,
            'message' => 'Customer logged in successfully',
            'data' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'username' => $customer->username,
                'phone_no1' => $customer->phone_no1,
                'phone_no2' => $customer->phone_no2,
                'father_name' => $customer->father_name,
                'address' => $customer->address,
                'card_type' => $customer->card_type,
                'card_no' => $customer->card_no,
                'date_of_birth' => $dob,
                'date_of_anniversary' => $anniv,
                'balance' => (float) $customer->balance,
            ],
        ]);
    }
}
