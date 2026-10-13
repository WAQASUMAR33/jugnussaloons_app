<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Manager\AccountCategoryController;
use App\Http\Controllers\Manager\AccountController;
use App\Http\Controllers\Manager\AccountLedgerController;
use App\Http\Controllers\Manager\AppointmentController;
use App\Http\Controllers\Manager\DashboardController as ManagerDashboardController;
use App\Http\Controllers\Manager\GalleryController;
use App\Http\Controllers\Manager\ProductCategoryController;
use App\Http\Controllers\Manager\ProductMainCategoryController;
use App\Http\Controllers\Manager\ProductController;
use App\Http\Controllers\Manager\PurchaseController;
use App\Http\Controllers\Manager\ReportController;
use App\Http\Controllers\Manager\SaleController;
use App\Http\Controllers\Manager\ServiceCategoryController;
use App\Http\Controllers\Manager\ServiceController;
use App\Http\Controllers\Manager\ExpenseCategoryController;
use App\Http\Controllers\Manager\ExpenseController;
use App\Http\Controllers\Manager\PayrollController;
use App\Http\Controllers\Manager\AttendanceController;
use App\Http\Controllers\Manager\CommissionController;
use App\Http\Controllers\Manager\SettingController;
use App\Http\Controllers\Manager\BankAccountController;
use App\Http\Controllers\Manager\StoreController;
use App\Http\Controllers\Manager\StoreStockController;
use App\Http\Controllers\Manager\StockTransferController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Role & Permission protected Routes
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::resource('users', UserController::class)->middleware('permission:manage-users');
    Route::resource('roles', RoleController::class)->except(['create', 'edit', 'show'])->middleware('permission:manage-roles');
});

Route::middleware(['auth', 'role:manager,admin'])->prefix('manager')->name('manager.')->group(function () {
    Route::get('/dashboard', [ManagerDashboardController::class, 'index'])->name('dashboard');
    
    // Services & Treatments
    Route::middleware('permission:manage-services,manage-service-categories')->group(function () {
        Route::resource('service-categories', ServiceCategoryController::class)->except(['create', 'edit', 'show']);
        Route::resource('services', ServiceController::class)->except(['create', 'edit', 'show']);
    });

    // Products & Inventory Catalog
    Route::middleware('permission:manage-products,manage-product-categories')->group(function () {
        Route::get('product-categories/subcategories', [ProductCategoryController::class, 'getSubCategories'])->name('product-categories.subcategories');
        Route::resource('product-main-categories', ProductMainCategoryController::class)->except(['create', 'edit', 'show']);
        Route::resource('product-categories', ProductCategoryController::class)->except(['create', 'edit', 'show']);
        Route::resource('products', ProductController::class)->except(['create', 'edit', 'show']);
    });

    // Stores, Store Stocks & Stock Transfers
    Route::middleware('permission:manage-stores,manage-store-stocks,manage-stock-transfers')->group(function () {
        Route::post('stores/reset-all', [StoreController::class, 'resetAllStoresStock'])->name('stores.reset-all');
        Route::post('stores/{store}/default', [StoreController::class, 'setDefault'])->name('stores.default');
        Route::post('stores/{store}/reset', [StoreController::class, 'resetStock'])->name('stores.reset');
        Route::post('stores/{store}/inventory', [StoreController::class, 'updateInventory'])->name('stores.inventory.update');
        Route::resource('stores', StoreController::class)->except(['create', 'edit', 'show']);
        Route::get('store-stocks', [StoreStockController::class, 'index'])->name('store-stocks.index');
        Route::resource('stock-transfers', StockTransferController::class)->only(['index', 'store']);
    });

    // Customer Accounts & Client Directory
    Route::middleware('permission:manage-accounts,manage-account-categories')->group(function () {
        Route::resource('account-categories', AccountCategoryController::class)->except(['create', 'edit', 'show']);
        Route::resource('accounts', AccountController::class)->except(['create', 'edit', 'show']);
        Route::post('accounts/{account}/transaction', [AccountController::class, 'recordTransaction'])->name('accounts.transaction');
        Route::post('accounts/{account}/reset-password', [AccountController::class, 'resetPassword'])->name('accounts.reset-password');
    });

    // Supplier Purchases
    Route::middleware('permission:manage-purchases')->group(function () {
        Route::get('purchases/{purchase}/print', [PurchaseController::class, 'print'])->name('purchases.print');
        Route::resource('purchases', PurchaseController::class)->only(['index', 'store']);
    });

    // POS Sales
    Route::middleware('permission:manage-sales')->group(function () {
        Route::resource('sales', SaleController::class)->only(['index', 'store', 'update']);
        Route::post('sales/{sale}/pay-remaining', [SaleController::class, 'payRemaining'])->name('sales.payRemaining');
    });

    // Appointments & Bookings
    Route::middleware('permission:manage-appointments,book-appointment')->group(function () {
        Route::post('appointments/verify-admin-password', [AppointmentController::class, 'verifyAdminPassword'])->name('appointments.verify-admin-password');
        Route::get('appointments/create', [AppointmentController::class, 'create'])->name('appointments.create');
        Route::get('appointments/upcoming', [AppointmentController::class, 'upcoming'])->name('appointments.upcoming');
        Route::get('appointments/{appointment}/edit', [AppointmentController::class, 'edit'])->name('appointments.edit');
        Route::resource('appointments', AppointmentController::class)->only(['index', 'store', 'update']);
        Route::patch('appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])->name('appointments.updateStatus');
        Route::post('appointments/{appointment}/pay-remaining', [AppointmentController::class, 'payRemaining'])->name('appointments.payRemaining');
        Route::post('appointments/{appointment}/rate', [AppointmentController::class, 'rateEmployee'])->name('appointments.rate');
        Route::post('appointments/{appointment}/upload-receipt', [AppointmentController::class, 'uploadReceipt'])->name('appointments.upload-receipt');
    });

    // Dedicated Staff Feedback & Ratings Module
    Route::middleware('permission:manage-ratings,manage-appointments')->group(function () {
        Route::get('ratings', [\App\Http\Controllers\Manager\StaffRatingController::class, 'index'])->name('ratings.index');
        Route::post('ratings', [\App\Http\Controllers\Manager\StaffRatingController::class, 'store'])->name('ratings.store');
    });

    // General Ledgers
    Route::get('ledger', [AccountLedgerController::class, 'index'])->name('ledger.index')->middleware('permission:manage-ledger,manage-accounts');

    // Expenses & Expense Categories
    Route::middleware('permission:manage-expenses,manage-expense-categories')->group(function () {
        Route::resource('expense-categories', ExpenseCategoryController::class)->except(['create', 'edit', 'show']);
        Route::resource('expenses', ExpenseController::class)->except(['create', 'edit', 'show']);
    });

    // Staff Payroll, Deductions & Advances
    Route::middleware('permission:manage-payroll,manage-payroll-deductions,manage-payroll-advances')->group(function () {
        Route::get('payroll/deductions', [PayrollController::class, 'deductionsIndex'])->name('payroll.deductions.index');
        Route::post('payroll/deductions', [PayrollController::class, 'storeDeduction'])->name('payroll.deductions.store');
        Route::delete('payroll/deductions/{deduction}', [PayrollController::class, 'destroyDeduction'])->name('payroll.deductions.destroy');
        Route::get('payroll/advances', [PayrollController::class, 'advancesIndex'])->name('payroll.advances.index');
        Route::post('payroll/advances', [PayrollController::class, 'storeAdvance'])->name('payroll.advances.store');
        Route::delete('payroll/advances/{advance}', [PayrollController::class, 'destroyAdvance'])->name('payroll.advances.destroy');
        Route::get('payroll/employee-data', [PayrollController::class, 'getEmployeeData'])->name('payroll.employee.data');
        Route::post('payroll/sync/{payroll}', [PayrollController::class, 'syncFromDatabase'])->name('payroll.sync');
        Route::post('payroll/generate-all', [PayrollController::class, 'generateAllForMonth'])->name('payroll.generate.all');
        Route::resource('payroll', PayrollController::class)->except(['create', 'edit', 'show']);
    });

    // Staff Attendance Module
    Route::middleware('permission:manage-attendance')->group(function () {
        Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('attendance', [AttendanceController::class, 'store'])->name('attendance.store');
        Route::match(['get', 'post'], 'attendance/bulk', [AttendanceController::class, 'bulkStore'])->name('attendance.bulkStore');
        Route::delete('attendance/{attendance}', [AttendanceController::class, 'destroy'])
            ->name('attendance.destroy')
            ->whereNumber('attendance');
    });

    // Commission Management
    Route::middleware('permission:manage-commissions,manage-payroll')->group(function () {
        Route::get('commissions/print', [CommissionController::class, 'print'])->name('commissions.print');
        Route::resource('commissions', CommissionController::class)->except(['create', 'edit', 'show']);
    });

    // Reports Module Routes
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('sales', [ReportController::class, 'sales'])->name('sales')->middleware('permission:view-sales-reports,view-reports');
        Route::get('stock', [ReportController::class, 'stock'])->name('stock')->middleware('permission:view-stock-reports,view-reports');
        Route::get('services', [ReportController::class, 'services'])->name('services')->middleware('permission:view-service-reports,view-reports');
        Route::get('ledger', [ReportController::class, 'ledger'])->name('ledger')->middleware('permission:view-ledger-reports,view-reports');
        Route::get('purchases', [ReportController::class, 'purchases'])->name('purchases')->middleware('permission:view-purchase-reports,view-reports');
        Route::get('profit', [ReportController::class, 'profit'])->name('profit')->middleware('permission:view-profit-report');
        Route::get('aesthetic-profit', [ReportController::class, 'aestheticProfit'])->name('aesthetic-profit')->middleware('permission:view-profit-report');
        Route::get('day-closing', [ReportController::class, 'dayClosing'])->name('day-closing')->middleware('permission:manage-day-closing');
        Route::post('day-closing/save', [ReportController::class, 'saveDayClosing'])->name('day-closing.save')->middleware('permission:manage-day-closing');
    });

    // Brand Identity & System Settings
    Route::middleware('permission:manage-settings')->group(function () {
        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('settings', [SettingController::class, 'update'])->name('settings.update');
    });

    // Bank Accounts
    Route::resource('bank-accounts', BankAccountController::class)->except(['create', 'edit', 'show'])->middleware('permission:manage-bank-accounts,manage-settings');

    // Photo Gallery Showcase & Management
    Route::middleware('permission:manage-gallery')->group(function () {
        Route::post('galleries/bulk-delete', [GalleryController::class, 'bulkDelete'])->name('galleries.bulk-delete');
        Route::delete('galleries/delete-all', [GalleryController::class, 'deleteAll'])->name('galleries.delete-all');
        Route::resource('galleries', GalleryController::class)->except(['create', 'edit', 'show']);
    });
});

require __DIR__.'/auth.php';

// Public Storage & Uploads Delivery Fallback (resolves files in storage/app/public across all environments)
Route::get('/storage/{path}', [\App\Http\Controllers\StorageFileController::class, 'show'])
    ->where('path', '.*')
    ->name('storage.file');

Route::get('/public/storage/{path}', [\App\Http\Controllers\StorageFileController::class, 'show'])
    ->where('path', '.*')
    ->name('public.storage.file');

Route::get('/uploads/{path}', [\App\Http\Controllers\StorageFileController::class, 'show'])
    ->where('path', '.*')
    ->name('uploads.file');

// Public Customer Feedback / Stylist Rating Kiosk Route
Route::get('/staff-feedback', [\App\Http\Controllers\Manager\StaffRatingController::class, 'publicFeedback'])->name('public.feedback');
Route::post('/staff-feedback', [\App\Http\Controllers\Manager\StaffRatingController::class, 'store'])->name('public.feedback.store');

