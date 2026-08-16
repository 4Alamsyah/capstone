<?php

namespace App\Http\Controllers;

use App\Http\Requests\Part\StorePartRequest;
use App\Http\Requests\Part\UpdatePartRequest;
use App\Models\Currency;
use App\Models\Part;
use App\Models\PartSupplierPrice;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\ToolLoan;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PartController extends Controller
{
    /**
     * Show list of parts and supplier prices.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $parts = Part::query()
            ->with(['supplierPrices.supplier'])
            ->withSum('stocks as total_stock', 'quantity')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('part_number', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('supplierPrices.supplier', function ($supplierQuery) use ($search): void {
                            $supplierQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $partItems = collect($parts->items())->map(function (Part $part): array {
                return [
                    'id' => $part->id,
                    'part_number' => $part->part_number,
                    'name' => $part->name,
                    'category' => $part->category,
                    'inventory_type' => $part->inventory_type,
                    'description' => $part->description,
                    'selling_price' => (float) $part->selling_price,
                    'safety_stock' => (int) $part->safety_stock,
                    'total_stock' => (int) ($part->total_stock ?? 0),
                    'suppliers' => $part->supplierPrices->map(function (PartSupplierPrice $price): array {
                        return [
                            'id' => $price->id,
                            'supplier_id' => $price->supplier_id,
                            'supplier_name' => $price->supplier?->name,
                            'purchase_price' => (float) $price->purchase_price,
                        ];
                    })->values(),
                    'stocks' => $part->stocks()
                        ->with('warehouse:id,code,name')
                        ->get()
                        ->map(function (Stock $stock): array {
                            return [
                                'id' => $stock->id,
                                'warehouse_id' => $stock->warehouse_id,
                                'warehouse_code' => $stock->warehouse?->code,
                                'warehouse_name' => $stock->warehouse?->name,
                                'quantity' => (int) $stock->quantity,
                            ];
                        })->values(),
                ];
            })->values();

        return Inertia::render('parts/List', [
            'parts' => $partItems,
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'code', 'name']),
            'defaultCurrency' => Currency::currentDefault(),
            'filters' => [
                'search' => $search,
            ],
            'pagination' => [
                'current_page' => $parts->currentPage(),
                'last_page' => $parts->lastPage(),
                'per_page' => $parts->perPage(),
                'total' => $parts->total(),
                'from' => $parts->firstItem(),
                'to' => $parts->lastItem(),
                'prev_page_url' => $parts->previousPageUrl(),
                'next_page_url' => $parts->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Show part registration page.
     */
    public function create(): Response
    {
        return Inertia::render('parts/Register', [
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'code', 'name', 'location']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'defaultCurrency' => Currency::currentDefault(),
        ]);
    }

    /**
     * Store a new part along with supplier prices and initial stock.
     */
    public function store(StorePartRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $part = Part::query()->create([
                'part_number' => $validated['part_number'],
                'name' => $validated['name'],
                'category' => $validated['category'],
                'inventory_type' => $validated['inventory_type'],
                'description' => $validated['description'] ?? null,
                'selling_price' => $validated['selling_price'],
                'safety_stock' => $validated['safety_stock'],
            ]);

            collect($validated['suppliers'] ?? [])
                ->unique('supplier_id')
                ->each(function (array $supplierRow) use ($part): void {
                    PartSupplierPrice::query()->create([
                        'part_id' => $part->id,
                        'supplier_id' => $supplierRow['supplier_id'],
                        'purchase_price' => $supplierRow['purchase_price'],
                    ]);
                });

            $stockRows = collect($validated['stocks'] ?? [])
                ->filter(fn (array $stock): bool => (int) $stock['quantity'] > 0)
                ->groupBy('warehouse_id')
                ->map(fn (Collection $rows): int => $rows->sum('quantity'));

            foreach ($stockRows as $warehouseId => $quantity) {
                Stock::query()->create([
                    'part_id' => $part->id,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $quantity,
                ]);
            }
        });

        return to_route('parts.index');
    }

    /**
     * Update an existing part and its supplier prices.
     */
    public function update(UpdatePartRequest $request, Part $part): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $part): void {
            $part->update([
                'part_number' => $validated['part_number'],
                'name' => $validated['name'],
                'category' => $validated['category'],
                'inventory_type' => $validated['inventory_type'],
                'description' => $validated['description'] ?? null,
                'selling_price' => $validated['selling_price'],
                'safety_stock' => $validated['safety_stock'],
            ]);

            $part->supplierPrices()->delete();

            collect($validated['suppliers'] ?? [])
                ->unique('supplier_id')
                ->each(function (array $supplierRow) use ($part): void {
                    PartSupplierPrice::query()->create([
                        'part_id' => $part->id,
                        'supplier_id' => $supplierRow['supplier_id'],
                        'purchase_price' => $supplierRow['purchase_price'],
                    ]);
                });

            $part->stocks()->delete();

            collect($validated['stocks'] ?? [])
                ->filter(fn (array $stock): bool => (int) $stock['quantity'] > 0)
                ->unique('warehouse_id')
                ->each(function (array $stockRow) use ($part): void {
                    Stock::query()->create([
                        'part_id' => $part->id,
                        'warehouse_id' => $stockRow['warehouse_id'],
                        'quantity' => $stockRow['quantity'],
                    ]);
                });
        });

        return to_route('parts.index', $request->only('search', 'page'));
    }

    /**
     * Delete a part.
     */
    public function destroy(Request $request, Part $part): RedirectResponse
    {
        $part->delete();

        return to_route('parts.index', $request->only('search', 'page'));
    }

    /**
     * Show stock by warehouse and total quantity.
     */
    public function stock(): Response
    {
        $stocks = Stock::query()
            ->with(['part:id,part_number,name,inventory_type', 'warehouse:id,code,name'])
            ->orderByDesc('quantity')
            ->get()
            ->map(function (Stock $stock): array {
                return [
                    'id' => $stock->id,
                    'part_number' => $stock->part?->part_number,
                    'part_name' => $stock->part?->name,
                    'inventory_type' => $stock->part?->inventory_type,
                    'warehouse_code' => $stock->warehouse?->code,
                    'warehouse_name' => $stock->warehouse?->name,
                    'quantity' => (int) $stock->quantity,
                ];
            });

        $stockSummary = Stock::query()
            ->join('parts', 'parts.id', '=', 'stocks.part_id')
            ->selectRaw('stocks.warehouse_id, parts.inventory_type, SUM(stocks.quantity) as total_quantity')
            ->groupBy('stocks.warehouse_id', 'parts.inventory_type')
            ->with('warehouse:id,code,name')
            ->get()
            ->map(function (Stock $stock): array {
                return [
                    'warehouse_code' => $stock->warehouse?->code,
                    'warehouse_name' => $stock->warehouse?->name,
                    'inventory_type' => $stock->inventory_type,
                    'total_quantity' => (int) $stock->total_quantity,
                ];
            });

        $history = StockMovement::query()
            ->with(['part:id,part_number,name,inventory_type', 'warehouse:id,code,name', 'workOrder:id,wo_number', 'toolLoan:id,borrower_name'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(function (StockMovement $movement): array {
                return [
                    'id' => $movement->id,
                    'part_number' => $movement->part?->part_number,
                    'part_name' => $movement->part?->name,
                    'inventory_type' => $movement->part?->inventory_type,
                    'warehouse_code' => $movement->warehouse?->code,
                    'warehouse_name' => $movement->warehouse?->name,
                    'work_order_id' => $movement->workOrder?->id,
                    'wo_number' => $movement->workOrder?->wo_number,
                    'tool_loan_id' => $movement->tool_loan_id,
                    'borrower_name' => $movement->toolLoan?->borrower_name,
                    'movement_type' => $movement->movement_type,
                    'quantity_change' => (float) $movement->quantity_change,
                    'notes' => $movement->notes,
                    'created_at' => $movement->created_at->format('Y-m-d H:i'),
                ];
            });

        $activeToolLoans = ToolLoan::query()
            ->with(['part:id,part_number,name', 'warehouse:id,code,name'])
            ->where('status', ToolLoan::STATUS_BORROWED)
            ->latest('borrowed_at')
            ->get()
            ->map(function (ToolLoan $loan): array {
                $remainingQuantity = max(0, (int) $loan->borrowed_quantity - (int) $loan->returned_quantity);

                return [
                    'id' => $loan->id,
                    'part_id' => $loan->part_id,
                    'part_number' => $loan->part?->part_number,
                    'part_name' => $loan->part?->name,
                    'warehouse_id' => $loan->warehouse_id,
                    'warehouse_code' => $loan->warehouse?->code,
                    'warehouse_name' => $loan->warehouse?->name,
                    'borrower_name' => $loan->borrower_name,
                    'borrowed_quantity' => (int) $loan->borrowed_quantity,
                    'returned_quantity' => (int) $loan->returned_quantity,
                    'remaining_quantity' => $remainingQuantity,
                    'borrowed_at' => $loan->borrowed_at?->format('Y-m-d H:i'),
                    'due_at' => $loan->due_at?->format('Y-m-d H:i'),
                    'notes' => $loan->notes,
                ];
            });

        $toolParts = Part::query()
            ->where('inventory_type', Part::INVENTORY_TYPE_TOOL)
            ->orderBy('name')
            ->get(['id', 'part_number', 'name']);

        return Inertia::render('parts/Stock', [
            'stocks' => $stocks,
            'summary' => $stockSummary,
            'history' => $history,
            'active_tool_loans' => $activeToolLoans,
            'tool_parts' => $toolParts,
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }
}
