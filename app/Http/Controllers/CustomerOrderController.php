<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerOrder\StoreCustomerOrderRequest;
use App\Http\Requests\CustomerOrder\UpdateCustomerOrderRequest;
use App\Http\Requests\CustomerOrder\UpdateCustomerOrderStatusRequest;
use App\Models\AppSetting;
use App\Models\Bom;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Part;
use App\Models\PurchaseVoucher;
use App\Models\PurchaseVoucherItem;
use App\Models\WorkOrder;
use App\Models\WorkOrderLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CustomerOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $statusFilter = $request->integer('status');
        $deliveryFrom = $request->string('delivery_from')->toString();
        $deliveryTo = $request->string('delivery_to')->toString();

        $query = CustomerOrder::query()
            ->with(['customer', 'items.part'])
            ->where('status', '!=', CustomerOrder::STATUS_QUOTATION)
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($inner) use ($search): void {
                    $inner->where('co_number', 'like', "%{$search}%")
                        ->orWhere('po_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search): void {
                            $customerQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('items.part', function ($partQuery) use ($search): void {
                            $partQuery->where('part_number', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($statusFilter > 0, function ($builder) use ($statusFilter): void {
                $builder->where('status', $statusFilter);
            })
            ->when($deliveryFrom !== '', function ($builder) use ($deliveryFrom): void {
                $builder->whereDate('delivery_date', '>=', $deliveryFrom);
            })
            ->when($deliveryTo !== '', function ($builder) use ($deliveryTo): void {
                $builder->whereDate('delivery_date', '<=', $deliveryTo);
            });

        $orders = (clone $query)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $invoiceByOrderId = Invoice::query()
            ->whereIn('customer_order_id', collect($orders->items())->pluck('id')->all())
            ->get(['id', 'customer_order_id', 'invoice_number'])
            ->keyBy('customer_order_id');

        $planningRows = (clone $query)
            ->whereNotNull('delivery_date')
            ->where('status', '!=', CustomerOrder::STATUS_HISTORICAL)
            ->orderBy('delivery_date')
            ->get(['delivery_date', 'status', 'needs_mo_suggestion'])
            ->groupBy(fn (CustomerOrder $order): string => (string) $order->delivery_date?->format('Y-m-d'))
            ->map(function ($group, $date): array {
                $delayed = $group->filter(fn (CustomerOrder $order): bool =>
                    $order->delivery_date !== null
                    && $order->delivery_date->isPast()
                    && $order->status !== CustomerOrder::STATUS_DELIVERED
                )->count();

                return [
                    'delivery_date' => $date,
                    'orders_count' => $group->count(),
                    'needs_mo_count' => $group->where('needs_mo_suggestion', true)->count(),
                    'delayed_count' => $delayed,
                ];
            })
            ->values();

        return Inertia::render('sales/customer-orders/Index', [
            'orders' => collect($orders->items())->map(fn (CustomerOrder $order): array => [
                'id' => $order->id,
                'co_number' => $order->co_number,
                'status' => $order->status,
                'order_date' => $order->order_date?->format('Y-m-d'),
                'delivery_date' => $order->delivery_date?->format('Y-m-d'),
                'po_number' => $order->po_number,
                'project_code' => $order->project_code,
                'delivery_type' => $order->delivery_type,
                'currency_code' => $order->currency_code,
                'subtotal' => (string) $order->subtotal,
                'needs_mo_suggestion' => $order->needs_mo_suggestion,
                'customer' => [
                    'id' => $order->customer?->id,
                    'name' => $order->customer?->name,
                ],
                'invoice' => [
                    'id' => $invoiceByOrderId->get($order->id)?->id,
                    'invoice_number' => $invoiceByOrderId->get($order->id)?->invoice_number,
                ],
                'items' => $order->items->map(fn (CustomerOrderItem $item): array => [
                    'id' => $item->id,
                    'part_number' => $item->part?->part_number,
                    'part_name' => $item->part?->name,
                    'quantity' => (string) $item->quantity,
                    'unit' => $item->unit,
                    'stock_on_hand' => (int) $item->stock_on_hand,
                    'requires_mo' => $item->requires_mo,
                    'remarks' => $item->remarks,
                ])->values(),
            ])->values(),
            'filters' => [
                'search' => $search,
                'status' => $statusFilter > 0 ? (string) $statusFilter : '',
                'delivery_from' => $deliveryFrom,
                'delivery_to' => $deliveryTo,
            ],
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
                'prev_page_url' => $orders->previousPageUrl(),
                'next_page_url' => $orders->nextPageUrl(),
            ],
            'planning' => $planningRows,
            'statusLabels' => CustomerOrder::statusLabels(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('sales/customer-orders/Create', [
            'nextCoNumber' => CustomerOrder::generateNumber(),
            'defaultCurrency' => (string) AppSetting::get('default_currency_code', 'IDR'),
            'paymentTermsOptions' => collect(json_decode((string) AppSetting::get('payment_terms_options', '[]'), true))
                ->filter(fn ($term): bool => is_string($term) && trim($term) !== '')
                ->map(fn ($term): string => trim((string) $term))
                ->unique(fn (string $term): string => mb_strtolower($term))
                ->values(),
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
                ->get(['id', 'name', 'shipping_address', 'payment_terms', 'currency_code'])
                ->map(fn (Customer $customer): array => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'shipping_address' => $customer->shipping_address,
                    'payment_terms' => $customer->payment_terms,
                    'currency_code' => $customer->currency_code,
                ]),
            'parts' => Part::query()
                ->withSum('stocks as total_stock', 'quantity')
                ->orderBy('part_number')
                ->get(['id', 'part_number', 'name', 'selling_price'])
                ->map(fn (Part $part): array => [
                    'id' => $part->id,
                    'part_number' => $part->part_number,
                    'name' => $part->name,
                    'selling_price' => (float) $part->selling_price,
                    'stock_on_hand' => (int) ($part->total_stock ?? 0),
                ]),
        ]);
    }

    public function edit(CustomerOrder $customerOrder): Response|RedirectResponse
    {
        if ($customerOrder->status !== CustomerOrder::STATUS_REGISTERED) {
            return to_route('sales.customer-orders.index')
                ->with('error', 'Order hanya bisa diedit selama status masih Registered.');
        }

        $customerOrder->loadMissing(['items.part']);

        return Inertia::render('sales/customer-orders/Edit', [
            'order' => [
                'id' => $customerOrder->id,
                'co_number' => $customerOrder->co_number,
                'customer_id' => $customerOrder->customer_id,
                'order_date' => $customerOrder->order_date?->format('Y-m-d'),
                'delivery_date' => $customerOrder->delivery_date?->format('Y-m-d'),
                'shipping_address' => $customerOrder->shipping_address,
                'payment_terms' => $customerOrder->payment_terms,
                'project_code' => $customerOrder->project_code,
                'delivery_type' => $customerOrder->delivery_type,
                'po_number' => $customerOrder->po_number,
                'currency_code' => $customerOrder->currency_code,
                'notes' => $customerOrder->notes,
                'lines' => $customerOrder->items->map(fn (CustomerOrderItem $item): array => [
                    'part_id' => $item->part_id,
                    'quantity' => (string) $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => (string) $item->unit_price,
                    'remarks' => $item->remarks,
                ])->values(),
            ],
            'defaultCurrency' => (string) AppSetting::get('default_currency_code', 'IDR'),
            'paymentTermsOptions' => collect(json_decode((string) AppSetting::get('payment_terms_options', '[]'), true))
                ->filter(fn ($term): bool => is_string($term) && trim($term) !== '')
                ->map(fn ($term): string => trim((string) $term))
                ->unique(fn (string $term): string => mb_strtolower($term))
                ->values(),
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
                ->get(['id', 'name', 'shipping_address', 'payment_terms', 'currency_code'])
                ->map(fn (Customer $customer): array => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'shipping_address' => $customer->shipping_address,
                    'payment_terms' => $customer->payment_terms,
                    'currency_code' => $customer->currency_code,
                ]),
            'parts' => Part::query()
                ->withSum('stocks as total_stock', 'quantity')
                ->orderBy('part_number')
                ->get(['id', 'part_number', 'name', 'selling_price'])
                ->map(fn (Part $part): array => [
                    'id' => $part->id,
                    'part_number' => $part->part_number,
                    'name' => $part->name,
                    'selling_price' => (float) $part->selling_price,
                    'stock_on_hand' => (int) ($part->total_stock ?? 0),
                ]),
        ]);
    }

    public function store(StoreCustomerOrderRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $partIds = collect($validated['lines'])->pluck('part_id')->all();

            $parts = Part::query()
                ->whereIn('id', $partIds)
                ->withSum('stocks as total_stock', 'quantity')
                ->get(['id', 'selling_price'])
                ->keyBy('id');

            $order = CustomerOrder::query()->create([
                'co_number' => CustomerOrder::generateNumber(),
                'customer_id' => $validated['customer_id'],
                'status' => CustomerOrder::STATUS_REGISTERED,
                'order_date' => $validated['order_date'],
                'delivery_date' => $validated['delivery_date'] ?? null,
                'shipping_address' => $validated['shipping_address'] ?? null,
                'payment_terms' => $validated['payment_terms'] ?? null,
                'project_code' => $validated['project_code'] ?? null,
                'delivery_type' => $validated['delivery_type'] ?? null,
                'po_number' => $validated['po_number'] ?? null,
                'currency_code' => strtoupper((string) ($validated['currency_code'] ?? AppSetting::get('default_currency_code', 'IDR'))),
                'subtotal' => 0,
                'needs_mo_suggestion' => false,
                'notes' => $validated['notes'] ?? null,
            ]);

            $subtotal = 0.0;
            $needMo = false;
            $allocatedByPart = [];

            foreach ($validated['lines'] as $line) {
                $part = $parts->get($line['part_id']);

                if (! $part instanceof Part) {
                    continue;
                }

                $quantity = (float) $line['quantity'];
                $unitPrice = (float) $line['unit_price'];
                $lineTotal = $quantity * $unitPrice;

                $stockOnHand = (int) ($part->total_stock ?? 0);
                $alreadyAllocated = (float) ($allocatedByPart[$part->id] ?? 0.0);
                $remainingStock = max(0.0, (float) $stockOnHand - $alreadyAllocated);
                $requiresMo = $remainingStock < $quantity;

                $allocatedByPart[$part->id] = $alreadyAllocated + $quantity;
                $subtotal += $lineTotal;
                $needMo = $needMo || $requiresMo;

                CustomerOrderItem::query()->create([
                    'customer_order_id' => $order->id,
                    'part_id' => $part->id,
                    'quantity' => $quantity,
                    'unit' => strtoupper((string) ($line['unit'] ?? 'PCS')),
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'remarks' => $line['remarks'] ?? null,
                    'stock_on_hand' => (int) $remainingStock,
                    'requires_mo' => $requiresMo,
                ]);
            }

            $order->update([
                'subtotal' => $subtotal,
                'needs_mo_suggestion' => $needMo,
            ]);
        });

        return to_route('sales.customer-orders.index');
    }

    public function update(UpdateCustomerOrderRequest $request, CustomerOrder $customerOrder): RedirectResponse
    {
        if ($customerOrder->status !== CustomerOrder::STATUS_REGISTERED) {
            return to_route('sales.customer-orders.index')
                ->with('error', 'Order hanya bisa diedit selama status masih Registered.');
        }

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $customerOrder): void {
            $partIds = collect($validated['lines'])->pluck('part_id')->all();

            $parts = Part::query()
                ->whereIn('id', $partIds)
                ->withSum('stocks as total_stock', 'quantity')
                ->get(['id', 'selling_price'])
                ->keyBy('id');

            $customerOrder->update([
                'customer_id' => $validated['customer_id'],
                'order_date' => $validated['order_date'],
                'delivery_date' => $validated['delivery_date'] ?? null,
                'shipping_address' => $validated['shipping_address'] ?? null,
                'payment_terms' => $validated['payment_terms'] ?? null,
                'project_code' => $validated['project_code'] ?? null,
                'delivery_type' => $validated['delivery_type'] ?? null,
                'po_number' => $validated['po_number'] ?? null,
                'currency_code' => strtoupper((string) ($validated['currency_code'] ?? AppSetting::get('default_currency_code', 'IDR'))),
                'notes' => $validated['notes'] ?? null,
            ]);

            $customerOrder->items()->delete();

            $subtotal = 0.0;
            $needMo = false;
            $allocatedByPart = [];

            foreach ($validated['lines'] as $line) {
                $part = $parts->get($line['part_id']);

                if (! $part instanceof Part) {
                    continue;
                }

                $quantity = (float) $line['quantity'];
                $unitPrice = (float) $line['unit_price'];
                $lineTotal = $quantity * $unitPrice;

                $stockOnHand = (int) ($part->total_stock ?? 0);
                $alreadyAllocated = (float) ($allocatedByPart[$part->id] ?? 0.0);
                $remainingStock = max(0.0, (float) $stockOnHand - $alreadyAllocated);
                $requiresMo = $remainingStock < $quantity;

                $allocatedByPart[$part->id] = $alreadyAllocated + $quantity;
                $subtotal += $lineTotal;
                $needMo = $needMo || $requiresMo;

                CustomerOrderItem::query()->create([
                    'customer_order_id' => $customerOrder->id,
                    'part_id' => $part->id,
                    'quantity' => $quantity,
                    'unit' => strtoupper((string) ($line['unit'] ?? 'PCS')),
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'remarks' => $line['remarks'] ?? null,
                    'stock_on_hand' => (int) $remainingStock,
                    'requires_mo' => $requiresMo,
                ]);
            }

            $customerOrder->update([
                'subtotal' => $subtotal,
                'needs_mo_suggestion' => $needMo,
            ]);
        });

        return to_route('sales.customer-orders.index')->with('success', 'Customer order berhasil diperbarui.');
    }

    public function confirm(CustomerOrder $customerOrder): RedirectResponse
    {
        if ($customerOrder->status !== CustomerOrder::STATUS_REGISTERED) {
            return to_route('sales.customer-orders.index')->with('error', 'Order hanya bisa dikonfirmasi dari status Registered.');
        }

        $customerOrder->loadMissing('items.part');

        $orderedPartIds = $customerOrder->items
            ->pluck('part_id')
            ->filter()
            ->unique()
            ->values();

        $activeBomsByPartId = Bom::query()
            ->where('is_active', true)
            ->whereIn('part_id', $orderedPartIds)
            ->get(['id', 'part_id'])
            ->keyBy('part_id');

        $missingBomParts = $customerOrder->items
            ->map(fn (CustomerOrderItem $item): ?Part => $item->part)
            ->filter(fn (?Part $part): bool => $part instanceof Part && ! $activeBomsByPartId->has($part->id))
            ->unique('id')
            ->values();

        if ($missingBomParts->isNotEmpty()) {
            $firstMissingPart = $missingBomParts->first();
            $missingPartList = $missingBomParts
                ->map(fn (Part $part): string => trim(($part->part_number ?? '-') . ' - ' . ($part->name ?? '-')))
                ->implode(', ');

            return to_route('bom.create', ['part_id' => $firstMissingPart?->id])
                ->with('error', 'CO tidak bisa di-confirm. Part berikut belum memiliki BOM aktif: '.$missingPartList);
        }

        DB::transaction(function () use ($customerOrder, $activeBomsByPartId): void {
            $customerOrder->update([
                'status' => CustomerOrder::STATUS_CONFIRMED,
            ]);

            $itemsGroupedByPart = $customerOrder->items->groupBy('part_id');

            foreach ($itemsGroupedByPart as $partId => $items) {
                $partIdInt = (int) $partId;

                if ($partIdInt <= 0) {
                    continue;
                }

                $bom = $activeBomsByPartId->get($partIdInt);

                if (! $bom instanceof Bom) {
                    continue;
                }

                $quantity = (float) $items->sum(fn (CustomerOrderItem $item): float => (float) $item->quantity);
                $autoGeneratedNote = sprintf('Auto generated from %s for part #%d', $customerOrder->co_number, $partIdInt);

                $alreadyExists = WorkOrder::query()
                    ->where('notes', $autoGeneratedNote)
                    ->exists();

                if ($alreadyExists) {
                    continue;
                }

                $workOrder = WorkOrder::query()->create([
                    'wo_number' => WorkOrder::generateNumber(),
                    'bom_id' => $bom->id,
                    'quantity' => $quantity,
                    'status' => 'draft',
                    'scheduled_date' => $customerOrder->delivery_date,
                    'notes' => $autoGeneratedNote,
                ]);

                WorkOrderLog::query()->create([
                    'work_order_id' => $workOrder->id,
                    'user_id' => request()->user()?->id,
                    'log_type' => 'created',
                    'title' => 'Manufacture order auto-created',
                    'description' => 'Manufacture order dibuat otomatis saat konfirmasi customer order '.$customerOrder->co_number.'.',
                    'metadata' => [
                        'source' => 'customer_order_confirm',
                        'customer_order_id' => $customerOrder->id,
                        'customer_order_number' => $customerOrder->co_number,
                        'status' => $workOrder->status,
                        'quantity' => (string) $workOrder->quantity,
                    ],
                ]);
            }

            // Auto-create Purchase Voucher for items with insufficient stock
            $insufficientItems = $customerOrder->items->filter(
                fn (CustomerOrderItem $item): bool => (int) $item->stock_on_hand < (float) $item->quantity
            );

            if ($insufficientItems->isNotEmpty()) {
                $alreadyExists = PurchaseVoucher::query()
                    ->where('customer_order_id', $customerOrder->id)
                    ->exists();

                if (!$alreadyExists) {
                    $pv = PurchaseVoucher::query()->create([
                        'pv_number' => PurchaseVoucher::generateNumber(),
                        'status' => PurchaseVoucher::STATUS_DRAFT,
                        'source' => PurchaseVoucher::SOURCE_CO_CONFIRMATION,
                        'customer_order_id' => $customerOrder->id,
                        'created_by' => request()->user()?->id,
                        'notes' => 'Auto-generated from CO '.$customerOrder->co_number,
                    ]);

                    foreach ($insufficientItems as $item) {
                        $deficit = max(0, (float) $item->quantity - (int) $item->stock_on_hand);
                        PurchaseVoucherItem::query()->create([
                            'purchase_voucher_id' => $pv->id,
                            'part_id' => $item->part_id,
                            'quantity' => $deficit,
                            'unit' => $item->unit,
                            'stock_on_hand' => (int) $item->stock_on_hand,
                            'remarks' => 'From CO item: '.($item->part?->part_number ?? ''),
                        ]);
                    }
                }
            }
        });

        $message = 'Customer order berhasil di-confirm dan manufacture order otomatis dibuat.';
        if (isset($pv)) {
            $message .= ' Purchase Voucher otomatis dibuat untuk item dengan stok kurang.';
        }

        return to_route('sales.customer-orders.index')->with('success', $message);
    }

    public function updateStatus(UpdateCustomerOrderStatusRequest $request, CustomerOrder $customerOrder): RedirectResponse
    {
        $newStatus = (int) $request->validated()['status'];

        if ($customerOrder->status < CustomerOrder::STATUS_CONFIRMED && $newStatus >= CustomerOrder::STATUS_PICKING) {
            return to_route('sales.customer-orders.index')->with('error', 'Order harus dikonfirmasi dulu sebelum masuk Picking.');
        }

        if ($newStatus <= $customerOrder->status) {
            return to_route('sales.customer-orders.index')->with('error', 'Perubahan status harus lebih maju dari status saat ini.');
        }

        $generatedInvoiceNumber = null;

        DB::transaction(function () use ($customerOrder, $newStatus, &$generatedInvoiceNumber): void {
            $customerOrder->update([
                'status' => $newStatus,
            ]);

            if ($newStatus !== CustomerOrder::STATUS_DELIVERED) {
                return;
            }

            $existingInvoice = Invoice::query()
                ->where('customer_order_id', $customerOrder->id)
                ->first();

            if ($existingInvoice instanceof Invoice) {
                return;
            }

            $customerOrder->loadMissing(['items.part']);

            $invoice = Invoice::query()->create([
                'invoice_number' => Invoice::generateNumber(),
                'customer_id' => $customerOrder->customer_id,
                'customer_order_id' => $customerOrder->id,
                'status' => Invoice::STATUS_DRAFT,
                'invoice_date' => $customerOrder->delivery_date ?? now()->toDateString(),
                'due_date' => $customerOrder->delivery_date,
                'currency_code' => strtoupper((string) ($customerOrder->currency_code ?: AppSetting::get('default_currency_code', 'IDR'))),
                'subtotal' => 0,
                'tax_amount' => 0,
                'total_amount' => 0,
                'notes' => 'Auto-generated from customer order ' . $customerOrder->co_number,
            ]);

            $subtotal = 0.0;

            foreach ($customerOrder->items as $item) {
                $lineTotal = (float) $item->line_total;
                $subtotal += $lineTotal;

                $description = trim((string) (
                    $item->part?->part_number && $item->part?->name
                        ? ($item->part->part_number . ' - ' . $item->part->name)
                        : ($item->part?->name ?? $item->part?->part_number ?? 'Item')
                ));

                InvoiceItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'part_id' => $item->part_id,
                    'description' => $description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $lineTotal,
                ]);
            }

            $taxAmount = round($subtotal * (float) AppSetting::get('tax_rate', 0) / 100, 2);

            $invoice->update([
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $subtotal + $taxAmount,
            ]);

            $generatedInvoiceNumber = $invoice->invoice_number;
        });

        if ($generatedInvoiceNumber !== null) {
            return to_route('sales.customer-orders.index')
                ->with('success', 'Status order berhasil diubah dan invoice otomatis dibuat: ' . $generatedInvoiceNumber . '.');
        }

        return to_route('sales.customer-orders.index');
    }

    public function undoReport(CustomerOrder $customerOrder): RedirectResponse
    {
        if ($customerOrder->status === CustomerOrder::STATUS_REGISTERED) {
            return to_route('sales.customer-orders.index')->with('error', 'Order masih di status Registered, tidak perlu undo.');
        }

        DB::transaction(function () use ($customerOrder): void {
            $invoiceIds = Invoice::query()
                ->where('customer_order_id', $customerOrder->id)
                ->pluck('id');

            if ($invoiceIds->isNotEmpty()) {
                InvoiceItem::query()
                    ->whereIn('invoice_id', $invoiceIds)
                    ->delete();

                Invoice::query()
                    ->whereIn('id', $invoiceIds)
                    ->delete();
            }

            $customerOrder->update([
                'status' => CustomerOrder::STATUS_REGISTERED,
            ]);
        });

        return to_route('sales.customer-orders.index')->with('success', 'Status order berhasil di-undo ke Registered. Invoice terkait juga berhasil dihapus.');
    }

    public function deliveryOrder(CustomerOrder $customerOrder): \Symfony\Component\HttpFoundation\Response|RedirectResponse
    {
        if ($customerOrder->status < CustomerOrder::STATUS_DELIVERED) {
            return to_route('sales.customer-orders.index')
                ->with('error', 'Delivery Order hanya tersedia jika status order sudah Delivered.');
        }

        $customerOrder->loadMissing(['customer', 'items.part']);

        $prefix = (string) AppSetting::get('do_prefix', 'DO');
        $yearMonth = $customerOrder->delivery_date?->format('Ym')
            ?? $customerOrder->order_date?->format('Ym')
            ?? now()->format('Ym');
        $seq = str_pad((string) $customerOrder->id, 5, '0', STR_PAD_LEFT);

        $documentNumber = sprintf('%s-%s-%s', strtoupper($prefix), $yearMonth, $seq);

        $pdf = Pdf::loadView('documents.delivery-order', [
            'order' => $customerOrder,
            'documentNumber' => $documentNumber,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("Delivery-Order-{$documentNumber}.pdf");
    }
}
