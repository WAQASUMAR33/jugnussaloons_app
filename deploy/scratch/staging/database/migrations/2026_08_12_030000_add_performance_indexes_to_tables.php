<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run performance indexing migrations.
     */
    public function up(): void
    {
        $safeAddIndex = function ($table, $column) {
            try {
                if (Schema::hasColumn($table, $column)) {
                    Schema::table($table, function (Blueprint $t) use ($column) {
                        $t->index($column);
                    });
                }
            } catch (\Throwable $e) {
                // Ignore duplicate index errors
            }
        };

        // 1. Accounts
        $safeAddIndex('accounts', 'account_category_id');
        $safeAddIndex('accounts', 'name');
        $safeAddIndex('accounts', 'phone_no1');
        $safeAddIndex('accounts', 'emp_type');

        // 2. Account Ledgers
        $safeAddIndex('account_ledgers', 'account_id');
        $safeAddIndex('account_ledgers', 'date');
        $safeAddIndex('account_ledgers', 'type');
        $safeAddIndex('account_ledgers', 'reference_no');

        // 3. Appointments
        $safeAddIndex('appointments', 'account_id');
        $safeAddIndex('appointments', 'employee_id');
        $safeAddIndex('appointments', 'appointment_date');
        $safeAddIndex('appointments', 'status');
        $safeAddIndex('appointments', 'booking_no');

        // 4. Appointment Services
        $safeAddIndex('appointment_services', 'appointment_id');
        $safeAddIndex('appointment_services', 'saloon_service_id');

        // 5. Purchases & Items
        $safeAddIndex('purchases', 'account_id');
        $safeAddIndex('purchases', 'purchase_date');
        $safeAddIndex('purchase_items', 'purchase_id');
        $safeAddIndex('purchase_items', 'product_id');

        // 6. Sales & Items
        $safeAddIndex('sales', 'account_id');
        $safeAddIndex('sales', 'sale_date');
        $safeAddIndex('sale_items', 'sale_id');
        $safeAddIndex('sale_items', 'product_id');

        // 7. Products
        $safeAddIndex('products', 'product_category_id');
        $safeAddIndex('products', 'name');
        $safeAddIndex('products', 'stock');

        // 8. Expenses
        $safeAddIndex('expenses', 'exp_category_id');
    }

    /**
     * Reverse performance indexing migrations.
     */
    public function down(): void
    {
    }
};
