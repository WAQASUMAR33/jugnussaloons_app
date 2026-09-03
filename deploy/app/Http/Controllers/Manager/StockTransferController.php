<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductStoreStock;
use App\Models\StockTransfer;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockTransferController extends Controller
{
    /**
     * Display stock transfer history and transfer terminal.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $sourceStoreId = $request->input('source_store_id');
        $destinationStoreId = $request->input('destination_store_id');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $query = StockTransfer::with(['sourceStore', 'destinationStore', 'product', 'creator']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('transfer_no', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('title', 'like', "%{$search}%");
                  });
            });
        }

        if ($sourceStoreId) {
            $query->where('source_store_id', $sourceStoreId);
        }

        if ($destinationStoreId) {
            $query->where('destination_store_id', $destinationStoreId);
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

        $stores = Store::where('is_active', true)->orderBy('name')->get();
        $products = Product::with('storeStocks')->orderBy('title')->get();

        // Metrics
        $totalTransfersCount = StockTransfer::count();
        $totalTransferredUnits = StockTransfer::sum('quantity');

        return view('manager.stock-transfers.index', compact(
            'transfers',
            'stores',
            'products',
            'search',
            'sourceStoreId',
            'destinationStoreId',
            'fromDate',
            'toDate',
            'totalTransfersCount',
            'totalTransferredUnits'
        ));
    }

    /**
     * Execute an inter-store inventory transfer.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'source_store_id' => ['required', 'exists:stores,id', 'different:destination_store_id'],
            'destination_store_id' => ['required', 'exists:stores,id'],
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'transfer_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $sourceStore = Store::findOrFail($validated['source_store_id']);
        $destStore = Store::findOrFail($validated['destination_store_id']);
        $quantity = (int) $validated['quantity'];

        $sourceStock = $product->stockInStore($sourceStore->id);

        if ($sourceStock < $quantity) {
            return back()->withInput()->with('error', "Insufficient stock in '{$sourceStore->name}'. Available: {$sourceStock}, Requested: {$quantity}.");
        }

        DB::transaction(function () use ($validated, $product, $quantity) {
            // Decrement source store stock
            $product->decrementStoreStock((int) $validated['source_store_id'], $quantity);

            // Increment destination store stock
            $product->incrementStoreStock((int) $validated['destination_store_id'], $quantity);

            // Auto-generate transfer tracking number
            $transferNo = 'TRF-' . date('Ym') . '-' . str_pad(StockTransfer::count() + 1, 4, '0', STR_PAD_LEFT);

            StockTransfer::create([
                'transfer_no' => $transferNo,
                'source_store_id' => $validated['source_store_id'],
                'destination_store_id' => $validated['destination_store_id'],
                'product_id' => $validated['product_id'],
                'quantity' => $quantity,
                'transfer_date' => $validated['transfer_date'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);
        });

        return redirect()->route('manager.stock-transfers.index')
            ->with('success', "Transferred {$quantity} unit(s) of '{$product->title}' from '{$sourceStore->name}' to '{$destStore->name}' successfully!");
    }
}
