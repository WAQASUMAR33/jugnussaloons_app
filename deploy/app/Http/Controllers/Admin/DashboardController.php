<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\CelebrationService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $usersCount = User::count();
        $rolesCount = Role::count();
        $permissionsCount = Permission::count();
        $users = User::with('roles')->latest()->take(5)->get();

        // Birthday & Anniversary Celebrations
        $celebrations = CelebrationService::getCelebrationsData();

        return view('admin.dashboard', array_merge(
            compact(
                'usersCount', 
                'rolesCount', 
                'permissionsCount', 
                'users'
            ),
            $celebrations
        ));
    }
}
