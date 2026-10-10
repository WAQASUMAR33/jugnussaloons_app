<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\EmployeeReview;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StaffRatingController extends Controller
{
    /**
     * Display the dedicated Staff Feedback & Rating page.
     */
    public function index(Request $request)
    {
        $settings = Setting::getSettings();
        $brandName = $settings->brand_name ?: 'Salon & Spa';

        // Retrieve active staff / stylist employees
        $employees = Account::where(function($q) {
            $q->whereHas('category', function ($cq) {
                $cq->where('title', 'like', '%Employee%')
                   ->orWhere('title', 'like', '%Staff%')
                   ->orWhere('title', 'like', '%Stylist%')
                   ->orWhere('title', 'like', '%Barber%');
            })->orWhere(function($sq) {
                $sq->whereNotNull('emp_type')->where('emp_type', '!=', '');
            });
        })->whereDoesntHave('category', function($q) {
            $q->where('title', 'like', '%Customer%')
              ->orWhere('title', 'like', '%Client%')
              ->orWhere('title', 'like', '%Supplier%')
              ->orWhere('title', 'like', '%Vendor%');
        })->with(['category', 'reviews'])->orderBy('name')->get();

        if ($employees->isEmpty()) {
            $employees = Account::where(function($q) {
                $q->whereNotNull('emp_type')->where('emp_type', '!=', '');
            })->with(['category', 'reviews'])->orderBy('name')->get();
        }

        if ($employees->isEmpty()) {
            $employees = Account::whereDoesntHave('category', function($q) {
                $q->where('title', 'like', '%Supplier%')
                  ->orWhere('title', 'like', '%Vendor%');
            })->with(['category', 'reviews'])->orderBy('name')->take(10)->get();
        }

        // Format employee cards with display data
        $stylists = $employees->map(function ($emp) {
            $nameParts = explode(' ', trim($emp->name));
            $firstName = $nameParts[0] ?? 'Stylist';
            $lastInitial = isset($nameParts[1]) ? ' ' . strtoupper(substr($nameParts[1], 0, 1)) . '.' : '';
            $shortName = $firstName . $lastInitial;

            // Determine specialization title
            $specialty = '';
            if ($emp->category && $emp->category->title) {
                $specialty = str_ireplace(['owner', 'Staff', 'Employee', 'Saloon'], '', $emp->category->title);
                $specialty = trim($specialty, " -/,\t\n\r\0\x0B");
            }
            if (empty($specialty)) {
                $empType = strtolower(trim($emp->emp_type ?? ''));
                if ($empType === 'senior') {
                    $specialty = 'Senior Stylist';
                } elseif ($empType === 'junior') {
                    $specialty = 'Junior Stylist';
                } else {
                    $specialty = $empType ? ucfirst($empType) : 'Stylist';
                }
            }

            // Image URL or fallback avatar
            $avatarUrl = null;
            if ($emp->image) {
                $avatarUrl = asset('storage/' . $emp->image);
            } else {
                // Stylish SVG avatar placeholder
                $avatarUrl = 'https://ui-avatars.com/api/?name=' . urlencode($emp->name) . '&background=134E4A&color=FDE047&size=200&bold=true';
            }

            $avgRating = $emp->average_ranking > 0 ? $emp->average_ranking : 5.0;

            return [
                'id' => $emp->id,
                'full_name' => $emp->name,
                'short_name' => $shortName,
                'first_name' => $firstName,
                'specialty' => $specialty,
                'avatar' => $avatarUrl,
                'avg_rating' => $avgRating,
                'reviews_count' => $emp->rankings_count,
            ];
        });

        // Pre-selection params from query string
        $preselectedEmployeeId = (int) $request->input('employee_id', 0);
        $appointmentId = (int) $request->input('appointment_id', 0);
        $preselectedAppointment = null;

        if ($appointmentId) {
            $preselectedAppointment = Appointment::with(['customer', 'employee'])->find($appointmentId);
            if ($preselectedAppointment && !$preselectedEmployeeId && $preselectedAppointment->employee_id) {
                $preselectedEmployeeId = $preselectedAppointment->employee_id;
            }
        }

        if (!$preselectedEmployeeId && $stylists->isNotEmpty()) {
            $preselectedEmployeeId = $stylists->first()['id'];
        }

        // Reviews History & KPI Metrics for Manager Overview
        $search = $request->input('search');
        $filterEmployeeId = $request->input('filter_employee_id');
        $filterRating = $request->input('filter_rating');

        $reviewsQuery = EmployeeReview::with(['employee.category', 'customer', 'appointment'])
            ->orderBy('id', 'desc');

        if ($search) {
            $reviewsQuery->where(function($q) use ($search) {
                $q->where('comments', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhereHas('employee', fn($eq) => $eq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('customer', fn($cq) => $cq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('appointment', fn($aq) => $aq->where('booking_no', 'like', "%{$search}%"));
            });
        }

        if ($filterEmployeeId) {
            $reviewsQuery->where('employee_id', $filterEmployeeId);
        }

        if ($filterRating) {
            $reviewsQuery->where('rating', (int)$filterRating);
        }

        $totalReviewsCount = EmployeeReview::count();
        $avgScore = round((float) (EmployeeReview::avg('rating') ?: 5.0), 1);
        $fiveStarReviewsCount = EmployeeReview::where('rating', 5)->count();
        $topStylist = $stylists->sortByDesc('avg_rating')->first();

        $recentReviews = $reviewsQuery->paginate(15)->withQueryString();

        return view('manager.ratings.index', compact(
            'brandName',
            'stylists',
            'preselectedEmployeeId',
            'appointmentId',
            'preselectedAppointment',
            'recentReviews',
            'totalReviewsCount',
            'avgScore',
            'fiveStarReviewsCount',
            'topStylist',
            'search',
            'filterEmployeeId',
            'filterRating'
        ));
    }

    /**
     * Store submitted staff feedback and rating.
     */
    public function store(Request $request)
    {
        // Normalize appointment_id: convert empty string, 0, or non-positive value to null
        if (!$request->filled('appointment_id') || (int) $request->input('appointment_id') <= 0) {
            $request->merge(['appointment_id' => null]);
        }

        $validated = $request->validate([
            'employee_id' => ['required', 'exists:accounts,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comments' => ['nullable', 'string', 'max:2000'],
            'is_anonymous' => ['nullable'],
            'appointment_id' => ['nullable', 'exists:appointments,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
        ]);

        $employee = Account::findOrFail($validated['employee_id']);
        $isAnonymous = $request->has('is_anonymous') && filter_var($request->input('is_anonymous'), FILTER_VALIDATE_BOOLEAN);

        $customerId = null;
        $appointment = null;

        if (!empty($validated['appointment_id'])) {
            $appointment = Appointment::find($validated['appointment_id']);
            if ($appointment) {
                $customerId = $appointment->account_id;
            }
        }

        // Create the dedicated employee review record
        $review = EmployeeReview::create([
            'employee_id' => $employee->id,
            'customer_id' => $customerId,
            'appointment_id' => $appointment ? $appointment->id : null,
            'rating' => (int) $validated['rating'],
            'comments' => $validated['comments'] ?? null,
            'is_anonymous' => $isAnonymous,
            'customer_name' => $isAnonymous ? null : ($validated['customer_name'] ?? null),
            'customer_phone' => $isAnonymous ? null : ($validated['customer_phone'] ?? null),
            'ip_address' => $request->ip(),
        ]);

        // Sync with appointment table if linked
        if ($appointment) {
            $appointment->update([
                'ranking' => (int) $validated['rating'],
                'ranking_notes' => $validated['comments'] ?? null,
                'ranked_by' => Auth::check() ? Auth::id() : null,
                'ranked_at' => now(),
            ]);
        }

        $stylistFirstName = explode(' ', trim($employee->name))[0] ?? 'our stylist';
        $successMessage = "Thank you! Your {$validated['rating']}-star review for {$stylistFirstName} has been submitted successfully.";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'review' => $review,
            ]);
        }

        if ($request->routeIs('public.*')) {
            return redirect()->route('public.feedback')
                ->with('success', $successMessage);
        }

        return redirect()->back()
            ->with('success', $successMessage);
    }

    /**
     * Dedicated full-screen kiosk / client feedback screen.
     */
    public function publicFeedback(Request $request)
    {
        $settings = Setting::getSettings();
        $brandName = $settings->brand_name ?: 'Salon & Spa';

        $employees = Account::where(function($q) {
            $q->whereHas('category', function ($cq) {
                $cq->where('title', 'like', '%Employee%')
                   ->orWhere('title', 'like', '%Staff%')
                   ->orWhere('title', 'like', '%Stylist%')
                   ->orWhere('title', 'like', '%Barber%');
            })->orWhere(function($sq) {
                $sq->whereNotNull('emp_type')->where('emp_type', '!=', '');
            });
        })->whereDoesntHave('category', function($q) {
            $q->where('title', 'like', '%Customer%')
              ->orWhere('title', 'like', '%Client%')
              ->orWhere('title', 'like', '%Supplier%')
              ->orWhere('title', 'like', '%Vendor%');
        })->with(['category', 'reviews'])->orderBy('name')->get();

        if ($employees->isEmpty()) {
            $employees = Account::whereDoesntHave('category', function($q) {
                $q->where('title', 'like', '%Supplier%')
                  ->orWhere('title', 'like', '%Vendor%');
            })->with(['category', 'reviews'])->orderBy('name')->take(10)->get();
        }

        $stylists = $employees->map(function ($emp) {
            $nameParts = explode(' ', trim($emp->name));
            $firstName = $nameParts[0] ?? 'Stylist';
            $lastInitial = isset($nameParts[1]) ? ' ' . strtoupper(substr($nameParts[1], 0, 1)) . '.' : '';
            $shortName = $firstName . $lastInitial;

            $specialty = '';
            if ($emp->category && $emp->category->title) {
                $specialty = str_ireplace(['owner', 'Staff', 'Employee', 'Saloon'], '', $emp->category->title);
                $specialty = trim($specialty, " -/,\t\n\r\0\x0B");
            }
            if (empty($specialty)) {
                $empType = strtolower(trim($emp->emp_type ?? ''));
                if ($empType === 'senior') {
                    $specialty = 'Senior Stylist';
                } elseif ($empType === 'junior') {
                    $specialty = 'Junior Stylist';
                } else {
                    $specialty = $empType ? ucfirst($empType) : 'Stylist';
                }
            }

            $avatarUrl = $emp->image 
                ? asset('storage/' . $emp->image)
                : 'https://ui-avatars.com/api/?name=' . urlencode($emp->name) . '&background=134E4A&color=FDE047&size=200&bold=true';

            return [
                'id' => $emp->id,
                'full_name' => $emp->name,
                'short_name' => $shortName,
                'first_name' => $firstName,
                'specialty' => $specialty,
                'avatar' => $avatarUrl,
                'avg_rating' => $emp->average_ranking > 0 ? $emp->average_ranking : 5.0,
                'reviews_count' => $emp->rankings_count,
            ];
        });

        $preselectedEmployeeId = (int) $request->input('employee_id', 0);
        $appointmentId = (int) $request->input('appointment_id', 0);
        $preselectedAppointment = $appointmentId ? Appointment::with(['customer', 'employee'])->find($appointmentId) : null;

        if ($preselectedAppointment && !$preselectedEmployeeId && $preselectedAppointment->employee_id) {
            $preselectedEmployeeId = $preselectedAppointment->employee_id;
        }

        if (!$preselectedEmployeeId && $stylists->isNotEmpty()) {
            $preselectedEmployeeId = $stylists->first()['id'];
        }

        return view('manager.ratings.kiosk', compact(
            'brandName',
            'stylists',
            'preselectedEmployeeId',
            'appointmentId',
            'preselectedAppointment'
        ));
    }
}
