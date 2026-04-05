<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkOrder\ReportWorkOrderRequest;
use App\Http\Requests\WorkOrder\StoreWorkOrderRequest;
use App\Http\Requests\WorkOrder\UpdateWorkOrderRequest;
use App\Models\Bom;
use App\Models\BomItem;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\WorkOrder;
use App\Models\WorkOrderLog;
use App\Models\WorkOrderReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WorkOrderController extends Controller
{
    /**
     * List work orders with search + pagination.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $statusFilter = $request->string('status')->toString();

        $workOrders = WorkOrder::query()
            ->with('bom.part')
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
            'Work order created',
            'Work order created from selected BOM.',
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

        $componentRequirements = $workOrder->bom->items
            ->filter(fn (BomItem $item): bool => $item->line_type === 'part' && $item->componentPart !== null)
            ->map(function (BomItem $item) use ($workOrder): array {
                $recommendedQuantity = (int) max(0, round((float) $workOrder->quantity * (float) $item->quantity));

                return [
                    'bom_item_id' => $item->id,
                    'part_id' => $item->componentPart->id,
                    'part_number' => $item->componentPart->part_number,
                    'part_name' => $item->componentPart->name,
                    'bom_quantity' => (string) $item->quantity,
                    'recommended_quantity' => $recommendedQuantity,
                    'stocks' => $item->componentPart->stocks
                        ->map(fn (Stock $stock): array => [
                            'warehouse_id' => $stock->warehouse_id,
                            'warehouse_code' => $stock->warehouse?->code,
                            'warehouse_name' => $stock->warehouse?->name,
                            'quantity' => (int) $stock->quantity,
                        ])
                        ->values(),
                ];
            })
            ->values();

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

        DB::transaction(function () use ($request, $validated, $workOrder): void {
            $workOrder->loadMissing('bom.items.componentPart');

            $report = WorkOrderReport::create([
                'work_order_id' => $workOrder->id,
                'reported_by' => $request->user()?->id,
                'previous_status' => $workOrder->status,
                'new_status' => $validated['new_status'],
                'good_quantity' => $validated['good_quantity'],
                'reject_quantity' => $validated['reject_quantity'] ?? 0,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['consumptions'] ?? [] as $index => $consumption) {
                $quantity = (int) ($consumption['quantity'] ?? 0);

                if ($quantity <= 0) {
                    continue;
                }

                if (empty($consumption['warehouse_id'])) {
                    throw ValidationException::withMessages([
                        "consumptions.{$index}.warehouse_id" => 'Warehouse wajib dipilih untuk konsumsi stock.',
                    ]);
                }

                $bomItem = $workOrder->bom->items->firstWhere('id', $consumption['bom_item_id']);

                if (! $bomItem instanceof BomItem || $bomItem->line_type !== 'part' || (int) $bomItem->component_part_id !== (int) $consumption['part_id']) {
                    throw ValidationException::withMessages([
                        "consumptions.{$index}.quantity" => 'Baris konsumsi tidak valid untuk work order ini.',
                    ]);
                }

                $stock = Stock::query()
                    ->where('part_id', $consumption['part_id'])
                    ->where('warehouse_id', $consumption['warehouse_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $stock) {
                    throw ValidationException::withMessages([
                        "consumptions.{$index}.warehouse_id" => 'Stock part pada warehouse tersebut tidak ditemukan.',
                    ]);
                }

                if ($stock->quantity < $quantity) {
                    throw ValidationException::withMessages([
                        "consumptions.{$index}.quantity" => 'Stock tidak cukup untuk dikonsumsi dari warehouse tersebut.',
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
                        $bomItem->componentPart?->part_number,
                        $bomItem->componentPart?->name,
                        $stock->warehouse_id,
                    ),
                    [
                        'part_id' => $stock->part_id,
                        'warehouse_id' => $stock->warehouse_id,
                        'quantity' => $quantity,
                    ],
                    $request->user()?->id,
                );
            }

            $workOrder->update([
                'status' => $validated['new_status'],
                'notes' => $validated['notes'] ?? $workOrder->notes,
            ]);

            $this->logWorkOrder(
                $workOrder,
                'reported',
                'Work order reported',
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
        $workOrder->load(['bom.part', 'bom.items.componentPart', 'bom.items.workCenter', 'reports']);

        return Inertia::render('work-orders/Show', [
            'workOrder' => [
                'id'             => $workOrder->id,
                'wo_number'      => $workOrder->wo_number,
                'status'         => $workOrder->status,
                'quantity'       => (string) $workOrder->quantity,
                'scheduled_date' => $workOrder->scheduled_date?->format('Y-m-d'),
                'notes'          => $workOrder->notes,
                'created_at'     => $workOrder->created_at->format('Y-m-d H:i'),
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
                'Work order updated',
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
}
