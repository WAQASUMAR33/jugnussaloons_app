<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $usersCount = User::count();
        $rolesCount = Role::count();
        $permissionsCount = Permission::count();
        $users = User::with('roles')->latest()->take(5)->get();

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

        return view('admin.dashboard', compact(
            'usersCount', 
            'rolesCount', 
            'permissionsCount', 
            'users',
            'todayBirthdays',
            'todayAnniversaries'
        ));
    }
}
