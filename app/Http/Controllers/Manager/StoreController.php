<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductStoreStock;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    /**
     * Display a listing of stores with inventory metrics.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $query = Store::with(['productStocks.product']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $stores = $query->orderBy('is_default', 'desc')
            ->orderBy('name', 'asc')
            ->get();

        $allProducts = Product::orderBy('title')->get();

        // Ensure every store has a record for every product
        foreach ($stores as $store) {
            foreach ($allProducts as $product) {
                ProductStoreStock::firstOrCreate(
                    ['product_id' => $product->id, 'store_id' => $store->id],
                    ['stock' => 0, 'low_stock' => $product->low_stock ?? 5]
                );
            }
        }

        // Refresh product stocks for accurate counts
        $stores->load('productStocks.product');

        // Calculate store-level metrics (units & inventory valuation)
        $stores->map(function ($store) {
            $store->total_products_count = $store->productStocks->where('stock', '>', 0)->count();
            $store->total_units = (int) $store->productStocks->sum('stock');
            $store->low_stock_count = (int) $store->productStocks
                ->where('stock', '>', 0)
                ->filter(function ($pss) {
                    return $pss->stock <= ($pss->low_stock ?? 5);
                })->count();
            $store->out_of_stock_count = (int) $store->productStocks
                ->where('stock', '<=', 0)
                ->count();
            
            // Valuation based on product cost and selling price
            $store->inventory_cost_value = (float) $store->productStocks->sum(function ($pss) {
                return $pss->stock * ($pss->product->price ?? 0);
            });

            $store->inventory_retail_value = (float) $store->productStocks->sum(function ($pss) {
                return $pss->stock * ($pss->product->discounted_price ?? $pss->product->price ?? 0);
            });

            return $store;
        });

        $totalStores = $stores->count();
        $totalSystemUnits = $stores->sum('total_units');
        $totalRetailValuation = $stores->sum('inventory_retail_value');

        return view('manager.stores.index', compact(
            'stores',
            'allProducts',
            'search',
            'totalStores',
            'totalSystemUnits',
            'totalRetailValuation'
        ));
    }

    /**
     * Store a newly created store in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:stores,code'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $isDefault = $request->boolean('is_default');

        if ($isDefault) {
            Store::where('is_default', true)->update(['is_default' => false]);
        }

        $store = Store::create([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
            'is_default' => $isDefault,
        ]);

        // Automatically initialize stock rows with 0 for all existing products
        $productIds = Product::pluck('id');
        $now = now();
        $stockInserts = [];
        foreach ($productIds as $pId) {
            $stockInserts[] = [
                'product_id' => $pId,
                'store_id' => $store->id,
                'stock' => 0,
                'low_stock' => 5,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($stockInserts)) {
            ProductStoreStock::insert($stockInserts);
        }

        return redirect()->route('manager.stores.index')
            ->with('success', "Store location '{$store->name}' created successfully with 0 opening stock!");
    }

    /**
     * Update the specified store in storage.
     */
    public function update(Request $request, Store $store)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('stores', 'code')->ignore($store->id)],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $isDefault = $request->boolean('is_default');

        if ($isDefault && !$store->is_default) {
            Store::where('is_default', true)->update(['is_default' => false]);
        }

        $store->update([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $store->is_active,
            'is_default' => $isDefault ? true : ($store->is_default && Store::count() === 1 ? true : $store->is_default),
        ]);

        return redirect()->route('manager.stores.index')
            ->with('success', "Store '{$store->name}' updated successfully!");
    }

    /**
     * Set a store as the default store.
     */
    public function setDefault(Store $store)
    {
        Store::where('is_default', true)->update(['is_default' => false]);
        $store->update(['is_default' => true, 'is_active' => true]);

        return redirect()->route('manager.stores.index')
            ->with('success', "'{$store->name}' is now set as the default store location.");
    }

    /**
     * Update inventory levels for all products in a specific store.
     */
    public function updateInventory(Request $request, Store $store)
    {
        $stocks = $request->input('stocks', []);

        DB::transaction(function () use ($store, $stocks) {
            foreach ($stocks as $productId => $qty) {
                $stock = max(0, (int) $qty);
                ProductStoreStock::updateOrCreate(
                    ['product_id' => (int) $productId, 'store_id' => $store->id],
                    ['stock' => $stock]
                );

                $product = Product::find($productId);
                if ($product) {
                    $product->syncTotalStock();
                }
            }
        });

        return redirect()->route('manager.stores.index')
            ->with('success', "Inventory balances for '{$store->name}' updated successfully!");
    }

    /**
     * Reset all product stock in this specific store to 0.
     */
    public function resetStock(Store $store)
    {
        DB::transaction(function () use ($store) {
            ProductStoreStock::where('store_id', $store->id)->update(['stock' => 0]);

            $products = Product::all();
            foreach ($products as $p) {
                $p->syncTotalStock();
            }
        });

        return redirect()->route('manager.stores.index')
            ->with('success', "All product inventory in '{$store->name}' has been reset to 0 units.");
    }

    /**
     * Global Reset: Reset all product stock across all stores to 0.
     */
    public function resetAllStoresStock()
    {
        DB::transaction(function () {
            ProductStoreStock::query()->update(['stock' => 0]);
            Product::query()->update(['stock' => 0]);
        });

        return redirect()->route('manager.stores.index')
            ->with('success', "All stores inventory stock has been reset to 0 units across the entire system.");
    }

    /**
     * Remove the specified store from storage.
     */
    public function destroy(Store $store)
    {
        if ($store->is_default) {
            return redirect()->route('manager.stores.index')
                ->with('error', 'Cannot delete the default store. Please assign another store as default first.');
        }

        $totalUnits = (int) ProductStoreStock::where('store_id', $store->id)->sum('stock');
        if ($totalUnits > 0) {
            return redirect()->route('manager.stores.index')
                ->with('error', "Cannot delete '{$store->name}' because it currently holds {$totalUnits} stock units. Please reset stock to 0 or transfer units first.");
        }

        $store->productStocks()->delete();
        $store->delete();

        return redirect()->route('manager.stores.index')
            ->with('success', "Store '{$store->name}' deleted successfully!");
    }
}
