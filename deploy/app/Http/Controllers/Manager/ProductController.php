<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductStoreStock;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Display a listing of products with multi-store stock breakdown.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $selectedStoreId = $request->input('store_id');

        $query = Product::with(['storeStocks.store']);

        if ($search) {
            $query->where('title', 'like', "%{$search}%");
        }

        if ($selectedStoreId) {
            $query->whereHas('storeStocks', function ($q) use ($selectedStoreId) {
                $q->where('store_id', $selectedStoreId);
            });
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(12)->withQueryString();
        $stores = Store::where('is_active', true)->orderBy('is_default', 'desc')->orderBy('name')->get();
        
        $lowStockCount = Product::whereColumn('stock', '<=', 'low_stock')->count();

        return view('manager.products.index', compact('products', 'search', 'stores', 'selectedStoreId', 'lowStockCount'));
    }

    /**
     * Store a newly created product with multi-store stock allocation.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discounted_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'low_stock' => ['nullable', 'integer', 'min:0'],
            'store_stocks' => ['nullable', 'array'],
            'store_stocks.*' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:4096'],
        ]);

        $price = (float) $validated['price'];
        $discount = (float) ($validated['discount'] ?? 0);
        $discountedPrice = isset($validated['discounted_price']) && $validated['discounted_price'] > 0
            ? (float) $validated['discounted_price']
            : round($price - ($price * ($discount / 100)), 2);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('products', $fileName, 'public');
            $imagePath = 'storage/' . $path;
        }

        $defaultLowStock = isset($validated['low_stock']) ? (int) $validated['low_stock'] : 5;

        $product = Product::create([
            'title' => $validated['title'],
            'price' => $price,
            'discount' => $discount,
            'discounted_price' => $discountedPrice,
            'stock' => 0, // Will be calculated from store stocks
            'low_stock' => $defaultLowStock,
            'image' => $imagePath,
        ]);

        // Allocate stocks to stores
        $allStores = Store::all();
        $defaultStore = Store::getDefaultStore();

        if (!empty($validated['store_stocks'])) {
            foreach ($validated['store_stocks'] as $storeId => $qty) {
                ProductStoreStock::create([
                    'product_id' => $product->id,
                    'store_id' => (int) $storeId,
                    'stock' => max(0, (int) $qty),
                    'low_stock' => $defaultLowStock,
                ]);
            }
        } else {
            // Assign entered stock to default store
            $initialStock = isset($validated['stock']) ? (int) $validated['stock'] : 0;
            ProductStoreStock::create([
                'product_id' => $product->id,
                'store_id' => $defaultStore->id,
                'stock' => $initialStock,
                'low_stock' => $defaultLowStock,
            ]);
        }

        // Initialize zero entries for remaining stores
        foreach ($allStores as $s) {
            ProductStoreStock::firstOrCreate(
                ['product_id' => $product->id, 'store_id' => $s->id],
                ['stock' => 0, 'low_stock' => $defaultLowStock]
            );
        }

        $product->syncTotalStock();

        return redirect()->route('manager.products.index')
            ->with('success', "Product '{$product->title}' created successfully with store stock allocation!");
    }

    /**
     * Update the specified product and store stock allocations.
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discounted_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'low_stock' => ['nullable', 'integer', 'min:0'],
            'store_stocks' => ['nullable', 'array'],
            'store_stocks.*' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:4096'],
        ]);

        $price = (float) $validated['price'];
        $discount = (float) ($validated['discount'] ?? 0);
        $discountedPrice = isset($validated['discounted_price']) && $validated['discounted_price'] > 0
            ? (float) $validated['discounted_price']
            : round($price - ($price * ($discount / 100)), 2);

        $imagePath = $product->image;
        if ($request->hasFile('image')) {
            if ($product->image && file_exists(public_path($product->image))) {
                @unlink(public_path($product->image));
            }
            $file = $request->file('image');
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('products', $fileName, 'public');
            $imagePath = 'storage/' . $path;
        }

        $defaultLowStock = isset($validated['low_stock']) ? (int) $validated['low_stock'] : ($product->low_stock ?? 5);

        $product->update([
            'title' => $validated['title'],
            'price' => $price,
            'discount' => $discount,
            'discounted_price' => $discountedPrice,
            'low_stock' => $defaultLowStock,
            'image' => $imagePath,
        ]);

        // Update multi-store stocks if provided
        if (!empty($validated['store_stocks'])) {
            foreach ($validated['store_stocks'] as $storeId => $qty) {
                ProductStoreStock::updateOrCreate(
                    ['product_id' => $product->id, 'store_id' => (int) $storeId],
                    [
                        'stock' => max(0, (int) $qty),
                        'low_stock' => $defaultLowStock,
                    ]
                );
            }
        }

        $product->syncTotalStock();

        return redirect()->route('manager.products.index')
            ->with('success', "Product '{$product->title}' updated successfully!");
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Product $product)
    {
        if ($product->image && file_exists(public_path($product->image))) {
            @unlink(public_path($product->image));
        }

        $product->storeStocks()->delete();
        $product->delete();

        return redirect()->route('manager.products.index')
            ->with('success', 'Product deleted successfully!');
    }
}
