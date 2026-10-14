<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\AccountLedger;
use App\Models\Appointment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMainCategory;
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
use App\Models\AppointmentPayment;
use App\Models\SaleItem;
use App\Models\AppointmentService;
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

        $query = Sale::with(['customer.category', 'items.product', 'bankAccount'])
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
     * 2. Stock Report (Main Category & Sub-Category Support)
     */
    public function stock(Request $request)
    {
        $status = $request->input('status'); // all, low, out, healthy
        $search = $request->input('search');
        $mainCategoryId = $request->input('product_main_category_id');
        $categoryId = $request->input('product_category_id');
        $storeId = $request->input('store_id');

        $mainCategories = ProductMainCategory::where('is_active', true)->orderBy('title')->get();
        $categories = ProductCategory::orderBy('title')->get();
        $stores = Store::where('is_active', true)->orderBy('is_default', 'desc')->orderBy('name')->get();

        $query = Product::with(['mainCategory', 'category', 'storeStocks.store']);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($mainCategoryId) {
            $query->where('product_main_category_id', $mainCategoryId);
        }

        if ($categoryId) {
            $query->where('product_category_id', $categoryId);
        }

        if ($status === 'out') {
            $query->where('stock', '<=', 0);
        } elseif ($status === 'low') {
            $query->where('stock', '>', 0)->where('stock', '<=', 5);
        } elseif ($status === 'healthy') {
            $query->where('stock', '>', 5);
        }

        $products = (clone $query)->orderBy('stock', 'asc')->paginate(15)->withQueryString();

        // Inventory summary calculations for filtered scope
        $totalProductsCount = (clone $query)->count();
        $totalStockUnits = (int) (clone $query)->sum('stock');
        $totalCostValuation = (float) (clone $query)->sum(DB::raw('stock * price'));
        $totalRetailValuation = (float) (clone $query)->sum(DB::raw('stock * COALESCE(NULLIF(discounted_price, 0), price)'));
        $lowStockCount = (int) (clone $query)->where('stock', '>', 0)->where('stock', '<=', 5)->count();
        $outOfStockCount = (int) (clone $query)->where('stock', '<=', 0)->count();

        return view('manager.reports.stock', compact(
            'products', 'mainCategories', 'categories', 'mainCategoryId', 'categoryId',
            'stores', 'storeId', 'status', 'search', 'totalProductsCount',
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

        $query = Appointment::with([
            'customer',
            'employee',
            'items' => function ($q) use ($serviceCategoryId) {
                $q->with('service.category');
                if ($serviceCategoryId) {
                    $q->whereHas('service', function ($sq) use ($serviceCategoryId) {
                        $sq->where('service_category_id', $serviceCategoryId);
                    });
                }
            }
        ])
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

        // Calculate category-specific net bill and commission for each appointment row
        $appointments->getCollection()->transform(function ($apt) use ($serviceCategoryId) {
            if ($serviceCategoryId) {
                $catGross = (float) $apt->items->sum('discounted_price');
                $ratio = (float) $apt->total_amount > 0 ? ((float) $apt->net_amount / (float) $apt->total_amount) : 1;
                $apt->display_net_amount = round($catGross * $ratio, 2);
                $apt->display_commission = (float) $apt->items->sum('commission');
            } else {
                $apt->display_net_amount = (float) $apt->net_amount;
                $apt->display_commission = (float) $apt->total_commission;
            }
            return $apt;
        });

        // Summary Cards & Totals
        if ($serviceCategoryId) {
            $catSummary = DB::table('appointment_services')
                ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
                ->join('services', 'appointment_services.saloon_service_id', '=', 'services.id')
                ->whereBetween('appointments.appointment_date', [$startDate, $endDate])
                ->where('services.service_category_id', $serviceCategoryId)
                ->when($employeeId, function ($q) use ($employeeId) {
                    $q->where('appointments.employee_id', $employeeId);
                })
                ->selectRaw('
                    SUM(appointment_services.discounted_price) as gross_revenue,
                    SUM(appointment_services.discounted_price * (CASE WHEN appointments.total_amount > 0 THEN (appointments.net_amount / appointments.total_amount) ELSE 1 END)) as net_revenue,
                    SUM(appointment_services.commission) as commission
                ')
                ->first();

            $totalAppointmentsCount = (clone $query)->count();
            $totalGrossRevenue = (float) ($catSummary->gross_revenue ?? 0);
            $totalNetRevenue = (float) ($catSummary->net_revenue ?? 0);
            $totalDiscount = max(0, round($totalGrossRevenue - $totalNetRevenue, 2));
            $totalCommission = (float) ($catSummary->commission ?? 0);
        } else {
            $totalAppointmentsCount = (clone $query)->count();
            $totalGrossRevenue = (float) (clone $query)->sum('total_amount');
            $totalDiscount = (float) (clone $query)->sum('discount');
            $totalNetRevenue = (float) (clone $query)->sum('net_amount');
            $totalCommission = (float) (clone $query)->sum('total_commission');
        }

        // Date-wise Report Breakdown (Filtered by date, stylist, and service category)
        if ($serviceCategoryId) {
            $datewiseBreakdown = DB::table('appointment_services')
                ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
                ->join('services', 'appointment_services.saloon_service_id', '=', 'services.id')
                ->whereBetween('appointments.appointment_date', [$startDate, $endDate])
                ->where('services.service_category_id', $serviceCategoryId)
                ->when($employeeId, function ($q) use ($employeeId) {
                    $q->where('appointments.employee_id', $employeeId);
                })
                ->select(
                    'appointments.appointment_date',
                    DB::raw('COUNT(DISTINCT appointments.id) as total_count'),
                    DB::raw('SUM(appointment_services.discounted_price) as gross_amount'),
                    DB::raw('SUM(appointment_services.discounted_price - (appointment_services.discounted_price * (CASE WHEN appointments.total_amount > 0 THEN (appointments.net_amount / appointments.total_amount) ELSE 1 END))) as discount_amount'),
                    DB::raw('SUM(appointment_services.discounted_price * (CASE WHEN appointments.total_amount > 0 THEN (appointments.net_amount / appointments.total_amount) ELSE 1 END)) as net_amount'),
                    DB::raw('SUM(appointment_services.commission) as total_commission')
                )
                ->groupBy('appointments.appointment_date')
                ->orderBy('appointments.appointment_date', 'desc')
                ->get();
        } else {
            $datewiseQuery = DB::table('appointments')
                ->whereBetween('appointments.appointment_date', [$startDate, $endDate]);

            if ($employeeId) {
                $datewiseQuery->where('appointments.employee_id', $employeeId);
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
        }

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

        $preset = $request->input('preset');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if (!$startDate && !$endDate) {
            $startDate = now()->startOfMonth()->toDateString();
            $endDate = now()->toDateString();
            if (!$preset) {
                $preset = 'this_month';
            }
        } elseif (!$preset) {
            $today = now()->toDateString();
            $yesterday = now()->subDay()->toDateString();
            $startOfWeek = now()->startOfWeek()->toDateString();
            $startOfMonth = now()->startOfMonth()->toDateString();
            $endOfLastMonth = now()->subMonth()->endOfMonth()->toDateString();
            $startOfLastMonth = now()->subMonth()->startOfMonth()->toDateString();
            $startOfYear = now()->startOfYear()->toDateString();

            if ($startDate === $today && $endDate === $today) {
                $preset = 'today';
            } elseif ($startDate === $yesterday && $endDate === $yesterday) {
                $preset = 'yesterday';
            } elseif ($startDate === $startOfWeek && ($endDate === $today || $endDate === now()->endOfWeek()->toDateString())) {
                $preset = 'this_week';
            } elseif ($startDate === $startOfMonth && ($endDate === $today || $endDate === now()->endOfMonth()->toDateString())) {
                $preset = 'this_month';
            } elseif ($startDate === $startOfLastMonth && $endDate === $endOfLastMonth) {
                $preset = 'last_month';
            } elseif ($startDate === $startOfYear && ($endDate === $today || $endDate === now()->endOfYear()->toDateString())) {
                $preset = 'this_year';
            }
        }

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
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->whereNotIn('status', ['cancelled', 'rejected']);

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

        // -------------------------------------------------------------
        // D. AESTHETIC SECTION PROFIT & LOSS SUMMARY
        // -------------------------------------------------------------
        $aestheticProdSalesQuery = SaleItem::whereHas('product.category', function ($q) {
                $q->where('title', 'like', '%Aesthetic%');
            })
            ->whereHas('sale', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('sale_date', [$startDate, $endDate]);
            });
        $aestheticProdSalesTotal = (float) (clone $aestheticProdSalesQuery)->sum('subtotal');
        $aestheticProdSalesCount = (clone $aestheticProdSalesQuery)->count();

        $aestheticServiceSalesQuery = AppointmentService::whereHas('service.category', function ($q) {
                $q->where('title', 'like', '%Aesthetic%');
            })
            ->whereHas('appointment', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('appointment_date', [$startDate, $endDate])
                  ->whereNotIn('status', ['cancelled', 'rejected']);
            });
        $aestheticServiceSalesTotal = (float) (clone $aestheticServiceSalesQuery)->sum('discounted_price');
        $aestheticServiceSalesCount = (clone $aestheticServiceSalesQuery)->count();

        $aestheticExpensesQuery = Expense::whereHas('category', function ($q) {
                $q->where('title', 'like', '%Aesthetic%');
            })
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);
        $aestheticExpensesTotal = (float) (clone $aestheticExpensesQuery)->sum('amount');
        $aestheticExpensesCount = (clone $aestheticExpensesQuery)->count();

        $aestheticTotalSales = $aestheticProdSalesTotal + $aestheticServiceSalesTotal;
        $aestheticNetProfit = $aestheticTotalSales - $aestheticExpensesTotal;
        $aestheticProfitMargin = $aestheticTotalSales > 0 ? round(($aestheticNetProfit / $aestheticTotalSales) * 100, 2) : 0;

        return view('manager.reports.profit', compact(
            'startDate', 'endDate', 'preset',
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
            'netProfit', 'totalRevenue', 'totalCosts', 'profitMargin',
            // Aesthetic Summary
            'aestheticProdSalesTotal', 'aestheticProdSalesCount',
            'aestheticServiceSalesTotal', 'aestheticServiceSalesCount',
            'aestheticExpensesTotal', 'aestheticExpensesCount',
            'aestheticTotalSales', 'aestheticNetProfit', 'aestheticProfitMargin'
        ));
    }

    /**
     * Dedicated Aesthetic Profit & Loss Report
     * Calculates Revenue from Aesthetic Product Sales + Aesthetic Service Bookings,
     * subtracts Aesthetic Category Expenses, and presents Net Profit and Margin.
     */
    public function aestheticProfit(Request $request)
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized. Aesthetic Profit & Loss Report is restricted to Administrators only.');
        }

        $preset = $request->input('preset');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if (!$startDate && !$endDate) {
            $startDate = now()->startOfMonth()->toDateString();
            $endDate = now()->toDateString();
            if (!$preset) {
                $preset = 'this_month';
            }
        } elseif (!$preset) {
            $today = now()->toDateString();
            $yesterday = now()->subDay()->toDateString();
            $startOfWeek = now()->startOfWeek()->toDateString();
            $startOfMonth = now()->startOfMonth()->toDateString();
            $endOfLastMonth = now()->subMonth()->endOfMonth()->toDateString();
            $startOfLastMonth = now()->subMonth()->startOfMonth()->toDateString();
            $startOfYear = now()->startOfYear()->toDateString();

            if ($startDate === $today && $endDate === $today) {
                $preset = 'today';
            } elseif ($startDate === $yesterday && $endDate === $yesterday) {
                $preset = 'yesterday';
            } elseif ($startDate === $startOfWeek && ($endDate === $today || $endDate === now()->endOfWeek()->toDateString())) {
                $preset = 'this_week';
            } elseif ($startDate === $startOfMonth && ($endDate === $today || $endDate === now()->endOfMonth()->toDateString())) {
                $preset = 'this_month';
            } elseif ($startDate === $startOfLastMonth && $endDate === $endOfLastMonth) {
                $preset = 'last_month';
            } elseif ($startDate === $startOfYear && ($endDate === $today || $endDate === now()->endOfYear()->toDateString())) {
                $preset = 'this_year';
            }
        }

        // 1. Aesthetic Product Sales
        $aestheticProdSalesQuery = SaleItem::with(['sale.customer', 'product.category'])
            ->whereHas('product.category', function ($q) {
                $q->where('title', 'like', '%Aesthetic%');
            })
            ->whereHas('sale', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('sale_date', [$startDate, $endDate]);
            });
        $aestheticProdSalesTotal = (float) (clone $aestheticProdSalesQuery)->sum('subtotal');
        $aestheticProdSalesCount = (clone $aestheticProdSalesQuery)->count();
        $aestheticProdSalesList = (clone $aestheticProdSalesQuery)->orderBy('created_at', 'desc')->get();

        // 2. Aesthetic Services Bookings
        $aestheticServiceSalesQuery = AppointmentService::with(['appointment.customer', 'appointment.employee', 'service.category'])
            ->whereHas('service.category', function ($q) {
                $q->where('title', 'like', '%Aesthetic%');
            })
            ->whereHas('appointment', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('appointment_date', [$startDate, $endDate])
                  ->whereNotIn('status', ['cancelled', 'rejected']);
            });
        $aestheticServiceSalesTotal = (float) (clone $aestheticServiceSalesQuery)->sum('discounted_price');
        $aestheticServiceSalesCount = (clone $aestheticServiceSalesQuery)->count();
        $aestheticServiceSalesList = (clone $aestheticServiceSalesQuery)->orderBy('created_at', 'desc')->get();

        // 3. Aesthetic Category Expenses
        $aestheticExpensesQuery = Expense::with(['category', 'creator'])
            ->whereHas('category', function ($q) {
                $q->where('title', 'like', '%Aesthetic%');
            })
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);
        $aestheticExpensesTotal = (float) (clone $aestheticExpensesQuery)->sum('amount');
        $aestheticExpensesCount = (clone $aestheticExpensesQuery)->count();
        $aestheticExpensesList = (clone $aestheticExpensesQuery)->orderBy('created_at', 'desc')->get();

        // Totals & Net Profit
        $aestheticTotalSales = $aestheticProdSalesTotal + $aestheticServiceSalesTotal;
        $aestheticNetProfit = $aestheticTotalSales - $aestheticExpensesTotal;
        $aestheticProfitMargin = $aestheticTotalSales > 0 ? round(($aestheticNetProfit / $aestheticTotalSales) * 100, 2) : 0;

        $appSetting = \App\Models\Setting::first();

        return view('manager.reports.aesthetic_profit', compact(
            'startDate', 'endDate', 'preset',
            'aestheticProdSalesTotal', 'aestheticProdSalesCount', 'aestheticProdSalesList',
            'aestheticServiceSalesTotal', 'aestheticServiceSalesCount', 'aestheticServiceSalesList',
            'aestheticExpensesTotal', 'aestheticExpensesCount', 'aestheticExpensesList',
            'aestheticTotalSales', 'aestheticNetProfit', 'aestheticProfitMargin',
            'appSetting'
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
        // 1. SAVED CLOSING RECORD & OPENING BALANCE
        // -------------------------------------------------------------
        $dayClosing = DayClosing::with('closedBy')->where('closing_date', $date)->first();

        // Determine Opening Balance for selected date:
        if ($dayClosing && ($dayClosing->opening_cash > 0 || $dayClosing->opening_bank > 0 || $dayClosing->status !== 'open')) {
            $openingCash = (float) ($dayClosing->opening_cash ?? 0.0);
            $openingBank = (float) ($dayClosing->opening_bank ?? 0.0);
        } else {
            // Check previous closed day's designated next day opening float
            $prevClosing = DayClosing::where('closing_date', '<', $date)
                ->whereIn('status', ['closed', 'verified'])
                ->orderBy('closing_date', 'desc')
                ->first();

            if (!$prevClosing) {
                // If not locked yet, check any prior day closing record
                $prevClosing = DayClosing::where('closing_date', '<', $date)
                    ->orderBy('closing_date', 'desc')
                    ->first();
            }

            if ($prevClosing) {
                $openingCash = $prevClosing->next_day_opening_cash !== null
                    ? (float) $prevClosing->next_day_opening_cash
                    : (float) ($prevClosing->cash_actual_counted ?? $prevClosing->closing_cash_expected ?? 0.0);

                $openingBank = $prevClosing->next_day_opening_bank !== null
                    ? (float) $prevClosing->next_day_opening_bank
                    : (float) ($prevClosing->closing_bank_expected ?? 0.0);
            } else {
                $openingCash = 0.0;
                $openingBank = 0.0;
            }
        }
        $totalOpening = $openingCash + $openingBank;

        // -------------------------------------------------------------
        // 2. DAILY RECEIVINGS ON SELECTED DATE (INFLOWS)
        // -------------------------------------------------------------
        // Appointments (Query actual payments received on this calendar date, excluding cancelled/rejected bookings)
        $allAppointments = AppointmentPayment::with(['appointment.customer', 'appointment.employee', 'bankAccount'])
            ->where('payment_date', $date)
            ->whereHas('appointment', function($q) {
                $q->whereNotIn('status', ['cancelled', 'rejected']);
            })
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
                $bankAptTotal += (float) $a->amount;
            } else {
                $cashAptTotal += (float) $a->amount;
            }
        }
        $totalAptReceived = $cashAptTotal + $bankAptTotal;

        // Product Sales
        $allSales = Sale::with(['customer', 'store', 'bankAccount'])
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
        $bankAccounts = BankAccount::where('is_active', true)->orderBy('bank_name')->get();

        // -------------------------------------------------------------
        // ITEMIZE INFLOWS & OUTFLOWS PER SPECIFIC BANK ACCOUNT
        // -------------------------------------------------------------
        $bankBreakdown = [];
        foreach ($bankAccounts as $ba) {
            $bankBreakdown[$ba->bankid] = [
                'bankid' => $ba->bankid,
                'bank_name' => $ba->bank_name,
                'account_title' => $ba->account_title,
                'account_no' => $ba->account_no,
                'branch_name' => $ba->branch_name,
                'iban' => $ba->iban,
                'opening' => 0.0,
                'received_appointments' => 0.0,
                'received_sales' => 0.0,
                'total_received' => 0.0,
                'paid_advances' => 0.0,
                'total_paid' => 0.0,
                'net_flow' => 0.0,
                'closing_expected' => 0.0,
                'appointments_count' => 0,
                'sales_count' => 0,
            ];
        }

        $unassignedBankReceived = 0.0;
        $unassignedBankPaid = 0.0;
        $unassignedAptCount = 0;

        foreach ($allAppointments as $a) {
            $mode = strtolower(trim($a->payment_mode ?? ''));
            $aptBankAmt = 0.0;
            if ($mode === 'split' && !empty($a->split_payments)) {
                $splits = is_array($a->split_payments) ? $a->split_payments : json_decode($a->split_payments, true);
                $aptBankAmt = (float) (($splits['Bank'] ?? 0) + ($splits['Card'] ?? 0));
            } elseif (in_array($mode, ['bank', 'card', 'bank transfer', 'online'])) {
                $aptBankAmt = (float) $a->amount;
            }

            if ($aptBankAmt > 0) {
                if ($a->bank_account_id && isset($bankBreakdown[$a->bank_account_id])) {
                    $bankBreakdown[$a->bank_account_id]['received_appointments'] += $aptBankAmt;
                    $bankBreakdown[$a->bank_account_id]['total_received'] += $aptBankAmt;
                    $bankBreakdown[$a->bank_account_id]['appointments_count']++;
                } else {
                    $unassignedBankReceived += $aptBankAmt;
                    $unassignedAptCount++;
                }
            }
        }

        $unassignedSalesReceived = 0.0;
        $unassignedSalesCount = 0;
        foreach ($allSales as $s) {
            $mode = strtolower(trim($s->payment_mode ?? ''));
            $saleBankAmt = 0.0;
            if ($mode === 'split' && !empty($s->split_payments)) {
                $splits = is_array($s->split_payments) ? $s->split_payments : json_decode($s->split_payments, true);
                $saleBankAmt = (float) (($splits['Bank'] ?? 0) + ($splits['Card'] ?? 0));
            } elseif (in_array($mode, ['bank', 'card', 'bank transfer', 'online'])) {
                $saleBankAmt = (float) $s->received_amount;
            }

            if ($saleBankAmt > 0) {
                if ($s->bank_account_id && isset($bankBreakdown[$s->bank_account_id])) {
                    $bankBreakdown[$s->bank_account_id]['received_sales'] += $saleBankAmt;
                    $bankBreakdown[$s->bank_account_id]['total_received'] += $saleBankAmt;
                    $bankBreakdown[$s->bank_account_id]['sales_count']++;
                } else {
                    $unassignedBankReceived += $saleBankAmt;
                    $unassignedSalesReceived += $saleBankAmt;
                    $unassignedSalesCount++;
                }
            }
        }

        // Outflows: Advances disbursed through bank
        foreach ($bankAdvances as $adv) {
            $advAmt = (float) $adv->amount;
            if ($advAmt > 0) {
                if ($adv->bank_account_id && isset($bankBreakdown[$adv->bank_account_id])) {
                    $bankBreakdown[$adv->bank_account_id]['paid_advances'] += $advAmt;
                    $bankBreakdown[$adv->bank_account_id]['total_paid'] += $advAmt;
                } else {
                    $unassignedBankPaid += $advAmt;
                }
            }
        }

        if ($unassignedBankReceived > 0 || $unassignedBankPaid > 0) {
            $bankBreakdown['unassigned'] = [
                'bankid' => 'unassigned',
                'bank_name' => 'General / Other Digital Transfers',
                'account_title' => 'Unassigned Bank Channel',
                'account_no' => 'N/A',
                'branch_name' => '',
                'iban' => '',
                'opening' => 0.0,
                'received_appointments' => $unassignedBankReceived - $unassignedSalesReceived,
                'received_sales' => $unassignedSalesReceived,
                'total_received' => $unassignedBankReceived,
                'paid_advances' => $unassignedBankPaid,
                'total_paid' => $unassignedBankPaid,
                'net_flow' => $unassignedBankReceived - $unassignedBankPaid,
                'closing_expected' => $unassignedBankReceived - $unassignedBankPaid,
                'appointments_count' => $unassignedAptCount,
                'sales_count' => $unassignedSalesCount,
            ];
        }

        foreach ($bankBreakdown as $bId => &$bInfo) {
            $bInfo['net_flow'] = $bInfo['total_received'] - $bInfo['total_paid'];
            $bInfo['closing_expected'] = $bInfo['opening'] + $bInfo['net_flow'];
        }
        unset($bInfo);

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
            'dayClosing', 'bankAccounts', 'bankBreakdown', 'appSetting'
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
            'next_day_opening_cash' => ['nullable', 'numeric', 'min:0'],
            'next_day_opening_bank' => ['nullable', 'numeric', 'min:0'],
        ]);

        $cashCounted = isset($validated['cash_actual_counted']) ? (float) $validated['cash_actual_counted'] : null;
        $expectedCash = (float) $validated['closing_cash_expected'];
        $diff = $cashCounted !== null ? round($cashCounted - $expectedCash, 2) : null;

        $nextDayOpeningCash = isset($validated['next_day_opening_cash']) && $validated['next_day_opening_cash'] !== '' 
            ? (float) $validated['next_day_opening_cash'] 
            : null;
        $nextDayOpeningBank = isset($validated['next_day_opening_bank']) && $validated['next_day_opening_bank'] !== '' 
            ? (float) $validated['next_day_opening_bank'] 
            : null;
        $nextDayOpeningTotal = ($nextDayOpeningCash !== null || $nextDayOpeningBank !== null)
            ? (float) (($nextDayOpeningCash ?? 0) + ($nextDayOpeningBank ?? 0))
            : null;

        DayClosing::updateOrCreate(
            ['closing_date' => $validated['closing_date']],
            [
                'opening_cash' => (float) $validated['opening_cash'],
                'opening_bank' => (float) $validated['opening_bank'],
                'total_opening' => (float) $validated['total_opening'],
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
                'next_day_opening_cash' => $nextDayOpeningCash,
                'next_day_opening_bank' => $nextDayOpeningBank,
                'next_day_opening_total' => $nextDayOpeningTotal,
            ]
        );

        // Automatically initialize or update next day's opening balance if specified
        if ($nextDayOpeningCash !== null || $nextDayOpeningBank !== null) {
            $nextDate = \Carbon\Carbon::parse($validated['closing_date'])->addDay()->toDateString();
            $nextDayRecord = DayClosing::where('closing_date', $nextDate)->first();

            if (!$nextDayRecord) {
                DayClosing::create([
                    'closing_date' => $nextDate,
                    'opening_cash' => $nextDayOpeningCash ?? 0.0,
                    'opening_bank' => $nextDayOpeningBank ?? 0.0,
                    'total_opening' => $nextDayOpeningTotal ?? 0.0,
                    'status' => 'open',
                    'notes' => 'Opening balance brought forward from ' . \Carbon\Carbon::parse($validated['closing_date'])->format('d M Y') . ' day closing.',
                ]);
            } elseif ($nextDayRecord->status === 'open') {
                $nextDayRecord->update([
                    'opening_cash' => $nextDayOpeningCash ?? 0.0,
                    'opening_bank' => $nextDayOpeningBank ?? 0.0,
                    'total_opening' => $nextDayOpeningTotal ?? 0.0,
                ]);
            }
        }

        $successMsg = 'Day Closing record saved and locked successfully!';
        if ($nextDayOpeningCash !== null) {
            $successMsg .= ' Next day opening balance set to Rs. ' . number_format($nextDayOpeningCash, 2) . '.';
        }

        return redirect()->route('manager.reports.day-closing', ['date' => $validated['closing_date']])
            ->with('success', $successMsg);
    }

    /**
     * Directly set or update the Starting Opening Balance for a specific calendar date
     */
    public function setOpeningBalance(Request $request)
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized. Daily Day Closing Report is restricted to Administrators only.');
        }

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'opening_bank' => ['required', 'numeric', 'min:0'],
        ]);

        $cash = (float) $validated['opening_cash'];
        $bank = (float) $validated['opening_bank'];
        $total = $cash + $bank;

        $record = DayClosing::where('closing_date', $validated['date'])->first();

        if ($record) {
            $record->opening_cash = $cash;
            $record->opening_bank = $bank;
            $record->total_opening = $total;

            // Recalculate expected closing and discrepancy to guarantee consistency
            if ($record->status !== 'open' || $record->closing_cash_expected > 0) {
                $record->closing_cash_expected = $cash + (float) $record->net_cash_flow;
                $record->closing_bank_expected = $bank + (float) $record->net_bank_flow;
                $record->closing_total_expected = $total + (float) $record->total_net_flow;
                if ($record->cash_actual_counted !== null) {
                    $record->cash_difference = round((float) $record->cash_actual_counted - (float) $record->closing_cash_expected, 2);
                }
            }

            $record->save();
        } else {
            DayClosing::create([
                'closing_date' => $validated['date'],
                'opening_cash' => $cash,
                'opening_bank' => $bank,
                'total_opening' => $total,
                'status' => 'open',
                'notes' => 'Starting opening balance directly initialized.',
            ]);
        }

        return redirect()->route('manager.reports.day-closing', ['date' => $validated['date']])
            ->with('success', 'Starting Opening Float for ' . \Carbon\Carbon::parse($validated['date'])->format('d M Y') . ' updated to Rs. ' . number_format($cash, 2) . ' (Cash) & Rs. ' . number_format($bank, 2) . ' (Bank)!');
    }

    /**
     * Day Closing History & Comprehensive Register Audit Trail
     */
    public function dayClosingHistory(Request $request)
    {
        if (!auth()->user()->hasRole('admin') && !auth()->user()->hasPermission('manage-day-closing')) {
            abort(403, 'Unauthorized. Daily Day Closing History is restricted to Administrators only.');
        }

        // Quick date presets
        $preset = $request->input('preset', 'this_month');
        $now = now();

        switch ($preset) {
            case 'today':
                $startDate = $now->toDateString();
                $endDate = $now->toDateString();
                break;
            case 'last_7_days':
                $startDate = $now->copy()->subDays(6)->toDateString();
                $endDate = $now->toDateString();
                break;
            case 'this_month':
                $startDate = $now->copy()->startOfMonth()->toDateString();
                $endDate = $now->copy()->endOfMonth()->toDateString();
                break;
            case 'last_month':
                $startDate = $now->copy()->subMonth()->startOfMonth()->toDateString();
                $endDate = $now->copy()->subMonth()->endOfMonth()->toDateString();
                break;
            case 'this_year':
                $startDate = $now->copy()->startOfYear()->toDateString();
                $endDate = $now->copy()->endOfYear()->toDateString();
                break;
            case 'all':
                $startDate = null;
                $endDate = null;
                break;
            case 'custom':
            default:
                $startDate = $request->input('start_date', $now->copy()->startOfMonth()->toDateString());
                $endDate = $request->input('end_date', $now->toDateString());
                break;
        }

        // Query builder
        $query = DayClosing::with('closedBy');

        if ($startDate && $endDate) {
            $query->whereBetween('closing_date', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->where('closing_date', '>=', $startDate);
        } elseif ($endDate) {
            $query->where('closing_date', '<=', $endDate);
        }

        // Status filter
        $status = $request->input('status', 'all');
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        // Discrepancy filter
        $discrepancy = $request->input('discrepancy', 'all');
        if ($discrepancy === 'balanced') {
            $query->where('cash_difference', 0);
        } elseif ($discrepancy === 'shortage') {
            $query->where('cash_difference', '<', 0);
        } elseif ($discrepancy === 'surplus') {
            $query->where('cash_difference', '>', 0);
        } elseif ($discrepancy === 'has_diff') {
            $query->where('cash_difference', '!=', 0);
        }

        // Search filter (notes or closedBy user name)
        $search = trim($request->input('search', ''));
        if ($search !== '') {
            $query->where(function($q) use ($search) {
                $q->where('notes', 'like', "%{$search}%")
                  ->orWhereHas('closedBy', function($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Calculate KPI summary figures across all matched records (unpaginated)
        $summaryQuery = clone $query;
        $allMatched = $summaryQuery->get();

        $metrics = [
            'total_days' => $allMatched->count(),
            'closed_days' => $allMatched->whereIn('status', ['closed', 'verified'])->count(),
            'open_days' => $allMatched->where('status', 'open')->count(),
            'total_opening' => (float) $allMatched->sum('total_opening'),
            'total_received' => (float) $allMatched->sum('total_received'),
            'cash_received' => (float) $allMatched->sum('cash_received'),
            'bank_received' => (float) $allMatched->sum('bank_received'),
            'total_paid' => (float) $allMatched->sum('total_paid'),
            'cash_paid' => (float) $allMatched->sum('cash_paid'),
            'bank_paid' => (float) $allMatched->sum('bank_paid'),
            'net_flow' => (float) $allMatched->sum('total_net_flow'),
            'closing_cash_expected' => (float) $allMatched->sum('closing_cash_expected'),
            'closing_bank_expected' => (float) $allMatched->sum('closing_bank_expected'),
            'closing_total_expected' => (float) $allMatched->sum('closing_total_expected'),
            'cash_actual_counted' => (float) $allMatched->sum('cash_actual_counted'),
            'balanced_days' => $allMatched->where('cash_difference', 0)->count(),
            'shortage_days' => $allMatched->where('cash_difference', '<', 0)->count(),
            'surplus_days' => $allMatched->where('cash_difference', '>', 0)->count(),
            'total_shortage' => (float) abs($allMatched->where('cash_difference', '<', 0)->sum('cash_difference')),
            'total_surplus' => (float) $allMatched->where('cash_difference', '>', 0)->sum('cash_difference'),
        ];

        // Paginate records ordered by closing_date DESC
        $closings = $query->orderBy('closing_date', 'desc')->paginate(25)->withQueryString();

        $appSetting = \App\Models\Setting::first();

        return view('manager.reports.day_closing_history', compact(
            'closings',
            'metrics',
            'startDate',
            'endDate',
            'preset',
            'status',
            'discrepancy',
            'search',
            'appSetting'
        ));
    }
}
