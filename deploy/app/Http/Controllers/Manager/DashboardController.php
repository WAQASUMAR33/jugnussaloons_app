<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Sale;
use App\Models\SaloonService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display Manager Control Panel with live dynamic stats.
     */
    public function index()
    {
        $servicesCount = SaloonService::count();
        $appointmentsCount = Appointment::count();
        $todaySales = Sale::whereDate('sale_date', now()->format('Y-m-d'))->sum('total_amount');

        // Today's Birthday & Anniversary Celebrations
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

        return view('manager.dashboard', compact(
            'servicesCount', 
            'appointmentsCount', 
            'todaySales',
            'todayBirthdays',
            'todayAnniversaries'
        ));
    }
}
