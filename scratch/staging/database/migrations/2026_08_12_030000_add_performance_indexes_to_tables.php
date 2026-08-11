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
        // 1. Indexes for Accounts table
        Schema::table('accounts', function (Blueprint $table) {
            $table->index('account_category_id');
            $table->index('name');
            $table->index('phone_no1');
            $table->index('emp_type');
        });

        // 2. Indexes for Account Ledgers table
        Schema::table('account_ledgers', function (Blueprint $table) {
            $table->index('account_id');
            $table->index('date');
            $table->index('type');
            $table->index('reference_no');
            $table->index(['account_id', 'date']);
        });

        // 3. Indexes for Appointments table
        Schema::table('appointments', function (Blueprint $table) {
            $table->index('account_id');
            $table->index('employee_id');
            $table->index('appointment_date');
            $table->index('status');
            $table->index('booking_no');
            $table->index(['appointment_date', 'status']);
        });

        // 4. Indexes for Appointment Services table
        Schema::table('appointment_services', function (Blueprint $table) {
            $table->index('appointment_id');
            $table->index('saloon_service_id');
        });

        // 5. Indexes for Purchases & Items
        Schema::table('purchases', function (Blueprint $table) {
            $table->index('supplier_account_id');
            $table->index('purchase_date');
            $table->index('invoice_no');
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->index('purchase_id');
            $table->index('product_id');
        });

        // 6. Indexes for Sales & Items
        Schema::table('sales', function (Blueprint $table) {
            $table->index('account_id');
            $table->index('sale_date');
            $table->index('invoice_no');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->index('sale_id');
            $table->index('product_id');
        });

        // 7. Indexes for Products table
        Schema::table('products', function (Blueprint $table) {
            $table->index('product_category_id');
            $table->index('name');
            $table->index('stock_quantity');
        });

        // 8. Indexes for Expenses table
        Schema::table('expenses', function (Blueprint $table) {
            $table->index('exp_category_id');
        });
    }

    /**
     * Reverse performance indexing migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex(['account_category_id']);
            $table->dropIndex(['name']);
            $table->dropIndex(['phone_no1']);
            $table->dropIndex(['emp_type']);
        });

        Schema::table('account_ledgers', function (Blueprint $table) {
            $table->dropIndex(['account_id']);
            $table->dropIndex(['date']);
            $table->dropIndex(['type']);
            $table->dropIndex(['reference_no']);
            $table->dropIndex(['account_id', 'date']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['account_id']);
            $table->dropIndex(['employee_id']);
            $table->dropIndex(['appointment_date']);
            $table->dropIndex(['status']);
            $table->dropIndex(['booking_no']);
            $table->dropIndex(['appointment_date', 'status']);
        });

        Schema::table('appointment_services', function (Blueprint $table) {
            $table->dropIndex(['appointment_id']);
            $table->dropIndex(['saloon_service_id']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex(['supplier_account_id']);
            $table->dropIndex(['purchase_date']);
            $table->dropIndex(['invoice_no']);
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropIndex(['purchase_id']);
            $table->dropIndex(['product_id']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['account_id']);
            $table->dropIndex(['sale_date']);
            $table->dropIndex(['invoice_no']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropIndex(['sale_id']);
            $table->dropIndex(['product_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['product_category_id']);
            $table->dropIndex(['name']);
            $table->dropIndex(['stock_quantity']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['exp_category_id']);
        });
    }
};
