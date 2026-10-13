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
        // 1. Create product_main_categories table
        if (!Schema::hasTable('product_main_categories')) {
            Schema::create('product_main_categories', function (Blueprint $table) {
                $table->id();
                $table->string('title')->unique();
                $table->text('description')->nullable();
                $table->string('image')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. Add product_main_category_id to product_categories table
        if (Schema::hasTable('product_categories') && !Schema::hasColumn('product_categories', 'product_main_category_id')) {
            Schema::table('product_categories', function (Blueprint $table) {
                $table->foreignId('product_main_category_id')->nullable()->after('id')->constrained('product_main_categories')->nullOnDelete();
            });
        }

        // 3. Add product_main_category_id to products table
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'product_main_category_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('product_main_category_id')->nullable()->after('product_category_id')->constrained('product_main_categories')->nullOnDelete();
            });
        }

        // 4. Add product_main_category_id to purchases table
        if (Schema::hasTable('purchases') && !Schema::hasColumn('purchases', 'product_main_category_id')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->foreignId('product_main_category_id')->nullable()->after('account_id')->constrained('product_main_categories')->nullOnDelete();
            });
        }

        // 5. Add product_main_category_id to sales table
        if (Schema::hasTable('sales') && !Schema::hasColumn('sales', 'product_main_category_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->foreignId('product_main_category_id')->nullable()->after('account_id')->constrained('product_main_categories')->nullOnDelete();
            });
        }

        // 6. Add category transfer columns to stock_transfers table
        if (Schema::hasTable('stock_transfers')) {
            Schema::table('stock_transfers', function (Blueprint $table) {
                if (!Schema::hasColumn('stock_transfers', 'source_main_category_id')) {
                    $table->foreignId('source_main_category_id')->nullable()->after('product_id')->constrained('product_main_categories')->nullOnDelete();
                }
                if (!Schema::hasColumn('stock_transfers', 'destination_main_category_id')) {
                    $table->foreignId('destination_main_category_id')->nullable()->after('source_main_category_id')->constrained('product_main_categories')->nullOnDelete();
                }
                if (!Schema::hasColumn('stock_transfers', 'source_category_id')) {
                    $table->foreignId('source_category_id')->nullable()->after('destination_main_category_id')->constrained('product_categories')->nullOnDelete();
                }
                if (!Schema::hasColumn('stock_transfers', 'destination_category_id')) {
                    $table->foreignId('destination_category_id')->nullable()->after('source_category_id')->constrained('product_categories')->nullOnDelete();
                }
            });
        }

        // 7. Seed Initial Main Categories and link existing categories & products
        $defaultMainCategories = [
            ['title' => 'Retail Products', 'description' => 'Retail cosmetics and hair care products for clients'],
            ['title' => 'Salon Use / Professional', 'description' => 'In-house professional salon supplies and consumables'],
            ['title' => 'Cosmetics & Skincare', 'description' => 'Skin care, facial kits and beautician supplies'],
        ];

        foreach ($defaultMainCategories as $cat) {
            $exists = DB::table('product_main_categories')->where('title', $cat['title'])->first();
            if (!$exists) {
                DB::table('product_main_categories')->insert([
                    'title' => $cat['title'],
                    'description' => $cat['description'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $retailMainCat = DB::table('product_main_categories')->where('title', 'Retail Products')->first();
        if ($retailMainCat) {
            // Assign existing sub-categories without main category to Retail Products
            DB::table('product_categories')
                ->whereNull('product_main_category_id')
                ->update(['product_main_category_id' => $retailMainCat->id]);

            // Assign products to that main category based on their sub-category or default
            $categories = DB::table('product_categories')->pluck('product_main_category_id', 'id');
            foreach ($categories as $catId => $mainCatId) {
                DB::table('products')
                    ->where('product_category_id', $catId)
                    ->whereNull('product_main_category_id')
                    ->update(['product_main_category_id' => $mainCatId]);
            }

            // Any remaining products without category get default main category
            DB::table('products')
                ->whereNull('product_main_category_id')
                ->update(['product_main_category_id' => $retailMainCat->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('stock_transfers')) {
            Schema::table('stock_transfers', function (Blueprint $table) {
                if (Schema::hasColumn('stock_transfers', 'destination_category_id')) {
                    $table->dropConstrainedForeignId('destination_category_id');
                }
                if (Schema::hasColumn('stock_transfers', 'source_category_id')) {
                    $table->dropConstrainedForeignId('source_category_id');
                }
                if (Schema::hasColumn('stock_transfers', 'destination_main_category_id')) {
                    $table->dropConstrainedForeignId('destination_main_category_id');
                }
                if (Schema::hasColumn('stock_transfers', 'source_main_category_id')) {
                    $table->dropConstrainedForeignId('source_main_category_id');
                }
            });
        }

        if (Schema::hasColumn('sales', 'product_main_category_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropConstrainedForeignId('product_main_category_id');
            });
        }

        if (Schema::hasColumn('purchases', 'product_main_category_id')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->dropConstrainedForeignId('product_main_category_id');
            });
        }

        if (Schema::hasColumn('products', 'product_main_category_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropConstrainedForeignId('product_main_category_id');
            });
        }

        if (Schema::hasColumn('product_categories', 'product_main_category_id')) {
            Schema::table('product_categories', function (Blueprint $table) {
                $table->dropConstrainedForeignId('product_main_category_id');
            });
        }

        Schema::dropIfExists('product_main_categories');
    }
};
