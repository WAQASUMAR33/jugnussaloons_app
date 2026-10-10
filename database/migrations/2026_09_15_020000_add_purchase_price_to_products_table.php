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
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'purchase_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('purchase_price', 10, 2)->nullable()->after('price');
            });
        }

        // Backfill purchase_price from latest purchase_items if available
        $products = DB::table('products')->get();
        foreach ($products as $p) {
            $latestPurchaseItem = DB::table('purchase_items')
                ->where('product_id', $p->id)
                ->orderBy('id', 'desc')
                ->first();

            $rate = ($latestPurchaseItem && (float)$latestPurchaseItem->unit_price > 0)
                ? (float)$latestPurchaseItem->unit_price
                : (float)($p->price ?? 0);

            DB::table('products')->where('id', $p->id)->update([
                'purchase_price' => $rate
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'purchase_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('purchase_price');
            });
        }
    }
};
