<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\ContactMessage;
use App\Models\Product;
use App\Models\SaloonService;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
     * Book a public appointment from the front-end.
     */
    public function bookAppointment(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['nullable', 'string'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['required', 'exists:services,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'receipt_image' => ['nullable', 'file', 'image', 'max:5120'],
        ]);

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

        return response()->json([
            'success' => true,
            'message' => 'Appointment booked successfully! Our team will contact you for confirmation.',
            'data' => [
                'booking_no' => $appointment->booking_no,
                'appointment_date' => $appointment->appointment_date->format('Y-m-d'),
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
}
