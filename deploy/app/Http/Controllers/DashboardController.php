<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Sale;
use App\Models\SaloonService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Display the main saloon overview dashboard with live dynamic statistics.
     */
    public function index()
    {
        $today = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');

        // 1. Revenue Metrics (Appointments paid + Product Sales)
        $todayAppointmentRev = (float) Appointment::whereDate('appointment_date', $today)->sum('paid_amount');
        $todaySaleRev = (float) Sale::whereDate('sale_date', $today)->sum('total_amount');
        $todayRevenue = $todayAppointmentRev + $todaySaleRev;

        $yesterdayAppointmentRev = (float) Appointment::whereDate('appointment_date', $yesterday)->sum('paid_amount');
        $yesterdaySaleRev = (float) Sale::whereDate('sale_date', $yesterday)->sum('total_amount');
        $yesterdayRevenue = $yesterdayAppointmentRev + $yesterdaySaleRev;

        $revenueGrowth = 0;
        if ($yesterdayRevenue > 0) {
            $revenueGrowth = round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1);
        } elseif ($todayRevenue > 0) {
            $revenueGrowth = 100;
        }

        // 2. Appointment Metrics
        $todayAppointmentsCount = Appointment::whereDate('appointment_date', $today)->count();
        $pendingAppointmentsCount = Appointment::where('status', 'pending')->count();
        $totalAppointmentsCount = Appointment::count();

        // 3. Customer Account Metrics
        $customersQuery = Account::whereHas('category', function ($q) {
            $q->where('title', 'like', '%Customer%')
              ->orWhere('title', 'like', '%Client%')
              ->orWhere('title', 'like', '%VIP%')
              ->orWhere('title', 'like', '%Member%');
        });

        $totalCustomersCount = $customersQuery->exists()
            ? $customersQuery->count()
            : Account::whereDoesntHave('category', function ($q) {
                $q->where('title', 'like', '%Supplier%')
                  ->orWhere('title', 'like', '%Vendor%')
                  ->orWhere('title', 'like', '%Employee%')
                  ->orWhere('title', 'like', '%Staff%');
            })->count();

        $newCustomersThisWeekCount = Account::where('created_at', '>=', now()->startOfWeek())->count();

        // 4. Staff / Stylist Metrics
        $staffQuery = Account::whereHas('category', function ($q) {
            $q->where('title', 'like', '%Employee%')
              ->orWhere('title', 'like', '%Staff%')
              ->orWhere('title', 'like', '%Stylist%');
        });

        $activeStaffCount = $staffQuery->exists()
            ? $staffQuery->count()
            : Account::whereHas('category', function ($q) {
                $q->where('title', 'not like', '%Supplier%')
                  ->where('title', 'not like', '%Vendor%');
            })->count();

        // 5. Today's Appointments (or Fallback to Recent Appointments if Today is empty)
        $todayAppointments = Appointment::with(['customer', 'employee', 'items.service'])
            ->whereDate('appointment_date', $today)
            ->orderBy('start_time', 'asc')
            ->get();

        $isFallbackSchedule = false;
        if ($todayAppointments->isEmpty()) {
            $todayAppointments = Appointment::with(['customer', 'employee', 'items.service'])
                ->orderBy('appointment_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get();
            $isFallbackSchedule = true;
        }

        // 6. Popular Saloon Services (calculated from AppointmentService occurrences)
        $serviceCounts = DB::table('appointment_services')
            ->select('saloon_service_id', DB::raw('COUNT(*) as total_bookings'))
            ->groupBy('saloon_service_id')
            ->orderByDesc('total_bookings')
            ->limit(4)
            ->get();

        $totalBookingsSum = $serviceCounts->sum('total_bookings');
        $popularServices = collect();

        if ($totalBookingsSum > 0) {
            foreach ($serviceCounts as $sc) {
                $srv = SaloonService::find($sc->saloon_service_id);
                if ($srv) {
                    $pct = round(($sc->total_bookings / $totalBookingsSum) * 100);
                    $popularServices->push([
                        'title' => $srv->title,
                        'count' => $sc->total_bookings,
                        'percentage' => $pct,
                    ]);
                }
            }
        }

        // Fallback: If no appointment service items exist yet, display top services from catalog
        if ($popularServices->isEmpty()) {
            $catalogServices = SaloonService::orderBy('title')->take(4)->get();
            $catalogCount = $catalogServices->count();
            foreach ($catalogServices as $index => $srv) {
                $pct = $catalogCount > 0 ? round(100 / $catalogCount) : 0;
                $popularServices->push([
                    'title' => $srv->title,
                    'count' => 0,
                    'percentage' => $pct,
                ]);
            }
        }

        // 7. Today's Birthday & Anniversary Celebrations
        $todayMonth = now()->month;
        $todayDay = now()->day;

        $todayBirthdays = Account::with('category')
            ->whereMonth('date_of_birth', $todayMonth)
            ->whereDay('date_of_birth', $todayDay)
            ->get();

        $todayAnniversaries = Account::with('category')
            ->whereMonth('date_of_anniversary', $todayMonth)
            ->whereDay('date_of_anniversary', $todayDay)
            ->get();

        return view('dashboard', compact(
            'todayRevenue',
            'yesterdayRevenue',
            'revenueGrowth',
            'todayAppointmentsCount',
            'pendingAppointmentsCount',
            'totalAppointmentsCount',
            'totalCustomersCount',
            'newCustomersThisWeekCount',
            'activeStaffCount',
            'todayAppointments',
            'isFallbackSchedule',
            'popularServices',
            'todayBirthdays',
            'todayAnniversaries'
        ));
    }
}
