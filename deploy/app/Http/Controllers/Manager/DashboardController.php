<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Sale;
use App\Models\SaloonService;
use App\Services\CelebrationService;
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

        // Birthday & Anniversary Celebrations
        $celebrations = CelebrationService::getCelebrationsData();

        return view('manager.dashboard', array_merge(
            compact(
                'servicesCount', 
                'appointmentsCount', 
                'todaySales'
            ),
            $celebrations
        ));
    }
}
