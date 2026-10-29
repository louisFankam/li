<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InsuranceCompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InsuranceController;
use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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
    Route::get('stocks', [ProductController::class, 'index'])->name('stocks');
    Route::post('stocks', [ProductController::class, 'store'])->name('stocks.store');
    Route::put('stocks/{product}', [ProductController::class, 'update'])->name('stocks.update');
    Route::delete('stocks/{product}', [ProductController::class, 'destroy'])->name('stocks.destroy');
    Route::get('assurances', [InsuranceController::class, 'index'])->name('assurances');
    Route::get('caisses', [CashController::class, 'index'])->name('caisses');
    Route::get('administration', [AdminController::class, 'index'])->name('administration');

    Route::resource('categories', CategoryController::class)->only(['index','store','update','destroy']);
    Route::resource('suppliers', SupplierController::class)->only(['index','store','update','destroy']);
    Route::resource('pharmacies', PharmacyController::class)->only(['index','store','update','destroy']);
    Route::resource('users', UserController::class)->only(['index','store','update','destroy']);
    Route::resource('customers', CustomerController::class)->only(['index','store','destroy']);
    Route::resource('insurers', InsuranceCompanyController::class)->only(['index','store','destroy']);
    Route::get('transfers', [TransferController::class, 'index'])->name('transfers.index');
    Route::post('transfers', [TransferController::class, 'store'])->name('transfers.store');
    Route::post('transfers/{transfer}/ship', [TransferController::class, 'ship'])->name('transfers.ship');
    Route::post('transfers/{transfer}/receive', [TransferController::class, 'receive'])->name('transfers.receive');
    Route::post('transfers/{transfer}/cancel', [TransferController::class, 'cancel'])->name('transfers.cancel');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
