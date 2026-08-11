<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $usersCount = User::count();
        $rolesCount = Role::count();
        $users = User::with('roles')->latest()->take(5)->get();

        return view('admin.dashboard', compact('usersCount', 'rolesCount', 'users'));
    }
}
