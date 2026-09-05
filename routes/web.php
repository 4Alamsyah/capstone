<?php

use App\Http\Controllers\Accounting\AccountingAuditTrailController;
use App\Http\Controllers\Accounting\ApAgingController;
use App\Http\Controllers\Accounting\ArAgingController;
use App\Http\Controllers\Accounting\BalanceSheetController;
use App\Http\Controllers\Accounting\ChartOfAccountController;
use App\Http\Controllers\Accounting\ExchangeRateController;
use App\Http\Controllers\Accounting\FiscalPeriodController;
use App\Http\Controllers\Accounting\FxRevaluationController;
use App\Http\Controllers\Accounting\GlSettingController;
use App\Http\Controllers\Accounting\JournalEntryController;
use App\Http\Controllers\Accounting\JournalLineController;
use App\Http\Controllers\Accounting\JournalReportController;
use App\Http\Controllers\Accounting\ProfitLossController;
use App\Http\Controllers\Accounting\TaxSettingController;
use App\Http\Controllers\ApInvoiceController;
use App\Http\Controllers\BomController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PartController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseVoucherController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\ToolLoanController;
use App\Http\Controllers\UomController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WorkCenterController;
use App\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('/documentation', 'Documentation')->name('documentation');

    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');

    Route::middleware('permission:module.dashboard')->group(function (): void {
        Route::get('/api/dashboard/analytics', [DashboardController::class, 'analytics'])->name('api.dashboard.analytics');
        Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    });

    Route::prefix('parts')->group(function () {
        Route::get('/', [PartController::class, 'index'])->middleware('permission:module.parts.master')->name('parts.index');
        Route::get('/export', [PartController::class, 'export'])->middleware('permission:module.parts.master')->name('parts.export');
        Route::get('/import-template', [PartController::class, 'importTemplate'])->middleware('permission:module.parts.master')->name('parts.import-template');
        Route::post('/import', [PartController::class, 'import'])->middleware('permission:module.parts.master,full')->name('parts.import');
        Route::get('/register', [PartController::class, 'create'])->middleware('permission:module.parts.master')->name('parts.create');
        Route::post('/', [PartController::class, 'store'])->middleware('permission:module.parts.master,full')->name('parts.store');
        Route::put('/{part}', [PartController::class, 'update'])->middleware('permission:module.parts.master,edit')->name('parts.update');
        Route::delete('/{part}', [PartController::class, 'destroy'])->middleware('permission:module.parts.master,full')->name('parts.destroy');

        Route::get('/stock-opname', [PartController::class, 'stockOpname'])->middleware('permission:module.parts.master')->name('parts.stock-opname');
        Route::post('/stock-opname', [PartController::class, 'updateStockOpname'])->middleware('permission:module.parts.master,edit')->name('parts.stock-opname.update');
        Route::post('/stock-opname/zero', [PartController::class, 'zeroStockOpname'])->middleware('permission:module.parts.master,full')->name('parts.stock-opname.zero');

        Route::get('/stock', [PartController::class, 'stock'])->middleware('permission:module.parts.stock')->name('parts.stock');
        Route::post('/stock/tool-loans', [ToolLoanController::class, 'store'])->middleware('permission:module.parts.stock,full')->name('parts.stock.tool-loans.store');
        Route::patch('/stock/tool-loans/{toolLoan}', [ToolLoanController::class, 'update'])->middleware('permission:module.parts.stock,edit')->name('parts.stock.tool-loans.update');

        Route::get('/warehouses', [WarehouseController::class, 'index'])->middleware('permission:module.parts.warehouse')->name('parts.warehouses.index');
        Route::post('/warehouses', [WarehouseController::class, 'store'])->middleware('permission:module.parts.warehouse,full')->name('parts.warehouses.store');
        Route::post('/warehouses/quick-create', [WarehouseController::class, 'quickStore'])->middleware('permission:module.parts.warehouse,full')->name('parts.warehouses.quick-create');
        Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->middleware('permission:module.parts.warehouse,edit')->name('parts.warehouses.update');
        Route::delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->middleware('permission:module.parts.warehouse,full')->name('parts.warehouses.destroy');

        Route::get('/uoms', [UomController::class, 'index'])->middleware('permission:module.parts.uom')->name('parts.uoms.index');
        Route::post('/uoms', [UomController::class, 'store'])->middleware('permission:module.parts.uom,full')->name('parts.uoms.store');
        Route::post('/uoms/quick-create', [UomController::class, 'quickStore'])->middleware('permission:module.parts.uom,full')->name('parts.uoms.quick-create');
        Route::put('/uoms/{uom}', [UomController::class, 'update'])->middleware('permission:module.parts.uom,edit')->name('parts.uoms.update');
        Route::delete('/uoms/{uom}', [UomController::class, 'destroy'])->middleware('permission:module.parts.uom,full')->name('parts.uoms.destroy');
    });

    Route::get('/suppliers', [SupplierController::class, 'index'])->middleware('permission:module.purchase.suppliers')->name('suppliers.index');
    Route::post('/suppliers', [SupplierController::class, 'store'])->middleware('permission:module.purchase.suppliers,full')->name('suppliers.store');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('permission:module.purchase.suppliers,edit')->name('suppliers.update');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->middleware('permission:module.purchase.suppliers,full')->name('suppliers.destroy');

    Route::get('/work-centers', [WorkCenterController::class, 'index'])->middleware('permission:module.manufacturing.work_centers')->name('work-centers.index');
    Route::post('/work-centers', [WorkCenterController::class, 'store'])->middleware('permission:module.manufacturing.work_centers,full')->name('work-centers.store');
    Route::put('/work-centers/{workCenter}', [WorkCenterController::class, 'update'])->middleware('permission:module.manufacturing.work_centers,edit')->name('work-centers.update');
    Route::delete('/work-centers/{workCenter}', [WorkCenterController::class, 'destroy'])->middleware('permission:module.manufacturing.work_centers,full')->name('work-centers.destroy');

    Route::get('/bom', [BomController::class, 'index'])->middleware('permission:module.parts.master')->name('bom.index');
    Route::get('/bom/create', [BomController::class, 'create'])->middleware('permission:module.parts.master')->name('bom.create');
    Route::post('/bom', [BomController::class, 'store'])->middleware('permission:module.parts.master,full')->name('bom.store');
    Route::get('/bom/tree/{part}', [BomController::class, 'tree'])->middleware('permission:module.parts.master')->name('bom.tree');
    Route::get('/bom/{bom}', [BomController::class, 'show'])->middleware('permission:module.parts.master')->name('bom.show');
    Route::put('/bom/{bom}', [BomController::class, 'update'])->middleware('permission:module.parts.master,edit')->name('bom.update');
    Route::delete('/bom/{bom}', [BomController::class, 'destroy'])->middleware('permission:module.parts.master,full')->name('bom.destroy');

    Route::get('/work-orders', [WorkOrderController::class, 'index'])->middleware('permission:module.manufacturing.work_orders')->name('work-orders.index');
    Route::get('/work-orders/create', [WorkOrderController::class, 'create'])->middleware('permission:module.manufacturing.work_orders')->name('work-orders.create');
    Route::get('/work-orders/report', [WorkOrderController::class, 'reportIndex'])->middleware('permission:module.manufacturing.work_orders')->name('work-orders.report.index');
    Route::get('/work-orders/logs', [WorkOrderController::class, 'logs'])->middleware('permission:module.manufacturing.work_orders')->name('work-orders.logs');
    Route::get('/work-orders/lead-time', [WorkOrderController::class, 'leadTimeTimeline'])->middleware('permission:module.manufacturing.work_orders')->name('work-orders.lead-time');
    Route::post('/work-orders', [WorkOrderController::class, 'store'])->middleware('permission:module.manufacturing.work_orders,full')->name('work-orders.store');
    Route::get('/work-orders/{workOrder}/report', [WorkOrderController::class, 'report'])->middleware('permission:module.manufacturing.work_orders')->name('work-orders.report.form');
    Route::post('/work-orders/{workOrder}/report', [WorkOrderController::class, 'submitReport'])->middleware('permission:module.manufacturing.work_orders,edit')->name('work-orders.report.store');
    Route::get('/work-orders/{workOrder}', [WorkOrderController::class, 'show'])->middleware('permission:module.manufacturing.work_orders')->name('work-orders.show');
    Route::put('/work-orders/{workOrder}', [WorkOrderController::class, 'update'])->middleware('permission:module.manufacturing.work_orders,edit')->name('work-orders.update');
    Route::delete('/work-orders/{workOrder}', [WorkOrderController::class, 'destroy'])->middleware('permission:module.manufacturing.work_orders,full')->name('work-orders.destroy');

    Route::prefix('sales')->group(function () {
        Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:module.sales.customers')->name('sales.customers.index');
        Route::post('/customers', [CustomerController::class, 'store'])->middleware('permission:module.sales.customers,full')->name('sales.customers.store');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:module.sales.customers,edit')->name('sales.customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:module.sales.customers,full')->name('sales.customers.destroy');

        Route::get('/customer-orders', [CustomerOrderController::class, 'index'])->middleware('permission:module.sales.customer_orders')->name('sales.customer-orders.index');
        Route::get('/customer-orders/create', [CustomerOrderController::class, 'create'])->middleware('permission:module.sales.customer_orders')->name('sales.customer-orders.create');
        Route::post('/customer-orders', [CustomerOrderController::class, 'store'])->middleware('permission:module.sales.customer_orders,full')->name('sales.customer-orders.store');
        Route::get('/customer-orders/{customerOrder}/edit', [CustomerOrderController::class, 'edit'])->middleware('permission:module.sales.customer_orders')->name('sales.customer-orders.edit');
        Route::put('/customer-orders/{customerOrder}', [CustomerOrderController::class, 'update'])->middleware('permission:module.sales.customer_orders,edit')->name('sales.customer-orders.update');
        Route::delete('/customer-orders/{customerOrder}', [CustomerOrderController::class, 'destroy'])->middleware('permission:module.sales.customer_orders,full')->name('sales.customer-orders.destroy');
        Route::post('/customer-orders/{customerOrder}/confirm', [CustomerOrderController::class, 'confirm'])->middleware('permission:module.sales.customer_orders,edit')->name('sales.customer-orders.confirm');
        Route::patch('/customer-orders/{customerOrder}/status', [CustomerOrderController::class, 'updateStatus'])->middleware('permission:module.sales.customer_orders,edit')->name('sales.customer-orders.status');
        Route::post('/customer-orders/{customerOrder}/undo-report', [CustomerOrderController::class, 'undoReport'])->middleware('permission:module.sales.customer_orders,edit')->name('sales.customer-orders.undo-report');
        Route::get('/customer-orders/{customerOrder}/delivery-order', [CustomerOrderController::class, 'deliveryOrder'])->middleware('permission:module.sales.customer_orders')->name('sales.customer-orders.delivery-order');

        Route::get('/quotations', [QuotationController::class, 'index'])->middleware('permission:module.sales.quotations')->name('sales.quotations.index');
        Route::get('/quotations/create', [QuotationController::class, 'create'])->middleware('permission:module.sales.quotations')->name('sales.quotations.create');
        Route::post('/quotations', [QuotationController::class, 'store'])->middleware('permission:module.sales.quotations,full')->name('sales.quotations.store');
        Route::post('/quotations/{quotation}/generate-customer-order', [QuotationController::class, 'generateCustomerOrder'])->middleware('permission:module.sales.quotations,full')->name('sales.quotations.generate-customer-order');
        Route::get('/quotations/{quotation}/edit', [QuotationController::class, 'edit'])->middleware('permission:module.sales.quotations')->name('sales.quotations.edit');
        Route::put('/quotations/{quotation}', [QuotationController::class, 'update'])->middleware('permission:module.sales.quotations,edit')->name('sales.quotations.update');
        Route::delete('/quotations/{quotation}', [QuotationController::class, 'destroy'])->middleware('permission:module.sales.quotations,full')->name('sales.quotations.destroy');
        Route::get('/quotations/{quotation}/document', [QuotationController::class, 'document'])->middleware('permission:module.sales.quotations')->name('sales.quotations.document');

        Route::get('/invoices', [InvoiceController::class, 'index'])->middleware('permission:module.sales.invoices')->name('sales.invoices.index');
        Route::get('/invoices/create', [InvoiceController::class, 'create'])->middleware('permission:module.sales.invoices')->name('sales.invoices.create');
        Route::post('/invoices', [InvoiceController::class, 'store'])->middleware('permission:module.sales.invoices,full')->name('sales.invoices.store');
        Route::get('/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->middleware('permission:module.sales.invoices')->name('sales.invoices.edit');
        Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->middleware('permission:module.sales.invoices,edit')->name('sales.invoices.update');
        Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send'])->middleware('permission:module.sales.invoices,edit')->name('sales.invoices.send');
        Route::post('/invoices/{invoice}/payment-request', [InvoiceController::class, 'requestPayment'])->middleware('permission:module.sales.invoices,edit')->name('sales.invoices.payment-request');
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
        Route::get('/invoices/{invoice}/document', [InvoiceController::class, 'document'])->middleware('permission:module.sales.invoices')->name('sales.invoices.document');
    });

    Route::prefix('purchase')->group(function () {
        Route::get('/po', [PurchaseOrderController::class, 'index'])->middleware('permission:module.purchase.orders')->name('purchase.po.index');
        Route::get('/po/create', [PurchaseOrderController::class, 'create'])->middleware('permission:module.purchase.orders')->name('purchase.po.create');
        Route::post('/po', [PurchaseOrderController::class, 'store'])->middleware('permission:module.purchase.orders,full')->name('purchase.po.store');
        Route::get('/po/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->middleware('permission:module.purchase.orders,edit')->name('purchase.po.edit');
        Route::put('/po/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->middleware('permission:module.purchase.orders,edit')->name('purchase.po.update');
        Route::get('/po/{purchaseOrder}/print', [PurchaseOrderController::class, 'document'])->middleware('permission:module.purchase.orders')->name('purchase.po.print');
        Route::delete('/po/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->middleware('permission:module.purchase.orders,full')->name('purchase.po.destroy');
        Route::post('/po/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])
            ->middleware('permission:approve.purchase_order')
            ->name('purchase.po.approve');
        Route::post('/po/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject'])
            ->middleware('permission:approve.purchase_order')
            ->name('purchase.po.reject');

        Route::get('/po/arrivals', [PurchaseOrderController::class, 'reportIndex'])->middleware('permission:module.purchase.orders')->name('purchase.po.arrivals');
        Route::get('/po/{purchaseOrder}/arrivals/report', [PurchaseOrderController::class, 'reportForm'])->middleware('permission:module.purchase.orders')->name('purchase.po.arrivals.report-form');
        Route::post('/po/{purchaseOrder}/arrivals/report', [PurchaseOrderController::class, 'submitArrival'])->middleware('permission:module.purchase.orders,edit')->name('purchase.po.arrivals.report-store');

        Route::get('/po/arrivals/logs', [PurchaseOrderController::class, 'logs'])->middleware('permission:module.purchase.orders')->name('purchase.po.arrivals.logs');

        // Voucher routes — order matters: specific paths before wildcard
        Route::get('/voucher/stock-recommendations', [PurchaseVoucherController::class, 'stockRecommendations'])->middleware('permission:module.purchase.vouchers')->name('purchase.voucher.stock-recommendations');
        Route::post('/voucher/stock-recommendations', [PurchaseVoucherController::class, 'generateFromStock'])->middleware('permission:module.purchase.vouchers,full')->name('purchase.voucher.generate-from-stock');
        Route::get('/voucher', [PurchaseVoucherController::class, 'index'])->middleware('permission:module.purchase.vouchers')->name('purchase.voucher.index');
        Route::get('/voucher/create', [PurchaseVoucherController::class, 'create'])->middleware('permission:module.purchase.vouchers')->name('purchase.voucher.create');
        Route::post('/voucher', [PurchaseVoucherController::class, 'store'])->middleware('permission:module.purchase.vouchers,full')->name('purchase.voucher.store');
        Route::get('/voucher/{purchaseVoucher}', [PurchaseVoucherController::class, 'show'])->middleware('permission:module.purchase.vouchers')->name('purchase.voucher.show');
        Route::post('/voucher/{purchaseVoucher}/submit', [PurchaseVoucherController::class, 'submit'])->middleware('permission:module.purchase.vouchers,edit')->name('purchase.voucher.submit');
        Route::post('/voucher/{purchaseVoucher}/approve', [PurchaseVoucherController::class, 'approve'])
            ->middleware('permission:approve.purchase_voucher')
            ->name('purchase.voucher.approve');
        Route::post('/voucher/{purchaseVoucher}/reject', [PurchaseVoucherController::class, 'reject'])
            ->middleware('permission:approve.purchase_voucher')
            ->name('purchase.voucher.reject');
        Route::post('/voucher/{purchaseVoucher}/convert-to-po', [PurchaseVoucherController::class, 'convertToPo'])->middleware('permission:module.purchase.vouchers,full')->name('purchase.voucher.convert-to-po');
        Route::delete('/voucher/{purchaseVoucher}', [PurchaseVoucherController::class, 'destroy'])->middleware('permission:module.purchase.vouchers,full')->name('purchase.voucher.destroy');

        // AP (Accounts Payable) routes — order matters: specific paths before wildcard
        Route::get('/ap/invoices', [ApInvoiceController::class, 'index'])->middleware('permission:module.purchase.ap_invoices')->name('purchase.ap.invoices.index');
        Route::get('/ap/invoices/create', [ApInvoiceController::class, 'create'])->middleware('permission:module.purchase.ap_invoices')->name('purchase.ap.invoices.create');
        Route::post('/ap/invoices', [ApInvoiceController::class, 'store'])->middleware('permission:module.purchase.ap_invoices,full')->name('purchase.ap.invoices.store');
        Route::get('/ap/invoices/{apInvoice}/edit', [ApInvoiceController::class, 'edit'])->middleware('permission:module.purchase.ap_invoices')->name('purchase.ap.invoices.edit');
        Route::put('/ap/invoices/{apInvoice}', [ApInvoiceController::class, 'update'])->middleware('permission:module.purchase.ap_invoices,edit')->name('purchase.ap.invoices.update');
        Route::delete('/ap/invoices/{apInvoice}', [ApInvoiceController::class, 'destroy'])->middleware('permission:module.purchase.ap_invoices,full')->name('purchase.ap.invoices.destroy');
        Route::post('/ap/invoices/{apInvoice}/submit', [ApInvoiceController::class, 'submit'])->middleware('permission:module.purchase.ap_invoices,edit')->name('purchase.ap.invoices.submit');
        Route::post('/ap/invoices/{apInvoice}/approve', [ApInvoiceController::class, 'approve'])
            ->middleware('permission:approve.ap_invoice')
            ->name('purchase.ap.invoices.approve');
        Route::post('/ap/invoices/{apInvoice}/reject', [ApInvoiceController::class, 'reject'])
            ->middleware('permission:approve.ap_invoice')
            ->name('purchase.ap.invoices.reject');
        Route::get('/ap/invoices/{apInvoice}/record-payment', [ApInvoiceController::class, 'newPayment'])->middleware('permission:module.purchase.ap_invoices')->name('purchase.ap.invoices.record-payment.form');
        Route::post('/ap/invoices/{apInvoice}/record-payment', [ApInvoiceController::class, 'recordPayment'])->middleware('permission:module.purchase.ap_invoices,edit')->name('purchase.ap.invoices.record-payment.store');
    });

    Route::inertia('/accounting/general', 'accounting/General')->middleware('permission:module.accounting.chart_of_accounts')->name('accounting.general');

    Route::get('/accounting/chart-of-accounts', [ChartOfAccountController::class, 'index'])->middleware('permission:module.accounting.chart_of_accounts')->name('accounting.chart-of-accounts');
    Route::post('/accounting/chart-of-accounts', [ChartOfAccountController::class, 'store'])->middleware('permission:module.accounting.chart_of_accounts,full')->name('accounting.chart-of-accounts.store');
    Route::put('/accounting/chart-of-accounts/{chartOfAccount}', [ChartOfAccountController::class, 'update'])->middleware('permission:module.accounting.chart_of_accounts,edit')->name('accounting.chart-of-accounts.update');
    Route::delete('/accounting/chart-of-accounts/{chartOfAccount}', [ChartOfAccountController::class, 'destroy'])->middleware('permission:module.accounting.chart_of_accounts,full')->name('accounting.chart-of-accounts.destroy');
    Route::get('/accounting/chart-of-accounts/export', [ChartOfAccountController::class, 'export'])->middleware('permission:module.accounting.chart_of_accounts')->name('accounting.chart-of-accounts.export');
    Route::get('/accounting/chart-of-accounts/import-template', [ChartOfAccountController::class, 'importTemplate'])->middleware('permission:module.accounting.chart_of_accounts')->name('accounting.chart-of-accounts.import-template');
    Route::post('/accounting/chart-of-accounts/import', [ChartOfAccountController::class, 'import'])->middleware('permission:module.accounting.chart_of_accounts,full')->name('accounting.chart-of-accounts.import');

    Route::get('/accounting/fiscal-periods', [FiscalPeriodController::class, 'index'])->middleware('permission:module.accounting.fiscal_periods')->name('accounting.fiscal-periods');
    Route::post('/accounting/fiscal-periods', [FiscalPeriodController::class, 'store'])->middleware('permission:module.accounting.fiscal_periods,full')->name('accounting.fiscal-periods.store');
    Route::put('/accounting/fiscal-periods/{fiscalPeriod}', [FiscalPeriodController::class, 'update'])->middleware('permission:module.accounting.fiscal_periods,edit')->name('accounting.fiscal-periods.update');
    Route::delete('/accounting/fiscal-periods/{fiscalPeriod}', [FiscalPeriodController::class, 'destroy'])->middleware('permission:module.accounting.fiscal_periods,full')->name('accounting.fiscal-periods.destroy');

    Route::get('/accounting/manual-journal', [JournalEntryController::class, 'manualJournal'])->middleware('permission:module.accounting.journal')->name('accounting.manual-journal');
    Route::post('/accounting/manual-journal', [JournalEntryController::class, 'store'])->middleware('permission:module.accounting.journal,full')->name('accounting.manual-journal.store');
    Route::put('/accounting/manual-journal/{journalEntry}', [JournalEntryController::class, 'update'])->middleware('permission:module.accounting.journal,edit')->name('accounting.manual-journal.update');
    Route::delete('/accounting/manual-journal/{journalEntry}', [JournalEntryController::class, 'destroy'])->middleware('permission:module.accounting.journal,full')->name('accounting.manual-journal.destroy');

    Route::get('/accounting/journal-entries', [JournalEntryController::class, 'index'])->middleware('permission:module.accounting.journal')->name('accounting.journal-entries');
    Route::get('/accounting/journal-lines', [JournalLineController::class, 'index'])->middleware('permission:module.accounting.journal')->name('accounting.journal-lines');
    Route::post('/accounting/journal-lines', [JournalLineController::class, 'store'])->middleware('permission:module.accounting.journal,full')->name('accounting.journal-lines.store');
    Route::put('/accounting/journal-lines/{journalLine}', [JournalLineController::class, 'update'])->middleware('permission:module.accounting.journal,edit')->name('accounting.journal-lines.update');
    Route::delete('/accounting/journal-lines/{journalLine}', [JournalLineController::class, 'destroy'])->middleware('permission:module.accounting.journal,full')->name('accounting.journal-lines.destroy');

    Route::get('/accounting/journal-report', [JournalReportController::class, 'index'])->middleware('permission:module.accounting.journal')->name('accounting.journal-report');
    Route::get('/accounting/journal-report/export', [JournalReportController::class, 'export'])->middleware('permission:module.accounting.journal')->name('accounting.journal-report.export');
    Route::get('/accounting/journal-report/pdf', [JournalReportController::class, 'pdf'])->middleware('permission:module.accounting.journal')->name('accounting.journal-report.pdf');

    Route::get('/accounting/profit-loss', [ProfitLossController::class, 'index'])->middleware('permission:module.accounting.journal')->name('accounting.profit-loss');

    Route::get('/accounting/audit-trails', [AccountingAuditTrailController::class, 'index'])->middleware('permission:module.accounting.reports')->name('accounting.audit-trails');
    Route::delete('/accounting/audit-trails/{accountingAuditTrail}', [AccountingAuditTrailController::class, 'destroy'])->middleware('permission:module.accounting.reports,full')->name('accounting.audit-trails.destroy');

    Route::get('/accounting/tax-setting', [TaxSettingController::class, 'edit'])->middleware('permission:module.accounting.tax_gl_settings')->name('accounting.tax-setting.edit');
    Route::patch('/accounting/tax-setting', [TaxSettingController::class, 'update'])->middleware('permission:module.accounting.tax_gl_settings,edit')->name('accounting.tax-setting.update');

    Route::get('/accounting/gl-setting', [GlSettingController::class, 'edit'])->middleware('permission:module.accounting.tax_gl_settings')->name('accounting.gl-setting.edit');
    Route::patch('/accounting/gl-setting', [GlSettingController::class, 'update'])->middleware('permission:module.accounting.tax_gl_settings,edit')->name('accounting.gl-setting.update');

    Route::get('/accounting/exchange-rates', [ExchangeRateController::class, 'index'])->middleware('permission:module.accounting.exchange_rates')->name('accounting.exchange-rates.index');
    Route::post('/accounting/exchange-rates', [ExchangeRateController::class, 'store'])->middleware('permission:module.accounting.exchange_rates,full')->name('accounting.exchange-rates.store');
    Route::post('/accounting/exchange-rates/fetch-latest', [ExchangeRateController::class, 'fetchLatest'])->middleware('permission:module.accounting.exchange_rates,full')->name('accounting.exchange-rates.fetch-latest');
    Route::delete('/accounting/exchange-rates/{exchangeRate}', [ExchangeRateController::class, 'destroy'])->middleware('permission:module.accounting.exchange_rates,full')->name('accounting.exchange-rates.destroy');

    Route::get('/accounting/fx-revaluation', [FxRevaluationController::class, 'index'])->middleware('permission:module.accounting.exchange_rates')->name('accounting.fx-revaluation.index');
    Route::post('/accounting/fx-revaluation', [FxRevaluationController::class, 'store'])->middleware('permission:module.accounting.exchange_rates,full')->name('accounting.fx-revaluation.store');

    Route::get('/accounting/ar-aging', [ArAgingController::class, 'index'])->middleware('permission:module.accounting.reports')->name('accounting.ar-aging');

    Route::get('/accounting/ap-aging', [ApAgingController::class, 'index'])->middleware('permission:module.accounting.reports')->name('accounting.ap-aging');

    Route::get('/accounting/balance-sheet', [BalanceSheetController::class, 'index'])->middleware('permission:module.accounting.reports')->name('accounting.balance-sheet');
});

require __DIR__.'/settings.php';
