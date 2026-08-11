<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseOrder\StorePurchaseArrivalRequest;
use App\Http\Requests\PurchaseOrder\StorePurchaseOrderRequest;
use App\Models\AppSetting;
use App\Models\Bom;
use App\Models\Currency;
use App\Models\Part;
use App\Models\PurchaseArrival;
use App\Models\PurchaseArrivalItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Models\WorkOrderLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $statusFilter = $request->string('status')->toString();

        $purchaseOrders = PurchaseOrder::query()
            ->with(['supplier', 'items.part', 'workOrders'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('po_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function ($supplierQuery) use ($search): void {
                            $supplierQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('items.part', function ($partQuery) use ($search): void {
                            $partQuery->where('part_number', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($statusFilter !== '', function ($query) use ($statusFilter): void {
                $query->where('status', (int) $statusFilter);
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('purchase/po/Index', [
            'purchaseOrders' => collect($purchaseOrders->items())->map(fn (PurchaseOrder $purchaseOrder): array => [
                'id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'status' => $purchaseOrder->status,
                'approved_at' => $purchaseOrder->approved_at?->format('Y-m-d H:i'),
                'rejected_at' => $purchaseOrder->rejected_at?->format('Y-m-d H:i'),
                'approval_notes' => $purchaseOrder->approval_notes,
                'order_date' => $purchaseOrder->order_date?->format('Y-m-d'),
                'expected_date' => $purchaseOrder->expected_date?->format('Y-m-d'),
                'currency_code' => $purchaseOrder->currency_code,
                'subtotal' => (string) $purchaseOrder->subtotal,
                'projects' => $purchaseOrder->workOrders->pluck('wo_number')->values(),
                'supplier' => [
                    'id' => $purchaseOrder->supplier?->id,
                    'name' => $purchaseOrder->supplier?->name,
                ],
                'items' => $purchaseOrder->items->map(fn (PurchaseOrderItem $item): array => [
                    'id' => $item->id,
                    'part_number' => $item->part?->part_number,
                    'part_name' => $item->part?->name,
                    'quantity' => (string) $item->quantity,
                    'received_quantity' => (string) $item->received_quantity,
                    'unit' => $item->unit,
                    'unit_price' => (string) $item->unit_price,
                    'line_total' => (string) $item->line_total,
                ])->values(),
            ])->values(),
            'filters' => [
                'search' => $search,
                'status' => $statusFilter,
            ],
            'pagination' => [
                'current_page' => $purchaseOrders->currentPage(),
                'last_page' => $purchaseOrders->lastPage(),
                'per_page' => $purchaseOrders->perPage(),
                'total' => $purchaseOrders->total(),
                'from' => $purchaseOrders->firstItem(),
                'to' => $purchaseOrders->lastItem(),
                'prev_page_url' => $purchaseOrders->previousPageUrl(),
                'next_page_url' => $purchaseOrders->nextPageUrl(),
            ],
            'statusLabels' => PurchaseOrder::statusLabels(),
            'canManageApprovals' => $request->user() instanceof User ? $request->user()->canApprovePurchaseOrder() : false,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('purchase/po/Create', [
            'nextPoNumber' => PurchaseOrder::generateNumber(),
            'defaultCurrency' => (string) AppSetting::get('default_currency_code', 'IDR'),
            'currencies' => Currency::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['code', 'name'])
                ->map(fn (Currency $currency): array => [
                    'code' => $currency->code,
                    'name' => $currency->name,
                ])
                ->values(),
            'suppliers' => Supplier::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Supplier $supplier): array => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                ]),
            'parts' => Part::query()
                ->with('supplierPrices:id,part_id,supplier_id,purchase_price')
                ->orderBy('part_number')
                ->get(['id', 'part_number', 'name'])
                ->map(fn (Part $part): array => [
                    'id' => $part->id,
                    'part_number' => $part->part_number,
                    'name' => $part->name,
                    'supplier_prices' => $part->supplierPrices->map(fn ($price): array => [
                        'supplier_id' => (int) $price->supplier_id,
                        'purchase_price' => (float) $price->purchase_price,
                    ])->values(),
                ]),
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $purchaseOrder = PurchaseOrder::query()->create([
                'po_number' => PurchaseOrder::generateNumber(),
                'supplier_id' => $validated['supplier_id'],
                'status' => PurchaseOrder::STATUS_PENDING_APPROVAL,
                'order_date' => $validated['order_date'],
                'expected_date' => $validated['expected_date'] ?? null,
                'currency_code' => strtoupper((string) ($validated['currency_code'] ?? AppSetting::get('default_currency_code', 'IDR'))),
                'subtotal' => 0,
                'notes' => $validated['notes'] ?? null,
            ]);

            $subtotal = 0.0;

            foreach ($validated['lines'] as $line) {
                $quantity = (float) $line['quantity'];
                $unitPrice = (float) $line['unit_price'];
                $lineTotal = $quantity * $unitPrice;
                $subtotal += $lineTotal;

                PurchaseOrderItem::query()->create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'part_id' => $line['part_id'],
                    'quantity' => $quantity,
                    'unit' => strtoupper((string) ($line['unit'] ?? 'PCS')),
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'received_quantity' => 0,
                    'remarks' => $line['remarks'] ?? null,
                ]);
            }

            $purchaseOrder->update([
                'subtotal' => $subtotal,
            ]);
        });

        return to_route('purchase.po.index');
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->ensureManagementApprover($request);

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_PENDING_APPROVAL) {
            throw ValidationException::withMessages([
                'purchase_order' => 'PO tidak dalam status pending approval.',
            ]);
        }

        $validated = $request->validate([
            'approval_notes' => ['nullable', 'string'],
        ]);

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_APPROVED,
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
            'approval_notes' => $validated['approval_notes'] ?? null,
        ]);

        $generatedWorkOrders = $this->generateWorkOrdersFromPurchaseOrder($purchaseOrder, $request->user()?->id);

        if ($generatedWorkOrders !== []) {
            $generatedNumbers = collect($generatedWorkOrders)->pluck('wo_number')->implode(', ');

            return back()->with('success', 'PO berhasil di-approve dan MO otomatis dibuat: '.$generatedNumbers.'.');
        }

        return back()->with('success', 'PO berhasil di-approve oleh manajemen.');
    }

    public function reject(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->ensureManagementApprover($request);

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_PENDING_APPROVAL) {
            throw ValidationException::withMessages([
                'purchase_order' => 'PO tidak dalam status pending approval.',
            ]);
        }

        $validated = $request->validate([
            'approval_notes' => ['required', 'string'],
        ]);

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_REJECTED,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => $request->user()?->id,
            'rejected_at' => now(),
            'approval_notes' => $validated['approval_notes'],
        ]);

        return back()->with('success', 'PO berhasil di-reject oleh manajemen.');
    }

    public function destroy(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->ensurePurchaseOrderDeletable($purchaseOrder);

        $purchaseOrder->delete();

        return back()->with('success', 'PO berhasil dihapus.');
    }

    public function reportIndex(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $purchaseOrders = PurchaseOrder::query()
            ->with(['supplier', 'items.part'])
            ->whereIn('status', [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_PARTIAL])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('po_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function ($supplierQuery) use ($search): void {
                            $supplierQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('purchase/po/ReportArrival', [
            'purchaseOrders' => collect($purchaseOrders->items())->map(fn (PurchaseOrder $purchaseOrder): array => [
                'id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'status' => $purchaseOrder->status,
                'order_date' => $purchaseOrder->order_date?->format('Y-m-d'),
                'expected_date' => $purchaseOrder->expected_date?->format('Y-m-d'),
                'supplier' => [
                    'id' => $purchaseOrder->supplier?->id,
                    'name' => $purchaseOrder->supplier?->name,
                ],
                'items' => $purchaseOrder->items->map(fn (PurchaseOrderItem $item): array => [
                    'id' => $item->id,
                    'part_number' => $item->part?->part_number,
                    'part_name' => $item->part?->name,
                    'quantity' => (string) $item->quantity,
                    'received_quantity' => (string) $item->received_quantity,
                    'unit' => $item->unit,
                ])->values(),
            ])->values(),
            'filters' => [
                'search' => $search,
            ],
            'pagination' => [
                'current_page' => $purchaseOrders->currentPage(),
                'last_page' => $purchaseOrders->lastPage(),
                'per_page' => $purchaseOrders->perPage(),
                'total' => $purchaseOrders->total(),
                'from' => $purchaseOrders->firstItem(),
                'to' => $purchaseOrders->lastItem(),
                'prev_page_url' => $purchaseOrders->previousPageUrl(),
                'next_page_url' => $purchaseOrders->nextPageUrl(),
            ],
            'statusLabels' => PurchaseOrder::statusLabels(),
        ]);
    }

    public function reportForm(PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder->loadMissing(['supplier', 'items.part']);

        return Inertia::render('purchase/po/ReportForm', [
            'purchaseOrder' => [
                'id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'status' => $purchaseOrder->status,
                'order_date' => $purchaseOrder->order_date?->format('Y-m-d'),
                'expected_date' => $purchaseOrder->expected_date?->format('Y-m-d'),
                'supplier' => [
                    'id' => $purchaseOrder->supplier?->id,
                    'name' => $purchaseOrder->supplier?->name,
                ],
                'items' => $purchaseOrder->items->map(fn (PurchaseOrderItem $item): array => [
                    'id' => $item->id,
                    'part_id' => $item->part_id,
                    'part_number' => $item->part?->part_number,
                    'part_name' => $item->part?->name,
                    'quantity' => (string) $item->quantity,
                    'received_quantity' => (string) $item->received_quantity,
                    'remaining_quantity' => (string) max(0, (float) $item->quantity - (float) $item->received_quantity),
                    'unit' => $item->unit,
                ])->values(),
            ],
            'warehouses' => Warehouse::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn (Warehouse $warehouse): array => [
                    'id' => $warehouse->id,
                    'code' => $warehouse->code,
                    'name' => $warehouse->name,
                ]),
        ]);
    }

    public function submitArrival(StorePurchaseArrivalRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $validated = $request->validated();

        if (! in_array($purchaseOrder->status, [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_PARTIAL], true)) {
            throw ValidationException::withMessages([
                'purchase_order' => 'PO belum di-approve oleh manajemen, arrival tidak dapat diproses.',
            ]);
        }

        DB::transaction(function () use ($request, $purchaseOrder, $validated): void {
            $purchaseOrder->loadMissing('items');

            $itemsById = $purchaseOrder->items->keyBy('id');
            $arrivalLines = collect($validated['lines'])
                ->filter(fn (array $line): bool => (float) ($line['received_quantity'] ?? 0) > 0)
                ->values();

            if ($arrivalLines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => 'Minimal satu baris arrival harus memiliki quantity lebih dari 0.',
                ]);
            }

            $arrival = PurchaseArrival::query()->create([
                'purchase_order_id' => $purchaseOrder->id,
                'reported_by' => $request->user()?->id,
                'arrival_date' => $validated['arrival_date'],
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($arrivalLines as $index => $line) {
                $lineIndex = (int) $index;
                $poItem = $itemsById->get((int) $line['purchase_order_item_id']);

                if (! $poItem instanceof PurchaseOrderItem) {
                    throw ValidationException::withMessages([
                        "lines.{$lineIndex}.purchase_order_item_id" => 'Item PO tidak valid untuk dokumen ini.',
                    ]);
                }

                $receivedQty = (float) $line['received_quantity'];
                $remainingQty = max(0.0, (float) $poItem->quantity - (float) $poItem->received_quantity);

                if ($receivedQty > $remainingQty) {
                    throw ValidationException::withMessages([
                        "lines.{$lineIndex}.received_quantity" => 'Qty diterima melebihi sisa qty pada item PO.',
                    ]);
                }

                if (empty($line['warehouse_id'])) {
                    throw ValidationException::withMessages([
                        "lines.{$lineIndex}.warehouse_id" => 'Warehouse wajib dipilih untuk qty yang diterima.',
                    ]);
                }

                PurchaseArrivalItem::query()->create([
                    'purchase_arrival_id' => $arrival->id,
                    'purchase_order_item_id' => $poItem->id,
                    'part_id' => $poItem->part_id,
                    'warehouse_id' => $line['warehouse_id'],
                    'quantity' => $receivedQty,
                    'notes' => $line['notes'] ?? null,
                ]);

                $poItem->increment('received_quantity', $receivedQty);

                $stock = Stock::query()->firstOrCreate(
                    [
                        'part_id' => $poItem->part_id,
                        'warehouse_id' => (int) $line['warehouse_id'],
                    ],
                    [
                        'quantity' => 0,
                    ],
                );

                $stock->increment('quantity', (int) round($receivedQty));

                StockMovement::query()->create([
                    'part_id' => $poItem->part_id,
                    'warehouse_id' => (int) $line['warehouse_id'],
                    'work_order_id' => null,
                    'work_order_report_id' => null,
                    'movement_type' => 'purchase_arrival',
                    'quantity_change' => $receivedQty,
                    'notes' => 'Arrival from '.$purchaseOrder->po_number,
                ]);
            }

            $purchaseOrder->refresh()->loadMissing('items');
            $isCompleted = $purchaseOrder->items->every(fn (PurchaseOrderItem $item): bool => (float) $item->received_quantity >= (float) $item->quantity);

            $purchaseOrder->update([
                'status' => $isCompleted ? PurchaseOrder::STATUS_COMPLETED : PurchaseOrder::STATUS_PARTIAL,
            ]);
        });

        return to_route('purchase.po.arrivals')->with('success', 'Arrival report berhasil disimpan.');
    }

    public function logs(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $arrivals = PurchaseArrival::query()
            ->with(['purchaseOrder.supplier', 'reporter', 'items.part', 'items.warehouse'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->orWhereHas('purchaseOrder', function ($poQuery) use ($search): void {
                        $poQuery->where('po_number', 'like', "%{$search}%")
                            ->orWhereHas('supplier', function ($supplierQuery) use ($search): void {
                                $supplierQuery->where('name', 'like', "%{$search}%");
                            });
                    });
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('purchase/po/Logs', [
            'logs' => collect($arrivals->items())->map(fn (PurchaseArrival $arrival): array => [
                'id' => $arrival->id,
                'arrival_date' => $arrival->arrival_date?->format('Y-m-d'),
                'notes' => $arrival->notes,
                'reported_by' => $arrival->reporter?->name,
                'purchase_order' => [
                    'id' => $arrival->purchaseOrder?->id,
                    'po_number' => $arrival->purchaseOrder?->po_number,
                    'supplier_name' => $arrival->purchaseOrder?->supplier?->name,
                ],
                'items' => $arrival->items->map(fn (PurchaseArrivalItem $item): array => [
                    'id' => $item->id,
                    'part_number' => $item->part?->part_number,
                    'part_name' => $item->part?->name,
                    'warehouse_code' => $item->warehouse?->code,
                    'warehouse_name' => $item->warehouse?->name,
                    'quantity' => (string) $item->quantity,
                    'notes' => $item->notes,
                ])->values(),
            ])->values(),
            'filters' => [
                'search' => $search,
            ],
            'pagination' => [
                'current_page' => $arrivals->currentPage(),
                'last_page' => $arrivals->lastPage(),
                'per_page' => $arrivals->perPage(),
                'total' => $arrivals->total(),
                'from' => $arrivals->firstItem(),
                'to' => $arrivals->lastItem(),
                'prev_page_url' => $arrivals->previousPageUrl(),
                'next_page_url' => $arrivals->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Ensure only GM/Director can approve or reject PO.
     */
    private function ensureManagementApprover(Request $request): void
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->canApprovePurchaseOrder()) {
            throw new AuthorizationException('Hanya GM/Director yang dapat melakukan approval.');
        }
    }

    private function ensurePurchaseOrderDeletable(PurchaseOrder $purchaseOrder): void
    {
        if ($purchaseOrder->arrivals()->exists()) {
            throw ValidationException::withMessages([
                'purchase_order' => 'PO yang sudah memiliki arrival report tidak bisa dihapus.',
            ]);
        }

        if (! in_array($purchaseOrder->status, [
            PurchaseOrder::STATUS_PENDING_APPROVAL,
            PurchaseOrder::STATUS_REJECTED,
            PurchaseOrder::STATUS_CANCELLED,
        ], true)) {
            throw ValidationException::withMessages([
                'purchase_order' => 'PO hanya bisa dihapus saat masih pending, rejected, atau cancelled.',
            ]);
        }
    }

    /**
     * Auto-generate work orders from eligible PO items.
     *
     * @return array<int, WorkOrder>
     */
    private function generateWorkOrdersFromPurchaseOrder(PurchaseOrder $purchaseOrder, ?int $userId = null): array
    {
        $purchaseOrder->loadMissing(['items.part', 'workOrders']);

        $eligibleBoms = Bom::query()
            ->where('is_active', true)
            ->whereIn('part_id', $purchaseOrder->items->pluck('part_id')->filter()->unique()->values())
            ->get(['id', 'part_id'])
            ->keyBy('part_id');

        $createdWorkOrders = [];

        foreach ($purchaseOrder->items->groupBy('part_id') as $partId => $items) {
            $partIdInt = (int) $partId;
            $bom = $eligibleBoms->get($partIdInt);

            if (! $bom instanceof Bom) {
                continue;
            }

            if ($purchaseOrder->workOrders->contains('bom_id', $bom->id)) {
                continue;
            }

            $quantity = (float) $items->sum(fn (PurchaseOrderItem $item): float => (float) $item->quantity);

            if ($quantity <= 0) {
                continue;
            }

            $workOrder = WorkOrder::query()->create([
                'wo_number' => WorkOrder::generateNumber(),
                'bom_id' => $bom->id,
                'purchase_order_id' => $purchaseOrder->id,
                'quantity' => $quantity,
                'status' => 'draft',
                'scheduled_date' => $purchaseOrder->expected_date ?? $purchaseOrder->order_date,
                'notes' => 'Auto generated from '.$purchaseOrder->po_number.' for part #'.$partIdInt,
            ]);

            WorkOrderLog::query()->create([
                'work_order_id' => $workOrder->id,
                'user_id' => $userId,
                'log_type' => 'created',
                'title' => 'Manufacture order auto-created from PO',
                'description' => 'Manufacture order dibuat otomatis saat approval purchase order '.$purchaseOrder->po_number.'.',
                'metadata' => [
                    'source' => 'purchase_order_approval',
                    'purchase_order_id' => $purchaseOrder->id,
                    'purchase_order_number' => $purchaseOrder->po_number,
                    'status' => $workOrder->status,
                    'quantity' => (string) $workOrder->quantity,
                ],
            ]);

            $createdWorkOrders[] = $workOrder;
        }

        return $createdWorkOrders;
    }
}
