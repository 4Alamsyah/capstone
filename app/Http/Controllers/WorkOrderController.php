<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkOrder\ReportWorkOrderRequest;
use App\Http\Requests\WorkOrder\StoreWorkOrderRequest;
use App\Http\Requests\WorkOrder\UpdateWorkOrderRequest;
use App\Models\Bom;
use App\Models\BomItem;
use App\Models\Part;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Models\WorkOrderLog;
use App\Models\WorkOrderReport;
use App\Models\WorkOrderReportProduction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WorkOrderController extends Controller
{
    public function leadTimeTimeline(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $statusFilter = $request->string('status')->toString();

        $workOrders = WorkOrder::query()
            ->with([
                'bom.part',
                'purchaseOrder',
                'logs' => fn ($query) => $query->with('user')->oldest(),
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('wo_number', 'like', "%{$search}%")
                        ->orWhereHas('bom.part', function ($partQuery) use ($search): void {
                            $partQuery->where('part_number', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($statusFilter !== '', function ($query) use ($statusFilter): void {
                $query->where('status', $statusFilter);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('work-orders/LeadTime', [
            'workOrders' => collect($workOrders->items())->map(function (WorkOrder $workOrder): array {
                $startAt = $workOrder->created_at;
                $lastLogAt = $workOrder->logs->last()?->created_at;

                $endAt = in_array($workOrder->status, ['completed', 'cancelled'], true)
                    ? ($lastLogAt ?? $workOrder->updated_at)
                    : now();

                $leadTimeHours = max(0, round($startAt->diffInMinutes($endAt) / 60, 2));

                return [
                    'id' => $workOrder->id,
                    'wo_number' => $workOrder->wo_number,
                    'status' => $workOrder->status,
                    'status_label' => WorkOrder::statusLabels()[$workOrder->status] ?? $workOrder->status,
                    'product' => [
                        'part_number' => $workOrder->bom->part->part_number,
                        'name' => $workOrder->bom->part->name,
                        'bom_name' => $workOrder->bom->name,
                    ],
                    'source_po' => $workOrder->purchaseOrder?->po_number,
                    'created_at' => $startAt->format('Y-m-d H:i'),
                    'ended_at' => $endAt->format('Y-m-d H:i'),
                    'lead_time_hours' => $leadTimeHours,
                    'timeline' => $workOrder->logs->map(function (WorkOrderLog $log) use ($startAt): array {
                        $hoursFromStart = max(0, round($startAt->diffInMinutes($log->created_at) / 60, 2));

                        return [
                            'id' => $log->id,
                            'log_type' => $log->log_type,
                            'title' => $log->title,
                            'description' => $log->description,
                            'user_name' => $log->user?->name,
                            'created_at' => $log->created_at->format('Y-m-d H:i'),
                            'hours_from_start' => $hoursFromStart,
                        ];
                    })->values(),
                ];
            })->values(),
            'filters' => [
                'search' => $search,
                'status' => $statusFilter,
            ],
            'pagination' => [
                'current_page' => $workOrders->currentPage(),
                'last_page' => $workOrders->lastPage(),
                'per_page' => $workOrders->perPage(),
                'total' => $workOrders->total(),
                'from' => $workOrders->firstItem(),
                'to' => $workOrders->lastItem(),
                'prev_page_url' => $workOrders->previousPageUrl(),
                'next_page_url' => $workOrders->nextPageUrl(),
            ],
            'statusLabels' => WorkOrder::statusLabels(),
        ]);
    }

    /**
     * List work orders with search + pagination.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $statusFilter = $request->string('status')->toString();

        $workOrders = WorkOrder::query()
            ->with(['bom.part', 'purchaseOrder'])
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('wo_number', 'like', "%{$search}%")
                          ->orWhereHas('bom.part', function ($p) use ($search): void {
                              $p->where('name', 'like', "%{$search}%")
                                ->orWhere('part_number', 'like', "%{$search}%");
                          });
                });
            })
            ->when($statusFilter !== '', function ($q) use ($statusFilter): void {
                $q->where('status', $statusFilter);
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('work-orders/Index', [
            'workOrders' => collect($workOrders->items())->map(fn (WorkOrder $wo): array => [
                'id'             => $wo->id,
                'wo_number'      => $wo->wo_number,
                'status'         => $wo->status,
                'quantity'       => (string) $wo->quantity,
                'scheduled_date' => $wo->scheduled_date?->format('Y-m-d'),
                'notes'          => $wo->notes,
                'created_at'     => $wo->created_at->format('Y-m-d'),
                'purchase_order' => [
                    'id' => $wo->purchaseOrder?->id,
                    'po_number' => $wo->purchaseOrder?->po_number,
                ],
                'bom'            => [
                    'id'   => $wo->bom->id,
                    'name' => $wo->bom->name,
                    'part' => [
                        'id'          => $wo->bom->part->id,
                        'part_number' => $wo->bom->part->part_number,
                        'name'        => $wo->bom->part->name,
                    ],
                ],
            ])->values(),
            'filters' => [
                'search' => $search,
                'status' => $statusFilter,
            ],
            'pagination' => [
                'current_page'  => $workOrders->currentPage(),
                'last_page'     => $workOrders->lastPage(),
                'per_page'      => $workOrders->perPage(),
                'total'         => $workOrders->total(),
                'from'          => $workOrders->firstItem(),
                'to'            => $workOrders->lastItem(),
                'prev_page_url' => $workOrders->previousPageUrl(),
                'next_page_url' => $workOrders->nextPageUrl(),
            ],
            'statusLabels' => WorkOrder::statusLabels(),
        ]);
    }

    /**
     * List work orders that can be reported.
     */
    public function reportIndex(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $statusFilter = $request->string('status')->toString();

        $workOrders = WorkOrder::query()
            ->with('bom.part')
            ->withCount('reports')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('wo_number', 'like', "%{$search}%")
                        ->orWhereHas('bom.part', function ($partQuery) use ($search): void {
                            $partQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('part_number', 'like', "%{$search}%");
                        });
                });
            })
            ->when($statusFilter !== '', function ($query) use ($statusFilter): void {
                $query->where('status', $statusFilter);
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('work-orders/Report', [
            'workOrders' => collect($workOrders->items())->map(fn (WorkOrder $workOrder): array => [
                'id' => $workOrder->id,
                'wo_number' => $workOrder->wo_number,
                'status' => $workOrder->status,
                'quantity' => (string) $workOrder->quantity,
                'reports_count' => (int) $workOrder->reports_count,
                'bom' => [
                    'id' => $workOrder->bom->id,
                    'name' => $workOrder->bom->name,
                    'part' => [
                        'part_number' => $workOrder->bom->part->part_number,
                        'name' => $workOrder->bom->part->name,
                    ],
                ],
            ])->values(),
            'filters' => [
                'search' => $search,
                'status' => $statusFilter,
            ],
            'pagination' => [
                'current_page' => $workOrders->currentPage(),
                'last_page' => $workOrders->lastPage(),
                'per_page' => $workOrders->perPage(),
                'total' => $workOrders->total(),
                'from' => $workOrders->firstItem(),
                'to' => $workOrders->lastItem(),
                'prev_page_url' => $workOrders->previousPageUrl(),
                'next_page_url' => $workOrders->nextPageUrl(),
            ],
            'statusLabels' => WorkOrder::statusLabels(),
        ]);
    }

    /**
     * Show create form.
     */
    public function create(): Response
    {
        return Inertia::render('work-orders/Create', [
            'boms'          => Bom::query()->where('is_active', true)->with('part')->get()->map(fn (Bom $bom): array => [
                'id'   => $bom->id,
                'name' => $bom->name,
                'part' => [
                    'id'          => $bom->part->id,
                    'part_number' => $bom->part->part_number,
                    'name'        => $bom->part->name,
                ],
            ]),
            'nextWoNumber'  => WorkOrder::generateNumber(),
        ]);
    }

    /**
     * Store a new work order.
     */
    public function store(StoreWorkOrderRequest $request): RedirectResponse
    {
        $wo = WorkOrder::create([
            'wo_number'      => WorkOrder::generateNumber(),
            'bom_id'         => $request->validated()['bom_id'],
            'quantity'       => $request->validated()['quantity'],
            'status'         => 'draft',
            'scheduled_date' => $request->validated()['scheduled_date'] ?? null,
            'notes'          => $request->validated()['notes'] ?? null,
        ]);

        $this->logWorkOrder(
            $wo,
            'created',
            'Manufacture order created',
            'Manufacture order created from selected BOM.',
            [
                'status' => $wo->status,
                'quantity' => $wo->quantity,
            ],
            $request->user()?->id,
        );

        return to_route('work-orders.show', $wo->id);
    }

    /**
     * Show work order reporting page.
     */
    public function report(WorkOrder $workOrder): Response
    {
        $workOrder->load([
            'bom.part',
            'bom.items.componentPart.stocks.warehouse',
            'bom.items.workCenter',
            'reports.reporter',
        ]);

        $componentRequirements = $this->buildComponentRequirements($workOrder->bom->items, (float) $workOrder->quantity);

        return Inertia::render('work-orders/ReportForm', [
            'workOrder' => [
                'id' => $workOrder->id,
                'wo_number' => $workOrder->wo_number,
                'status' => $workOrder->status,
                'quantity' => (string) $workOrder->quantity,
                'scheduled_date' => $workOrder->scheduled_date?->format('Y-m-d'),
                'bom' => [
                    'id' => $workOrder->bom->id,
                    'name' => $workOrder->bom->name,
                    'part' => [
                        'part_number' => $workOrder->bom->part->part_number,
                        'name' => $workOrder->bom->part->name,
                    ],
                ],
            ],
            'components' => $componentRequirements,
            'warehouses' => Warehouse::query()->orderBy('code')->get(['id', 'code', 'name']),
            'reportedGoodQuantity' => (string) $workOrder->reports->sum('good_quantity'),
            'recentReports' => $workOrder->reports->take(10)->map(fn (WorkOrderReport $report): array => [
                'id' => $report->id,
                'previous_status' => $report->previous_status,
                'new_status' => $report->new_status,
                'good_quantity' => (string) $report->good_quantity,
                'reject_quantity' => (string) $report->reject_quantity,
                'notes' => $report->notes,
                'reported_by' => $report->reporter?->name,
                'created_at' => $report->created_at->format('Y-m-d H:i'),
            ])->values(),
            'statusLabels' => WorkOrder::statusLabels(),
        ]);
    }

    /**
     * Submit work order report and consume stock.
     */
    public function submitReport(ReportWorkOrderRequest $request, WorkOrder $workOrder): RedirectResponse
    {
        $validated = $request->validated();

        $workOrder->loadMissing('bom.items.componentPart');

        $shortfalls = $this->findStockDrivenShortfalls(
            $workOrder->bom->items,
            $validated['components'] ?? [],
            $request->user()?->id,
        );

        if ($shortfalls !== []) {
            $summary = collect($shortfalls)
                ->map(fn (array $s): string => "{$s['part']->part_number} (MO {$s['work_order']->wo_number})")
                ->implode(', ');

            throw ValidationException::withMessages([
                'components' => "Stock tidak cukup untuk: {$summary}. MO replenishment sudah dibuat/tersedia - selesaikan MO tersebut dulu sebelum melanjutkan report ini.",
            ]);
        }

        DB::transaction(function () use ($request, $validated, $workOrder): void {
            $previousStatus = $workOrder->status;
            $reportedGoodQuantity = (float) $workOrder->reports()->sum('good_quantity');
            $totalGoodQuantity = $reportedGoodQuantity + (float) $validated['good_quantity'];
            $plannedQuantity = (float) $workOrder->quantity;

            $newStatus = match (true) {
                $plannedQuantity > 0 && $totalGoodQuantity >= $plannedQuantity => 'completed',
                $totalGoodQuantity > 0 => 'in_progress',
                default => $previousStatus,
            };

            $report = WorkOrderReport::create([
                'work_order_id' => $workOrder->id,
                'reported_by' => $request->user()?->id,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'good_quantity' => $validated['good_quantity'],
                'reject_quantity' => $validated['reject_quantity'] ?? 0,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->processComponentTree(
                $workOrder->bom->items,
                $validated['components'] ?? [],
                $workOrder,
                $report,
                $request->user()?->id,
            );

            $workOrder->update([
                'status' => $newStatus,
                'notes' => $validated['notes'] ?? $workOrder->notes,
            ]);

            $this->logWorkOrder(
                $workOrder,
                'reported',
                'Manufacture order reported',
                sprintf(
                    'Status changed from %s to %s. Good qty: %s, reject qty: %s.',
                    WorkOrder::statusLabels()[$report->previous_status] ?? $report->previous_status,
                    WorkOrder::statusLabels()[$report->new_status] ?? $report->new_status,
                    $report->good_quantity,
                    $report->reject_quantity,
                ),
                [
                    'report_id' => $report->id,
                    'previous_status' => $report->previous_status,
                    'new_status' => $report->new_status,
                ],
                $request->user()?->id,
            );
        });

        return to_route('work-orders.report.form', $workOrder->id);
    }

    /**
     * Show a single work order.
     */
    public function show(WorkOrder $workOrder): Response
    {
        $workOrder->load(['bom.part', 'bom.items.componentPart', 'bom.items.workCenter', 'purchaseOrder', 'reports']);

        return Inertia::render('work-orders/Show', [
            'workOrder' => [
                'id'             => $workOrder->id,
                'wo_number'      => $workOrder->wo_number,
                'status'         => $workOrder->status,
                'quantity'       => (string) $workOrder->quantity,
                'scheduled_date' => $workOrder->scheduled_date?->format('Y-m-d'),
                'notes'          => $workOrder->notes,
                'created_at'     => $workOrder->created_at->format('Y-m-d H:i'),
                'purchase_order' => [
                    'id' => $workOrder->purchaseOrder?->id,
                    'po_number' => $workOrder->purchaseOrder?->po_number,
                ],
                'reports_count' => $workOrder->reports->count(),
                'bom'            => [
                    'id'   => $workOrder->bom->id,
                    'name' => $workOrder->bom->name,
                    'part' => [
                        'id'          => $workOrder->bom->part->id,
                        'part_number' => $workOrder->bom->part->part_number,
                        'name'        => $workOrder->bom->part->name,
                    ],
                    'items' => $workOrder->bom->items->map(fn ($item): array => [
                        'id'        => $item->id,
                        'line_type' => $item->line_type,
                        'quantity'  => (string) $item->quantity,
                        'notes'     => $item->notes,
                        'label'     => $item->line_type === 'part'
                            ? ($item->componentPart?->part_number . ' – ' . $item->componentPart?->name)
                            : $item->workCenter?->name,
                    ])->values(),
                ],
            ],
            'statusLabels' => WorkOrder::statusLabels(),
        ]);
    }

    /**
     * Update status / editable fields.
     */
    public function update(UpdateWorkOrderRequest $request, WorkOrder $workOrder): RedirectResponse
    {
        $before = $workOrder->only(['status', 'quantity', 'scheduled_date', 'notes']);

        $workOrder->update($request->validated());

        $changes = array_keys($workOrder->getChanges());

        if ($changes !== []) {
            $this->logWorkOrder(
                $workOrder,
                'updated',
                'Manufacture order updated',
                'Updated fields: '.implode(', ', $changes),
                [
                    'before' => $before,
                    'after' => $workOrder->only(['status', 'quantity', 'scheduled_date', 'notes']),
                ],
                $request->user()?->id,
            );
        }

        return to_route('work-orders.show', $workOrder->id);
    }

    /**
     * Show work order log page.
     */
    public function logs(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $logs = WorkOrderLog::query()
            ->with(['workOrder.bom.part', 'user'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('workOrder', function ($workOrderQuery) use ($search): void {
                            $workOrderQuery->where('wo_number', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('work-orders/Log', [
            'logs' => collect($logs->items())->map(fn (WorkOrderLog $log): array => [
                'id' => $log->id,
                'log_type' => $log->log_type,
                'title' => $log->title,
                'description' => $log->description,
                'created_at' => $log->created_at->format('Y-m-d H:i'),
                'user_name' => $log->user?->name,
                'work_order' => [
                    'id' => $log->workOrder?->id,
                    'wo_number' => $log->workOrder?->wo_number,
                    'part_number' => $log->workOrder?->bom?->part?->part_number,
                    'part_name' => $log->workOrder?->bom?->part?->name,
                ],
            ])->values(),
            'filters' => [
                'search' => $search,
            ],
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
                'prev_page_url' => $logs->previousPageUrl(),
                'next_page_url' => $logs->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Delete a work order.
     */
    public function destroy(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $workOrder->delete();

        return to_route('work-orders.index', $request->only('search', 'status', 'page'));
    }

    private function logWorkOrder(
        WorkOrder $workOrder,
        string $logType,
        string $title,
        ?string $description = null,
        ?array $metadata = null,
        ?int $userId = null,
    ): void {
        WorkOrderLog::create([
            'work_order_id' => $workOrder->id,
            'user_id' => $userId,
            'log_type' => $logType,
            'title' => $title,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Recursively produce (for sub-assemblies) and consume (for raw materials)
     * a BOM's part items for a report submission. Walks the authoritative BOM
     * structure server-side — client input only supplies quantities, keyed by
     * bom_item_id, so the structure itself can't be tampered with.
     *
     * @param  Collection<int, BomItem>  $bomItems
     * @param  array<int|string, array<string, mixed>>  $inputs
     * @param  list<int>  $visited
     */
    private function processComponentTree(
        Collection $bomItems,
        array $inputs,
        WorkOrder $workOrder,
        WorkOrderReport $report,
        ?int $userId,
        array $visited = [],
    ): void {
        foreach ($bomItems as $bomItem) {
            if ($bomItem->line_type !== 'part' || ! $bomItem->componentPart) {
                continue;
            }

            $part = $bomItem->componentPart;
            $input = $inputs[$bomItem->id] ?? [];
            $subBom = in_array($part->id, $visited, true) ? null : $part->activeBom();
            $isOrderOriented = $subBom !== null && $subBom->planning_strategy === Bom::PLANNING_STRATEGY_ORDER_ORIENTED;

            if ($isOrderOriented) {
                $goodQty = (int) round((float) ($input['good_quantity'] ?? 0));
                $rejectQty = (int) round((float) ($input['reject_quantity'] ?? 0));

                if ($goodQty <= 0 && $rejectQty <= 0) {
                    continue;
                }

                if (empty($input['warehouse_id'])) {
                    throw ValidationException::withMessages([
                        "components.{$bomItem->id}.warehouse_id" => "Warehouse wajib dipilih untuk produksi {$part->part_number}.",
                    ]);
                }

                $warehouseId = (int) $input['warehouse_id'];

                // Produce this sub-assembly's own components first.
                $this->processComponentTree(
                    $subBom->loadMissing('items.componentPart')->items,
                    $inputs,
                    $workOrder,
                    $report,
                    $userId,
                    [...$visited, $part->id],
                );

                $stock = Stock::query()->firstOrCreate(
                    ['part_id' => $part->id, 'warehouse_id' => $warehouseId],
                    ['quantity' => 0],
                );

                $stock->increment('quantity', $goodQty);

                StockMovement::create([
                    'part_id' => $part->id,
                    'warehouse_id' => $warehouseId,
                    'work_order_id' => $workOrder->id,
                    'work_order_report_id' => $report->id,
                    'movement_type' => 'produce',
                    'quantity_change' => $goodQty,
                    'notes' => 'Produced for '.$workOrder->wo_number,
                ]);

                WorkOrderReportProduction::create([
                    'work_order_report_id' => $report->id,
                    'work_order_id' => $workOrder->id,
                    'bom_item_id' => $bomItem->id,
                    'part_id' => $part->id,
                    'bom_id' => $subBom->id,
                    'warehouse_id' => $warehouseId,
                    'good_quantity' => $goodQty,
                    'reject_quantity' => $rejectQty,
                ]);

                // Immediately consumed into the parent ("produce lalu consume").
                $stock->decrement('quantity', $goodQty);

                StockMovement::create([
                    'part_id' => $part->id,
                    'warehouse_id' => $warehouseId,
                    'work_order_id' => $workOrder->id,
                    'work_order_report_id' => $report->id,
                    'movement_type' => 'consume',
                    'quantity_change' => -$goodQty,
                    'notes' => 'Consumed by '.$workOrder->wo_number,
                ]);

                $this->logWorkOrder(
                    $workOrder,
                    'sub_assembly_produced',
                    'Sub-assembly produced',
                    sprintf(
                        'Produced %d (reject %d) of %s - %s using BOM "%s", then consumed into parent.',
                        $goodQty,
                        $rejectQty,
                        $part->part_number,
                        $part->name,
                        $subBom->name,
                    ),
                    [
                        'part_id' => $part->id,
                        'warehouse_id' => $warehouseId,
                        'good_quantity' => $goodQty,
                        'reject_quantity' => $rejectQty,
                    ],
                    $userId,
                );

                continue;
            }

            $quantity = (int) ($input['quantity'] ?? 0);

            if ($quantity <= 0) {
                continue;
            }

            if (empty($input['warehouse_id'])) {
                throw ValidationException::withMessages([
                    "components.{$bomItem->id}.warehouse_id" => 'Warehouse wajib dipilih untuk konsumsi stock.',
                ]);
            }

            $stock = Stock::query()
                ->with('part:id,inventory_type')
                ->where('part_id', $part->id)
                ->where('warehouse_id', $input['warehouse_id'])
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                throw ValidationException::withMessages([
                    "components.{$bomItem->id}.warehouse_id" => 'Stock part pada warehouse tersebut tidak ditemukan.',
                ]);
            }

            if ($stock->quantity < $quantity) {
                throw ValidationException::withMessages([
                    "components.{$bomItem->id}.quantity" => 'Stock tidak cukup untuk dikonsumsi dari warehouse tersebut.',
                ]);
            }

            if ($stock->part?->inventory_type === Part::INVENTORY_TYPE_TOOL) {
                throw ValidationException::withMessages([
                    "components.{$bomItem->id}.quantity" => 'Part dengan tipe tool tidak boleh dikonsumsi. Gunakan alur peminjaman tools.',
                ]);
            }

            $stock->decrement('quantity', $quantity);

            StockMovement::create([
                'part_id' => $stock->part_id,
                'warehouse_id' => $stock->warehouse_id,
                'work_order_id' => $workOrder->id,
                'work_order_report_id' => $report->id,
                'movement_type' => 'consume',
                'quantity_change' => -$quantity,
                'notes' => 'Consumed by '.$workOrder->wo_number,
            ]);

            $this->logWorkOrder(
                $workOrder,
                'stock_consumed',
                'Stock consumed',
                sprintf(
                    'Consumed %d of %s - %s from warehouse #%d.',
                    $quantity,
                    $part->part_number,
                    $part->name,
                    $stock->warehouse_id,
                ),
                [
                    'part_id' => $stock->part_id,
                    'warehouse_id' => $stock->warehouse_id,
                    'quantity' => $quantity,
                ],
                $userId,
            );
        }
    }

    /**
     * Walk the BOM tree looking for components that won't have enough stock
     * for what's being reported. Order-oriented sub-assemblies are descended
     * into (their materials are handled inline within this same report
     * submission), everything else — raw materials and stock-driven
     * sub-assemblies alike — is checked directly against current stock. A
     * shortfall on a stock-driven part auto-generates (or reuses) a
     * replenishment work order for it.
     *
     * @param  Collection<int, BomItem>  $bomItems
     * @param  array<int|string, array<string, mixed>>  $inputs
     * @param  list<int>  $visited
     * @return list<array{part: Part, work_order: WorkOrder}>
     */
    private function findStockDrivenShortfalls(Collection $bomItems, array $inputs, ?int $userId, array $visited = []): array
    {
        $shortfalls = [];

        foreach ($bomItems as $bomItem) {
            if ($bomItem->line_type !== 'part' || ! $bomItem->componentPart) {
                continue;
            }

            $part = $bomItem->componentPart;
            $input = $inputs[$bomItem->id] ?? [];
            $subBom = in_array($part->id, $visited, true) ? null : $part->activeBom();
            $isOrderOriented = $subBom !== null && $subBom->planning_strategy === Bom::PLANNING_STRATEGY_ORDER_ORIENTED;

            if ($isOrderOriented) {
                $goodQty = (int) round((float) ($input['good_quantity'] ?? 0));
                $rejectQty = (int) round((float) ($input['reject_quantity'] ?? 0));

                if ($goodQty <= 0 && $rejectQty <= 0) {
                    continue;
                }

                $shortfalls = [
                    ...$shortfalls,
                    ...$this->findStockDrivenShortfalls(
                        $subBom->loadMissing('items.componentPart')->items,
                        $inputs,
                        $userId,
                        [...$visited, $part->id],
                    ),
                ];

                continue;
            }

            $requestedQty = (int) ($input['quantity'] ?? 0);

            if ($requestedQty <= 0 || empty($input['warehouse_id']) || ! $subBom || $subBom->planning_strategy !== Bom::PLANNING_STRATEGY_STOCK_DRIVEN) {
                continue;
            }

            $availableQty = (int) (Stock::query()
                ->where('part_id', $part->id)
                ->where('warehouse_id', $input['warehouse_id'])
                ->value('quantity') ?? 0);

            if ($availableQty >= $requestedQty) {
                continue;
            }

            $shortfalls[] = [
                'part' => $part,
                'work_order' => $this->maybeGenerateReplenishmentWorkOrder($part, $subBom, $requestedQty - $availableQty, $userId),
            ];
        }

        return $shortfalls;
    }

    /**
     * Get (or auto-create) an open replenishment work order for a stock-driven
     * part that's come up short. Reuses an already-open replenishment WO
     * instead of spawning duplicates. The generated quantity covers at least
     * the immediate shortfall, and the part's safety stock if that's larger.
     */
    private function maybeGenerateReplenishmentWorkOrder(Part $part, Bom $bom, int $shortfallQty, ?int $userId): WorkOrder
    {
        $notesMarker = 'Auto generated (stock driven replenishment)';

        $existing = WorkOrder::query()
            ->where('bom_id', $bom->id)
            ->whereIn('status', ['draft', 'released', 'in_progress'])
            ->where('notes', 'like', $notesMarker.'%')
            ->first();

        if ($existing) {
            return $existing;
        }

        $quantity = max($shortfallQty, (int) $part->safety_stock, 1);

        $workOrder = WorkOrder::create([
            'wo_number' => WorkOrder::generateNumber(),
            'bom_id' => $bom->id,
            'quantity' => $quantity,
            'status' => 'draft',
            'notes' => $notesMarker.' - stock '.$part->part_number.' tidak cukup.',
        ]);

        $this->logWorkOrder(
            $workOrder,
            'created',
            'Replenishment MO auto-created',
            sprintf(
                'MO dibuat otomatis karena stock %s - %s tidak cukup (kurang %d).',
                $part->part_number,
                $part->name,
                $shortfallQty,
            ),
            [
                'source' => 'stock_driven_replenishment',
                'part_id' => $part->id,
                'shortfall_quantity' => $shortfallQty,
                'quantity' => $quantity,
            ],
            $userId,
        );

        return $workOrder;
    }

    /**
     * Recursively build the component requirement tree for a BOM's part items.
     * When a component part is itself a sub-assembly with an *order oriented*
     * active BOM, its own components are expanded underneath it so they can be
     * produced and reported inline within the same work order. Sub-assemblies
     * whose BOM is *stock driven* are treated as leaves here — their materials
     * are that other (separately reported) work order's concern.
     *
     * @param  Collection<int, BomItem>  $bomItems
     * @param  list<int>  $visited
     * @return list<array<string, mixed>>
     */
    private function buildComponentRequirements(Collection $bomItems, float $parentQuantity, array $visited = []): array
    {
        return $bomItems
            ->filter(fn (BomItem $item): bool => $item->line_type === 'part' && $item->componentPart !== null)
            ->map(function (BomItem $item) use ($parentQuantity, $visited): array {
                $part = $item->componentPart;
                $recommendedQuantity = (int) max(0, round($parentQuantity * (float) $item->quantity));
                $subBom = in_array($part->id, $visited, true) ? null : $part->activeBom();
                $isOrderOriented = $subBom !== null && $subBom->planning_strategy === Bom::PLANNING_STRATEGY_ORDER_ORIENTED;

                if ($isOrderOriented) {
                    $subBom->load(['items.componentPart.stocks.warehouse', 'items.workCenter']);
                }

                $node = [
                    'bom_item_id' => $item->id,
                    'part_id' => $part->id,
                    'part_number' => $part->part_number,
                    'part_name' => $part->name,
                    'bom_quantity' => (string) $item->quantity,
                    'recommended_quantity' => $recommendedQuantity,
                    'is_sub_assembly' => $isOrderOriented,
                    'bom_id' => $subBom?->id,
                    'bom_name' => $subBom?->name,
                    'planning_strategy' => $subBom?->planning_strategy,
                    'stocks' => $part->stocks
                        ->map(fn (Stock $stock): array => [
                            'warehouse_id' => $stock->warehouse_id,
                            'warehouse_code' => $stock->warehouse?->code,
                            'warehouse_name' => $stock->warehouse?->name,
                            'quantity' => (int) $stock->quantity,
                        ])
                        ->values(),
                    'children' => $isOrderOriented
                        ? $this->buildComponentRequirements($subBom->items, $recommendedQuantity, [...$visited, $part->id])
                        : [],
                ];

                if ($subBom && $subBom->planning_strategy === Bom::PLANNING_STRATEGY_STOCK_DRIVEN) {
                    $node['safety_stock'] = (int) $part->safety_stock;
                    $node['total_stock'] = (int) $part->stocks->sum('quantity');
                }

                return $node;
            })
            ->values()
            ->all();
    }
}
