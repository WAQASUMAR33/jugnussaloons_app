<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountLedger;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    /**
     * Display a listing of purchases.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $storeId = $request->input('store_id');

        $query = Purchase::with(['supplier', 'store', 'items.product']);

        if ($search) {
            $query->where('invoice_no', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
        }

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $purchases = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        // Fetch accounts whose category is "Supplier" or "Vendor"
        $suppliers = Account::whereHas('category', function ($q) {
            $q->where('title', 'like', '%Supplier%')
              ->orWhere('title', 'like', '%Vendor%');
        })->orderBy('name')->get();

        $products = Product::with('storeStocks')->orderBy('title')->get();
        $stores = Store::where('is_active', true)->orderBy('is_default', 'desc')->orderBy('name')->get();
        $defaultStore = Store::getDefaultStore();

        return view('manager.purchases.index', compact('purchases', 'suppliers', 'products', 'stores', 'defaultStore', 'search', 'storeId'));
    }

    /**
     * Store a newly created purchase transaction, update stock, write ledger, & update balance.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'store_id' => ['nullable', 'exists:stores,id'],
            'purchase_date' => ['required', 'date'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.sale_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated) {
            $account = Account::findOrFail($validated['account_id']);
            $purchaseDate = $validated['purchase_date'];
            $paidAmount = (float) $validated['paid_amount'];
            $storeId = !empty($validated['store_id']) ? (int) $validated['store_id'] : Store::getDefaultStore()->id;

            // Auto Generate Invoice Number
            $invoiceNo = 'PUR-' . date('Ym') . '-' . str_pad(Purchase::count() + 1, 4, '0', STR_PAD_LEFT);

            // Calculate total
            $totalAmount = 0;
            foreach ($validated['items'] as $item) {
                $totalAmount += (int) $item['quantity'] * (float) $item['unit_price'];
            }
            $balanceDue = round($totalAmount - $paidAmount, 2);

            // 1. Create Purchase Record
            $purchase = Purchase::create([
                'invoice_no' => $invoiceNo,
                'account_id' => $account->id,
                'store_id' => $storeId,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'balance_due' => $balanceDue,
                'purchase_date' => $purchaseDate,
                'notes' => $validated['notes'] ?? null,
            ]);

            // 2. Create Line Items, Increment Stock & Update Product Sale Rate
            foreach ($validated['items'] as $itemData) {
                $subtotal = round((int) $itemData['quantity'] * (float) $itemData['unit_price'], 2);
                
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $itemData['product_id'],
                    'quantity' => (int) $itemData['quantity'],
                    'unit_price' => (float) $itemData['unit_price'],
                    'subtotal' => $subtotal,
                ]);

                // Increment Product Stock for Destination Store & Sync Total
                $product = Product::findOrFail($itemData['product_id']);
                $product->incrementStoreStock($storeId, (int) $itemData['quantity']);

                if (isset($itemData['sale_price']) && (float) $itemData['sale_price'] > 0) {
                    $newSaleRate = (float) $itemData['sale_price'];
                    $product->price = $newSaleRate;
                    $product->discounted_price = $product->calculateDiscountedPrice();
                    $product->save();
                }
            }

            // 3. Write Purchase Ledger Entry (Debit/Credit)
            $newBalance = $account->balance + $totalAmount - $paidAmount;

            // Record Purchase Invoice Ledger
            AccountLedger::create([
                'account_id' => $account->id,
                'date' => $purchaseDate,
                'type' => 'purchase',
                'reference_no' => $invoiceNo,
                'description' => "Purchase Bill #{$invoiceNo} (" . count($validated['items']) . " items)",
                'debit' => $totalAmount,
                'credit' => 0.00,
                'running_balance' => $account->balance + $totalAmount,
            ]);

            // Record Payment Ledger if payment was made
            if ($paidAmount > 0) {
                AccountLedger::create([
                    'account_id' => $account->id,
                    'date' => $purchaseDate,
                    'type' => 'payment',
                    'reference_no' => $invoiceNo . '-PAY',
                    'description' => "Payment made for Purchase Bill #{$invoiceNo}",
                    'debit' => 0.00,
                    'credit' => $paidAmount,
                    'running_balance' => $newBalance,
                ]);
            }

            // 4. Update Account Balance
            $account->update(['balance' => $newBalance]);
        });

        return redirect()->route('manager.purchases.index')
            ->with('success', 'Purchase recorded successfully! Product stock & supplier ledger updated.');
    }
}
