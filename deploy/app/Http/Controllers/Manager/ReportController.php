<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\AccountLedger;
use App\Models\Appointment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaloonService;
use App\Models\ServiceCategory;
use App\Models\Store;
use App\Models\ProductStoreStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * 1. Sales Report (Between dates & Categories)
     */
    public function sales(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $categoryId = $request->input('account_category_id');
        $reportType = $request->input('report_type', 'datewise');

        $query = Sale::with(['customer.category', 'items.product'])
            ->whereBetween('sale_date', [$startDate, $endDate]);

        if ($categoryId) {
            $query->whereHas('customer', function ($q) use ($categoryId) {
                $q->where('account_category_id', $categoryId);
            });
        }

        $sales = (clone $query)->orderBy('sale_date', 'desc')->orderBy('id', 'desc')->paginate(15)->withQueryString();

        // Summary Statistics
        $totalSalesCount = (clone $query)->count();
        $totalGrossAmount = (clone $query)->sum('total_amount');
        $totalDiscount = (clone $query)->sum('discount');
        $totalNetAmount = $totalGrossAmount - $totalDiscount;
        $totalReceived = (clone $query)->sum('received_amount');
        $totalBalanceDue = (clone $query)->sum('balance_due');

        // Date-wise Breakdown
        $datewiseBreakdown = DB::table('sales')
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->select(
                'sale_date',
                DB::raw('COUNT(id) as total_count'),
                DB::raw('SUM(total_amount) as gross_amount'),
                DB::raw('SUM(discount) as discount_amount'),
                DB::raw('SUM(received_amount) as received_amount'),
                DB::raw('SUM(balance_due) as balance_due')
            )
            ->groupBy('sale_date')
            ->orderBy('sale_date', 'desc')
            ->get();

        // Category-wise Breakdown
        $categoryBreakdown = DB::table('sales')
            ->join('accounts', 'sales.account_id', '=', 'accounts.id')
            ->join('account_categories', 'accounts.account_category_id', '=', 'account_categories.id')
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->select(
                'account_categories.title as category_title',
                DB::raw('COUNT(sales.id) as total_count'),
                DB::raw('SUM(sales.total_amount) as gross_amount'),
                DB::raw('SUM(sales.discount) as discount_amount'),
        $invoicesCount = (clone $query)->count();

        // Payment mode breakdown
        $paymentModesBreakdown = (clone $query)
            ->select('payment_mode', DB::raw('COUNT(id) as count'), DB::raw('SUM(total_amount) as total'))
            ->groupBy('payment_mode')
            ->get();

        $stores = Store::where('is_active', true)->orderBy('name')->get();

        return view('manager.reports.sales', compact(
            'sales', 'startDate', 'endDate', 'stores', 'storeId',
            'totalSalesAmount', 'totalDiscount', 'totalReceived',
            'totalBalanceDue', 'invoicesCount', 'paymentModesBreakdown'
        ));
    }

    /**
     * 2. Stock Report (Multi-Store Support)
     */
    public function stock(Request $request)
    {
        $status = $request->input('status'); // all, low, out
        $search = $request->input('search');
        $storeId = $request->input('store_id');

        $stores = Store::where('is_active', true)->orderBy('is_default', 'desc')->orderBy('name')->get();
        $query = Product::with(['storeStocks.store']);

        if ($search) {
            $query->where('title', 'like', "%{$search}%");
        }

        if ($storeId) {
            if ($status === 'out') {
                $query->whereHas('storeStocks', function ($q) use ($storeId) {
                    $q->where('store_id', $storeId)->where('stock', '<=', 0);
                });
            } elseif ($status === 'low') {
                $query->whereHas('storeStocks', function ($q) use ($storeId) {
                    $q->where('store_id', $storeId)->where('stock', '>', 0)->whereColumn('stock', '<=', 'low_stock');
                });
            } elseif ($status === 'healthy') {
                $query->whereHas('storeStocks', function ($q) use ($storeId) {
                    $q->where('store_id', $storeId)->whereColumn('stock', '>', 'low_stock');
                });
            }

            $products = (clone $query)->paginate(15)->withQueryString();

            // Store specific metrics
            $totalProductsCount = Product::count();
            $totalStockUnits = (int) ProductStoreStock::where('store_id', $storeId)->sum('stock');
            $totalCostValuation = (float) DB::table('product_store_stocks')
                ->join('products', 'product_store_stocks.product_id', '=', 'products.id')
                ->where('product_store_stocks.store_id', $storeId)
                ->sum(DB::raw('product_store_stocks.stock * products.price'));
            $totalRetailValuation = (float) DB::table('product_store_stocks')
                ->join('products', 'product_store_stocks.product_id', '=', 'products.id')
                ->where('product_store_stocks.store_id', $storeId)
                ->sum(DB::raw('product_store_stocks.stock * products.discounted_price'));
            $lowStockCount = (int) ProductStoreStock::where('store_id', $storeId)
                ->where('stock', '>', 0)
                ->whereColumn('stock', '<=', 'low_stock')
                ->count();
            $outOfStockCount = (int) ProductStoreStock::where('store_id', $storeId)
                ->where('stock', '<=', 0)
                ->count();
        } else {
            if ($status === 'out') {
                $query->where('stock', '<=', 0);
            } elseif ($status === 'low') {
                $query->where('stock', '>', 0)->where('stock', '<=', 5);
            } elseif ($status === 'healthy') {
                $query->where('stock', '>', 5);
            }

            $products = (clone $query)->orderBy('stock', 'asc')->paginate(15)->withQueryString();

            // Overall System Inventory Summary
            $totalProductsCount = Product::count();
            $totalStockUnits = Product::sum('stock');
            $totalCostValuation = Product::sum(DB::raw('stock * price'));
            $totalRetailValuation = Product::sum(DB::raw('stock * discounted_price'));
            $lowStockCount = Product::where('stock', '>', 0)->where('stock', '<=', 5)->count();
            $outOfStockCount = Product::where('stock', '<=', 0)->count();
        }

        return view('manager.reports.stock', compact(
            'products', 'stores', 'storeId', 'status', 'search', 'totalProductsCount',
            'totalStockUnits', 'totalCostValuation', 'totalRetailValuation',
            'lowStockCount', 'outOfStockCount'
        ));
    }

    /**
     * 3. Services Booking Report (Date-wise & Category-wise)
     */
    public function services(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $serviceCategoryId = $request->input('service_category_id');
        $employeeId = $request->input('employee_id');
        $reportType = $request->input('report_type', 'datewise');

        $query = Appointment::with(['customer', 'employee', 'items.service.category'])
            ->whereBetween('appointment_date', [$startDate, $endDate]);

        if ($serviceCategoryId) {
            $query->whereHas('items.service', function ($q) use ($serviceCategoryId) {
                $q->where('service_category_id', $serviceCategoryId);
            });
        }

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $appointments = (clone $query)->orderBy('appointment_date', 'desc')->orderBy('id', 'desc')->paginate(15)->withQueryString();

        // Summary Cards
        $totalAppointmentsCount = (clone $query)->count();
        $totalGrossRevenue = (clone $query)->sum('total_amount');
        $totalDiscount = (clone $query)->sum('discount');
        $totalNetRevenue = (clone $query)->sum('net_amount');
        $totalCommission = (clone $query)->sum('total_commission');

        // Date-wise Report Breakdown (Filtered by date, stylist, and service category)
        $datewiseQuery = DB::table('appointments')
            ->whereBetween('appointments.appointment_date', [$startDate, $endDate]);

        if ($employeeId) {
            $datewiseQuery->where('appointments.employee_id', $employeeId);
        }

        if ($serviceCategoryId) {
            $datewiseQuery->whereExists(function ($sub) use ($serviceCategoryId) {
                $sub->select(DB::raw(1))
                    ->from('appointment_services')
                    ->join('services', 'appointment_services.saloon_service_id', '=', 'services.id')
                    ->whereColumn('appointment_services.appointment_id', 'appointments.id')
                    ->where('services.service_category_id', $serviceCategoryId);
            });
        }

        $datewiseBreakdown = $datewiseQuery
            ->select(
                'appointments.appointment_date',
                DB::raw('COUNT(appointments.id) as total_count'),
                DB::raw('SUM(appointments.total_amount) as gross_amount'),
                DB::raw('SUM(appointments.discount) as discount_amount'),
                DB::raw('SUM(appointments.net_amount) as net_amount'),
                DB::raw('SUM(appointments.total_commission) as total_commission')
            )
            ->groupBy('appointments.appointment_date')
            ->orderBy('appointments.appointment_date', 'desc')
            ->get();

        // Category-wise Report Breakdown (Filtered by date, stylist, and service category)
        $categorywiseQuery = DB::table('appointment_services')
            ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
            ->join('services', 'appointment_services.saloon_service_id', '=', 'services.id')
            ->leftJoin('service_categories', 'services.service_category_id', '=', 'service_categories.id')
            ->whereBetween('appointments.appointment_date', [$startDate, $endDate]);

        if ($employeeId) {
            $categorywiseQuery->where('appointments.employee_id', $employeeId);
        }

        if ($serviceCategoryId) {
            $categorywiseQuery->where('services.service_category_id', $serviceCategoryId);
        }

        $categorywiseBreakdown = $categorywiseQuery
            ->select(
                DB::raw("COALESCE(service_categories.title, 'Uncategorized') as category_title"),
                DB::raw('COUNT(appointment_services.id) as service_count'),
                DB::raw('SUM(appointment_services.discounted_price) as total_revenue'),
                DB::raw('SUM(appointment_services.commission) as total_commission')
            )
            ->groupBy('service_categories.id', 'service_categories.title')
            ->get();

        $serviceCategories = ServiceCategory::orderBy('title')->get();
        $employees = Account::whereHas('category', function ($q) {
            $q->where('title', 'like', '%Employee%')
              ->orWhere('title', 'like', '%Staff%')
              ->orWhere('title', 'like', '%Barber%')
              ->orWhere('title', 'like', '%Stylist%');
        })->orderBy('name')->get();

        if ($employees->isEmpty()) {
            $employees = Account::whereHas('category', function ($q) {
                $q->where('title', 'not like', '%Supplier%')
                  ->where('title', 'not like', '%Vendor%');
            })->orWhereDoesntHave('category')->orderBy('name')->get();
        }

        return view('manager.reports.services', compact(
            'appointments', 'startDate', 'endDate', 'serviceCategoryId', 'employeeId',
            'serviceCategories', 'employees', 'totalAppointmentsCount', 'totalGrossRevenue',
            'totalDiscount', 'totalNetRevenue', 'totalCommission', 'datewiseBreakdown', 'categorywiseBreakdown', 'reportType'
        ));
    }

    /**
     * 4. Ledger Report (Account-wise)
     */
    public function ledger(Request $request)
    {
        $accountId = $request->input('account_id');
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $accounts = Account::with('category')->orderBy('name')->get();
        $selectedAccount = $accountId ? Account::with('category')->find($accountId) : $accounts->first();

        $ledgers = collect();
        $totalDebit = 0;
        $totalCredit = 0;
        $openingBalance = 0;

        if ($selectedAccount) {
            // Opening balance prior to start date
            $priorLedger = AccountLedger::where('account_id', $selectedAccount->id)
                ->where('date', '<', $startDate)
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            $openingBalance = $priorLedger ? $priorLedger->running_balance : 0;

            $ledgerQuery = AccountLedger::where('account_id', $selectedAccount->id)
                ->whereBetween('date', [$startDate, $endDate])
                ->orderBy('date', 'asc')
                ->orderBy('id', 'asc');

            $ledgers = $ledgerQuery->paginate(30)->withQueryString();
            $totalDebit = AccountLedger::where('account_id', $selectedAccount->id)->whereBetween('date', [$startDate, $endDate])->sum('debit');
            $totalCredit = AccountLedger::where('account_id', $selectedAccount->id)->whereBetween('date', [$startDate, $endDate])->sum('credit');
        }

        return view('manager.reports.ledger', compact(
            'accounts', 'selectedAccount', 'startDate', 'endDate',
            'ledgers', 'openingBalance', 'totalDebit', 'totalCredit'
        ));
    }

    /**
     * 5. Purchase Report (Date-wise & Account-wise)
     */
    public function purchases(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $accountId = $request->input('account_id');
        $reportType = $request->input('report_type', 'datewise');

        $query = Purchase::with(['supplier.category', 'items.product'])
            ->whereBetween('purchase_date', [$startDate, $endDate]);

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $purchases = (clone $query)->orderBy('purchase_date', 'desc')->orderBy('id', 'desc')->paginate(15)->withQueryString();

        // Summary Statistics
        $totalPurchaseOrders = (clone $query)->count();
        $totalPurchaseAmount = (clone $query)->sum('total_amount');
        $totalPaidAmount = (clone $query)->sum('paid_amount');
        $totalBalanceDue = (clone $query)->sum('balance_due');

        // Date-wise Breakdown
        $datewiseBreakdown = DB::table('purchases')
            ->whereBetween('purchase_date', [$startDate, $endDate])
            ->select(
                'purchase_date',
                DB::raw('COUNT(id) as total_count'),
                DB::raw('SUM(total_amount) as total_amount'),
                DB::raw('SUM(paid_amount) as paid_amount'),
                DB::raw('SUM(balance_due) as balance_due')
            )
            ->groupBy('purchase_date')
            ->orderBy('purchase_date', 'desc')
            ->get();

        // Account-wise Breakdown (Suppliers)
        $accountwiseBreakdown = DB::table('purchases')
            ->join('accounts', 'purchases.account_id', '=', 'accounts.id')
            ->leftJoin('account_categories', 'accounts.account_category_id', '=', 'account_categories.id')
            ->whereBetween('purchases.purchase_date', [$startDate, $endDate])
            ->select(
                'accounts.id as account_id',
                'accounts.name as supplier_name',
                'account_categories.title as category_title',
                DB::raw('COUNT(purchases.id) as total_orders'),
                DB::raw('SUM(purchases.total_amount) as total_amount'),
                DB::raw('SUM(purchases.paid_amount) as paid_amount'),
                DB::raw('SUM(purchases.balance_due) as balance_due'),
                'accounts.balance as current_account_balance'
            )
            ->groupBy('accounts.id', 'accounts.name', 'account_categories.title', 'accounts.balance')
            ->get();

        $suppliers = Account::whereHas('category', function ($q) {
            $q->where('title', 'like', '%Supplier%')
              ->orWhere('title', 'like', '%Vendor%');
        })->orderBy('name')->get();

        return view('manager.reports.purchases', compact(
            'purchases', 'startDate', 'endDate', 'accountId', 'suppliers',
            'totalPurchaseOrders', 'totalPurchaseAmount', 'totalPaidAmount', 'totalBalanceDue',
            'datewiseBreakdown', 'accountwiseBreakdown', 'reportType'
        ));
    }
}
