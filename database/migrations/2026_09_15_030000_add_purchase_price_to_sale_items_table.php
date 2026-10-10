<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('sale_items') && !Schema::hasColumn('sale_items', 'purchase_price')) {
            Schema::table('sale_items', function (Blueprint $table) {
                $table->decimal('purchase_price', 12, 2)->default(0.00)->after('quantity');
            });
        }

        // Backfill existing sale_items purchase_price from product's purchase_price or price
        if (Schema::hasTable('sale_items') && Schema::hasColumn('sale_items', 'purchase_price')) {
            DB::statement("
                UPDATE sale_items si
                JOIN products p ON si.product_id = p.id
                SET si.purchase_price = COALESCE(p.purchase_price, p.price, 0.00)
                WHERE si.purchase_price = 0.00 OR si.purchase_price IS NULL
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sale_items') && Schema::hasColumn('sale_items', 'purchase_price')) {
            Schema::table('sale_items', function (Blueprint $table) {
                $table->dropColumn('purchase_price');
            });
        }
    }
};
