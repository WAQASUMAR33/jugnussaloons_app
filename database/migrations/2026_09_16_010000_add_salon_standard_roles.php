<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Accountant Role
        $accountant = Role::firstOrCreate(['slug' => 'accountant'], [
            'name' => 'Accountant',
            'description' => 'Finance, accounts, ledgers, payroll, and expense management',
        ]);

        $accountantPerms = Permission::whereIn('slug', [
            'manage-accounts',
            'manage-account-categories',
            'manage-ledger',
            'manage-expenses',
            'manage-expense-categories',
            'manage-payroll',
            'manage-payroll-deductions',
            'manage-payroll-advances',
            'manage-attendance',
            'manage-commissions',
            'manage-bank-accounts',
            'view-ledger-reports',
            'view-purchase-reports',
            'view-reports',
        ])->pluck('id')->toArray();
        $accountant->permissions()->sync($accountantPerms);

        // 2. Sales Person Role
        $salesPerson = Role::firstOrCreate(['slug' => 'sales-person'], [
            'name' => 'Sales Person',
            'description' => 'POS sale checkout, product inventory, and billing',
        ]);

        $salesPerms = Permission::whereIn('slug', [
            'manage-sales',
            'manage-products',
            'manage-product-categories',
            'book-appointment',
            'manage-appointments',
            'allow-bill-discount',
            'manage-store-stocks',
            'view-sales-reports',
        ])->pluck('id')->toArray();
        $salesPerson->permissions()->sync($salesPerms);

        // 3. Receptionist Role
        $receptionist = Role::firstOrCreate(['slug' => 'receptionist'], [
            'name' => 'Receptionist',
            'description' => 'Appointment bookings, customer directory, and front desk reception',
        ]);

        $receptionistPerms = Permission::whereIn('slug', [
            'manage-appointments',
            'book-appointment',
            'manage-ratings',
            'manage-accounts',
            'manage-gallery',
            'view-service-reports',
        ])->pluck('id')->toArray();
        $receptionist->permissions()->sync($receptionistPerms);

        // 4. Staff / Stylist Role
        $staff = Role::firstOrCreate(['slug' => 'staff'], [
            'name' => 'Staff / Stylist',
            'description' => 'Salon treatments, services catalog, and staff ratings',
        ]);

        $staffPerms = Permission::whereIn('slug', [
            'manage-services',
            'manage-gallery',
            'manage-ratings',
        ])->pluck('id')->toArray();
        $staff->permissions()->sync($staffPerms);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $roles = Role::whereIn('slug', ['accountant', 'sales-person', 'receptionist', 'staff'])->get();
        foreach ($roles as $role) {
            $role->permissions()->detach();
            $role->users()->detach();
            $role->delete();
        }
    }
};
