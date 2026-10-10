<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Sale;
use App\Models\SaloonService;
use App\Services\CelebrationService;
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

        // 1. Revenue Metrics (Appointments paid + Product Sales) - Batched in 2 queries
        $appointmentRevs = Appointment::whereIn('appointment_date', [$yesterday, $today])
            ->selectRaw('appointment_date, SUM(paid_amount) as total')
            ->groupBy('appointment_date')
            ->pluck('total', 'appointment_date');

        $todayAppointmentRev = (float) ($appointmentRevs[$today] ?? 0);
        $yesterdayAppointmentRev = (float) ($appointmentRevs[$yesterday] ?? 0);

        $saleRevs = Sale::whereIn('sale_date', [$yesterday, $today])
            ->selectRaw('sale_date, SUM(total_amount) as total')
            ->groupBy('sale_date')
            ->pluck('total', 'sale_date');

        $todaySaleRev = (float) ($saleRevs[$today] ?? 0);
        $yesterdaySaleRev = (float) ($saleRevs[$yesterday] ?? 0);

        $todayRevenue = $todayAppointmentRev + $todaySaleRev;
        $yesterdayRevenue = $yesterdayAppointmentRev + $yesterdaySaleRev;

        $revenueGrowth = 0;
        if ($yesterdayRevenue > 0) {
            $revenueGrowth = round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1);
        } elseif ($todayRevenue > 0) {
            $revenueGrowth = 100;
        }

        // 2. Appointment Metrics - Batched in 1 single query
        $appointmentStats = Appointment::selectRaw(
            "COUNT(*) as total,
             SUM(CASE WHEN appointment_date = ? THEN 1 ELSE 0 END) as today_cnt,
             SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_cnt",
            [$today]
        )->first();

        $todayAppointmentsCount = (int) ($appointmentStats->today_cnt ?? 0);
        $pendingAppointmentsCount = (int) ($appointmentStats->pending_cnt ?? 0);
        $totalAppointmentsCount = (int) ($appointmentStats->total ?? 0);

        // 3. Customer Account Metrics
        $customersQuery = Account::whereHas('category', function ($q) {
            $q->where('title', 'like', '%Customer%')
              ->orWhere('title', 'like', '%Client%')
              ->orWhere('title', 'like', '%VIP%')
              ->orWhere('title', 'like', '%Member%');
        });

        $totalCustomersCount = $customersQuery->count();
        if ($totalCustomersCount === 0) {
            $totalCustomersCount = Account::whereDoesntHave('category', function ($q) {
                $q->where('title', 'like', '%Supplier%')
                  ->orWhere('title', 'like', '%Vendor%')
                  ->orWhere('title', 'like', '%Employee%')
                  ->orWhere('title', 'like', '%Staff%');
            })->count();
        }

        $newCustomersThisWeekCount = Account::where('created_at', '>=', now()->startOfWeek())->count();

        // 4. Staff / Stylist Metrics
        $staffQuery = Account::whereHas('category', function ($q) {
            $q->where('title', 'like', '%Employee%')
              ->orWhere('title', 'like', '%Staff%')
              ->orWhere('title', 'like', '%Stylist%');
        });

        $activeStaffCount = $staffQuery->count();
        if ($activeStaffCount === 0) {
            $activeStaffCount = Account::whereHas('category', function ($q) {
                $q->where('title', 'not like', '%Supplier%')
                  ->where('title', 'not like', '%Vendor%');
            })->count();
        }

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
            // Batch-load all needed services in ONE query instead of N+1
            $serviceIds = $serviceCounts->pluck('saloon_service_id')->all();
            $serviceMap = SaloonService::whereIn('id', $serviceIds)->get()->keyBy('id');

            foreach ($serviceCounts as $sc) {
                $srv = $serviceMap->get($sc->saloon_service_id);
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

        // 7. Birthday & Anniversary Celebrations System
        $celebrations = CelebrationService::getCelebrationsData();

        return view('dashboard', array_merge(
            compact(
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
                'popularServices'
            ),
            $celebrations
        ));
    }
}
