<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountLedger;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    /**
     * Display a listing of sales transactions & active POS terminal with multi-store inventory.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $storeId = $request->input('store_id');

        $query = Sale::with(['customer', 'store', 'items.product']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        if ($startDate) {
            $query->whereDate('sale_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('sale_date', '<=', $endDate);
        }

        $sales = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();

        // 1. Overall Sales Metrics
        $todaySales = Sale::whereDate('sale_date', today())->sum('total_amount');
        $thisMonthSales = Sale::whereYear('sale_date', now()->year)->whereMonth('sale_date', now()->month)->sum('total_amount');
        $totalReceivings = Sale::sum('received_amount');
        $totalBalanceDue = Sale::sum('balance_due');

        // 2. Date-wise Sales Statistics Breakdown
        $statsQuery = DB::table('sales')
            ->select(
                'sale_date',
                DB::raw('COUNT(id) as invoice_count'),
                DB::raw('SUM(total_amount) as total_sales'),
                DB::raw('SUM(discount) as total_discount'),
                DB::raw('SUM(received_amount) as total_received'),
                DB::raw('SUM(balance_due) as total_balance_due')
            );

        if ($startDate) {
            $statsQuery->whereDate('sale_date', '>=', $startDate);
        }

        if ($endDate) {
            $statsQuery->whereDate('sale_date', '<=', $endDate);
        }

        $dateWiseStats = $statsQuery->groupBy('sale_date')
            ->orderBy('sale_date', 'desc')
            ->get();

        // 3. Fetch Customer accounts & Ensure Walk-in Customer is default
        $customerCategory = \App\Models\AccountCategory::where('title', 'like', '%Customer%')
            ->orWhere('title', 'like', '%Client%')
            ->first();

        if (!$customerCategory) {
            $customerCategory = \App\Models\AccountCategory::firstOrCreate(['title' => 'Walk-in Client']);
        }

        $walkinCustomer = Account::where('name', 'like', '%Walk-in%')
            ->orWhere('name', 'like', '%Walk in%')
            ->orWhere('name', 'Walkin Customer')
            ->first();

        if (!$walkinCustomer) {
            $walkinCustomer = Account::create([
                'name' => 'Walk-in Customer',
                'account_category_id' => $customerCategory->id,
                'phone_no1' => '0300-0000000',
                'balance' => 0.00,
            ]);
        }

        $customers = Account::where(function($q) {
            $q->whereHas('category', function ($cq) {
                $cq->where('title', 'like', '%Customer%')
                   ->orWhere('title', 'like', '%Client%')
                   ->orWhere('title', 'like', '%Member%');
            })->orWhereDoesntHave('category');
        })->where(function($q) {
            $q->whereNull('emp_type')->orWhere('emp_type', '');
        })->whereDoesntHave('category', function($q) {
            $q->where('title', 'like', '%Employee%')
              ->orWhere('title', 'like', '%Staff%')
              ->orWhere('title', 'like', '%Supplier%')
              ->orWhere('title', 'like', '%Vendor%');
        })->get();

        // Ensure Walk-in Customer is always the very first item
        $customers = $customers->reject(function ($c) use ($walkinCustomer) {
            return $c->id == $walkinCustomer->id;
        })->sortBy('name')->values();
        $customers->prepend($walkinCustomer);

        $defaultCustomer = $walkinCustomer;

        $products = Product::with('storeStocks')->orderBy('title')->get();
        $stores = Store::where('is_active', true)->orderBy('is_default', 'desc')->orderBy('name')->get();
        $defaultStore = Store::getDefaultStore();

        return view('manager.sales.index', compact(
            'sales', 
            'customers', 
            'defaultCustomer',
            'products', 
            'stores',
            'defaultStore',
            'storeId',
            'search',
            'startDate',
            'endDate',
            'todaySales',
            'thisMonthSales',
            'totalReceivings',
            'totalBalanceDue',
            'dateWiseStats'
        ));
    }

    /**
     * Store a newly created sale transaction, decrement stock, write ledger, & update balance.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'store_id' => ['nullable', 'exists:stores,id'],
            'sale_date' => ['required', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'received_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_mode' => ['nullable', 'string', 'in:Cash,Card,Bank,Other'],
            'extra_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $storeId = !empty($validated['store_id']) ? (int) $validated['store_id'] : Store::getDefaultStore()->id;

        // Track low stock warnings for the selling store (allows sale to proceed)
        $lowStockNotes = [];
        foreach ($validated['items'] as $itemData) {
            $product = Product::findOrFail($itemData['product_id']);
            $branchStock = $product->stockInStore($storeId);
            if ($branchStock < (int) $itemData['quantity']) {
                $lowStockNotes[] = "'{$product->title}' (Branch Stock was: {$branchStock}, Sold: {$itemData['quantity']})";
            }
        }

        DB::transaction(function () use ($validated, $storeId) {
            $account = Account::findOrFail($validated['account_id']);
            $saleDate = $validated['sale_date'];
            $discount = (float) ($validated['discount'] ?? 0);
            $receivedAmount = (float) $validated['received_amount'];
            $paymentMode = $validated['payment_mode'] ?? 'Cash';
            $extraAmount = in_array($paymentMode, ['Card', 'Bank']) ? (float) ($validated['extra_amount'] ?? 0) : 0.00;

            // Auto Generate Invoice Number
            $invoiceNo = 'INV-' . date('Ym') . '-' . str_pad(Sale::count() + 1, 4, '0', STR_PAD_LEFT);

            // Calculate item subtotal
            $subtotalAmount = 0;
            foreach ($validated['items'] as $item) {
                $subtotalAmount += (int) $item['quantity'] * (float) $item['unit_price'];
            }

            // Calculate final total after bill discount and extra amount
            $totalAmount = max(0, round($subtotalAmount - $discount + $extraAmount, 2));
            $balanceDue = round($totalAmount - $receivedAmount, 2);

            // 1. Create Sale Record
            $sale = Sale::create([
                'invoice_no' => $invoiceNo,
                'account_id' => $account->id,
                'store_id' => $storeId,
                'total_amount' => $totalAmount,
                'discount' => $discount,
                'received_amount' => $receivedAmount,
                'balance_due' => $balanceDue,
                'sale_date' => $saleDate,
                'notes' => $validated['notes'] ?? null,
                'payment_mode' => $paymentMode,
                'extra_amount' => $extraAmount,
            ]);

            // 2. Create Line Items & Decrement Stock from Selling Store
            foreach ($validated['items'] as $itemData) {
                $subtotal = round((int) $itemData['quantity'] * (float) $itemData['unit_price'], 2);
                
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $itemData['product_id'],
                    'quantity' => (int) $itemData['quantity'],
                    'unit_price' => (float) $itemData['unit_price'],
                    'subtotal' => $subtotal,
                ]);

                // Decrement Product Stock specifically in selling store
                $product = Product::findOrFail($itemData['product_id']);
                $product->decrementStoreStock($storeId, (int) $itemData['quantity']);
            }

            // 3. Write Sale Ledger Entry (Debit/Credit)
            $newBalance = $account->balance + $totalAmount - $receivedAmount;

            // Record Sale Invoice Ledger (Debit customer account)
            AccountLedger::create([
                'account_id' => $account->id,
                'date' => $saleDate,
                'type' => 'sale',
                'reference_no' => $invoiceNo,
                'description' => "Product Sale Invoice #{$invoiceNo} (" . count($validated['items']) . " items)",
                'debit' => $totalAmount,
                'credit' => 0.00,
                'running_balance' => $account->balance + $totalAmount,
            ]);

            // Record Receiving Ledger if payment received
            if ($receivedAmount > 0) {
                AccountLedger::create([
                    'account_id' => $account->id,
                    'date' => $saleDate,
                    'type' => 'receiving',
                    'reference_no' => $invoiceNo . '-REC',
                    'description' => "Payment received for Sale Invoice #{$invoiceNo}",
                    'debit' => 0.00,
                    'credit' => $receivedAmount,
                    'running_balance' => $newBalance,
                ]);
            }

            // 4. Update Customer Account Balance
            $account->update(['balance' => $newBalance]);
        });

        $msg = 'Product sale recorded successfully! Stock decremented & customer ledger updated.';
        if (!empty($lowStockNotes)) {
            $msg .= ' ⚠️ Low Stock Notice for: ' . implode(', ', $lowStockNotes);
        }

        return redirect()->route('manager.sales.index')
            ->with('success', $msg);
    }
}
