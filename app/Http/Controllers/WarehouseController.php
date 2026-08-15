<?php

namespace App\Http\Controllers;

use App\Http\Requests\Warehouse\StoreWarehouseRequest;
use App\Http\Requests\Warehouse\UpdateWarehouseRequest;
use App\Models\ToolLoan;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    /**
     * Show warehouse management page.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $warehouses = Warehouse::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('parts/Warehouses', [
            'warehouses' => collect($warehouses->items())->map(fn (Warehouse $warehouse): array => [
                'id' => $warehouse->id,
                'code' => $warehouse->code,
                'name' => $warehouse->name,
                'location' => $warehouse->location,
            ])->values(),
            'filters' => [
                'search' => $search,
            ],
            'pagination' => [
                'current_page' => $warehouses->currentPage(),
                'last_page' => $warehouses->lastPage(),
                'per_page' => $warehouses->perPage(),
                'total' => $warehouses->total(),
                'from' => $warehouses->firstItem(),
                'to' => $warehouses->lastItem(),
                'prev_page_url' => $warehouses->previousPageUrl(),
                'next_page_url' => $warehouses->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Store a new warehouse.
     */
    public function store(StoreWarehouseRequest $request): RedirectResponse
    {
        Warehouse::query()->create($request->validated());

        return to_route('parts.warehouses.index');
    }

    /**
     * Update warehouse data.
     */
    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($request->validated());

        return to_route('parts.warehouses.index');
    }

    /**
     * Delete warehouse (guarded by stock/tool-loan checks).
     */
    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        if ($warehouse->stocks()->exists() || $warehouse->toolLoans()->where('status', ToolLoan::STATUS_BORROWED)->exists()) {
            return back()->withErrors(['warehouse' => 'Warehouse masih memiliki stok atau pinjaman tool aktif, tidak bisa dihapus.']);
        }

        $warehouse->delete();

        return to_route('parts.warehouses.index');
    }

    /**
     * Quick-create warehouse (JSON response for inline creation from Register Part).
     */
    public function quickStore(StoreWarehouseRequest $request): JsonResponse
    {
        $warehouse = Warehouse::query()->create($request->validated());

        return response()->json($warehouse->only(['id', 'code', 'name', 'location']), 201);
    }
}
