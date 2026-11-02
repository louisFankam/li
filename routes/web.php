<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InsuranceCompanyController;
use App\Http\Controllers\InsuranceController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('vente', [SaleController::class, 'index'])->name('vente');
    Route::post('vente', [SaleController::class, 'store'])->name('vente.store');
    Route::post('vente/preview', [SaleController::class, 'preview'])->name('vente.preview');
    Route::get('vente/{sale}/ticket', [SaleController::class, 'ticket'])->name('vente.ticket');
    Route::post('vente/{sale}/cancel', [SaleController::class, 'cancel'])->name('vente.cancel');
    Route::get('stocks', [ProductController::class, 'index'])->name('stocks');
    Route::get('inventaires', [InventoryController::class, 'index'])->name('inventaires');
    Route::post('inventaires', [InventoryController::class, 'store'])->name('inventaires.store');
    Route::post('inventaires/adjust', [InventoryController::class, 'adjust'])->name('inventaires.adjust');
    Route::post('stocks', [ProductController::class, 'store'])->name('stocks.store');
    Route::put('stocks/{product}', [ProductController::class, 'update'])->name('stocks.update');
    Route::delete('stocks/{product}', [ProductController::class, 'destroy'])->name('stocks.destroy');
    Route::get('assurances', [InsuranceController::class, 'index'])->name('assurances');
    Route::post('assurances/{claim}/status', [InsuranceController::class, 'updateStatus'])->name('assurances.status');
    Route::get('caisses', [CashController::class, 'index'])->name('caisses');
    Route::post('caisses/open', [CashController::class, 'open'])->name('caisses.open');
    Route::post('caisses/{cashSession}/close', [CashController::class, 'close'])->name('caisses.close');
    Route::post('caisses/{cashSession}/movement', [CashController::class, 'movement'])->name('caisses.movement');
    Route::get('administration', [AdminController::class, 'index'])->name('administration');
    Route::get('audit', [AuditController::class, 'index'])->name('audit');
    Route::get('rapports', [ReportController::class, 'index'])->name('rapports');
    Route::get('rapports/export', [ReportController::class, 'export'])->name('rapports.export');

    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('suppliers', SupplierController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('achats', [PurchaseController::class, 'index'])->name('achats.index');
    Route::post('achats', [PurchaseController::class, 'store'])->name('achats.store');
    Route::post('achats/{purchase}/receive', [PurchaseController::class, 'receive'])->name('achats.receive');
    Route::post('achats/{purchase}/status', [PurchaseController::class, 'updateStatus'])->name('achats.status');
    Route::resource('pharmacies', PharmacyController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('pharmacies/active', [PharmacyController::class, 'activate'])->name('pharmacies.activate');
    Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('customers', CustomerController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('insurers', InsuranceCompanyController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('transfers', [TransferController::class, 'index'])->name('transfers.index');
    Route::post('transfers', [TransferController::class, 'store'])->name('transfers.store');
    Route::post('transfers/{transfer}/ship', [TransferController::class, 'ship'])->name('transfers.ship');
    Route::post('transfers/{transfer}/receive', [TransferController::class, 'receive'])->name('transfers.receive');
    Route::post('transfers/{transfer}/cancel', [TransferController::class, 'cancel'])->name('transfers.cancel');

    // Synchronisation offline (PWA)
    Route::middleware('throttle:sync')->group(function () {
        Route::post('sync/device', [SyncController::class, 'registerDevice'])->name('sync.device');
        Route::post('api/sync/push', [SyncController::class, 'push'])->name('sync.push');
        Route::get('api/sync/pull', [SyncController::class, 'pull'])->name('sync.pull');
    });
    Route::get('sync/conflicts', [SyncController::class, 'conflicts'])->name('sync.conflicts');
    Route::post('sync/conflicts/{conflict}/resolve', [SyncController::class, 'resolveConflict'])->name('sync.conflicts.resolve');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
