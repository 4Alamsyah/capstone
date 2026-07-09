<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invoice\RecordInvoicePaymentRequest;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\Currency;
use App\Models\FiscalPeriod;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Part;
use App\Models\Payment;
use App\Models\User;
use App\Support\AccountingAuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $status = $request->integer('status');

        $invoices = Invoice::query()
            ->with(['customer:id,name', 'customerOrder:id,co_number', 'items.part:id,part_number,name'])
            ->withSum('payments as amount_paid', 'amount')
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($inner) use ($search): void {
                    $inner->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search): void {
                            $customerQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('customerOrder', function ($orderQuery) use ($search): void {
                            $orderQuery->where('co_number', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status > 0, function ($builder) use ($status): void {
                $builder->where('status', $status);
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('sales/invoices/Index', [
            'invoices' => collect($invoices->items())->map(function (Invoice $invoice): array {
                $amountPaid = (float) ($invoice->amount_paid ?? 0);

                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
                    'due_date' => $invoice->due_date?->format('Y-m-d'),
                    'status' => $invoice->status,
                    'payment_approval_status' => $invoice->payment_approval_status,
                    'payment_approval_notes' => $invoice->payment_approval_notes,
                    'paid_at' => $invoice->paid_at?->format('Y-m-d H:i'),
                    'currency_code' => $invoice->currency_code,
                    'subtotal' => (string) $invoice->subtotal,
                    'tax_amount' => (string) $invoice->tax_amount,
                    'total_amount' => (string) $invoice->total_amount,
                    'amount_paid' => (string) $amountPaid,
                    'balance_due' => (string) ((float) $invoice->total_amount - $amountPaid),
                    'customer' => [
                        'id' => $invoice->customer?->id,
                        'name' => $invoice->customer?->name,
                    ],
                    'customer_order' => [
                        'id' => $invoice->customerOrder?->id,
                        'co_number' => $invoice->customerOrder?->co_number,
                    ],
                    'items' => $invoice->items->map(fn (InvoiceItem $item): array => [
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
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
                'from' => $invoices->firstItem(),
                'to' => $invoices->lastItem(),
                'prev_page_url' => $invoices->previousPageUrl(),
                'next_page_url' => $invoices->nextPageUrl(),
            ],
            'statusLabels' => Invoice::statusLabels(),
            'paymentApprovalLabels' => Invoice::paymentApprovalLabels(),
            'canManageApprovals' => $request->user() instanceof User ? $request->user()->canApproveInvoicePayment() : false,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('sales/invoices/Create', [
            'nextInvoiceNumber' => Invoice::generateNumber(),
            'defaultCurrency' => (string) AppSetting::get('default_currency_code', 'IDR'),
            'taxRate' => $this->taxRate(),
            'currencies' => Currency::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['code', 'name'])
                ->map(fn (Currency $currency): array => [
                    'code' => $currency->code,
                    'name' => $currency->name,
                ])
                ->values(),
            'customers' => Customer::query()
                ->orderBy('name')
                ->get(['id', 'name', 'currency_code'])
                ->map(fn (Customer $customer): array => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'currency_code' => $customer->currency_code,
                ]),
            'orders' => CustomerOrder::query()
                ->where('status', '!=', CustomerOrder::STATUS_QUOTATION)
                ->with(['items.part:id,part_number,name'])
                ->latest()
                ->get(['id', 'co_number', 'customer_id', 'currency_code'])
                ->map(fn (CustomerOrder $order): array => [
                    'id' => $order->id,
                    'co_number' => $order->co_number,
                    'customer_id' => $order->customer_id,
                    'currency_code' => $order->currency_code,
                    'items' => $order->items->map(fn (CustomerOrderItem $item): array => [
                        'part_id' => $item->part_id,
                        'part_number' => $item->part?->part_number,
                        'part_name' => $item->part?->name,
                        'quantity' => (string) $item->quantity,
                        'unit_price' => (string) $item->unit_price,
                    ])->values(),
                ]),
            'parts' => Part::query()
                ->orderBy('part_number')
                ->get(['id', 'part_number', 'name', 'selling_price'])
                ->map(fn (Part $part): array => [
                    'id' => $part->id,
                    'part_number' => $part->part_number,
                    'name' => $part->name,
                    'selling_price' => (float) $part->selling_price,
                ]),
        ]);
    }

    public function edit(Invoice $invoice): Response|RedirectResponse
    {
        if (! $this->isEditable($invoice)) {
            return to_route('sales.invoices.index')
                ->with('error', 'Invoice hanya bisa diedit sebelum payment diajukan.');
        }

        $invoice->loadMissing(['items.part']);

        return Inertia::render('sales/invoices/Edit', [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer_id' => $invoice->customer_id,
                'customer_order_id' => $invoice->customer_order_id,
                'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
                'due_date' => $invoice->due_date?->format('Y-m-d'),
                'currency_code' => $invoice->currency_code,
                'notes' => $invoice->notes,
                'lines' => $invoice->items->map(fn (InvoiceItem $item): array => [
                    'part_id' => $item->part_id,
                    'description' => $item->description,
                    'quantity' => (string) $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                ])->values(),
            ],
            'defaultCurrency' => (string) AppSetting::get('default_currency_code', 'IDR'),
            'taxRate' => $this->taxRate(),
            'currencies' => Currency::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['code', 'name'])
                ->map(fn (Currency $currency): array => [
                    'code' => $currency->code,
                    'name' => $currency->name,
                ])
                ->values(),
            'customers' => Customer::query()
                ->orderBy('name')
                ->get(['id', 'name', 'currency_code'])
                ->map(fn (Customer $customer): array => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'currency_code' => $customer->currency_code,
                ]),
            'orders' => CustomerOrder::query()
                ->where('status', '!=', CustomerOrder::STATUS_QUOTATION)
                ->with(['items.part:id,part_number,name'])
                ->latest()
                ->get(['id', 'co_number', 'customer_id', 'currency_code'])
                ->map(fn (CustomerOrder $order): array => [
                    'id' => $order->id,
                    'co_number' => $order->co_number,
                    'customer_id' => $order->customer_id,
                    'currency_code' => $order->currency_code,
                    'items' => $order->items->map(fn (CustomerOrderItem $item): array => [
                        'part_id' => $item->part_id,
                        'part_number' => $item->part?->part_number,
                        'part_name' => $item->part?->name,
                        'quantity' => (string) $item->quantity,
                        'unit_price' => (string) $item->unit_price,
                    ])->values(),
                ]),
            'parts' => Part::query()
                ->orderBy('part_number')
                ->get(['id', 'part_number', 'name', 'selling_price'])
                ->map(fn (Part $part): array => [
                    'id' => $part->id,
                    'part_number' => $part->part_number,
                    'name' => $part->name,
                    'selling_price' => (float) $part->selling_price,
                ]),
        ]);
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $order = null;
        if (! empty($validated['customer_order_id'])) {
            $order = CustomerOrder::query()->find($validated['customer_order_id']);

            if ($order && (int) $order->customer_id !== (int) $validated['customer_id']) {
                throw ValidationException::withMessages([
                    'customer_order_id' => 'Customer order tidak sesuai dengan customer yang dipilih.',
                ]);
            }
        }

        DB::transaction(function () use ($validated): void {
            $invoice = Invoice::query()->create([
                'invoice_number' => Invoice::generateNumber(),
                'customer_id' => $validated['customer_id'],
                'customer_order_id' => $validated['customer_order_id'] ?? null,
                'status' => Invoice::STATUS_DRAFT,
                'payment_approval_status' => Invoice::PAYMENT_APPROVAL_NOT_REQUESTED,
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'] ?? null,
                'currency_code' => strtoupper((string) ($validated['currency_code'] ?? AppSetting::get('default_currency_code', 'IDR'))),
                'subtotal' => 0,
                'tax_amount' => 0,
                'total_amount' => 0,
                'notes' => $validated['notes'] ?? null,
            ]);

            $subtotal = 0.0;

            foreach ($validated['lines'] as $line) {
                $quantity = (float) $line['quantity'];
                $unitPrice = (float) $line['unit_price'];
                $lineTotal = $quantity * $unitPrice;
                $subtotal += $lineTotal;

                $description = trim((string) ($line['description'] ?? ''));

                InvoiceItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'part_id' => $line['part_id'] ?? null,
                    'description' => $description !== '' ? $description : null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ]);
            }

            $taxAmount = round($subtotal * $this->taxRate() / 100, 2);

            $invoice->update([
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $subtotal + $taxAmount,
            ]);
        });

        return to_route('sales.invoices.index');
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        if (! $this->isEditable($invoice)) {
            return to_route('sales.invoices.index')
                ->with('error', 'Invoice hanya bisa diedit sebelum payment diajukan.');
        }

        $validated = $request->validated();

        if (! empty($validated['customer_order_id'])) {
            $order = CustomerOrder::query()->find($validated['customer_order_id']);

            if ($order && (int) $order->customer_id !== (int) $validated['customer_id']) {
                throw ValidationException::withMessages([
                    'customer_order_id' => 'Customer order tidak sesuai dengan customer yang dipilih.',
                ]);
            }
        }

        DB::transaction(function () use ($validated, $invoice): void {
            $invoice->update([
                'customer_id' => $validated['customer_id'],
                'customer_order_id' => $validated['customer_order_id'] ?? null,
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'] ?? null,
                'currency_code' => strtoupper((string) ($validated['currency_code'] ?? AppSetting::get('default_currency_code', 'IDR'))),
                'notes' => $validated['notes'] ?? null,
            ]);

            $invoice->items()->delete();

            $subtotal = 0.0;

            foreach ($validated['lines'] as $line) {
                $quantity = (float) $line['quantity'];
                $unitPrice = (float) $line['unit_price'];
                $lineTotal = $quantity * $unitPrice;
                $subtotal += $lineTotal;

                $description = trim((string) ($line['description'] ?? ''));

                InvoiceItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'part_id' => $line['part_id'] ?? null,
                    'description' => $description !== '' ? $description : null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ]);
            }

            $taxAmount = round($subtotal * $this->taxRate() / 100, 2);

            $invoice->update([
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $subtotal + $taxAmount,
            ]);
        });

        return to_route('sales.invoices.index')->with('success', 'Invoice berhasil diperbarui.');
    }

    /**
     * Send the invoice: locks it from further edits and posts the AR/Revenue
     * billing entry to the General Ledger (DR AR, CR Revenue + Tax Payable).
     */
    public function send(Invoice $invoice): RedirectResponse
    {
        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'invoice' => 'Invoice hanya bisa dikirim dari status Draft.',
            ]);
        }

        DB::transaction(function () use ($invoice): void {
            $this->postInvoiceBillingToGl($invoice);

            $invoice->update([
                'status' => Invoice::STATUS_SENT,
            ]);

            AccountingAuditLogger::record('Invoice sent / AR posted', $invoice, $invoice->invoice_number);
        });

        return back()->with('success', 'Invoice berhasil dikirim dan AR sudah tercatat di jurnal.');
    }

    /**
     * Submit payment request that requires management approval.
     */
    public function requestPayment(Request $request, Invoice $invoice): RedirectResponse
    {
        if (! in_array($invoice->status, [Invoice::STATUS_SENT, Invoice::STATUS_PARTIALLY_PAID], true)) {
            throw ValidationException::withMessages([
                'invoice' => 'Invoice harus dikirim (Sent) dulu sebelum payment bisa diajukan.',
            ]);
        }

        if ($invoice->payment_approval_status === Invoice::PAYMENT_APPROVAL_PENDING) {
            throw ValidationException::withMessages([
                'invoice' => 'Approval payment sedang diproses.',
            ]);
        }

        $validated = $request->validate([
            'payment_approval_notes' => ['nullable', 'string'],
        ]);

        $invoice->update([
            'payment_approval_status' => Invoice::PAYMENT_APPROVAL_PENDING,
            'payment_requested_by' => $request->user()?->id,
            'payment_requested_at' => now(),
            'payment_approved_by' => null,
            'payment_approved_at' => null,
            'payment_rejected_by' => null,
            'payment_rejected_at' => null,
            'payment_approval_notes' => $validated['payment_approval_notes'] ?? null,
        ]);

        return back()->with('success', 'Payment approval berhasil diajukan.');
    }

    /**
     * Approve payment request. This only authorizes recording a payment via
     * recordPayment() — it does not itself move money or touch the GL.
     */
    public function approvePayment(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->ensureManagementApprover($request);

        if ($invoice->payment_approval_status !== Invoice::PAYMENT_APPROVAL_PENDING) {
            throw ValidationException::withMessages([
                'invoice' => 'Invoice tidak dalam status payment approval pending.',
            ]);
        }

        $validated = $request->validate([
            'payment_approval_notes' => ['nullable', 'string'],
        ]);

        $invoice->update([
            'payment_approval_status' => Invoice::PAYMENT_APPROVAL_APPROVED,
            'payment_approved_by' => $request->user()?->id,
            'payment_approved_at' => now(),
            'payment_rejected_by' => null,
            'payment_rejected_at' => null,
            'payment_approval_notes' => $validated['payment_approval_notes'] ?? $invoice->payment_approval_notes,
        ]);

        return back()->with('success', 'Payment approval disetujui. Silakan Record Payment untuk mencatat pembayarannya.');
    }

    /**
     * Show the Record Payment form for an approved invoice.
     */
    public function newPayment(Invoice $invoice): Response|RedirectResponse
    {
        if ($invoice->payment_approval_status !== Invoice::PAYMENT_APPROVAL_APPROVED) {
            return to_route('sales.invoices.index')
                ->with('error', 'Payment harus di-approve dulu sebelum bisa dicatat.');
        }

        $amountPaid = (float) $invoice->payments()->sum('amount');

        return Inertia::render('sales/invoices/RecordPayment', [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'currency_code' => $invoice->currency_code,
                'total_amount' => (string) $invoice->total_amount,
                'amount_paid' => (string) $amountPaid,
                'balance_due' => (string) ((float) $invoice->total_amount - $amountPaid),
            ],
            'paymentMethods' => Payment::methodLabels(),
        ]);
    }

    /**
     * Record an actual (possibly partial) payment against the invoice and
     * post the Cash/Bank vs AR settlement entry to the General Ledger.
     */
    public function recordPayment(RecordInvoicePaymentRequest $request, Invoice $invoice): RedirectResponse
    {
        if ($invoice->payment_approval_status !== Invoice::PAYMENT_APPROVAL_APPROVED) {
            throw ValidationException::withMessages([
                'invoice' => 'Payment harus di-approve dulu sebelum bisa dicatat.',
            ]);
        }

        if (! in_array($invoice->status, [Invoice::STATUS_SENT, Invoice::STATUS_PARTIALLY_PAID], true)) {
            throw ValidationException::withMessages([
                'invoice' => 'Invoice tidak dalam status yang bisa menerima pembayaran.',
            ]);
        }

        $validated = $request->validated();
        $amountPaidSoFar = (float) $invoice->payments()->sum('amount');
        $balanceDue = (float) $invoice->total_amount - $amountPaidSoFar;

        if ((float) $validated['amount'] > $balanceDue + 0.01) {
            throw ValidationException::withMessages([
                'amount' => 'Jumlah pembayaran melebihi sisa tagihan (' . $balanceDue . ').',
            ]);
        }

        DB::transaction(function () use ($validated, $invoice, $amountPaidSoFar): void {
            $payment = Payment::query()->create([
                'payment_number' => Payment::generateNumber(),
                'payable_type' => Invoice::class,
                'payable_id' => $invoice->id,
                'amount' => $validated['amount'],
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'],
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'recorded_by' => auth()->id(),
            ]);

            $this->postPaymentToGl($payment, $invoice);

            $newAmountPaid = $amountPaidSoFar + (float) $validated['amount'];
            $isFullyPaid = $newAmountPaid >= (float) $invoice->total_amount - 0.01;

            $invoice->update([
                'status' => $isFullyPaid ? Invoice::STATUS_PAID : Invoice::STATUS_PARTIALLY_PAID,
                'payment_approval_status' => Invoice::PAYMENT_APPROVAL_NOT_REQUESTED,
                'paid_at' => $isFullyPaid ? now() : null,
            ]);

            AccountingAuditLogger::record('Invoice payment recorded', $invoice, $payment->payment_number);
        });

        return to_route('sales.invoices.index')->with('success', 'Pembayaran berhasil dicatat.');
    }

    /**
     * Reject payment request.
     */
    public function rejectPayment(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->ensureManagementApprover($request);

        if ($invoice->payment_approval_status !== Invoice::PAYMENT_APPROVAL_PENDING) {
            throw ValidationException::withMessages([
                'invoice' => 'Invoice tidak dalam status payment approval pending.',
            ]);
        }

        $validated = $request->validate([
            'payment_approval_notes' => ['required', 'string'],
        ]);

        $invoice->update([
            'payment_approval_status' => Invoice::PAYMENT_APPROVAL_REJECTED,
            'payment_rejected_by' => $request->user()?->id,
            'payment_rejected_at' => now(),
            'payment_approved_by' => null,
            'payment_approved_at' => null,
            'payment_approval_notes' => $validated['payment_approval_notes'],
        ]);

        return back()->with('success', 'Payment request berhasil di-reject oleh manajemen.');
    }

    public function document(Invoice $invoice): \Symfony\Component\HttpFoundation\Response
    {
        $invoice->loadMissing([
            'customer:id,name,address,shipping_address,payment_terms',
            'customerOrder:id,co_number,project_code',
            'items.part:id,part_number,name',
        ]);

        $pdf = Pdf::loadView('documents.invoice', [
            'invoice' => $invoice,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('Invoice-' . $invoice->invoice_number . '.pdf');
    }

    /**
     * Tax rate (%) configured in Accounting > Tax Setting, applied to invoice subtotal.
     */
    private function taxRate(): float
    {
        return (float) AppSetting::get('tax_rate', 0);
    }

    /**
     * Post the AR billing entry when an invoice is sent: DR Accounts Receivable,
     * CR Sales Revenue (+ CR Sales Tax Payable if tax_amount > 0).
     */
    private function postInvoiceBillingToGl(Invoice $invoice): void
    {
        $fiscalPeriod = FiscalPeriod::findOpenPeriodForDate((string) $invoice->invoice_date->format('Y-m-d'));

        if (! $fiscalPeriod) {
            throw ValidationException::withMessages([
                'invoice' => 'Tidak ada Fiscal Period terbuka untuk tanggal invoice ini. Buka dulu di Accounting > Fiscal Periods.',
            ]);
        }

        $arAccountId = (int) AppSetting::get('gl_ar_account_id');
        $revenueAccountId = (int) AppSetting::get('gl_sales_revenue_account_id');
        $taxPayableAccountId = (int) AppSetting::get('gl_sales_tax_payable_account_id');

        if (! $arAccountId || ! $revenueAccountId) {
            throw ValidationException::withMessages([
                'invoice' => 'GL Account Mapping belum diatur. Buka dulu di Accounting > GL Setting.',
            ]);
        }

        $entry = JournalEntry::query()->create([
            'fiscal_period_id' => $fiscalPeriod->id,
            'entry_number' => JournalEntry::generateEntryNumber(),
            'entry_date' => $invoice->invoice_date,
            'description' => 'AR billing: Invoice ' . $invoice->invoice_number,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        JournalLine::query()->create([
            'journal_entry_id' => $entry->id,
            'chart_of_account_id' => $arAccountId,
            'line_type' => 'debit',
            'amount' => $invoice->total_amount,
            'description' => 'Invoice ' . $invoice->invoice_number,
        ]);

        JournalLine::query()->create([
            'journal_entry_id' => $entry->id,
            'chart_of_account_id' => $revenueAccountId,
            'line_type' => 'credit',
            'amount' => $invoice->subtotal,
            'description' => 'Invoice ' . $invoice->invoice_number,
        ]);

        if ((float) $invoice->tax_amount > 0) {
            if (! $taxPayableAccountId) {
                throw ValidationException::withMessages([
                    'invoice' => 'GL Account Mapping untuk Sales Tax Payable belum diatur.',
                ]);
            }

            JournalLine::query()->create([
                'journal_entry_id' => $entry->id,
                'chart_of_account_id' => $taxPayableAccountId,
                'line_type' => 'credit',
                'amount' => $invoice->tax_amount,
                'description' => 'Invoice ' . $invoice->invoice_number . ' (tax)',
            ]);
        }
    }

    /**
     * Post the settlement entry when a payment is recorded: DR Cash/Bank, CR Accounts Receivable.
     */
    private function postPaymentToGl(Payment $payment, Invoice $invoice): void
    {
        $fiscalPeriod = FiscalPeriod::findOpenPeriodForDate((string) $payment->payment_date->format('Y-m-d'));

        if (! $fiscalPeriod) {
            throw ValidationException::withMessages([
                'amount' => 'Tidak ada Fiscal Period terbuka untuk tanggal pembayaran ini. Buka dulu di Accounting > Fiscal Periods.',
            ]);
        }

        $cashAccountId = (int) AppSetting::get('gl_cash_bank_account_id');
        $arAccountId = (int) AppSetting::get('gl_ar_account_id');

        if (! $cashAccountId || ! $arAccountId) {
            throw ValidationException::withMessages([
                'amount' => 'GL Account Mapping belum diatur. Buka dulu di Accounting > GL Setting.',
            ]);
        }

        $entry = JournalEntry::query()->create([
            'fiscal_period_id' => $fiscalPeriod->id,
            'entry_number' => JournalEntry::generateEntryNumber(),
            'entry_date' => $payment->payment_date,
            'description' => 'AR settlement: ' . $payment->payment_number . ' for Invoice ' . $invoice->invoice_number,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        JournalLine::query()->create([
            'journal_entry_id' => $entry->id,
            'chart_of_account_id' => $cashAccountId,
            'line_type' => 'debit',
            'amount' => $payment->amount,
            'description' => $payment->payment_number,
        ]);

        JournalLine::query()->create([
            'journal_entry_id' => $entry->id,
            'chart_of_account_id' => $arAccountId,
            'line_type' => 'credit',
            'amount' => $payment->amount,
            'description' => $payment->payment_number,
        ]);

        $payment->update(['journal_entry_id' => $entry->id]);
    }

    /**
     * Invoice can only be edited while still Draft — once sent, the AR/Revenue
     * journal entry is posted with these amounts and must not drift from them.
     */
    private function isEditable(Invoice $invoice): bool
    {
        return $invoice->status === Invoice::STATUS_DRAFT;
    }

    /**
     * Ensure only GM/Director can approve or reject payment.
     */
    private function ensureManagementApprover(Request $request): void
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->canApproveInvoicePayment()) {
            throw new AuthorizationException('Hanya GM/Director yang dapat melakukan approval.');
        }
    }
}
