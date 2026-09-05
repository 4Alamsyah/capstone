<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApInvoice\RecordApInvoicePaymentRequest;
use App\Http\Requests\ApInvoice\StoreApInvoiceRequest;
use App\Http\Requests\ApInvoice\UpdateApInvoiceRequest;
use App\Models\ApInvoice;
use App\Models\ApInvoiceItem;
use App\Models\AppSetting;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Part;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Support\AccountingAuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ApInvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $status = $request->integer('status');

        $apInvoices = ApInvoice::query()
            ->with(['supplier:id,name', 'purchaseOrder:id,po_number', 'items.part:id,part_number,name'])
            ->withSum('payments as amount_paid', 'amount')
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($inner) use ($search): void {
                    $inner->where('ap_invoice_number', 'like', "%{$search}%")
                        ->orWhere('supplier_invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function ($supplierQuery) use ($search): void {
                            $supplierQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('purchaseOrder', function ($poQuery) use ($search): void {
                            $poQuery->where('po_number', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status > 0, function ($builder) use ($status): void {
                $builder->where('status', $status);
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('purchase/ap/Index', [
            'apInvoices' => collect($apInvoices->items())->map(function (ApInvoice $apInvoice): array {
                $amountPaid = (float) ($apInvoice->amount_paid ?? 0);

                return [
                    'id' => $apInvoice->id,
                    'ap_invoice_number' => $apInvoice->ap_invoice_number,
                    'supplier_invoice_number' => $apInvoice->supplier_invoice_number,
                    'invoice_date' => $apInvoice->invoice_date?->format('Y-m-d'),
                    'due_date' => $apInvoice->due_date?->format('Y-m-d'),
                    'status' => $apInvoice->status,
                    'approval_notes' => $apInvoice->approval_notes,
                    'paid_at' => $apInvoice->paid_at?->format('Y-m-d H:i'),
                    'currency_code' => $apInvoice->currency_code,
                    'subtotal' => (string) $apInvoice->subtotal,
                    'tax_amount' => (string) $apInvoice->tax_amount,
                    'total_amount' => (string) $apInvoice->total_amount,
                    'amount_paid' => (string) $amountPaid,
                    'balance_due' => (string) ((float) $apInvoice->total_amount - $amountPaid),
                    'supplier' => [
                        'id' => $apInvoice->supplier?->id,
                        'name' => $apInvoice->supplier?->name,
                    ],
                    'purchase_order' => [
                        'id' => $apInvoice->purchaseOrder?->id,
                        'po_number' => $apInvoice->purchaseOrder?->po_number,
                    ],
                    'items' => $apInvoice->items->map(fn (ApInvoiceItem $item): array => [
                        'id' => $item->id,
                        'part_number' => $item->part?->part_number,
                        'part_name' => $item->part?->name,
                        'description' => $item->description,
                        'quantity' => (string) $item->quantity,
                        'unit_price' => (string) $item->unit_price,
                        'line_total' => (string) $item->line_total,
                    ])->values(),
                ];
            })->values(),
            'filters' => [
                'search' => $search,
                'status' => $status > 0 ? (string) $status : '',
            ],
            'pagination' => [
                'current_page' => $apInvoices->currentPage(),
                'last_page' => $apInvoices->lastPage(),
                'per_page' => $apInvoices->perPage(),
                'total' => $apInvoices->total(),
                'from' => $apInvoices->firstItem(),
                'to' => $apInvoices->lastItem(),
                'prev_page_url' => $apInvoices->previousPageUrl(),
                'next_page_url' => $apInvoices->nextPageUrl(),
            ],
            'statusLabels' => ApInvoice::statusLabels(),
            'canManageApprovals' => $request->user() instanceof User ? $request->user()->canApproveApInvoice() : false,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('purchase/ap/Create', [
            'nextApInvoiceNumber' => ApInvoice::generateNumber(),
            'defaultCurrency' => (string) AppSetting::get('default_currency_code', 'IDR'),
            'taxRate' => $this->taxRate(),
            'currencies' => $this->currencyOptions(),
            'suppliers' => Supplier::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Supplier $supplier): array => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                ]),
            'purchaseOrders' => $this->purchaseOrderOptions(),
            'parts' => $this->partOptions(),
        ]);
    }

    public function edit(ApInvoice $apInvoice): Response|RedirectResponse
    {
        if (! $this->isEditable($apInvoice)) {
            return to_route('purchase.ap.invoices.index')
                ->with('error', 'AP Invoice hanya bisa diedit selama masih Draft.');
        }

        $apInvoice->loadMissing(['items.part']);

        return Inertia::render('purchase/ap/Edit', [
            'apInvoice' => [
                'id' => $apInvoice->id,
                'ap_invoice_number' => $apInvoice->ap_invoice_number,
                'supplier_invoice_number' => $apInvoice->supplier_invoice_number,
                'supplier_id' => $apInvoice->supplier_id,
                'purchase_order_id' => $apInvoice->purchase_order_id,
                'invoice_date' => $apInvoice->invoice_date?->format('Y-m-d'),
                'due_date' => $apInvoice->due_date?->format('Y-m-d'),
                'currency_code' => $apInvoice->currency_code,
                'notes' => $apInvoice->notes,
                'lines' => $apInvoice->items->map(fn (ApInvoiceItem $item): array => [
                    'purchase_order_item_id' => $item->purchase_order_item_id,
                    'part_id' => $item->part_id,
                    'description' => $item->description,
                    'quantity' => (string) $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                ])->values(),
            ],
            'defaultCurrency' => (string) AppSetting::get('default_currency_code', 'IDR'),
            'taxRate' => $this->taxRate(),
            'currencies' => $this->currencyOptions(),
            'suppliers' => Supplier::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Supplier $supplier): array => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                ]),
            'purchaseOrders' => $this->purchaseOrderOptions(),
            'parts' => $this->partOptions(),
        ]);
    }

    public function store(StoreApInvoiceRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if (! empty($validated['purchase_order_id'])) {
            $purchaseOrder = PurchaseOrder::query()->find($validated['purchase_order_id']);

            if ($purchaseOrder && (int) $purchaseOrder->supplier_id !== (int) $validated['supplier_id']) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => 'Purchase order tidak sesuai dengan supplier yang dipilih.',
                ]);
            }
        }

        DB::transaction(function () use ($validated): void {
            $apInvoice = ApInvoice::query()->create([
                'ap_invoice_number' => ApInvoice::generateNumber(),
                'supplier_invoice_number' => $validated['supplier_invoice_number'] ?? null,
                'supplier_id' => $validated['supplier_id'],
                'purchase_order_id' => $validated['purchase_order_id'] ?? null,
                'status' => ApInvoice::STATUS_DRAFT,
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'] ?? null,
                'currency_code' => strtoupper((string) ($validated['currency_code'] ?? AppSetting::get('default_currency_code', 'IDR'))),
                'subtotal' => 0,
                'tax_amount' => 0,
                'total_amount' => 0,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->syncLines($apInvoice, $validated['lines']);
        });

        return to_route('purchase.ap.invoices.index')->with('success', 'AP Invoice berhasil disimpan sebagai Draft.');
    }

    public function update(UpdateApInvoiceRequest $request, ApInvoice $apInvoice): RedirectResponse
    {
        if (! $this->isEditable($apInvoice)) {
            return to_route('purchase.ap.invoices.index')
                ->with('error', 'AP Invoice hanya bisa diedit selama masih Draft.');
        }

        $validated = $request->validated();

        if (! empty($validated['purchase_order_id'])) {
            $purchaseOrder = PurchaseOrder::query()->find($validated['purchase_order_id']);

            if ($purchaseOrder && (int) $purchaseOrder->supplier_id !== (int) $validated['supplier_id']) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => 'Purchase order tidak sesuai dengan supplier yang dipilih.',
                ]);
            }
        }

        DB::transaction(function () use ($validated, $apInvoice): void {
            $apInvoice->update([
                'supplier_invoice_number' => $validated['supplier_invoice_number'] ?? null,
                'supplier_id' => $validated['supplier_id'],
                'purchase_order_id' => $validated['purchase_order_id'] ?? null,
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'] ?? null,
                'currency_code' => strtoupper((string) ($validated['currency_code'] ?? AppSetting::get('default_currency_code', 'IDR'))),
                'notes' => $validated['notes'] ?? null,
            ]);

            $apInvoice->items()->delete();
            $this->syncLines($apInvoice, $validated['lines']);
        });

        return to_route('purchase.ap.invoices.index')->with('success', 'AP Invoice berhasil diperbarui.');
    }

    public function destroy(ApInvoice $apInvoice): RedirectResponse
    {
        if (! in_array($apInvoice->status, [ApInvoice::STATUS_DRAFT, ApInvoice::STATUS_REJECTED, ApInvoice::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages([
                'ap_invoice' => 'AP Invoice hanya bisa dihapus saat masih Draft, Rejected, atau Cancelled.',
            ]);
        }

        $apInvoice->delete();

        return back()->with('success', 'AP Invoice berhasil dihapus.');
    }

    /**
     * Submit the draft AP invoice for management approval.
     */
    public function submit(ApInvoice $apInvoice): RedirectResponse
    {
        if ($apInvoice->status !== ApInvoice::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'ap_invoice' => 'AP Invoice hanya bisa diajukan dari status Draft.',
            ]);
        }

        $apInvoice->update([
            'status' => ApInvoice::STATUS_PENDING_APPROVAL,
            'submitted_by' => auth()->id(),
            'submitted_at' => now(),
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
        ]);

        return back()->with('success', 'AP Invoice berhasil diajukan untuk approval.');
    }

    /**
     * Approve the AP invoice: posts the AP liability entry to the General
     * Ledger (DR Purchase Expense (+ Input Tax), CR Accounts Payable).
     */
    public function approve(Request $request, ApInvoice $apInvoice): RedirectResponse
    {
        $this->ensureManagementApprover($request);

        if ($apInvoice->status !== ApInvoice::STATUS_PENDING_APPROVAL) {
            throw ValidationException::withMessages([
                'ap_invoice' => 'AP Invoice tidak dalam status pending approval.',
            ]);
        }

        $validated = $request->validate([
            'approval_notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($request, $apInvoice, $validated): void {
            $this->postApInvoiceToGl($apInvoice);

            $apInvoice->update([
                'status' => ApInvoice::STATUS_APPROVED,
                'approved_by' => $request->user()?->id,
                'approved_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'approval_notes' => $validated['approval_notes'] ?? null,
            ]);

            AccountingAuditLogger::record('AP Invoice approved / AP posted', $apInvoice, $apInvoice->ap_invoice_number);
        });

        return back()->with('success', 'AP Invoice berhasil di-approve dan AP sudah tercatat di jurnal.');
    }

    public function reject(Request $request, ApInvoice $apInvoice): RedirectResponse
    {
        $this->ensureManagementApprover($request);

        if ($apInvoice->status !== ApInvoice::STATUS_PENDING_APPROVAL) {
            throw ValidationException::withMessages([
                'ap_invoice' => 'AP Invoice tidak dalam status pending approval.',
            ]);
        }

        $validated = $request->validate([
            'approval_notes' => ['required', 'string'],
        ]);

        $apInvoice->update([
            'status' => ApInvoice::STATUS_REJECTED,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => $request->user()?->id,
            'rejected_at' => now(),
            'approval_notes' => $validated['approval_notes'],
        ]);

        return back()->with('success', 'AP Invoice berhasil di-reject.');
    }

    /**
     * Show the Record Payment form for an approved AP invoice.
     */
    public function newPayment(ApInvoice $apInvoice): Response|RedirectResponse
    {
        if (! in_array($apInvoice->status, [ApInvoice::STATUS_APPROVED, ApInvoice::STATUS_PARTIALLY_PAID], true)) {
            return to_route('purchase.ap.invoices.index')
                ->with('error', 'AP Invoice harus di-approve dulu sebelum pembayaran bisa dicatat.');
        }

        $amountPaid = (float) $apInvoice->payments()->sum('amount');

        return Inertia::render('purchase/ap/RecordPayment', [
            'apInvoice' => [
                'id' => $apInvoice->id,
                'ap_invoice_number' => $apInvoice->ap_invoice_number,
                'currency_code' => $apInvoice->currency_code,
                'total_amount' => (string) $apInvoice->total_amount,
                'amount_paid' => (string) $amountPaid,
                'balance_due' => (string) ((float) $apInvoice->total_amount - $amountPaid),
            ],
            'paymentMethods' => Payment::methodLabels(),
        ]);
    }

    /**
     * Record a (possibly partial) payment to the supplier and post the
     * settlement entry to the General Ledger (DR Accounts Payable, CR Cash/Bank).
     */
    public function recordPayment(RecordApInvoicePaymentRequest $request, ApInvoice $apInvoice): RedirectResponse
    {
        if (! in_array($apInvoice->status, [ApInvoice::STATUS_APPROVED, ApInvoice::STATUS_PARTIALLY_PAID], true)) {
            throw ValidationException::withMessages([
                'ap_invoice' => 'AP Invoice tidak dalam status yang bisa menerima pembayaran.',
            ]);
        }

        $validated = $request->validated();
        $amountPaidSoFar = (float) $apInvoice->payments()->sum('amount');
        $balanceDue = (float) $apInvoice->total_amount - $amountPaidSoFar;

        if ((float) $validated['amount'] > $balanceDue + 0.01) {
            throw ValidationException::withMessages([
                'amount' => 'Jumlah pembayaran melebihi sisa tagihan ('.$balanceDue.').',
            ]);
        }

        DB::transaction(function () use ($validated, $apInvoice, $amountPaidSoFar): void {
            $payment = Payment::query()->create([
                'payment_number' => Payment::generateNumber(),
                'payable_type' => ApInvoice::class,
                'payable_id' => $apInvoice->id,
                'amount' => $validated['amount'],
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'],
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'recorded_by' => auth()->id(),
            ]);

            $this->postPaymentToGl($payment, $apInvoice);

            $newAmountPaid = $amountPaidSoFar + (float) $validated['amount'];
            $isFullyPaid = $newAmountPaid >= (float) $apInvoice->total_amount - 0.01;

            $apInvoice->update([
                'status' => $isFullyPaid ? ApInvoice::STATUS_PAID : ApInvoice::STATUS_PARTIALLY_PAID,
                'paid_at' => $isFullyPaid ? now() : null,
            ]);

            AccountingAuditLogger::record('AP Invoice payment recorded', $apInvoice, $payment->payment_number);
        });

        return to_route('purchase.ap.invoices.index')->with('success', 'Pembayaran ke supplier berhasil dicatat.');
    }

    /**
     * Tax rate (%) configured in Accounting > Tax Setting, applied to AP invoice subtotal.
     */
    private function taxRate(): float
    {
        return (float) AppSetting::get('tax_rate', 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function currencyOptions(): array
    {
        return Currency::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['code', 'name'])
            ->map(fn (Currency $currency): array => [
                'code' => $currency->code,
                'name' => $currency->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Purchase orders eligible as an AP invoice source: must have had at
     * least one goods receipt (Approved / Partial / Completed).
     *
     * @return array<int, array<string, mixed>>
     */
    private function purchaseOrderOptions(): array
    {
        return PurchaseOrder::query()
            ->whereIn('status', [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_PARTIAL, PurchaseOrder::STATUS_COMPLETED])
            ->with(['items.part:id,part_number,name'])
            ->latest()
            ->get(['id', 'po_number', 'supplier_id', 'currency_code'])
            ->map(fn (PurchaseOrder $purchaseOrder): array => [
                'id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'supplier_id' => $purchaseOrder->supplier_id,
                'currency_code' => $purchaseOrder->currency_code,
                'items' => $purchaseOrder->items->map(fn (PurchaseOrderItem $item): array => [
                    'id' => $item->id,
                    'part_id' => $item->part_id,
                    'part_number' => $item->part?->part_number,
                    'part_name' => $item->part?->name,
                    'received_quantity' => (string) $item->received_quantity,
                    'unit_price' => (string) $item->unit_price,
                ])->values(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function partOptions(): array
    {
        return Part::query()
            ->orderBy('part_number')
            ->get(['id', 'part_number', 'name', 'category'])
            ->map(fn (Part $part): array => [
                'id' => $part->id,
                'part_number' => $part->part_number,
                'name' => $part->name,
                'category' => $part->category,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function syncLines(ApInvoice $apInvoice, array $lines): void
    {
        $subtotal = 0.0;

        foreach ($lines as $line) {
            $quantity = (float) $line['quantity'];
            $unitPrice = (float) $line['unit_price'];
            $lineTotal = $quantity * $unitPrice;
            $subtotal += $lineTotal;

            $description = trim((string) ($line['description'] ?? ''));

            ApInvoiceItem::query()->create([
                'ap_invoice_id' => $apInvoice->id,
                'purchase_order_item_id' => $line['purchase_order_item_id'] ?? null,
                'part_id' => $line['part_id'] ?? null,
                'description' => $description !== '' ? $description : null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ]);
        }

        $taxAmount = round($subtotal * $this->taxRate() / 100, 2);

        $apInvoice->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $subtotal + $taxAmount,
        ]);
    }

    /**
     * Post the AP liability entry when an invoice is approved: DR Purchase
     * Expense (+ DR Input Tax if tax_amount > 0), CR Accounts Payable.
     * Amounts are converted from the invoice's currency to the base currency
     * using the exchange rate as of the invoice date; that rate becomes the
     * invoice's carrying rate for future realized gain/loss and FX revaluation.
     */
    private function postApInvoiceToGl(ApInvoice $apInvoice): void
    {
        $fiscalPeriod = FiscalPeriod::findOpenPeriodForDate((string) $apInvoice->invoice_date->format('Y-m-d'));

        if (! $fiscalPeriod) {
            throw ValidationException::withMessages([
                'ap_invoice' => 'Tidak ada Fiscal Period terbuka untuk tanggal invoice ini. Buka dulu di Accounting > Fiscal Periods.',
            ]);
        }

        $apAccountId = (int) AppSetting::get('gl_ap_account_id');
        $expenseAccountId = (int) AppSetting::get('gl_purchase_expense_account_id');
        $inputTaxAccountId = (int) AppSetting::get('gl_purchase_tax_input_account_id');

        if (! $apAccountId || ! $expenseAccountId) {
            throw ValidationException::withMessages([
                'ap_invoice' => 'GL Account Mapping untuk AP belum diatur. Buka dulu di Accounting > GL Setting.',
            ]);
        }

        $baseCurrencyCode = (string) AppSetting::get('default_currency_code', 'IDR');
        $rate = ExchangeRate::rateFor($apInvoice->currency_code, $baseCurrencyCode, (string) $apInvoice->invoice_date->format('Y-m-d'));

        $baseSubtotal = round((float) $apInvoice->subtotal * $rate, 2);
        $baseTax = round((float) $apInvoice->tax_amount * $rate, 2);
        $baseTotal = $baseSubtotal + $baseTax;

        $fxNote = $rate !== 1.0 ? ' ('.$apInvoice->currency_code.' @ '.$rate.')' : '';

        $entry = JournalEntry::query()->create([
            'fiscal_period_id' => $fiscalPeriod->id,
            'entry_number' => JournalEntry::generateEntryNumber(),
            'entry_date' => $apInvoice->invoice_date,
            'description' => 'AP liability: AP Invoice '.$apInvoice->ap_invoice_number.$fxNote,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        JournalLine::query()->create([
            'journal_entry_id' => $entry->id,
            'chart_of_account_id' => $expenseAccountId,
            'line_type' => 'debit',
            'amount' => $baseSubtotal,
            'description' => 'AP Invoice '.$apInvoice->ap_invoice_number,
        ]);

        if ($baseTax > 0) {
            if (! $inputTaxAccountId) {
                throw ValidationException::withMessages([
                    'ap_invoice' => 'GL Account Mapping untuk Purchase Tax Input belum diatur.',
                ]);
            }

            JournalLine::query()->create([
                'journal_entry_id' => $entry->id,
                'chart_of_account_id' => $inputTaxAccountId,
                'line_type' => 'debit',
                'amount' => $baseTax,
                'description' => 'AP Invoice '.$apInvoice->ap_invoice_number.' (tax)',
            ]);
        }

        JournalLine::query()->create([
            'journal_entry_id' => $entry->id,
            'chart_of_account_id' => $apAccountId,
            'line_type' => 'credit',
            'amount' => $baseTotal,
            'description' => 'AP Invoice '.$apInvoice->ap_invoice_number,
        ]);

        $apInvoice->update(['carrying_exchange_rate' => $rate]);
    }

    /**
     * Post the settlement entry when a payment is recorded: DR Accounts
     * Payable, CR Cash/Bank, both converted to base currency. The AP side
     * uses the invoice's carrying rate (what it's currently recorded at in
     * the GL); the cash side uses today's rate. Any difference is a Realized
     * FX Gain (credit) or Loss (debit) so the entry still balances — for a
     * liability, paying out less base-currency value than it was carried at
     * is a gain, and paying out more is a loss.
     */
    private function postPaymentToGl(Payment $payment, ApInvoice $apInvoice): void
    {
        $fiscalPeriod = FiscalPeriod::findOpenPeriodForDate((string) $payment->payment_date->format('Y-m-d'));

        if (! $fiscalPeriod) {
            throw ValidationException::withMessages([
                'amount' => 'Tidak ada Fiscal Period terbuka untuk tanggal pembayaran ini. Buka dulu di Accounting > Fiscal Periods.',
            ]);
        }

        $cashAccountId = (int) AppSetting::get('gl_cash_bank_account_id');
        $apAccountId = (int) AppSetting::get('gl_ap_account_id');

        if (! $cashAccountId || ! $apAccountId) {
            throw ValidationException::withMessages([
                'amount' => 'GL Account Mapping belum diatur. Buka dulu di Accounting > GL Setting.',
            ]);
        }

        $baseCurrencyCode = (string) AppSetting::get('default_currency_code', 'IDR');
        $paymentRate = ExchangeRate::rateFor($apInvoice->currency_code, $baseCurrencyCode, (string) $payment->payment_date->format('Y-m-d'));
        $carryingRate = (float) ($apInvoice->carrying_exchange_rate ?? $paymentRate);

        $baseCashAmount = round((float) $payment->amount * $paymentRate, 2);
        $baseApReduction = round((float) $payment->amount * $carryingRate, 2);
        $fxDifference = round($baseApReduction - $baseCashAmount, 2);

        $entry = JournalEntry::query()->create([
            'fiscal_period_id' => $fiscalPeriod->id,
            'entry_number' => JournalEntry::generateEntryNumber(),
            'entry_date' => $payment->payment_date,
            'description' => 'AP settlement: '.$payment->payment_number.' for AP Invoice '.$apInvoice->ap_invoice_number,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        JournalLine::query()->create([
            'journal_entry_id' => $entry->id,
            'chart_of_account_id' => $apAccountId,
            'line_type' => 'debit',
            'amount' => $baseApReduction,
            'description' => $payment->payment_number,
        ]);

        JournalLine::query()->create([
            'journal_entry_id' => $entry->id,
            'chart_of_account_id' => $cashAccountId,
            'line_type' => 'credit',
            'amount' => $baseCashAmount,
            'description' => $payment->payment_number,
        ]);

        if (abs($fxDifference) >= 0.01) {
            if ($fxDifference > 0) {
                $gainAccountId = (int) AppSetting::get('gl_realized_fx_gain_account_id');

                if (! $gainAccountId) {
                    throw ValidationException::withMessages([
                        'amount' => 'GL Account Mapping untuk Realized FX Gain belum diatur. Buka dulu di Accounting > GL Setting.',
                    ]);
                }

                JournalLine::query()->create([
                    'journal_entry_id' => $entry->id,
                    'chart_of_account_id' => $gainAccountId,
                    'line_type' => 'credit',
                    'amount' => $fxDifference,
                    'description' => 'Realized FX gain: '.$payment->payment_number,
                ]);
            } else {
                $lossAccountId = (int) AppSetting::get('gl_realized_fx_loss_account_id');

                if (! $lossAccountId) {
                    throw ValidationException::withMessages([
                        'amount' => 'GL Account Mapping untuk Realized FX Loss belum diatur. Buka dulu di Accounting > GL Setting.',
                    ]);
                }

                JournalLine::query()->create([
                    'journal_entry_id' => $entry->id,
                    'chart_of_account_id' => $lossAccountId,
                    'line_type' => 'debit',
                    'amount' => abs($fxDifference),
                    'description' => 'Realized FX loss: '.$payment->payment_number,
                ]);
            }
        }

        $payment->update(['journal_entry_id' => $entry->id]);
    }

    /**
     * AP Invoice can only be edited while still Draft — once approved, the
     * AP liability journal entry is posted with these amounts and must not drift.
     */
    private function isEditable(ApInvoice $apInvoice): bool
    {
        return $apInvoice->status === ApInvoice::STATUS_DRAFT;
    }

    /**
     * Ensure only GM/Director can approve or reject AP invoice.
     */
    private function ensureManagementApprover(Request $request): void
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->canApproveApInvoice()) {
            throw new AuthorizationException('Hanya GM/Director yang dapat melakukan approval.');
        }
    }
}
