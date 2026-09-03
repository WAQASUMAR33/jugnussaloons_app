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
        // 1. Create stores table
        if (!Schema::hasTable('stores')) {
            Schema::create('stores', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->string('address')->nullable();
                $table->string('phone')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }

        // 2. Create product_store_stocks table
        if (!Schema::hasTable('product_store_stocks')) {
            Schema::create('product_store_stocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
                $table->integer('stock')->default(0);
                $table->integer('low_stock')->default(5);
                $table->timestamps();

                $table->unique(['product_id', 'store_id']);
            });
        }

        // 3. Add store_id to purchases if not present
        if (Schema::hasTable('purchases') && !Schema::hasColumn('purchases', 'store_id')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->foreignId('store_id')->nullable()->after('account_id')->constrained('stores')->nullOnDelete();
            });
        }

        // 4. Add store_id to sales if not present
        if (Schema::hasTable('sales') && !Schema::hasColumn('sales', 'store_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->foreignId('store_id')->nullable()->after('account_id')->constrained('stores')->nullOnDelete();
            });
        }

        // 5. Seed default store if none exists
        $defaultStoreId = DB::table('stores')->where('is_default', true)->value('id');
        if (!$defaultStoreId) {
            $defaultStoreId = DB::table('stores')->insertGetId([
                'name' => 'Main Branch Store',
                'code' => 'MAIN',
                'address' => 'Main Salon Commercial District',
                'phone' => '+92 300 1234567',
                'is_active' => true,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 6. Migrate existing products' single stock into product_store_stocks for default store
        $products = DB::table('products')->get(['id', 'stock', 'low_stock']);
        foreach ($products as $p) {
            $exists = DB::table('product_store_stocks')
                ->where('product_id', $p->id)
                ->where('store_id', $defaultStoreId)
                ->exists();

            if (!$exists) {
                DB::table('product_store_stocks')->insert([
                    'product_id' => $p->id,
                    'store_id' => $defaultStoreId,
                    'stock' => (int) ($p->stock ?? 0),
                    'low_stock' => (int) ($p->low_stock ?? 5),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Set default store on existing purchases & sales
        if ($defaultStoreId) {
            DB::table('purchases')->whereNull('store_id')->update(['store_id' => $defaultStoreId]);
            DB::table('sales')->whereNull('store_id')->update(['store_id' => $defaultStoreId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('sales', 'store_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropConstrainedForeignId('store_id');
            });
        }

        if (Schema::hasColumn('purchases', 'store_id')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->dropConstrainedForeignId('store_id');
            });
        }

        Schema::dropIfExists('product_store_stocks');
        Schema::dropIfExists('stores');
    }
};
