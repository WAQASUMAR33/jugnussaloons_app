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
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payroll;
use App\Models\SalaryAdvance;
use App\Models\DayClosing;
use App\Models\BankAccount;
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
            ->whereBetween('sales.sale_date', [$startDate, $endDate]);

        if ($categoryId) {
            $categoryBreakdown->where('accounts.account_category_id', $categoryId);
        }

        $categoryBreakdown = $categoryBreakdown
            ->select(
                'account_categories.title as category_title',
                DB::raw('COUNT(sales.id) as total_count'),
                DB::raw('SUM(sales.total_amount) as gross_amount'),
                DB::raw('SUM(sales.discount) as discount_amount'),
                DB::raw('SUM(sales.received_amount) as received_amount'),
                DB::raw('SUM(sales.balance_due) as balance_due')
            )
            ->groupBy('account_categories.id', 'account_categories.title')
            ->get();

        $accountCategories = AccountCategory::orderBy('title')->get();

        return view('manager.reports.sales', compact(
            'sales', 'startDate', 'endDate', 'categoryId', 'accountCategories',
            'totalSalesCount', 'totalGrossAmount', 'totalDiscount', 'totalNetAmount',
            'totalReceived', 'totalBalanceDue', 'categoryBreakdown', 'datewiseBreakdown', 'reportType'
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
        $type = $request->input('type');
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $accounts = Account::with('category')->orderBy('name')->get();
        $selectedAccount = $accountId ? Account::with('category')->find($accountId) : null;

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
                ->whereBetween('date', [$startDate, $endDate]);

            if ($type) {
                $ledgerQuery->where('type', $type);
            }

            $ledgerQuery->orderBy('date', 'asc')->orderBy('id', 'asc');

            $ledgers = $ledgerQuery->paginate(30)->withQueryString();

            $debitQuery = AccountLedger::where('account_id', $selectedAccount->id)->whereBetween('date', [$startDate, $endDate]);
            $creditQuery = AccountLedger::where('account_id', $selectedAccount->id)->whereBetween('date', [$startDate, $endDate]);
            if ($type) {
                $debitQuery->where('type', $type);
                $creditQuery->where('type', $type);
            }
            $totalDebit = $debitQuery->sum('debit');
            $totalCredit = $creditQuery->sum('credit');
        } else {
            // Consolidated ledger across all accounts
            $ledgerQuery = AccountLedger::with(['account.category'])
                ->whereBetween('date', [$startDate, $endDate]);

            if ($type) {
                $ledgerQuery->where('type', $type);
            }

            $ledgerQuery->orderBy('date', 'desc')->orderBy('id', 'desc');

            $ledgers = $ledgerQuery->paginate(30)->withQueryString();

            $debitQuery = AccountLedger::whereBetween('date', [$startDate, $endDate]);
            $creditQuery = AccountLedger::whereBetween('date', [$startDate, $endDate]);
            if ($type) {
                $debitQuery->where('type', $type);
                $creditQuery->where('type', $type);
            }
            $totalDebit = $debitQuery->sum('debit');
            $totalCredit = $creditQuery->sum('credit');
            $openingBalance = 0;
        }

        return view('manager.reports.ledger', compact(
            'accounts', 'selectedAccount', 'accountId', 'type', 'startDate', 'endDate',
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

        if ($suppliers->isEmpty()) {
            $suppliers = Account::where('type', 'supplier')->orderBy('name')->get();
            if ($suppliers->isEmpty()) {
                $suppliers = Account::orderBy('name')->get();
            }
        }

        return view('manager.reports.purchases', compact(
            'purchases', 'startDate', 'endDate', 'accountId', 'suppliers',
            'totalPurchaseOrders', 'totalPurchaseAmount', 'totalPaidAmount', 'totalBalanceDue',
            'datewiseBreakdown', 'accountwiseBreakdown', 'reportType'
        ));
    }

    /**
     * 6. Profit & Loss Report (Products, Services & Overall Net Profit between dates)
     */
    public function profit(Request $request)
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized. Profit & Loss Report is restricted to Administrators only.');
        }

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        // -------------------------------------------------------------
        // A. PRODUCTS PROFIT CALCULATION
        // Rules:
        // total Sales amount
        // total purchase amount
        // product profit = total sales amount - total purchase amount
        // -------------------------------------------------------------
        $salesQuery = Sale::with(['customer', 'store'])
            ->whereBetween('sale_date', [$startDate, $endDate]);
        
        $totalSalesAmount = (float) (clone $salesQuery)->sum('total_amount');
        $totalSalesDiscount = (float) (clone $salesQuery)->sum('discount');
        $totalSalesNetAmount = $totalSalesAmount - $totalSalesDiscount;
        $totalSalesReceived = (float) (clone $salesQuery)->sum('received_amount');
        $salesCount = (clone $salesQuery)->count();
        $salesList = (clone $salesQuery)->orderBy('sale_date', 'desc')->orderBy('id', 'desc')->take(50)->get();

        $purchasesQuery = Purchase::with(['supplier'])
            ->whereBetween('purchase_date', [$startDate, $endDate]);

        $totalPurchaseAmount = (float) (clone $purchasesQuery)->sum('total_amount');
        $totalPurchasePaid = (float) (clone $purchasesQuery)->sum('paid_amount');
        $purchasesCount = (clone $purchasesQuery)->count();
        $purchasesList = (clone $purchasesQuery)->orderBy('purchase_date', 'desc')->orderBy('id', 'desc')->take(50)->get();

        // Product Profit as defined by rule: total sales amount - total purchase amount
        $productProfit = $totalSalesAmount - $totalPurchaseAmount;

        // -------------------------------------------------------------
        // B. SERVICES PROFIT CALCULATION
        // Rules:
        // total Services amount
        // total expenses
        // total Salaries Paid
        // services profit = total services amount - (total expenses + total salaries paid)
        // -------------------------------------------------------------
        $appointmentsQuery = Appointment::with(['customer', 'employee'])
            ->whereBetween('appointment_date', [$startDate, $endDate]);

        $totalServicesGrossAmount = (float) (clone $appointmentsQuery)->sum('total_amount');
        $totalServicesDiscount = (float) (clone $appointmentsQuery)->sum('discount');
        $totalServicesAmount = (float) (clone $appointmentsQuery)->sum('net_amount');
        $appointmentsCount = (clone $appointmentsQuery)->count();
        $appointmentsList = (clone $appointmentsQuery)->orderBy('appointment_date', 'desc')->orderBy('id', 'desc')->take(50)->get();

        $expensesQuery = Expense::with(['category', 'creator'])
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);

        $totalExpenses = (float) (clone $expensesQuery)->sum('amount');
        $expensesCount = (clone $expensesQuery)->count();
        $expensesList = (clone $expensesQuery)->orderBy('created_at', 'desc')->take(50)->get();

        // Expense category breakdown
        $expenseCategoryBreakdown = Expense::whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->select('exp_category_id', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as count'))
            ->with('category')
            ->groupBy('exp_category_id')
            ->orderBy('total_amount', 'desc')
            ->get();

        // Salaries Paid:
        // 1) Paid payrolls with payment_date in range (or updated_at if null)
        $paidPayrollsQuery = Payroll::with(['employee'])
            ->where('status', 'paid')
            ->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('payment_date', [$startDate, $endDate])
                  ->orWhere(function($sub) use ($startDate, $endDate) {
                      $sub->whereNull('payment_date')
                          ->whereDate('updated_at', '>=', $startDate)
                          ->whereDate('updated_at', '<=', $endDate);
                  });
            });
        $totalPaidPayrollAmount = (float) (clone $paidPayrollsQuery)->sum('net_salary');
        $paidPayrollsList = (clone $paidPayrollsQuery)->get();

        // 2) Salary Advances paid in range
        $advancesQuery = SalaryAdvance::with(['employee'])
            ->whereBetween('advance_date', [$startDate, $endDate]);
        $totalAdvancesAmount = (float) (clone $advancesQuery)->sum('amount');
        $advancesList = (clone $advancesQuery)->get();

        // Total Salaries Paid = Paid Payrolls + Direct Advances
        $totalSalariesPaid = $totalPaidPayrollAmount + $totalAdvancesAmount;

        // Services Profit as defined by rule: total services amount - (total expenses + total salaries paid)
        $servicesProfit = $totalServicesAmount - ($totalExpenses + $totalSalariesPaid);

        // -------------------------------------------------------------
        // C. NET PROFIT CALCULATION
        // Rule: Net Profit = product profit + services profit
        // -------------------------------------------------------------
        $netProfit = $productProfit + $servicesProfit;

        // High-level aggregates
        $totalRevenue = $totalSalesAmount + $totalServicesAmount;
        $totalCosts = $totalPurchaseAmount + $totalExpenses + $totalSalariesPaid;
        $profitMargin = $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 2) : 0;

        return view('manager.reports.profit', compact(
            'startDate', 'endDate',
            // Products
            'totalSalesAmount', 'totalSalesDiscount', 'totalSalesNetAmount', 'totalSalesReceived', 'salesCount', 'salesList',
            'totalPurchaseAmount', 'totalPurchasePaid', 'purchasesCount', 'purchasesList',
            'productProfit',
            // Services
            'totalServicesGrossAmount', 'totalServicesDiscount', 'totalServicesAmount', 'appointmentsCount', 'appointmentsList',
            'totalExpenses', 'expensesCount', 'expensesList', 'expenseCategoryBreakdown',
            'totalSalariesPaid', 'totalPaidPayrollAmount', 'totalAdvancesAmount', 'paidPayrollsList', 'advancesList',
            'servicesProfit',
            // Net
            'netProfit', 'totalRevenue', 'totalCosts', 'profitMargin'
        ));
    }

    /**
     * 7. Daily Day Closing Report & Cash/Bank Reconciliation
     * Calculates Opening Balance, All Inflows (Receivings), All Outflows (Payments)
     * broken down into Cash and Bank, with Net Flows, Closing Balances, and Drawer Reconciliation.
     */
    public function dayClosing(Request $request)
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized. Daily Day Closing Report is restricted to Administrators only.');
        }

        $date = $request->input('date', now()->toDateString());

        // -------------------------------------------------------------
        // 1. OPENING BALANCE (Set to 0.00 in all cases as required)
        // -------------------------------------------------------------
        $openingCash = 0.0;
        $openingBank = 0.0;
        $totalOpening = 0.0;

        // -------------------------------------------------------------
        // 2. DAILY RECEIVINGS ON SELECTED DATE (INFLOWS)
        // -------------------------------------------------------------
        // Appointments
        $allAppointments = Appointment::with(['customer', 'employee'])
            ->where('appointment_date', $date)
            ->where('paid_amount', '>', 0)
            ->get();

        $cashAptTotal = 0.0;
        $bankAptTotal = 0.0;
        foreach ($allAppointments as $a) {
            $mode = strtolower(trim($a->payment_mode ?? ''));
            if ($mode === 'split' && !empty($a->split_payments)) {
                $splits = is_array($a->split_payments) ? $a->split_payments : json_decode($a->split_payments, true);
                $cashAptTotal += (float) (($splits['Cash'] ?? 0) + ($splits['Other'] ?? 0));
                $bankAptTotal += (float) (($splits['Bank'] ?? 0) + ($splits['Card'] ?? 0));
            } elseif (in_array($mode, ['bank', 'card', 'bank transfer', 'online'])) {
                $bankAptTotal += (float) $a->paid_amount;
            } else {
                $cashAptTotal += (float) $a->paid_amount;
            }
        }
        $totalAptReceived = $cashAptTotal + $bankAptTotal;

        // Product Sales
        $allSales = Sale::with(['customer', 'store'])
            ->where('sale_date', $date)
            ->where('received_amount', '>', 0)
            ->get();

        $cashSalesTotal = 0.0;
        $bankSalesTotal = 0.0;
        foreach ($allSales as $s) {
            $mode = strtolower(trim($s->payment_mode ?? ''));
            if ($mode === 'split' && !empty($s->split_payments)) {
                $splits = is_array($s->split_payments) ? $s->split_payments : json_decode($s->split_payments, true);
                $cashSalesTotal += (float) (($splits['Cash'] ?? 0) + ($splits['Other'] ?? 0));
                $bankSalesTotal += (float) (($splits['Bank'] ?? 0) + ($splits['Card'] ?? 0));
            } elseif (in_array($mode, ['bank', 'card', 'bank transfer', 'online'])) {
                $bankSalesTotal += (float) $s->received_amount;
            } elseif ($mode !== 'issue stock') {
                $cashSalesTotal += (float) $s->received_amount;
            }
        }
        $totalSalesReceived = $cashSalesTotal + $bankSalesTotal;

        // Direct Customer Receivings (from ledger, not ending in -REC)
        $directReceivings = AccountLedger::with('account')
            ->where('date', $date)
            ->where('type', 'receiving')
            ->where('reference_no', 'not like', '%-REC%')
            ->get();
        $cashDirectReceivingTotal = (float) $directReceivings->sum('credit');
        $bankDirectReceivingTotal = 0.0;
        $totalDirectReceivings = $cashDirectReceivingTotal + $bankDirectReceivingTotal;

        // Aggregated Receivings
        $totalCashReceived = $cashAptTotal + $cashSalesTotal + $cashDirectReceivingTotal;
        $totalBankReceived = $bankAptTotal + $bankSalesTotal + $bankDirectReceivingTotal;
        $totalReceived = $totalCashReceived + $totalBankReceived;

        // -------------------------------------------------------------
        // 3. DAILY PAYMENTS ON SELECTED DATE (OUTFLOWS)
        // -------------------------------------------------------------
        // Operational Expenses
        $expenses = Expense::with(['category', 'creator'])
            ->whereDate('created_at', $date)
            ->get();
        $totalExpensesCash = (float) $expenses->sum('amount');
        $totalExpensesBank = 0.0;
        $totalExpenses = $totalExpensesCash + $totalExpensesBank;

        // Purchases Paid
        $purchases = Purchase::with(['supplier', 'store'])
            ->where('purchase_date', $date)
            ->where('paid_amount', '>', 0)
            ->get();
        $totalPurchasesCash = (float) $purchases->sum('paid_amount');
        $totalPurchasesBank = 0.0;
        $totalPurchases = $totalPurchasesCash + $totalPurchasesBank;

        // Payroll Salaries Paid
        $payrolls = Payroll::with('employee')
            ->where('payment_date', $date)
            ->where('status', 'paid')
            ->get();
        $totalPayrollCash = (float) $payrolls->sum('net_salary');
        $totalPayrollBank = 0.0;
        $totalPayroll = $totalPayrollCash + $totalPayrollBank;

        // Salary Advances Disbursed
        $advances = SalaryAdvance::with(['employee', 'bankAccount'])
            ->where('advance_date', $date)
            ->get();
        $cashAdvances = $advances->filter(fn($a) => strtolower($a->payment_mode ?? '') !== 'bank transfer');
        $bankAdvances = $advances->filter(fn($a) => strtolower($a->payment_mode ?? '') === 'bank transfer');
        $totalAdvancesCash = (float) $cashAdvances->sum('amount');
        $totalAdvancesBank = (float) $bankAdvances->sum('amount');
        $totalAdvances = $totalAdvancesCash + $totalAdvancesBank;

        // Direct Ledger Payments (not -PAY, not ADV-SAL-, not PAYROLL-)
        $directPayments = AccountLedger::with('account')
            ->where('date', $date)
            ->where('type', 'payment')
            ->where('reference_no', 'not like', '%-PAY%')
            ->where('reference_no', 'not like', 'ADV-SAL-%')
            ->where('reference_no', 'not like', 'PAYROLL-%')
            ->get();
        $totalDirectPaymentsCash = (float) $directPayments->sum('credit');
        $totalDirectPaymentsBank = 0.0;
        $totalDirectPayments = $totalDirectPaymentsCash + $totalDirectPaymentsBank;

        // Aggregated Payments
        $totalCashPaid = $totalExpensesCash + $totalPurchasesCash + $totalPayrollCash + $totalAdvancesCash + $totalDirectPaymentsCash;
        $totalBankPaid = $totalExpensesBank + $totalPurchasesBank + $totalPayrollBank + $totalAdvancesBank + $totalDirectPaymentsBank;
        $totalPaid = $totalCashPaid + $totalBankPaid;

        // -------------------------------------------------------------
        // 4. NET MOVEMENT & CLOSING BALANCES
        // -------------------------------------------------------------
        $netCashFlow = $totalCashReceived - $totalCashPaid;
        $netBankFlow = $totalBankReceived - $totalBankPaid;
        $totalNetFlow = $totalReceived - $totalPaid;

        $closingCashExpected = $openingCash + $netCashFlow;
        $closingBankExpected = $openingBank + $netBankFlow;
        $closingTotalExpected = $totalOpening + $totalNetFlow;

        // -------------------------------------------------------------
        // 5. SAVED CLOSING RECORD & PHYSICAL RECONCILIATION
        // -------------------------------------------------------------
        $dayClosing = DayClosing::with('closedBy')->where('closing_date', $date)->first();

        // Bank Accounts list for Bank Breakdown tab
        $bankAccounts = BankAccount::where('is_active', true)->get();

        $appSetting = \App\Models\Setting::first();

        return view('manager.reports.day_closing', compact(
            'date',
            // Opening
            'openingCash', 'openingBank', 'totalOpening',
            // Receivings
            'cashAptTotal', 'bankAptTotal', 'totalAptReceived', 'allAppointments',
            'cashSalesTotal', 'bankSalesTotal', 'totalSalesReceived', 'allSales',
            'cashDirectReceivingTotal', 'bankDirectReceivingTotal', 'totalDirectReceivings', 'directReceivings',
            'totalCashReceived', 'totalBankReceived', 'totalReceived',
            // Payments
            'expenses', 'totalExpensesCash', 'totalExpensesBank', 'totalExpenses',
            'purchases', 'totalPurchasesCash', 'totalPurchasesBank', 'totalPurchases',
            'payrolls', 'totalPayrollCash', 'totalPayrollBank', 'totalPayroll',
            'advances', 'cashAdvances', 'bankAdvances', 'totalAdvancesCash', 'totalAdvancesBank', 'totalAdvances',
            'directPayments', 'totalDirectPaymentsCash', 'totalDirectPaymentsBank', 'totalDirectPayments',
            'totalCashPaid', 'totalBankPaid', 'totalPaid',
            // Net & Closing
            'netCashFlow', 'netBankFlow', 'totalNetFlow',
            'closingCashExpected', 'closingBankExpected', 'closingTotalExpected',
            // Saved record & metadata
            'dayClosing', 'bankAccounts', 'appSetting'
        ));
    }

    /**
     * Save / Lock Day Closing with actual counted physical cash & denominations
     */
    public function saveDayClosing(Request $request)
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized. Daily Day Closing Report is restricted to Administrators only.');
        }

        $validated = $request->validate([
            'closing_date' => ['required', 'date'],
            'opening_cash' => ['required', 'numeric'],
            'opening_bank' => ['required', 'numeric'],
            'total_opening' => ['required', 'numeric'],
            'cash_received' => ['required', 'numeric'],
            'bank_received' => ['required', 'numeric'],
            'total_received' => ['required', 'numeric'],
            'cash_paid' => ['required', 'numeric'],
            'bank_paid' => ['required', 'numeric'],
            'total_paid' => ['required', 'numeric'],
            'net_cash_flow' => ['required', 'numeric'],
            'net_bank_flow' => ['required', 'numeric'],
            'total_net_flow' => ['required', 'numeric'],
            'closing_cash_expected' => ['required', 'numeric'],
            'closing_bank_expected' => ['required', 'numeric'],
            'closing_total_expected' => ['required', 'numeric'],
            'cash_actual_counted' => ['nullable', 'numeric', 'min:0'],
            'denominations' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:open,closed,verified'],
        ]);

        $cashCounted = isset($validated['cash_actual_counted']) ? (float) $validated['cash_actual_counted'] : null;
        $expectedCash = (float) $validated['closing_cash_expected'];
        $diff = $cashCounted !== null ? round($cashCounted - $expectedCash, 2) : null;

        DayClosing::updateOrCreate(
            ['closing_date' => $validated['closing_date']],
            [
                'opening_cash' => 0.0,
                'opening_bank' => 0.0,
                'total_opening' => 0.0,
                'cash_received' => $validated['cash_received'],
                'bank_received' => $validated['bank_received'],
                'total_received' => $validated['total_received'],
                'cash_paid' => $validated['cash_paid'],
                'bank_paid' => $validated['bank_paid'],
                'total_paid' => $validated['total_paid'],
                'net_cash_flow' => $validated['net_cash_flow'],
                'net_bank_flow' => $validated['net_bank_flow'],
                'total_net_flow' => $validated['total_net_flow'],
                'closing_cash_expected' => $expectedCash,
                'closing_bank_expected' => $validated['closing_bank_expected'],
                'closing_total_expected' => $validated['closing_total_expected'],
                'cash_actual_counted' => $cashCounted,
                'cash_difference' => $diff,
                'denominations' => $validated['denominations'] ?? null,
                'status' => $validated['status'],
                'closed_by' => auth()->id(),
                'closed_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ]
        );

        return redirect()->route('manager.reports.day-closing', ['date' => $validated['closing_date']])
            ->with('success', 'Day Closing record saved and updated successfully!');
    }
}
