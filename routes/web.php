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
use App\Http\Controllers\Manager\ProductController;
use App\Http\Controllers\Manager\PurchaseController;
use App\Http\Controllers\Manager\ReportController;
use App\Http\Controllers\Manager\SaleController;
use App\Http\Controllers\Manager\ServiceCategoryController;
use App\Http\Controllers\Manager\ServiceController;
use App\Http\Controllers\Manager\SettingController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

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
});

Route::middleware(['auth', 'role:manager,admin'])->prefix('manager')->name('manager.')->group(function () {
    Route::get('/dashboard', [ManagerDashboardController::class, 'index'])->name('dashboard');
    Route::resource('service-categories', ServiceCategoryController::class)->except(['create', 'edit', 'show']);
    Route::resource('services', ServiceController::class)->except(['create', 'edit', 'show']);
    Route::resource('products', ProductController::class)->except(['create', 'edit', 'show']);
    Route::resource('account-categories', AccountCategoryController::class)->except(['create', 'edit', 'show']);
    Route::resource('accounts', AccountController::class)->except(['create', 'edit', 'show']);
    Route::post('accounts/{account}/transaction', [AccountController::class, 'recordTransaction'])->name('accounts.transaction');
    Route::resource('purchases', PurchaseController::class)->only(['index', 'store']);
    Route::resource('sales', SaleController::class)->only(['index', 'store']);
    Route::resource('appointments', AppointmentController::class)->only(['index', 'store']);
    Route::patch('appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])->name('appointments.updateStatus');
    Route::get('ledger', [AccountLedgerController::class, 'index'])->name('ledger.index');

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

    // Photo Gallery Showcase & Management
    Route::post('galleries/bulk-delete', [GalleryController::class, 'bulkDelete'])->name('galleries.bulk-delete');
    Route::resource('galleries', GalleryController::class)->except(['create', 'edit', 'show']);
});

require __DIR__.'/auth.php';
