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

        $customerRole = Role::firstOrCreate(['slug' => 'customer'], [
            'name' => 'Customer',
            'description' => 'Standard client access to book saloon services and view appointments',
        ]);

        $userRole = Role::firstOrCreate(['slug' => 'user'], [
            'name' => 'Standard User',
            'description' => 'General user access level',
        ]);

        // 2. Create Permissions
        $manageUsers = Permission::firstOrCreate(['slug' => 'manage-users'], ['name' => 'Manage Users']);
        $manageRoles = Permission::firstOrCreate(['slug' => 'manage-roles'], ['name' => 'Manage Roles & Permissions']);
        $viewReports = Permission::firstOrCreate(['slug' => 'view-reports'], ['name' => 'View Analytics & Reports']);
        $manageServices = Permission::firstOrCreate(['slug' => 'manage-services'], ['name' => 'Manage Saloon Services']);
        $manageAppointments = Permission::firstOrCreate(['slug' => 'manage-appointments'], ['name' => 'Manage All Appointments']);
        $bookAppointment = Permission::firstOrCreate(['slug' => 'book-appointment'], ['name' => 'Book Service Appointment']);

        // New Permissions for Recent Pages
        $manageAccounts = Permission::firstOrCreate(['slug' => 'manage-accounts'], ['name' => 'Manage Customer & Party Accounts']);
        $manageSales = Permission::firstOrCreate(['slug' => 'manage-sales'], ['name' => 'Manage POS & Sales']);
        $managePurchases = Permission::firstOrCreate(['slug' => 'manage-purchases'], ['name' => 'Manage Supplier Purchases']);
        $manageExpenses = Permission::firstOrCreate(['slug' => 'manage-expenses'], ['name' => 'Manage Expenses & Categories']);
        $managePayroll = Permission::firstOrCreate(['slug' => 'manage-payroll'], ['name' => 'Manage Employee Payroll']);
        $manageSettings = Permission::firstOrCreate(['slug' => 'manage-settings'], ['name' => 'Manage Brand & System Settings']);
        $manageGallery = Permission::firstOrCreate(['slug' => 'manage-gallery'], ['name' => 'Manage Photo Gallery Showcase']);

        // Assign Permissions to Roles
        $adminRole->permissions()->sync([
            $manageUsers->id, $manageRoles->id, $viewReports->id, 
            $manageServices->id, $manageAppointments->id, $bookAppointment->id,
            $manageAccounts->id, $manageSales->id, $managePurchases->id,
            $manageExpenses->id, $managePayroll->id, $manageSettings->id, $manageGallery->id
        ]);

        $managerRole->permissions()->sync([
            $viewReports->id, $manageServices->id, $manageAppointments->id, $bookAppointment->id,
            $manageAccounts->id, $manageSales->id, $managePurchases->id,
            $manageExpenses->id, $managePayroll->id, $manageGallery->id
        ]);

        $customerRole->permissions()->sync([
            $bookAppointment->id
        ]);

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
