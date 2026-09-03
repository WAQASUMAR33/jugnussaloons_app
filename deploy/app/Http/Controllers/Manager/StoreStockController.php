<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductStoreStock;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreStockController extends Controller
{
    /**
     * Display store-wise product stock matrix & filtered stock ledger.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $storeId = $request->input('store_id');
        $productId = $request->input('product_id');
        $status = $request->input('status');

        $stores = Store::where('is_active', true)->orderBy('is_default', 'desc')->orderBy('name')->get();
        $allProducts = Product::orderBy('title')->get();
        $selectedStore = $storeId ? Store::find($storeId) : null;
        $selectedProduct = $productId ? Product::find($productId) : null;

        // Ensure all products have entries in all stores
        foreach ($stores as $s) {
            foreach ($allProducts as $p) {
                ProductStoreStock::firstOrCreate(
                    ['product_id' => $p->id, 'store_id' => $s->id],
                    ['stock' => 0, 'low_stock' => $p->low_stock ?? 5]
                );
            }
        }

        $query = Product::with(['storeStocks.store']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%");
            });
        }

        if ($productId) {
            $query->where('id', $productId);
        }

        // Apply Store-Specific or Global Stock Status Filter
        if ($status) {
            if ($storeId) {
                $query->whereHas('storeStocks', function ($sq) use ($storeId, $status) {
                    $sq->where('store_id', $storeId);
                    if ($status === 'out') {
                        $sq->where('stock', '<=', 0);
                    } elseif ($status === 'low') {
                        $sq->where('stock', '>', 0)->whereColumn('stock', '<=', 'low_stock');
                    } elseif ($status === 'healthy') {
                        $sq->whereColumn('stock', '>', 'low_stock');
                    }
                });
            } else {
                if ($status === 'out') {
                    $query->where('stock', '<=', 0);
                } elseif ($status === 'low') {
                    $query->where('stock', '>', 0)->where('stock', '<=', 5);
                } elseif ($status === 'healthy') {
                    $query->where('stock', '>', 5);
                }
            }
        }

        $products = $query->orderBy('title', 'asc')->paginate(15)->withQueryString();

        // Calculate KPI summary metrics based on active filter
        if ($storeId) {
            $stockSumQuery = ProductStoreStock::where('store_id', $storeId);
            if ($productId) {
                $stockSumQuery->where('product_id', $productId);
            }
            $totalUnits = (int) $stockSumQuery->sum('stock');

            $valuationQuery = DB::table('product_store_stocks')
                ->join('products', 'product_store_stocks.product_id', '=', 'products.id')
                ->where('product_store_stocks.store_id', $storeId);
            
            if ($productId) {
                $valuationQuery->where('products.id', $productId);
            }

            $totalRetailValuation = (float) $valuationQuery->sum(DB::raw('product_store_stocks.stock * COALESCE(products.discounted_price, products.price)'));
            $totalCostValuation = (float) $valuationQuery->sum(DB::raw('product_store_stocks.stock * products.price'));
        } else {
            $productQuery = Product::query();
            if ($productId) {
                $productQuery->where('id', $productId);
            }
            if ($search) {
                $productQuery->where('title', 'like', "%{$search}%");
            }
            $totalUnits = (int) $productQuery->sum('stock');
            $totalRetailValuation = (float) $productQuery->sum(DB::raw('stock * COALESCE(discounted_price, price)'));
            $totalCostValuation = (float) $productQuery->sum(DB::raw('stock * price'));
        }

        $totalFilteredProducts = $products->total();

        return view('manager.store-stocks.index', compact(
            'products',
            'stores',
            'allProducts',
            'selectedStore',
            'selectedProduct',
            'search',
            'storeId',
            'productId',
            'status',
            'totalFilteredProducts',
            'totalUnits',
            'totalRetailValuation',
            'totalCostValuation'
        ));
    }
}
