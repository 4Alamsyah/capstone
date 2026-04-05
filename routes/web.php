<?php

use App\Http\Controllers\BomController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PartController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\WorkCenterController;
use App\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/api/dashboard/analytics', [DashboardController::class, 'analytics'])->name('api.dashboard.analytics');
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::prefix('parts')->group(function () {
        Route::get('/', [PartController::class, 'index'])->name('parts.index');
        Route::get('/register', [PartController::class, 'create'])->name('parts.create');
        Route::post('/', [PartController::class, 'store'])->name('parts.store');
        Route::put('/{part}', [PartController::class, 'update'])->name('parts.update');
        Route::delete('/{part}', [PartController::class, 'destroy'])->name('parts.destroy');
        Route::get('/stock', [PartController::class, 'stock'])->name('parts.stock');
    });

    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

    Route::get('/work-centers', [WorkCenterController::class, 'index'])->name('work-centers.index');
    Route::post('/work-centers', [WorkCenterController::class, 'store'])->name('work-centers.store');
    Route::put('/work-centers/{workCenter}', [WorkCenterController::class, 'update'])->name('work-centers.update');
    Route::delete('/work-centers/{workCenter}', [WorkCenterController::class, 'destroy'])->name('work-centers.destroy');

    Route::get('/bom', [BomController::class, 'index'])->name('bom.index');
    Route::get('/bom/create', [BomController::class, 'create'])->name('bom.create');
    Route::post('/bom', [BomController::class, 'store'])->name('bom.store');
    Route::get('/bom/{bom}', [BomController::class, 'show'])->name('bom.show');
    Route::put('/bom/{bom}', [BomController::class, 'update'])->name('bom.update');
    Route::delete('/bom/{bom}', [BomController::class, 'destroy'])->name('bom.destroy');

    Route::get('/work-orders', [WorkOrderController::class, 'index'])->name('work-orders.index');
    Route::get('/work-orders/create', [WorkOrderController::class, 'create'])->name('work-orders.create');
    Route::get('/work-orders/report', [WorkOrderController::class, 'reportIndex'])->name('work-orders.report.index');
    Route::get('/work-orders/logs', [WorkOrderController::class, 'logs'])->name('work-orders.logs');
    Route::post('/work-orders', [WorkOrderController::class, 'store'])->name('work-orders.store');
    Route::get('/work-orders/{workOrder}/report', [WorkOrderController::class, 'report'])->name('work-orders.report.form');
    Route::post('/work-orders/{workOrder}/report', [WorkOrderController::class, 'submitReport'])->name('work-orders.report.store');
    Route::get('/work-orders/{workOrder}', [WorkOrderController::class, 'show'])->name('work-orders.show');
    Route::put('/work-orders/{workOrder}', [WorkOrderController::class, 'update'])->name('work-orders.update');
    Route::delete('/work-orders/{workOrder}', [WorkOrderController::class, 'destroy'])->name('work-orders.destroy');

    Route::prefix('sales')->group(function () {
        Route::get('/customers', [CustomerController::class, 'index'])->name('sales.customers.index');
        Route::post('/customers', [CustomerController::class, 'store'])->name('sales.customers.store');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('sales.customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('sales.customers.destroy');

        Route::get('/customer-orders', [CustomerOrderController::class, 'index'])->name('sales.customer-orders.index');
        Route::get('/customer-orders/create', [CustomerOrderController::class, 'create'])->name('sales.customer-orders.create');
        Route::post('/customer-orders', [CustomerOrderController::class, 'store'])->name('sales.customer-orders.store');
        Route::post('/customer-orders/{customerOrder}/confirm', [CustomerOrderController::class, 'confirm'])->name('sales.customer-orders.confirm');
        Route::patch('/customer-orders/{customerOrder}/status', [CustomerOrderController::class, 'updateStatus'])->name('sales.customer-orders.status');
        Route::post('/customer-orders/{customerOrder}/undo-report', [CustomerOrderController::class, 'undoReport'])->name('sales.customer-orders.undo-report');
        Route::get('/customer-orders/{customerOrder}/delivery-order', [CustomerOrderController::class, 'deliveryOrder'])->name('sales.customer-orders.delivery-order');

        Route::get('/quotations', [QuotationController::class, 'index'])->name('sales.quotations.index');
        Route::get('/quotations/create', [QuotationController::class, 'create'])->name('sales.quotations.create');
        Route::post('/quotations', [QuotationController::class, 'store'])->name('sales.quotations.store');
        Route::get('/quotations/{quotation}/edit', [QuotationController::class, 'edit'])->name('sales.quotations.edit');
        Route::put('/quotations/{quotation}', [QuotationController::class, 'update'])->name('sales.quotations.update');
        Route::delete('/quotations/{quotation}', [QuotationController::class, 'destroy'])->name('sales.quotations.destroy');

        Route::get('/invoices', [InvoiceController::class, 'index'])->name('sales.invoices.index');
        Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('sales.invoices.create');
        Route::post('/invoices', [InvoiceController::class, 'store'])->name('sales.invoices.store');
        Route::get('/invoices/{invoice}/document', [InvoiceController::class, 'document'])->name('sales.invoices.document');
    });

    Route::prefix('purchase')->group(function () {
        Route::get('/po', [PurchaseOrderController::class, 'index'])->name('purchase.po.index');
        Route::get('/po/create', [PurchaseOrderController::class, 'create'])->name('purchase.po.create');
        Route::post('/po', [PurchaseOrderController::class, 'store'])->name('purchase.po.store');

        Route::get('/po/arrivals', [PurchaseOrderController::class, 'reportIndex'])->name('purchase.po.arrivals');
        Route::get('/po/{purchaseOrder}/arrivals/report', [PurchaseOrderController::class, 'reportForm'])->name('purchase.po.arrivals.report-form');
        Route::post('/po/{purchaseOrder}/arrivals/report', [PurchaseOrderController::class, 'submitArrival'])->name('purchase.po.arrivals.report-store');

        Route::get('/po/arrivals/logs', [PurchaseOrderController::class, 'logs'])->name('purchase.po.arrivals.logs');
    });
});

require __DIR__.'/settings.php';
