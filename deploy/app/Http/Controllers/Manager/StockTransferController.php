<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMainCategory;
use App\Models\StockTransfer;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockTransferController extends Controller
{
    /**
     * Display product category transfer history and transfer terminal.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $mainCatId = $request->input('main_category_id');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $query = StockTransfer::with([
            'product.mainCategory',
            'product.category',
            'sourceMainCategory',
            'destinationMainCategory',
            'sourceCategory',
            'destinationCategory',
            'creator'
        ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('transfer_no', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('title', 'like', "%{$search}%");
                  });
            });
        }

        if ($mainCatId) {
            $query->where(function ($q) use ($mainCatId) {
                $q->where('source_main_category_id', $mainCatId)
                  ->orWhere('destination_main_category_id', $mainCatId);
            });
        }

        if ($fromDate) {
            $query->whereDate('transfer_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('transfer_date', '<=', $toDate);
        }

        $transfers = $query->orderBy('transfer_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        $mainCategories = ProductMainCategory::where('is_active', true)->orderBy('title')->get();
        $subCategories = ProductCategory::with('mainCategory')->orderBy('title')->get();
        $products = Product::with(['mainCategory', 'category'])->orderBy('title')->get();

        // Metrics
        $totalTransfersCount = StockTransfer::count();
        $totalTransferredUnits = StockTransfer::sum('quantity');

        return view('manager.stock-transfers.index', compact(
            'transfers',
            'mainCategories',
            'subCategories',
            'products',
            'search',
            'mainCatId',
            'fromDate',
            'toDate',
            'totalTransfersCount',
            'totalTransferredUnits'
        ));
    }

    /**
     * Execute a product transfer between categories.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'destination_main_category_id' => ['required', 'exists:product_main_categories,id'],
            'destination_category_id' => ['nullable', 'exists:product_categories,id'],
            'destination_product_id' => ['nullable', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'transfer_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $sourceProduct = Product::with(['mainCategory', 'category'])->findOrFail($validated['product_id']);
        $quantity = (int) $validated['quantity'];

        if ($sourceProduct->stock < $quantity) {
            return back()->withInput()->with('error', "Insufficient stock for '{$sourceProduct->title}'. Available: {$sourceProduct->stock}, Requested: {$quantity}.");
        }

        $destMainCategory = ProductMainCategory::findOrFail($validated['destination_main_category_id']);
        $destCategory = !empty($validated['destination_category_id']) ? ProductCategory::find($validated['destination_category_id']) : null;
        
        $sourceMainCatId = $sourceProduct->product_main_category_id;
        $sourceCatId = $sourceProduct->product_category_id;

        DB::transaction(function () use ($validated, $sourceProduct, $destMainCategory, $destCategory, $sourceMainCatId, $sourceCatId, $quantity) {
            $defaultStore = Store::first();
            $defaultStoreId = $defaultStore ? $defaultStore->id : 1;

            if (!empty($validated['destination_product_id']) && (int)$validated['destination_product_id'] !== (int)$sourceProduct->id) {
                // Transfer units into an existing product in the destination category
                $destProduct = Product::findOrFail($validated['destination_product_id']);
                $sourceProduct->decrement('stock', $quantity);
                $destProduct->increment('stock', $quantity);
            } else {
                // Transfer the product itself into the destination category
                $sourceProduct->product_main_category_id = $destMainCategory->id;
                $sourceProduct->product_category_id = $destCategory ? $destCategory->id : null;
                $sourceProduct->save();
            }

            // Auto-generate transfer tracking number
            $transferNo = 'CAT-TRF-' . date('Ym') . '-' . str_pad(StockTransfer::count() + 1, 4, '0', STR_PAD_LEFT);

            StockTransfer::create([
                'transfer_no' => $transferNo,
                'source_store_id' => $defaultStoreId,
                'destination_store_id' => $defaultStoreId,
                'product_id' => $sourceProduct->id,
                'source_main_category_id' => $sourceMainCatId,
                'destination_main_category_id' => $destMainCategory->id,
                'source_category_id' => $sourceCatId,
                'destination_category_id' => $destCategory ? $destCategory->id : null,
                'quantity' => $quantity,
                'transfer_date' => $validated['transfer_date'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);
        });

        return redirect()->route('manager.stock-transfers.index')
            ->with('success', "Transferred {$quantity} unit(s) of '{$sourceProduct->title}' to '{$destMainCategory->title}' successfully!");
    }
}
