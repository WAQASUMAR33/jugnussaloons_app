<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMainCategory;
use App\Models\ProductStoreStock;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ImageUploadService;

class ProductController extends Controller
{
    /**
     * Display a listing of products with dynamic main category & sub category filters.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $selectedMainCategoryId = $request->input('main_category_id');
        $selectedCategoryId = $request->input('category_id');

        $query = Product::with(['mainCategory', 'category']);

        if ($search) {
            $query->where('title', 'like', "%{$search}%");
        }

        if ($selectedMainCategoryId) {
            $query->where('product_main_category_id', $selectedMainCategoryId);
        }

        if ($selectedCategoryId) {
            $query->where('product_category_id', $selectedCategoryId);
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(12)->withQueryString();
        
        $mainCategories = ProductMainCategory::where('is_active', true)->orderBy('title')->get();
        $categories = ProductCategory::with('mainCategory')->orderBy('title')->get();
        
        $lowStockCount = Product::whereColumn('stock', '<=', 'low_stock')->count();
        $totalStockCount = Product::sum('stock');

        return view('manager.products.index', compact(
            'products', 
            'search', 
            'mainCategories', 
            'selectedMainCategoryId', 
            'categories', 
            'selectedCategoryId', 
            'lowStockCount',
            'totalStockCount'
        ));
    }

    /**
     * Store a newly created product with main category & sub category allocation.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_main_category_id' => ['required', 'exists:product_main_categories,id'],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discounted_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'low_stock' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp,avif', 'max:10240'],
        ]);

        $price = (float) $validated['price'];
        $discount = (float) ($validated['discount'] ?? 0);
        $discountedPrice = isset($validated['discounted_price']) && $validated['discounted_price'] > 0
            ? (float) $validated['discounted_price']
            : round($price - ($price * ($discount / 100)), 2);

        // Guard against duplicate rapid submissions within 3 seconds
        $recentDuplicate = Product::where('title', $validated['title'])
            ->where('price', $price)
            ->where('created_at', '>=', now()->subSeconds(3))
            ->first();
        if ($recentDuplicate) {
            return redirect()->route('manager.products.index')
                ->with('success', "Product '{$recentDuplicate->title}' created successfully!");
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = ImageUploadService::upload($request->file('image'), 'products');
        }

        $defaultLowStock = isset($validated['low_stock']) ? (int) $validated['low_stock'] : 5;
        $initialStock = isset($validated['stock']) ? max(0, (int) $validated['stock']) : 0;

        $product = DB::transaction(function () use ($validated, $price, $discount, $discountedPrice, $defaultLowStock, $initialStock, $imagePath) {
            $product = Product::create([
                'product_main_category_id' => $validated['product_main_category_id'],
                'product_category_id' => $validated['product_category_id'] ?? null,
                'title' => $validated['title'],
                'price' => $price,
                'discount' => $discount,
                'discounted_price' => $discountedPrice,
                'stock' => $initialStock,
                'low_stock' => $defaultLowStock,
                'image' => $imagePath,
            ]);

            // Sync with default store stock for backward compatibility
            try {
                $defaultStore = Store::getDefaultStore();
                if ($defaultStore) {
                    ProductStoreStock::updateOrCreate(
                        ['product_id' => $product->id, 'store_id' => $defaultStore->id],
                        ['stock' => $initialStock, 'low_stock' => $defaultLowStock]
                    );
                }
            } catch (\Throwable $e) {}

            return $product;
        });

        return redirect()->route('manager.products.index')
            ->with('success', "Product '{$product->title}' created successfully in '{$product->mainCategory?->title}'!");
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'product_main_category_id' => ['required', 'exists:product_main_categories,id'],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discounted_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'low_stock' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp,avif', 'max:10240'],
        ]);

        $price = (float) $validated['price'];
        $discount = (float) ($validated['discount'] ?? 0);
        $discountedPrice = isset($validated['discounted_price']) && $validated['discounted_price'] > 0
            ? (float) $validated['discounted_price']
            : round($price - ($price * ($discount / 100)), 2);

        $imagePath = $product->image;
        if ($request->hasFile('image')) {
            ImageUploadService::delete($product->image);
            $imagePath = ImageUploadService::upload($request->file('image'), 'products');
        }

        $defaultLowStock = isset($validated['low_stock']) ? (int) $validated['low_stock'] : ($product->low_stock ?? 5);
        $updatedStock = isset($validated['stock']) ? max(0, (int) $validated['stock']) : (int) $product->stock;

        DB::transaction(function () use ($product, $validated, $price, $discount, $discountedPrice, $defaultLowStock, $updatedStock, $imagePath) {
            $product->update([
                'product_main_category_id' => $validated['product_main_category_id'],
                'product_category_id' => $validated['product_category_id'] ?? null,
                'title' => $validated['title'],
                'price' => $price,
                'discount' => $discount,
                'discounted_price' => $discountedPrice,
                'stock' => $updatedStock,
                'low_stock' => $defaultLowStock,
                'image' => $imagePath,
            ]);

            // Sync with default store stock for backward compatibility
            try {
                $defaultStore = Store::getDefaultStore();
                if ($defaultStore) {
                    ProductStoreStock::updateOrCreate(
                        ['product_id' => $product->id, 'store_id' => $defaultStore->id],
                        ['stock' => $updatedStock, 'low_stock' => $defaultLowStock]
                    );
                }
            } catch (\Throwable $e) {}
        });

        return redirect()->route('manager.products.index')
            ->with('success', "Product '{$product->title}' updated successfully!");
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Product $product)
    {
        ImageUploadService::delete($product->image);

        try {
            $product->storeStocks()->delete();
        } catch (\Throwable $e) {}

        $product->delete();

        return redirect()->route('manager.products.index')
            ->with('success', 'Product deleted successfully!');
    }
}
