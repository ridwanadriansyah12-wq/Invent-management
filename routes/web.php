<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryHealthController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
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

    // ── Master SKU Mutasi (Khusus Admin — Approver & Staff 403) ───────────────
    Route::middleware('can:manage-sku')->group(function () {
        Route::get('/items/create',              [ItemController::class, 'create'])->name('items.create');
        Route::post('/items',                    [ItemController::class, 'store'])->name('items.store');
        Route::get('/items/{item}/edit',         [ItemController::class, 'edit'])->name('items.edit');
        Route::put('/items/{item}',              [ItemController::class, 'update'])->name('items.update');
        Route::delete('/items/{item}',           [ItemController::class, 'destroy'])->name('items.destroy');
    });

    // ── Master SKU & Persediaan (Semua Peran: Admin, Approver, Staff) ────────
    Route::middleware('can:view-inventory')->group(function () {
        Route::get('/items',        [ItemController::class, 'index'])->name('items.index');
        Route::get('/items/{item}', [ItemController::class, 'show'])->name('items.show');

        // Stock Movements (Buku Besar & Pencatatan Mutasi)
        Route::get('/stock-movements',        [StockMovementController::class, 'index'])->name('stock-movements.index');
        Route::get('/stock-movements/create', [StockMovementController::class, 'create'])->name('stock-movements.create');
        Route::post('/stock-movements',       [StockMovementController::class, 'store'])->name('stock-movements.store');
        Route::get('/stock-movements/export', [StockMovementController::class, 'export'])->name('stock-movements.export');

        // Backward compatibility routes for transactions
        Route::get('/transactions',           [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('/transactions/create',    [TransactionController::class, 'create'])->name('transactions.create');
        Route::post('/transactions',          [TransactionController::class, 'store'])->name('transactions.store');

        // Kapasitas Gudang (Warehouses)
        Route::get('/warehouses',             [WarehouseController::class, 'index'])->name('warehouses.index');
        Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show'])->name('warehouses.show');

        // Diagnostik & Simulator Kebijakan Persediaan
        Route::get('/inventory/health',       [InventoryHealthController::class, 'index'])->name('inventory.health');
        Route::get('/inventory/simulator',    [InventoryHealthController::class, 'simulator'])->name('inventory.simulator');
    });

    // ── Manajemen Gudang & Kapasitas (Khusus Admin) ───────────────────────────
    Route::middleware('can:manage-settings')->group(function () {
        Route::post('/warehouses',                            [WarehouseController::class, 'store'])->name('warehouses.store');
        Route::put('/warehouses/{warehouse}',                 [WarehouseController::class, 'update'])->name('warehouses.update');
        Route::post('/warehouses/{warehouse}/check-capacity', [WarehouseController::class, 'triggerCheck'])->name('warehouses.check-capacity');
    });

    // ── Master Pendukung (Kategori & Supplier) ───────────────────────────────
    Route::middleware('role:staff,admin')->group(function () {

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

    // ── Navigation Counters JSON (Lightweight 60s Polling) ───────────────────
    Route::get('/nav/counters', function () {
        return response()->json([
            'pending_reviews'   => \Illuminate\Support\Facades\Cache::remember('prism_pending_review_count', 60, fn() => \App\Models\InventoryParameter::where('status', 'PENDING_REVIEW')->count()),
            'unresolved_alerts' => \Illuminate\Support\Facades\Cache::remember('prism_unresolved_alerts_count', 60, fn() => \App\Models\SystemAlert::whereNull('resolved_at')->count()),
            'open_prs'          => \Illuminate\Support\Facades\Cache::remember('prism_open_pr_count', 60, fn() => \App\Models\PurchaseRequisition::where('status', 'OPEN')->count()),
        ]);
    })->name('nav.counters');

    // ── Pipeline Trigger (Admin Only) ────────────────────────────────────────
    Route::middleware('can:run-pipeline')->group(function () {
        Route::post('/pipeline/run-now', function (\App\Services\Inventory\InventoryPipelineCoordinator $coordinator) {
            try {
                $summary = $coordinator->runDailyPipeline(null, auth()->id());
                return back()->with('success', 'Pipeline persediaan harian berhasil dijalankan! (ID Run: ' . substr($summary['run_id'], 0, 8) . ')');
            } catch (\Throwable $e) {
                return back()->with('error', 'Gagal menjalankan pipeline: ' . $e->getMessage());
            }
        })->name('pipeline.run-now');
    });
});
