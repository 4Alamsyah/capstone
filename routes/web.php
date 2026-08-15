<?php

use App\Http\Controllers\Accounting\AccountingAuditTrailController;
use App\Http\Controllers\Accounting\ArAgingController;
use App\Http\Controllers\Accounting\ChartOfAccountController;
use App\Http\Controllers\Accounting\FiscalPeriodController;
use App\Http\Controllers\Accounting\GlSettingController;
use App\Http\Controllers\Accounting\JournalEntryController;
use App\Http\Controllers\Accounting\JournalLineController;
use App\Http\Controllers\Accounting\JournalReportController;
use App\Http\Controllers\Accounting\TaxSettingController;
use App\Http\Controllers\BomController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PartController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseVoucherController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\ToolLoanController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WorkCenterController;
use App\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('/documentation', 'Documentation')->name('documentation');

    Route::middleware('permission:menu.dashboard')->group(function (): void {
        Route::get('/api/dashboard/analytics', [DashboardController::class, 'analytics'])->name('api.dashboard.analytics');
        Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    });

    Route::prefix('parts')->middleware('permission:menu.parts')->group(function () {
        Route::get('/', [PartController::class, 'index'])->name('parts.index');
        Route::get('/register', [PartController::class, 'create'])->name('parts.create');
        Route::post('/', [PartController::class, 'store'])->name('parts.store');
        Route::put('/{part}', [PartController::class, 'update'])->name('parts.update');
        Route::delete('/{part}', [PartController::class, 'destroy'])->name('parts.destroy');
        Route::get('/stock', [PartController::class, 'stock'])->name('parts.stock');
        Route::post('/stock/tool-loans', [ToolLoanController::class, 'store'])->name('parts.stock.tool-loans.store');
        Route::patch('/stock/tool-loans/{toolLoan}', [ToolLoanController::class, 'update'])->name('parts.stock.tool-loans.update');

        Route::get('/warehouses', [WarehouseController::class, 'index'])->name('parts.warehouses.index');
        Route::post('/warehouses', [WarehouseController::class, 'store'])->name('parts.warehouses.store');
        Route::post('/warehouses/quick-create', [WarehouseController::class, 'quickStore'])->name('parts.warehouses.quick-create');
        Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('parts.warehouses.update');
        Route::delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->name('parts.warehouses.destroy');
    });

    Route::middleware('permission:menu.suppliers')->group(function (): void {
        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
    });

    Route::middleware('permission:menu.work_orders')->group(function (): void {
        Route::get('/work-centers', [WorkCenterController::class, 'index'])->name('work-centers.index');
        Route::post('/work-centers', [WorkCenterController::class, 'store'])->name('work-centers.store');
        Route::put('/work-centers/{workCenter}', [WorkCenterController::class, 'update'])->name('work-centers.update');
        Route::delete('/work-centers/{workCenter}', [WorkCenterController::class, 'destroy'])->name('work-centers.destroy');
    });

    Route::middleware('permission:menu.parts')->group(function (): void {
        Route::get('/bom', [BomController::class, 'index'])->name('bom.index');
        Route::get('/bom/create', [BomController::class, 'create'])->name('bom.create');
        Route::post('/bom', [BomController::class, 'store'])->name('bom.store');
        Route::get('/bom/tree/{part}', [BomController::class, 'tree'])->name('bom.tree');
        Route::get('/bom/{bom}', [BomController::class, 'show'])->name('bom.show');
        Route::put('/bom/{bom}', [BomController::class, 'update'])->name('bom.update');
        Route::delete('/bom/{bom}', [BomController::class, 'destroy'])->name('bom.destroy');
    });

    Route::middleware('permission:menu.work_orders')->group(function (): void {
        Route::get('/work-orders', [WorkOrderController::class, 'index'])->name('work-orders.index');
        Route::get('/work-orders/create', [WorkOrderController::class, 'create'])->name('work-orders.create');
        Route::get('/work-orders/report', [WorkOrderController::class, 'reportIndex'])->name('work-orders.report.index');
        Route::get('/work-orders/logs', [WorkOrderController::class, 'logs'])->name('work-orders.logs');
        Route::get('/work-orders/lead-time', [WorkOrderController::class, 'leadTimeTimeline'])->name('work-orders.lead-time');
        Route::post('/work-orders', [WorkOrderController::class, 'store'])->name('work-orders.store');
        Route::get('/work-orders/{workOrder}/report', [WorkOrderController::class, 'report'])->name('work-orders.report.form');
        Route::post('/work-orders/{workOrder}/report', [WorkOrderController::class, 'submitReport'])->name('work-orders.report.store');
        Route::get('/work-orders/{workOrder}', [WorkOrderController::class, 'show'])->name('work-orders.show');
        Route::put('/work-orders/{workOrder}', [WorkOrderController::class, 'update'])->name('work-orders.update');
        Route::delete('/work-orders/{workOrder}', [WorkOrderController::class, 'destroy'])->name('work-orders.destroy');
    });

    Route::prefix('sales')->middleware('permission:menu.sales')->group(function () {
        Route::get('/customers', [CustomerController::class, 'index'])->name('sales.customers.index');
        Route::post('/customers', [CustomerController::class, 'store'])->name('sales.customers.store');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('sales.customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('sales.customers.destroy');

        Route::get('/customer-orders', [CustomerOrderController::class, 'index'])->name('sales.customer-orders.index');
        Route::get('/customer-orders/create', [CustomerOrderController::class, 'create'])->name('sales.customer-orders.create');
        Route::post('/customer-orders', [CustomerOrderController::class, 'store'])->name('sales.customer-orders.store');
        Route::get('/customer-orders/{customerOrder}/edit', [CustomerOrderController::class, 'edit'])->name('sales.customer-orders.edit');
        Route::put('/customer-orders/{customerOrder}', [CustomerOrderController::class, 'update'])->name('sales.customer-orders.update');
        Route::post('/customer-orders/{customerOrder}/confirm', [CustomerOrderController::class, 'confirm'])->name('sales.customer-orders.confirm');
        Route::patch('/customer-orders/{customerOrder}/status', [CustomerOrderController::class, 'updateStatus'])->name('sales.customer-orders.status');
        Route::post('/customer-orders/{customerOrder}/undo-report', [CustomerOrderController::class, 'undoReport'])->name('sales.customer-orders.undo-report');
        Route::get('/customer-orders/{customerOrder}/delivery-order', [CustomerOrderController::class, 'deliveryOrder'])->name('sales.customer-orders.delivery-order');

        Route::get('/quotations', [QuotationController::class, 'index'])->name('sales.quotations.index');
        Route::get('/quotations/create', [QuotationController::class, 'create'])->name('sales.quotations.create');
        Route::post('/quotations', [QuotationController::class, 'store'])->name('sales.quotations.store');
        Route::post('/quotations/{quotation}/generate-customer-order', [QuotationController::class, 'generateCustomerOrder'])->name('sales.quotations.generate-customer-order');
        Route::get('/quotations/{quotation}/edit', [QuotationController::class, 'edit'])->name('sales.quotations.edit');
        Route::put('/quotations/{quotation}', [QuotationController::class, 'update'])->name('sales.quotations.update');
        Route::delete('/quotations/{quotation}', [QuotationController::class, 'destroy'])->name('sales.quotations.destroy');

        Route::get('/invoices', [InvoiceController::class, 'index'])->name('sales.invoices.index');
        Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('sales.invoices.create');
        Route::post('/invoices', [InvoiceController::class, 'store'])->name('sales.invoices.store');
        Route::get('/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('sales.invoices.edit');
        Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->name('sales.invoices.update');
        Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('sales.invoices.send');
        Route::post('/invoices/{invoice}/payment-request', [InvoiceController::class, 'requestPayment'])->name('sales.invoices.payment-request');
        Route::post('/invoices/{invoice}/payment-approve', [InvoiceController::class, 'approvePayment'])
            ->middleware('permission:approve.invoice_payment')
            ->name('sales.invoices.payment-approve');
        Route::post('/invoices/{invoice}/payment-reject', [InvoiceController::class, 'rejectPayment'])
            ->middleware('permission:approve.invoice_payment')
            ->name('sales.invoices.payment-reject');
        Route::get('/invoices/{invoice}/record-payment', [InvoiceController::class, 'newPayment'])
            ->middleware('permission:approve.invoice_payment')
            ->name('sales.invoices.record-payment.form');
        Route::post('/invoices/{invoice}/record-payment', [InvoiceController::class, 'recordPayment'])
            ->middleware('permission:approve.invoice_payment')
            ->name('sales.invoices.record-payment.store');
        Route::get('/invoices/{invoice}/document', [InvoiceController::class, 'document'])->name('sales.invoices.document');
    });

    Route::prefix('purchase')->middleware('permission:menu.purchase')->group(function () {
        Route::get('/po', [PurchaseOrderController::class, 'index'])->name('purchase.po.index');
        Route::get('/po/create', [PurchaseOrderController::class, 'create'])->name('purchase.po.create');
        Route::post('/po', [PurchaseOrderController::class, 'store'])->name('purchase.po.store');
        Route::delete('/po/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->name('purchase.po.destroy');
        Route::post('/po/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])
            ->middleware('permission:approve.purchase_order')
            ->name('purchase.po.approve');
        Route::post('/po/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject'])
            ->middleware('permission:approve.purchase_order')
            ->name('purchase.po.reject');

        Route::get('/po/arrivals', [PurchaseOrderController::class, 'reportIndex'])->name('purchase.po.arrivals');
        Route::get('/po/{purchaseOrder}/arrivals/report', [PurchaseOrderController::class, 'reportForm'])->name('purchase.po.arrivals.report-form');
        Route::post('/po/{purchaseOrder}/arrivals/report', [PurchaseOrderController::class, 'submitArrival'])->name('purchase.po.arrivals.report-store');

        Route::get('/po/arrivals/logs', [PurchaseOrderController::class, 'logs'])->name('purchase.po.arrivals.logs');

        // Voucher routes — order matters: specific paths before wildcard
        Route::get('/voucher/stock-recommendations', [PurchaseVoucherController::class, 'stockRecommendations'])->name('purchase.voucher.stock-recommendations');
        Route::post('/voucher/stock-recommendations', [PurchaseVoucherController::class, 'generateFromStock'])->name('purchase.voucher.generate-from-stock');
        Route::get('/voucher', [PurchaseVoucherController::class, 'index'])->name('purchase.voucher.index');
        Route::get('/voucher/create', [PurchaseVoucherController::class, 'create'])->name('purchase.voucher.create');
        Route::post('/voucher', [PurchaseVoucherController::class, 'store'])->name('purchase.voucher.store');
        Route::get('/voucher/{purchaseVoucher}', [PurchaseVoucherController::class, 'show'])->name('purchase.voucher.show');
        Route::post('/voucher/{purchaseVoucher}/submit', [PurchaseVoucherController::class, 'submit'])->name('purchase.voucher.submit');
        Route::post('/voucher/{purchaseVoucher}/approve', [PurchaseVoucherController::class, 'approve'])
            ->middleware('permission:approve.purchase_voucher')
            ->name('purchase.voucher.approve');
        Route::post('/voucher/{purchaseVoucher}/reject', [PurchaseVoucherController::class, 'reject'])
            ->middleware('permission:approve.purchase_voucher')
            ->name('purchase.voucher.reject');
        Route::post('/voucher/{purchaseVoucher}/convert-to-po', [PurchaseVoucherController::class, 'convertToPo'])->name('purchase.voucher.convert-to-po');
        Route::delete('/voucher/{purchaseVoucher}', [PurchaseVoucherController::class, 'destroy'])->name('purchase.voucher.destroy');
    });

    Route::middleware('permission:menu.accounting')->group(function (): void {
        Route::inertia('/accounting/general', 'accounting/General')->name('accounting.general');

        Route::get('/accounting/chart-of-accounts', [ChartOfAccountController::class, 'index'])->name('accounting.chart-of-accounts');
        Route::post('/accounting/chart-of-accounts', [ChartOfAccountController::class, 'store'])->name('accounting.chart-of-accounts.store');
        Route::put('/accounting/chart-of-accounts/{chartOfAccount}', [ChartOfAccountController::class, 'update'])->name('accounting.chart-of-accounts.update');
        Route::delete('/accounting/chart-of-accounts/{chartOfAccount}', [ChartOfAccountController::class, 'destroy'])->name('accounting.chart-of-accounts.destroy');
        Route::get('/accounting/chart-of-accounts/export', [ChartOfAccountController::class, 'export'])->name('accounting.chart-of-accounts.export');
        Route::get('/accounting/chart-of-accounts/import-template', [ChartOfAccountController::class, 'importTemplate'])->name('accounting.chart-of-accounts.import-template');
        Route::post('/accounting/chart-of-accounts/import', [ChartOfAccountController::class, 'import'])->name('accounting.chart-of-accounts.import');

        Route::get('/accounting/fiscal-periods', [FiscalPeriodController::class, 'index'])->name('accounting.fiscal-periods');
        Route::post('/accounting/fiscal-periods', [FiscalPeriodController::class, 'store'])->name('accounting.fiscal-periods.store');
        Route::put('/accounting/fiscal-periods/{fiscalPeriod}', [FiscalPeriodController::class, 'update'])->name('accounting.fiscal-periods.update');
        Route::delete('/accounting/fiscal-periods/{fiscalPeriod}', [FiscalPeriodController::class, 'destroy'])->name('accounting.fiscal-periods.destroy');

        Route::get('/accounting/manual-journal', [JournalEntryController::class, 'manualJournal'])->name('accounting.manual-journal');
        Route::post('/accounting/manual-journal', [JournalEntryController::class, 'store'])->name('accounting.manual-journal.store');
        Route::put('/accounting/manual-journal/{journalEntry}', [JournalEntryController::class, 'update'])->name('accounting.manual-journal.update');
        Route::delete('/accounting/manual-journal/{journalEntry}', [JournalEntryController::class, 'destroy'])->name('accounting.manual-journal.destroy');

        Route::get('/accounting/journal-entries', [JournalEntryController::class, 'index'])->name('accounting.journal-entries');
        Route::get('/accounting/journal-lines', [JournalLineController::class, 'index'])->name('accounting.journal-lines');
        Route::post('/accounting/journal-lines', [JournalLineController::class, 'store'])->name('accounting.journal-lines.store');
        Route::put('/accounting/journal-lines/{journalLine}', [JournalLineController::class, 'update'])->name('accounting.journal-lines.update');
        Route::delete('/accounting/journal-lines/{journalLine}', [JournalLineController::class, 'destroy'])->name('accounting.journal-lines.destroy');

        Route::get('/accounting/journal-report', [JournalReportController::class, 'index'])->name('accounting.journal-report');
        Route::get('/accounting/journal-report/export', [JournalReportController::class, 'export'])->name('accounting.journal-report.export');
        Route::get('/accounting/journal-report/pdf', [JournalReportController::class, 'pdf'])->name('accounting.journal-report.pdf');

        Route::get('/accounting/audit-trails', [AccountingAuditTrailController::class, 'index'])->name('accounting.audit-trails');
        Route::delete('/accounting/audit-trails/{accountingAuditTrail}', [AccountingAuditTrailController::class, 'destroy'])->name('accounting.audit-trails.destroy');

        Route::get('/accounting/tax-setting', [TaxSettingController::class, 'edit'])->name('accounting.tax-setting.edit');
        Route::patch('/accounting/tax-setting', [TaxSettingController::class, 'update'])->name('accounting.tax-setting.update');

        Route::get('/accounting/gl-setting', [GlSettingController::class, 'edit'])->name('accounting.gl-setting.edit');
        Route::patch('/accounting/gl-setting', [GlSettingController::class, 'update'])->name('accounting.gl-setting.update');

        Route::get('/accounting/ar-aging', [ArAgingController::class, 'index'])->name('accounting.ar-aging');
    });
});

require __DIR__.'/settings.php';
