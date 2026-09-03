<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DiscountApprovalController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Manager\AccountCategoryController;
use App\Http\Controllers\Manager\AccountController;
use App\Http\Controllers\Manager\AccountLedgerController;
use App\Http\Controllers\Manager\AppointmentController;
use App\Http\Controllers\Manager\DashboardController as ManagerDashboardController;
use App\Http\Controllers\Manager\GalleryController;
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

// Role-protected Routes
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::resource('users', UserController::class);
    Route::resource('roles', RoleController::class)->except(['create', 'edit', 'show']);

    // Discount Approval Routes
    Route::get('discount-requests', [DiscountApprovalController::class, 'index'])->name('discount-requests.index');
    Route::post('discount-requests/{discountRequest}/approve', [DiscountApprovalController::class, 'approve'])->name('discount-requests.approve');
    Route::post('discount-requests/{discountRequest}/reject', [DiscountApprovalController::class, 'reject'])->name('discount-requests.reject');
});

Route::middleware(['auth', 'role:manager,admin'])->prefix('manager')->name('manager.')->group(function () {
    Route::get('/dashboard', [ManagerDashboardController::class, 'index'])->name('dashboard');
    Route::resource('service-categories', ServiceCategoryController::class)->except(['create', 'edit', 'show']);
    Route::resource('services', ServiceController::class)->except(['create', 'edit', 'show']);
    Route::resource('products', ProductController::class)->except(['create', 'edit', 'show']);
    Route::post('stores/reset-all', [StoreController::class, 'resetAllStoresStock'])->name('stores.reset-all');
    Route::post('stores/{store}/default', [StoreController::class, 'setDefault'])->name('stores.default');
    Route::post('stores/{store}/reset', [StoreController::class, 'resetStock'])->name('stores.reset');
    Route::post('stores/{store}/inventory', [StoreController::class, 'updateInventory'])->name('stores.inventory.update');
    Route::resource('stores', StoreController::class)->except(['create', 'edit', 'show']);
    Route::get('store-stocks', [StoreStockController::class, 'index'])->name('store-stocks.index');
    Route::resource('stock-transfers', StockTransferController::class)->only(['index', 'store']);
    Route::resource('account-categories', AccountCategoryController::class)->except(['create', 'edit', 'show']);
    Route::resource('accounts', AccountController::class)->except(['create', 'edit', 'show']);
    Route::post('accounts/{account}/transaction', [AccountController::class, 'recordTransaction'])->name('accounts.transaction');
    Route::post('accounts/{account}/reset-password', [AccountController::class, 'resetPassword'])->name('accounts.reset-password');
    Route::resource('purchases', PurchaseController::class)->only(['index', 'store']);
    Route::resource('sales', SaleController::class)->only(['index', 'store']);
    Route::resource('appointments', AppointmentController::class)->only(['index', 'store', 'update']);
    Route::patch('appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])->name('appointments.updateStatus');
    Route::post('appointments/{appointment}/rate', [AppointmentController::class, 'rateEmployee'])->name('appointments.rate');
    Route::get('ledger', [AccountLedgerController::class, 'index'])->name('ledger.index');
    Route::resource('expense-categories', ExpenseCategoryController::class)->except(['create', 'edit', 'show']);
    Route::resource('expenses', ExpenseController::class)->except(['create', 'edit', 'show']);
    Route::get('payroll/deductions', [PayrollController::class, 'deductionsIndex'])->name('payroll.deductions.index');
    Route::post('payroll/deductions', [PayrollController::class, 'storeDeduction'])->name('payroll.deductions.store');
    Route::delete('payroll/deductions/{deduction}', [PayrollController::class, 'destroyDeduction'])->name('payroll.deductions.destroy');
    Route::resource('payroll', PayrollController::class)->except(['create', 'edit', 'show']);

    // Staff Attendance Module
    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::match(['get', 'post'], 'attendance/bulk', [AttendanceController::class, 'bulkStore'])->name('attendance.bulkStore');
    Route::delete('attendance/{attendance}', [AttendanceController::class, 'destroy'])
        ->name('attendance.destroy')
        ->whereNumber('attendance');

    // Commission Management
    Route::resource('commissions', CommissionController::class)->except(['create', 'edit', 'show']);

    // Reports Module Routes
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('sales', [ReportController::class, 'sales'])->name('sales');
        Route::get('stock', [ReportController::class, 'stock'])->name('stock');
        Route::get('services', [ReportController::class, 'services'])->name('services');
        Route::get('ledger', [ReportController::class, 'ledger'])->name('ledger');
        Route::get('purchases', [ReportController::class, 'purchases'])->name('purchases');
    });

    // Brand Identity & System Settings
    Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('settings', [SettingController::class, 'update'])->name('settings.update');
    Route::resource('bank-accounts', BankAccountController::class)->except(['create', 'edit', 'show']);

    // Photo Gallery Showcase & Management
    Route::post('galleries/bulk-delete', [GalleryController::class, 'bulkDelete'])->name('galleries.bulk-delete');
    Route::resource('galleries', GalleryController::class)->except(['create', 'edit', 'show']);
});

require __DIR__.'/auth.php';

// Public Storage File Delivery Fallback (resolves files in storage/app/public across all environments)
Route::get('/storage/{path}', function ($path) {
    $filePath = storage_path('app/public/' . $path);
    if (file_exists($filePath) && is_file($filePath)) {
        $mime = mime_content_type($filePath) ?: 'application/octet-stream';
        return response()->file($filePath, ['Content-Type' => $mime]);
    }
    abort(404);
})->where('path', '.*');
