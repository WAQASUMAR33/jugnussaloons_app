<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleAndUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Roles
        $adminRole = Role::firstOrCreate(['slug' => 'admin'], [
            'name' => 'Administrator',
            'description' => 'Full control over system resources, settings, and users',
        ]);

        $managerRole = Role::firstOrCreate(['slug' => 'manager'], [
            'name' => 'Manager',
            'description' => 'Access to managerial operations, staff schedules, accounts, payroll, and reports',
        ]);

        $accountantRole = Role::firstOrCreate(['slug' => 'accountant'], [
            'name' => 'Accountant',
            'description' => 'Finance, accounts, ledgers, payroll, and expense management',
        ]);

        $salesPersonRole = Role::firstOrCreate(['slug' => 'sales-person'], [
            'name' => 'Sales Person',
            'description' => 'POS sale checkout, product inventory, and billing',
        ]);

        $receptionistRole = Role::firstOrCreate(['slug' => 'receptionist'], [
            'name' => 'Receptionist',
            'description' => 'Appointment bookings, customer directory, and front desk reception',
        ]);

        $staffRole = Role::firstOrCreate(['slug' => 'staff'], [
            'name' => 'Staff / Stylist',
            'description' => 'Salon treatments, services catalog, and staff ratings',
        ]);

        $customerRole = Role::firstOrCreate(['slug' => 'customer'], [
            'name' => 'Customer',
            'description' => 'Standard client access to book saloon services and view appointments',
        ]);

        $userRole = Role::firstOrCreate(['slug' => 'user'], [
            'name' => 'Standard User',
            'description' => 'General user access level',
        ]);

        // 2. Create Granular Page Permissions
        $permissionsList = [
            // Core & Appointments
            ['slug' => 'manage-appointments', 'name' => 'Appointments Calendar & History'],
            ['slug' => 'book-appointment', 'name' => 'Book Appointment & Billing'],
            ['slug' => 'manage-ratings', 'name' => 'Staff Feedback & Ratings'],
            ['slug' => 'allow-bill-discount', 'name' => 'Allow Bill Discount Authority'],
            ['slug' => 'approve-discounts', 'name' => 'Approve High Discount Requests (>10%)'],

            // Saloon Operations & Treatments
            ['slug' => 'manage-services', 'name' => 'Treatments & Services Catalog'],
            ['slug' => 'manage-service-categories', 'name' => 'Service Categories'],
            ['slug' => 'manage-gallery', 'name' => 'Photo Gallery Showcase'],

            // Products, Inventory & POS
            ['slug' => 'manage-sales', 'name' => 'POS Sale Checkout'],
            ['slug' => 'manage-products', 'name' => 'Products Inventory'],
            ['slug' => 'manage-product-categories', 'name' => 'Product Categories'],
            ['slug' => 'manage-purchases', 'name' => 'Supplier Purchases'],
            ['slug' => 'manage-stores', 'name' => 'Store Locations & Branches'],
            ['slug' => 'manage-store-stocks', 'name' => 'Store-Wise Stock Ledger'],
            ['slug' => 'manage-stock-transfers', 'name' => 'Stock Transfers Between Stores'],

            // Finance, Ledgers & Payroll
            ['slug' => 'manage-accounts', 'name' => 'Customer Accounts & Directory'],
            ['slug' => 'manage-account-categories', 'name' => 'Account Categories'],
            ['slug' => 'manage-ledger', 'name' => 'General Ledgers'],
            ['slug' => 'manage-expenses', 'name' => 'Daily Expenses'],
            ['slug' => 'manage-expense-categories', 'name' => 'Expense Categories'],
            ['slug' => 'manage-payroll', 'name' => 'Staff Monthly Payroll'],
            ['slug' => 'manage-payroll-deductions', 'name' => 'Staff Salary Deductions'],
            ['slug' => 'manage-payroll-advances', 'name' => 'Staff Advance Salary'],
            ['slug' => 'manage-attendance', 'name' => 'Staff Attendance & Timesheets'],
            ['slug' => 'manage-commissions', 'name' => 'Commission Management'],

            // Business Reports & Analytics
            ['slug' => 'view-profit-report', 'name' => 'Profit & Loss Report (P&L)'],
            ['slug' => 'manage-day-closing', 'name' => 'Daily Day Closing & Cash Reconciliation'],
            ['slug' => 'view-sales-reports', 'name' => 'Sales Reports'],
            ['slug' => 'view-stock-reports', 'name' => 'Stock Inventory Reports'],
            ['slug' => 'view-service-reports', 'name' => 'Services Rendered Reports'],
            ['slug' => 'view-ledger-reports', 'name' => 'General Ledger Reports'],
            ['slug' => 'view-purchase-reports', 'name' => 'Supplier Purchases Reports'],
            ['slug' => 'view-reports', 'name' => 'View All Analytics & Reports'],

            // Settings, Banking & Administration
            ['slug' => 'manage-settings', 'name' => 'Salon Branding & Invoice Settings'],
            ['slug' => 'manage-bank-accounts', 'name' => 'Bank Accounts & Digital Vaults'],
            ['slug' => 'manage-users', 'name' => 'Users & Page Permissions'],
            ['slug' => 'manage-roles', 'name' => 'Roles & Access Levels'],
        ];

        $allPermIds = [];
        foreach ($permissionsList as $p) {
            $perm = Permission::updateOrCreate(['slug' => $p['slug']], ['name' => $p['name']]);
            $allPermIds[] = $perm->id;
        }

        // Assign Permissions to Roles
        $adminRole->permissions()->sync($allPermIds);

        $managerPermIds = Permission::whereNotIn('slug', ['manage-users', 'manage-roles', 'view-profit-report', 'manage-day-closing'])->pluck('id')->toArray();
        $managerRole->permissions()->sync($managerPermIds);

        $customerRole->permissions()->sync(
            Permission::whereIn('slug', ['book-appointment', 'manage-appointments'])->pluck('id')->toArray()
        );

        // 3. Create Seeded Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('password123'),
            ]
        );
        $admin->roles()->sync([$adminRole->id]);

        // 4. Create Seeded Manager User
        $manager = User::firstOrCreate(
            ['email' => 'manager@example.com'],
            [
                'name' => 'Saloon Manager',
                'password' => Hash::make('password123'),
            ]
        );
        $manager->roles()->sync([$managerRole->id]);

        // 5. Create Seeded Customer User
        $customer = User::firstOrCreate(
            ['email' => 'customer@example.com'],
            [
                'name' => 'Sarah Johnson',
                'password' => Hash::make('password123'),
            ]
        );
        $customer->roles()->sync([$customerRole->id]);

        // 6. Create Seeded Standard User
        $standardUser = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'John Doe',
                'password' => Hash::make('password123'),
            ]
        );
        $standardUser->roles()->sync([$userRole->id]);
    }
}
