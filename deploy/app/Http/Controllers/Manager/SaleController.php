<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountLedger;
use App\Models\Product;
use App\Models\ProductCategory;
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

        $products = Product::with(['storeStocks', 'category', 'purchaseItems'])->orderBy('title')->get();
        $productCategories = ProductCategory::orderBy('title')->get();
        $stores = Store::where('is_active', true)->orderBy('is_default', 'desc')->orderBy('name')->get();
        $defaultStore = Store::getDefaultStore();

        return view('manager.sales.index', compact(
            'sales', 
            'customers', 
            'defaultCustomer',
            'products', 
            'productCategories',
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
            'admin_password' => ['nullable', 'string'],
            'received_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_mode' => ['nullable', 'string', 'in:Cash,Card,Bank,Other,Split'],
            'split_payments' => ['nullable'],
            'extra_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_type' => ['nullable', 'string', 'in:percentage,fixed'],
        ]);

        $storeId = !empty($validated['store_id']) ? (int) $validated['store_id'] : Store::getDefaultStore()->id;
        $store = Store::find($storeId);
        $isIssueStock = $store && (strtoupper(trim($store->name)) === 'ISSUE STOCK' || strtoupper(trim($store->code)) === 'ISSUE STOCK');

        // Track low stock warnings for the selling store (allows sale to proceed)
        $lowStockNotes = [];
        foreach ($validated['items'] as $itemData) {
            $product = Product::findOrFail($itemData['product_id']);
            $branchStock = $product->stockInStore($storeId);
            if ($branchStock < (int) $itemData['quantity']) {
                $lowStockNotes[] = "'{$product->title}' (Branch Stock was: {$branchStock}, Sold: {$itemData['quantity']})";
            }
        }

        // Split payments parsing
        $paymentMode = $isIssueStock ? 'Issue Stock' : ($validated['payment_mode'] ?? 'Cash');
        $splitPayments = null;
        if ($paymentMode === 'Split') {
            $rawSplits = $request->input('split_payments');
            if (is_string($rawSplits)) {
                $splitPayments = json_decode($rawSplits, true);
            } elseif (is_array($rawSplits)) {
                $splitPayments = $rawSplits;
            }
            if (is_array($splitPayments)) {
                $splitPayments = [
                    'Cash' => max(0, (float)($splitPayments['Cash'] ?? 0)),
                    'Card' => max(0, (float)($splitPayments['Card'] ?? 0)),
                    'Bank' => max(0, (float)($splitPayments['Bank'] ?? 0)),
                    'Other' => max(0, (float)($splitPayments['Other'] ?? 0)),
                ];
            }
        }

        // Calculate items subtotal, item-level discounts, and line items
        $discount = $isIssueStock ? 0.00 : (float) ($validated['discount'] ?? 0);
        $subtotalAmount = 0;
        $totalItemDiscounts = 0;
        $processedLineItems = [];

        foreach ($validated['items'] as $item) {
            $product = Product::findOrFail($item['product_id']);
            $qty = (int) $item['quantity'];
            $basePrice = $isIssueStock ? 0.00 : (float) $item['unit_price'];
            $rawItemDisc = $isIssueStock ? 0.00 : (float) ($item['discount'] ?? 0);
            $discType = ($item['discount_type'] ?? 'fixed') === 'percentage' ? 'percentage' : 'fixed';

            if ($discType === 'percentage') {
                $discPct = min(100, max(0, $rawItemDisc));
                $unitDiscAmt = round($basePrice * ($discPct / 100), 2);
            } else {
                $unitDiscAmt = min($basePrice, max(0, $rawItemDisc));
            }

            $unitNetPrice = max(0, round($basePrice - $unitDiscAmt, 2));
            $lineSubtotal = round($unitNetPrice * $qty, 2);
            $lineDiscTotal = round($unitDiscAmt * $qty, 2);

            $subtotalAmount += $lineSubtotal;
            $totalItemDiscounts += $lineDiscTotal;

            $purchaseRate = $isIssueStock ? 0.00 : (float) ($product->purchase_price ?: ($product->purchaseItems()->latest('id')->value('unit_price') ?: 0.00));
            if (!$isIssueStock && $purchaseRate <= 0) {
                $purchaseRate = (float) $product->price;
            }

            $processedLineItems[] = [
                'product_id' => $product->id,
                'quantity' => $qty,
                'purchase_price' => $purchaseRate,
                'unit_price' => $basePrice,
                'discount' => $lineDiscTotal,
                'discount_type' => $discType,
                'subtotal' => $lineSubtotal,
            ];
        }

        $discountPercentage = $subtotalAmount > 0 ? round(($discount / $subtotalAmount) * 100, 2) : 0.00;

        $isAdmin = auth()->user()->hasRole('admin');
        $hasDiscountPermission = $isAdmin || auth()->user()->canGiveDiscount();

        $adminAuthorized = false;
        if (!empty($validated['admin_password'])) {
            $adminPassword = $validated['admin_password'];
            $adminUsers = \App\Models\User::whereHas('roles', function ($q) {
                $q->where('slug', 'admin')->orWhere('name', 'admin')->orWhere('name', 'Administrator');
            })->get();
            foreach ($adminUsers as $adminUser) {
                if (\Illuminate\Support\Facades\Hash::check($adminPassword, $adminUser->password)) {
                    $adminAuthorized = true;
                    break;
                }
            }
        }

        if ($discount > 0 && !$isIssueStock) {
            if (!$hasDiscountPermission && !$adminAuthorized) {
                return redirect()->back()->withInput()->with('error', 'You do not have permission to apply discounts. Administrator authorization password is required.');
            }
            if ($discountPercentage > 10.00 && !$isAdmin && !$adminAuthorized) {
                return redirect()->back()->withInput()->with('error', 'Discounts exceeding 10% require administrator authorization password.');
            }
        }

        DB::transaction(function () use ($validated, $storeId, $isIssueStock, $processedLineItems, $subtotalAmount, $discount, $paymentMode, $splitPayments) {
            $account = Account::findOrFail($validated['account_id']);
            $saleDate = $validated['sale_date'];
            $extraAmount = $isIssueStock ? 0.00 : (in_array($paymentMode, ['Card', 'Bank', 'Split']) ? (float) ($validated['extra_amount'] ?? 0) : 0.00);

            // Auto Generate Invoice Number
            $invoiceNo = 'INV-' . date('Ym') . '-' . str_pad(Sale::count() + 1, 4, '0', STR_PAD_LEFT);

            // Calculate final total (For ISSUE STOCK, total amount is 0)
            $totalAmount = $isIssueStock ? 0.00 : max(0, round($subtotalAmount - $discount + $extraAmount, 2));

            if ($isIssueStock) {
                $receivedAmount = 0.00;
            } elseif ($paymentMode === 'Split' && is_array($splitPayments)) {
                $receivedAmount = (float) array_sum($splitPayments);
            } else {
                $receivedAmount = (float) ($validated['received_amount'] ?? 0);
            }

            $balanceDue = $isIssueStock ? 0.00 : round($totalAmount - $receivedAmount, 2);

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
                'notes' => $validated['notes'] ?? ($isIssueStock ? 'Stock Issued (Zero Value)' : null),
                'payment_mode' => $paymentMode,
                'split_payments' => $splitPayments,
                'extra_amount' => $extraAmount,
            ]);

            // 2. Create Line Items & Decrement Stock from Selling Store
            foreach ($processedLineItems as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $itemData['product_id'],
                    'quantity' => $itemData['quantity'],
                    'purchase_price' => $itemData['purchase_price'],
                    'unit_price' => $itemData['unit_price'],
                    'discount' => $itemData['discount'],
                    'discount_type' => $itemData['discount_type'],
                    'subtotal' => $itemData['subtotal'],
                ]);

                // Decrement Product Stock specifically in selling store
                $product->decrementStoreStock($storeId, (int) $itemData['quantity']);
            }

            // 3. Write Sale Ledger Entry (Debit/Credit)
            if ($isIssueStock) {
                // Internal stock issue does not change customer debt/balance
                AccountLedger::create([
                    'account_id' => $account->id,
                    'date' => $saleDate,
                    'type' => 'sale',
                    'reference_no' => $invoiceNo,
                    'description' => "Stock Issue #{$invoiceNo} (Internal Stock Out - " . count($processedLineItems) . " items)",
                    'debit' => 0.00,
                    'credit' => 0.00,
                    'running_balance' => $account->balance,
                ]);
            } else {
                $newBalance = $account->balance + $totalAmount - $receivedAmount;
                AccountLedger::create([
                    'account_id' => $account->id,
                    'date' => $saleDate,
                    'type' => 'sale',
                    'reference_no' => $invoiceNo,
                    'description' => "Product Sale Invoice #{$invoiceNo} (" . count($processedLineItems) . " items)",
                    'debit' => $totalAmount,
                    'credit' => 0.00,
                    'running_balance' => $account->balance + $totalAmount,
                ]);

                if ($receivedAmount > 0) {
                    $receivingDesc = "Payment received for Sale Invoice #{$invoiceNo}";
                    if ($paymentMode === 'Split' && is_array($splitPayments)) {
                        $parts = [];
                        foreach ($splitPayments as $m => $val) {
                            if ($val > 0) $parts[] = "{$m}: PKR " . number_format($val, 2);
                        }
                        if (!empty($parts)) {
                            $receivingDesc .= " (Split: " . implode(', ', $parts) . ")";
                        }
                    } elseif (!empty($paymentMode)) {
                        $receivingDesc .= " ({$paymentMode})";
                    }

                    AccountLedger::create([
                        'account_id' => $account->id,
                        'date' => $saleDate,
                        'type' => 'receiving',
                        'reference_no' => $invoiceNo . '-REC',
                        'description' => $receivingDesc,
                        'debit' => 0.00,
                        'credit' => $receivedAmount,
                        'running_balance' => $newBalance,
                    ]);
                }

                $account->update(['balance' => $newBalance]);
            }
        });

        $msg = $isIssueStock
            ? 'Stock issued successfully! Stock decremented from ISSUE STOCK (Zero Value).'
            : 'Product sale recorded successfully! Stock decremented & customer ledger updated.';
        if (!empty($lowStockNotes)) {
            $msg .= ' ⚠️ Low Stock Notice for: ' . implode(', ', $lowStockNotes);
        }

        return redirect()->route('manager.sales.index')
            ->with('success', $msg);
    }

    /**
     * Update an existing product sale, adjust inventory stock, customer ledgers, & payments.
     */
    public function update(Request $request, Sale $sale)
    {
        $validated = $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'store_id' => ['nullable', 'exists:stores,id'],
            'sale_date' => ['required', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'admin_password' => ['nullable', 'string'],
            'received_amount' => ['nullable', 'numeric', 'min:0'],
            'additional_payment' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_mode' => ['nullable', 'string', 'in:Cash,Card,Bank,Other,Split'],
            'split_payments' => ['nullable'],
            'extra_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_type' => ['nullable', 'string', 'in:percentage,fixed'],
        ]);

        $storeId = !empty($validated['store_id']) ? (int) $validated['store_id'] : ($sale->store_id ?: Store::getDefaultStore()->id);
        $store = Store::find($storeId);
        $isIssueStock = $store && (strtoupper(trim($store->name)) === 'ISSUE STOCK' || strtoupper(trim($store->code)) === 'ISSUE STOCK');

        // Split payments parsing
        $paymentMode = $isIssueStock ? 'Issue Stock' : ($validated['payment_mode'] ?? $sale->payment_mode ?? 'Cash');
        $splitPayments = null;
        if ($paymentMode === 'Split') {
            $rawSplits = $request->input('split_payments');
            if (is_string($rawSplits)) {
                $splitPayments = json_decode($rawSplits, true);
            } elseif (is_array($rawSplits)) {
                $splitPayments = $rawSplits;
            }
            if (is_array($splitPayments)) {
                $splitPayments = [
                    'Cash' => max(0, (float)($splitPayments['Cash'] ?? 0)),
                    'Card' => max(0, (float)($splitPayments['Card'] ?? 0)),
                    'Bank' => max(0, (float)($splitPayments['Bank'] ?? 0)),
                    'Other' => max(0, (float)($splitPayments['Other'] ?? 0)),
                ];
            }
        }

        // Calculate items subtotal, item-level discounts, and line items
        $discount = $isIssueStock ? 0.00 : (float) ($validated['discount'] ?? 0);
        $subtotalAmount = 0;
        $totalItemDiscounts = 0;
        $processedLineItems = [];

        foreach ($validated['items'] as $item) {
            $product = Product::findOrFail($item['product_id']);
            $qty = (int) $item['quantity'];
            $basePrice = $isIssueStock ? 0.00 : (float) $item['unit_price'];
            $rawItemDisc = $isIssueStock ? 0.00 : (float) ($item['discount'] ?? 0);
            $discType = ($item['discount_type'] ?? 'fixed') === 'percentage' ? 'percentage' : 'fixed';

            if ($discType === 'percentage') {
                $discPct = min(100, max(0, $rawItemDisc));
                $unitDiscAmt = round($basePrice * ($discPct / 100), 2);
            } else {
                $unitDiscAmt = min($basePrice, max(0, $rawItemDisc));
            }

            $unitNetPrice = max(0, round($basePrice - $unitDiscAmt, 2));
            $lineSubtotal = round($unitNetPrice * $qty, 2);
            $lineDiscTotal = round($unitDiscAmt * $qty, 2);

            $subtotalAmount += $lineSubtotal;
            $totalItemDiscounts += $lineDiscTotal;

            $purchaseRate = $isIssueStock ? 0.00 : (float) ($product->purchase_price ?: ($product->purchaseItems()->latest('id')->value('unit_price') ?: 0.00));
            if (!$isIssueStock && $purchaseRate <= 0) {
                $purchaseRate = (float) $product->price;
            }

            $processedLineItems[] = [
                'product_id' => $product->id,
                'quantity' => $qty,
                'purchase_price' => $purchaseRate,
                'unit_price' => $basePrice,
                'discount' => $lineDiscTotal,
                'discount_type' => $discType,
                'subtotal' => $lineSubtotal,
            ];
        }

        $discountPercentage = $subtotalAmount > 0 ? round(($discount / $subtotalAmount) * 100, 2) : 0.00;

        $isAdmin = auth()->user()->hasRole('admin');
        $hasDiscountPermission = $isAdmin || auth()->user()->canGiveDiscount();

        $adminAuthorized = false;
        if (!empty($validated['admin_password'])) {
            $adminPassword = $validated['admin_password'];
            $adminUsers = \App\Models\User::whereHas('roles', function ($q) {
                $q->where('slug', 'admin')->orWhere('name', 'admin')->orWhere('name', 'Administrator');
            })->get();
            foreach ($adminUsers as $adminUser) {
                if (\Illuminate\Support\Facades\Hash::check($adminPassword, $adminUser->password)) {
                    $adminAuthorized = true;
                    break;
                }
            }
        }

        if ($discount > 0 && !$isIssueStock) {
            if (!$hasDiscountPermission && !$adminAuthorized) {
                return redirect()->back()->withInput()->with('error', 'You do not have permission to apply discounts. Administrator authorization password is required.');
            }
            if ($discountPercentage > 10.00 && !$isAdmin && !$adminAuthorized) {
                return redirect()->back()->withInput()->with('error', 'Discounts exceeding 10% require administrator authorization password.');
            }
        }

        DB::transaction(function () use ($validated, $sale, $storeId, $isIssueStock, $processedLineItems, $subtotalAmount, $discount, $paymentMode, $splitPayments) {
            $invoiceNo = $sale->invoice_no;
            $oldCustomer = Account::find($sale->account_id);
            $newCustomer = Account::findOrFail($validated['account_id']);

            // 1. Revert previous inventory deductions for this sale
            $oldStoreId = $sale->store_id ?: $storeId;
            foreach ($sale->items as $oldItem) {
                $oldProduct = Product::find($oldItem->product_id);
                if ($oldProduct) {
                    $oldProduct->incrementStoreStock($oldStoreId, (int) $oldItem->quantity);
                }
            }

            // Remove old line items
            $sale->items()->delete();

            // 2. Reverse previous customer ledger records for this invoice
            AccountLedger::where('reference_no', 'like', "%{$invoiceNo}%")->delete();

            if ($oldCustomer) {
                $lastOldCustLedger = AccountLedger::where('account_id', $oldCustomer->id)->orderBy('id', 'desc')->first();
                $oldCustomer->update(['balance' => $lastOldCustLedger ? $lastOldCustLedger->running_balance : 0.00]);
            }

            // Reload new customer with refreshed balance
            $newCustomer->refresh();

            // 3. Compute new financial totals
            $saleDate = $validated['sale_date'];
            $extraAmount = $isIssueStock ? 0.00 : (in_array($paymentMode, ['Card', 'Bank', 'Split']) ? (float) ($validated['extra_amount'] ?? 0) : 0.00);

            // Calculate final total (For ISSUE STOCK, total amount is 0)
            $totalAmount = $isIssueStock ? 0.00 : max(0, round($subtotalAmount - $discount + $extraAmount, 2));

            // Determine received amount:
            $additionalPayment = (float) ($validated['additional_payment'] ?? 0);
            if ($isIssueStock) {
                $receivedAmount = 0.00;
            } elseif ($paymentMode === 'Split' && is_array($splitPayments)) {
                $receivedAmount = (float) array_sum($splitPayments);
            } elseif ($additionalPayment > 0) {
                $receivedAmount = round($sale->received_amount + $additionalPayment, 2);
            } elseif (isset($validated['received_amount'])) {
                $receivedAmount = (float) $validated['received_amount'];
            } else {
                $receivedAmount = (float) $sale->received_amount;
            }

            $balanceDue = $isIssueStock ? 0.00 : max(0, round($totalAmount - $receivedAmount, 2));

            // 4. Update Sale Record
            $sale->update([
                'account_id' => $newCustomer->id,
                'store_id' => $storeId,
                'total_amount' => $totalAmount,
                'discount' => $discount,
                'received_amount' => $receivedAmount,
                'balance_due' => $balanceDue,
                'sale_date' => $saleDate,
                'notes' => $validated['notes'] ?? ($isIssueStock ? 'Stock Issued (Zero Value)' : null),
                'payment_mode' => $paymentMode,
                'split_payments' => $splitPayments,
                'extra_amount' => $extraAmount,
            ]);

            // 5. Create New Line Items & Decrement Stock from Selling Store
            foreach ($processedLineItems as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $itemData['product_id'],
                    'quantity' => $itemData['quantity'],
                    'purchase_price' => $itemData['purchase_price'],
                    'unit_price' => $itemData['unit_price'],
                    'discount' => $itemData['discount'],
                    'discount_type' => $itemData['discount_type'],
                    'subtotal' => $itemData['subtotal'],
                ]);

                $product->decrementStoreStock($storeId, (int) $itemData['quantity']);
            }

            // 6. Write New Customer Ledgers
            if ($isIssueStock) {
                // Internal stock issue does not change customer debt/balance
                AccountLedger::create([
                    'account_id' => $newCustomer->id,
                    'date' => $saleDate,
                    'type' => 'sale',
                    'reference_no' => $invoiceNo,
                    'description' => "Stock Issue #{$invoiceNo} (Updated Internal Stock Out - " . count($processedLineItems) . " items)",
                    'debit' => 0.00,
                    'credit' => 0.00,
                    'running_balance' => $newCustomer->balance,
                ]);
            } else {
                $newBalance = $newCustomer->balance + $totalAmount - $receivedAmount;

                $ledgerDesc = "Product Sale Invoice #{$invoiceNo} (Updated - " . count($processedLineItems) . " items)";

                AccountLedger::create([
                    'account_id' => $newCustomer->id,
                    'date' => $saleDate,
                    'type' => 'sale',
                    'reference_no' => $invoiceNo,
                    'description' => $ledgerDesc,
                    'debit' => $totalAmount,
                    'credit' => 0.00,
                    'running_balance' => $newCustomer->balance + $totalAmount,
                ]);

                if ($receivedAmount > 0) {
                    $receivingDesc = "Payment received for Sale Invoice #{$invoiceNo}";
                    if ($paymentMode === 'Split' && is_array($splitPayments)) {
                        $parts = [];
                        foreach ($splitPayments as $m => $val) {
                            if ($val > 0) $parts[] = "{$m}: PKR " . number_format($val, 2);
                        }
                        if (!empty($parts)) {
                            $receivingDesc .= " (Split: " . implode(', ', $parts) . ")";
                        }
                    } elseif (!empty($paymentMode)) {
                        $receivingDesc .= " ({$paymentMode})";
                    }

                    AccountLedger::create([
                        'account_id' => $newCustomer->id,
                        'date' => $saleDate,
                        'type' => 'receiving',
                        'reference_no' => $invoiceNo . '-REC',
                        'description' => $receivingDesc,
                        'debit' => 0.00,
                        'credit' => $receivedAmount,
                        'running_balance' => $newBalance,
                    ]);
                }

                $newCustomer->update(['balance' => $newBalance]);
            }
        });

        $msg = $isIssueStock
            ? "Stock issue #{$sale->invoice_no} updated successfully! Stock decremented from ISSUE STOCK (Zero Value)."
            : "Sale invoice #{$sale->invoice_no} updated successfully! Customer ledger and stock adjusted.";

        return redirect()->route('manager.sales.index')
            ->with('success', $msg);
    }

    /**
     * Pay remaining balance directly for a bill.
     */
    public function payRemaining(Request $request, Sale $sale)
    {
        $validated = $request->validate([
            'payment_amount' => ['required', 'numeric', 'min:0.01', 'max:' . max(0.01, (float)$sale->balance_due)],
            'payment_mode' => ['nullable', 'string', 'in:Cash,Card,Bank,Other'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $amountToPay = (float) $validated['payment_amount'];
        $paymentMode = $validated['payment_mode'] ?? 'Cash';

        DB::transaction(function () use ($sale, $amountToPay, $paymentMode) {
            $customer = $sale->customer;
            $newReceived = round($sale->received_amount + $amountToPay, 2);
            $newBalanceDue = max(0, round($sale->total_amount - $newReceived, 2));

            $sale->update([
                'received_amount' => $newReceived,
                'balance_due' => $newBalanceDue,
                'payment_mode' => $paymentMode,
            ]);

            if ($customer) {
                $newCustBalance = $customer->balance - $amountToPay;
                $refNo = $sale->invoice_no . '-PAY-' . time();

                AccountLedger::create([
                    'account_id' => $customer->id,
                    'date' => now()->toDateString(),
                    'type' => 'receiving',
                    'reference_no' => $refNo,
                    'description' => "Remaining balance payment of PKR " . number_format($amountToPay, 2) . " received for Invoice #{$sale->invoice_no} ({$paymentMode})",
                    'debit' => 0.00,
                    'credit' => $amountToPay,
                    'running_balance' => $newCustBalance,
                ]);

                $customer->update(['balance' => $newCustBalance]);
            }
        });

        return redirect()->route('manager.sales.index')
            ->with('success', "Payment of PKR " . number_format($amountToPay, 2) . " recorded successfully for Invoice #{$sale->invoice_no}!");
    }
}
