<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create permission_user pivot table
        if (!Schema::hasTable('permission_user')) {
            Schema::create('permission_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('permission_id')->constrained('permissions')->onDelete('cascade');
                $table->timestamps();

                $table->unique(['user_id', 'permission_id']);
            });
        }

        // 2. Define and ensure all system permissions exist
        $permissions = [
            ['slug' => 'manage-appointments', 'name' => 'Manage Appointments & Billing', 'group' => 'Appointments'],
            ['slug' => 'book-appointment', 'name' => 'Book Service Appointment', 'group' => 'Appointments'],
            ['slug' => 'allow-bill-discount', 'name' => 'Allow Bill Discount', 'group' => 'Appointments'],
            ['slug' => 'approve-discounts', 'name' => 'Approve Discount Requests (>10%)', 'group' => 'Appointments'],

            ['slug' => 'manage-services', 'name' => 'Manage Services & Categories', 'group' => 'Services'],
            ['slug' => 'manage-gallery', 'name' => 'Manage Photo Gallery Showcase', 'group' => 'Services'],

            ['slug' => 'manage-sales', 'name' => 'Manage POS & Sales Checkout', 'group' => 'Inventory & POS'],
            ['slug' => 'manage-products', 'name' => 'Manage Products & Stock', 'group' => 'Inventory & POS'],
            ['slug' => 'manage-purchases', 'name' => 'Manage Supplier Purchases', 'group' => 'Inventory & POS'],

            ['slug' => 'manage-accounts', 'name' => 'Manage Customer Accounts', 'group' => 'Finance & Accounts'],
            ['slug' => 'manage-ledger', 'name' => 'Manage General Ledgers', 'group' => 'Finance & Accounts'],
            ['slug' => 'manage-bank-accounts', 'name' => 'Manage Bank Accounts', 'group' => 'Finance & Accounts'],
            ['slug' => 'manage-expenses', 'name' => 'Manage Expenses & Categories', 'group' => 'Finance & Accounts'],
            ['slug' => 'manage-payroll', 'name' => 'Manage Staff Payroll & Deductions', 'group' => 'Finance & Accounts'],
            ['slug' => 'manage-attendance', 'name' => 'Manage Staff Attendance', 'group' => 'Finance & Accounts'],

            ['slug' => 'view-reports', 'name' => 'View Analytics & Reports', 'group' => 'Reports'],

            ['slug' => 'manage-settings', 'name' => 'Manage Brand & System Settings', 'group' => 'Settings & Admin'],
            ['slug' => 'manage-users', 'name' => 'Manage Users & Permissions', 'group' => 'Settings & Admin'],
            ['slug' => 'manage-roles', 'name' => 'Manage Roles & Permissions', 'group' => 'Settings & Admin'],
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(
                ['slug' => $p['slug']],
                ['name' => $p['name']]
            );
        }

        // 3. Migrate existing user permissions from their assigned roles to direct user permissions
        $allPermissions = Permission::all();
        $users = User::with('roles.permissions')->get();

        foreach ($users as $user) {
            if ($user->hasRole('admin')) {
                // Admin gets all permissions directly
                $user->permissions()->syncWithoutDetaching($allPermissions->pluck('id')->toArray());
            } else {
                // Sync permissions from user's current roles
                $rolePermissionIds = $user->roles->flatMap->permissions->pluck('id')->unique()->toArray();
                if (!empty($rolePermissionIds)) {
                    $user->permissions()->syncWithoutDetaching($rolePermissionIds);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permission_user');
    }
};
