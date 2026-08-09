<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountLedger;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    /**
     * Display sale transactions and new sale entry.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = Sale::with(['customer', 'items.product']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($startDate) {
            $query->whereDate('sale_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('sale_date', '<=', $endDate);
        }

        $sales = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

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

        // Fetch accounts whose category is "Customer", "Client", "VIP", "Regular", or NOT a Supplier/Vendor
        $customers = Account::whereHas('category', function ($q) {
            $q->where('title', 'not like', '%Supplier%')
              ->where('title', 'not like', '%Vendor%');
        })->orWhereDoesntHave('category')
          ->orderBy('name')
          ->get();

        $products = Product::where('stock', '>', 0)->orderBy('title')->get();

        return view('manager.sales.index', compact(
            'sales', 
            'customers', 
            'products', 
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
            'sale_date' => ['required', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'received_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        // Validate stock availability first
        foreach ($validated['items'] as $itemData) {
            $product = Product::findOrFail($itemData['product_id']);
            if ($product->stock < (int) $itemData['quantity']) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', "Insufficient stock for '{$product->title}'. Available: {$product->stock}, Requested: {$itemData['quantity']}");
            }
        }

        DB::transaction(function () use ($validated) {
            $account = Account::findOrFail($validated['account_id']);
            $saleDate = $validated['sale_date'];
            $discount = (float) ($validated['discount'] ?? 0);
            $receivedAmount = (float) $validated['received_amount'];

            // Auto Generate Invoice Number
            $invoiceNo = 'INV-' . date('Ym') . '-' . str_pad(Sale::count() + 1, 4, '0', STR_PAD_LEFT);

            // Calculate item subtotal
            $subtotalAmount = 0;
            foreach ($validated['items'] as $item) {
                $subtotalAmount += (int) $item['quantity'] * (float) $item['unit_price'];
            }

            // Calculate final total after bill discount
            $totalAmount = max(0, round($subtotalAmount - $discount, 2));
            $balanceDue = round($totalAmount - $receivedAmount, 2);

            // 1. Create Sale Record
            $sale = Sale::create([
                'invoice_no' => $invoiceNo,
                'account_id' => $account->id,
                'total_amount' => $totalAmount,
                'discount' => $discount,
                'received_amount' => $receivedAmount,
                'balance_due' => $balanceDue,
                'sale_date' => $saleDate,
                'notes' => $validated['notes'] ?? null,
            ]);

            // 2. Create Line Items & Decrement Stock
            foreach ($validated['items'] as $itemData) {
                $subtotal = round((int) $itemData['quantity'] * (float) $itemData['unit_price'], 2);
                
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $itemData['product_id'],
                    'quantity' => (int) $itemData['quantity'],
                    'unit_price' => (float) $itemData['unit_price'],
                    'subtotal' => $subtotal,
                ]);

                // Decrement Product Stock
                $product = Product::findOrFail($itemData['product_id']);
                $product->decrement('stock', (int) $itemData['quantity']);
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

        return redirect()->route('manager.sales.index')
            ->with('success', 'Product sale recorded successfully! Stock decremented & customer ledger updated.');
    }
}
