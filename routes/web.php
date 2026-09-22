<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', fn() => redirect()->route('dashboard'));

// ── Guest routes ────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',     [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',    [AuthController::class, 'login']);
    Route::get('/register',  [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// ── Authenticated routes ─────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Dashboard – role-adaptive
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── Notifications (ALL roles) ────────────────────────────────────────────
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/',                       [NotificationController::class, 'index'])->name('index');
        Route::post('/read-all',              [NotificationController::class, 'markAllRead'])->name('read-all');
        Route::get('/unread-count',           [NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::post('/{notification}/read',   [NotificationController::class, 'markRead'])->name('read');
        Route::delete('/{notification}',      [NotificationController::class, 'destroy'])->name('destroy');
    });

    // ── Shared read/write: Items & Transactions (Gudang + Procurement) ────────
    Route::middleware('role:gudang,procurement')->group(function () {
        Route::get('/items',                       [ItemController::class, 'index'])->name('items.index');
        Route::get('/items/{item}',                [ItemController::class, 'show'])->name('items.show');
        Route::post('/items/{item}/override',      [ItemController::class, 'overrideParameters'])->name('items.override');
        Route::post('/items/{item}/reset-override', [ItemController::class, 'resetOverride'])->name('items.reset-override');
        Route::get('/transactions',                [TransactionController::class, 'index'])->name('transactions.index');
    });

    // ── Gudang-only write routes ─────────────────────────────────────────────
    Route::middleware('role:gudang')->group(function () {
        Route::post('/items/import',             [ItemController::class, 'import'])->name('items.import');
        Route::get('/items/create',              [ItemController::class, 'create'])->name('items.create');
        Route::post('/items',                    [ItemController::class, 'store'])->name('items.store');
        Route::get('/items/{item}/edit',         [ItemController::class, 'edit'])->name('items.edit');
        Route::put('/items/{item}',              [ItemController::class, 'update'])->name('items.update');
        Route::delete('/items/{item}',           [ItemController::class, 'destroy'])->name('items.destroy');
        Route::post('/items/{item}/recalculate', [ItemController::class, 'recalculate'])->name('items.recalculate');

        Route::get('/transactions/create',       [TransactionController::class, 'create'])->name('transactions.create');
        Route::post('/transactions',             [TransactionController::class, 'store'])->name('transactions.store');

        Route::get('/categories',                [CategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories',               [CategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}',     [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}',  [CategoryController::class, 'destroy'])->name('categories.destroy');

        Route::get('/suppliers',                 [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('/suppliers/create',          [SupplierController::class, 'create'])->name('suppliers.create');
        Route::post('/suppliers',                [SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
        Route::put('/suppliers/{supplier}',      [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('/suppliers/{supplier}',   [SupplierController::class, 'destroy'])->name('suppliers.destroy');
    });

    // ── Procurement-only routes ──────────────────────────────────────────────
    Route::middleware('role:procurement')->group(function () {
        Route::get('/purchase-orders',                            [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::get('/purchase-orders/create',                     [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
        Route::post('/purchase-orders',                           [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
        Route::get('/purchase-orders/{purchaseOrder}',            [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
        Route::post('/purchase-orders/{purchaseOrder}/receive',   [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
        Route::post('/purchase-orders/{purchaseOrder}/cancel',    [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');

        Route::get('/reports',                                    [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export-stock',                       [ReportController::class, 'exportStock'])->name('reports.export-stock');
        Route::get('/reports/export-transactions',                [ReportController::class, 'exportTransactions'])->name('reports.export-transactions');
    });

    // ── IT-only routes ───────────────────────────────────────────────────────
    Route::middleware('role:it')->group(function () {
        Route::get('/users',                     [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create',              [UserController::class, 'create'])->name('users.create');
        Route::post('/users',                    [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit',         [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}',              [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/toggle-active',[UserController::class, 'toggleActive'])->name('users.toggle-active');
    });
});
