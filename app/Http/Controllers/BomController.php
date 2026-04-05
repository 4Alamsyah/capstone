<?php

namespace App\Http\Controllers;

use App\Http\Requests\Bom\StoreBomRequest;
use App\Http\Requests\Bom\UpdateBomRequest;
use App\Models\Bom;
use App\Models\BomItem;
use App\Models\Part;
use App\Models\WorkCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BomController extends Controller
{
    /**
     * List all BOMs with search + pagination.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $boms = Bom::query()
            ->with('part')
            ->withCount('items')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhereHas('part', function ($pq) use ($search): void {
                          $pq->where('name', 'like', "%{$search}%")
                             ->orWhere('part_number', 'like', "%{$search}%");
                      });
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('bom/Index', [
            'boms' => collect($boms->items())->map(fn (Bom $bom): array => [
                'id'          => $bom->id,
                'name'        => $bom->name,
                'description' => $bom->description,
                'is_active'   => $bom->is_active,
                'items_count' => $bom->items_count,
                'part'        => [
                    'id'          => $bom->part->id,
                    'part_number' => $bom->part->part_number,
                    'name'        => $bom->part->name,
                ],
                'created_at'  => (string) $bom->created_at,
            ])->values(),
            'filters' => ['search' => $search],
            'pagination' => [
                'current_page'  => $boms->currentPage(),
                'last_page'     => $boms->lastPage(),
                'per_page'      => $boms->perPage(),
                'total'         => $boms->total(),
                'from'          => $boms->firstItem(),
                'to'            => $boms->lastItem(),
                'prev_page_url' => $boms->previousPageUrl(),
                'next_page_url' => $boms->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Show create BOM form.
     */
    public function create(Request $request): Response
    {
        $preselectedPartId = $request->integer('part_id');

        return Inertia::render('bom/Create', [
            'parts'       => Part::orderBy('name')->get(['id', 'part_number', 'name']),
            'workCenters' => WorkCenter::orderBy('name')->get(['id', 'name', 'price_per_operation']),
            'preselectedPartId' => $preselectedPartId > 0 ? $preselectedPartId : null,
        ]);
    }

    /**
     * Store new BOM with items.
     */
    public function store(StoreBomRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $bom = Bom::create([
                'part_id'     => $validated['part_id'],
                'name'        => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active'   => $validated['is_active'] ?? true,
            ]);

            foreach ($validated['items'] ?? [] as $i => $item) {
                BomItem::create([
                    'bom_id'             => $bom->id,
                    'line_type'          => $item['line_type'],
                    'component_part_id'  => $item['component_part_id'] ?? null,
                    'work_center_id'     => $item['work_center_id'] ?? null,
                    'quantity'           => $item['quantity'],
                    'notes'              => $item['notes'] ?? null,
                    'sort_order'         => $item['sort_order'] ?? $i,
                ]);
            }
        });

        return to_route('bom.index');
    }

    /**
     * Show BOM detail.
     */
    public function show(Bom $bom): Response
    {
        $bom->load(['part', 'items.componentPart', 'items.workCenter']);

        return Inertia::render('bom/Show', [
            'bom'         => [
                'id'          => $bom->id,
                'name'        => $bom->name,
                'description' => $bom->description,
                'is_active'   => $bom->is_active,
                'part'        => [
                    'id'          => $bom->part->id,
                    'part_number' => $bom->part->part_number,
                    'name'        => $bom->part->name,
                ],
                'items' => $bom->items->map(fn (BomItem $item): array => [
                    'id'                 => $item->id,
                    'line_type'          => $item->line_type,
                    'component_part_id'  => $item->component_part_id,
                    'work_center_id'     => $item->work_center_id,
                    'quantity'           => (string) $item->quantity,
                    'notes'              => $item->notes,
                    'sort_order'         => $item->sort_order,
                    'label'              => $item->line_type === 'part'
                        ? ($item->componentPart?->part_number . ' – ' . $item->componentPart?->name)
                        : $item->workCenter?->name,
                ])->values(),
                'created_at'  => (string) $bom->created_at,
            ],
            'parts'       => Part::orderBy('name')->get(['id', 'part_number', 'name']),
            'workCenters' => WorkCenter::orderBy('name')->get(['id', 'name', 'price_per_operation']),
        ]);
    }

    /**
     * Update BOM header + re-sync items.
     */
    public function update(UpdateBomRequest $request, Bom $bom): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $bom): void {
            $bom->update([
                'part_id'     => $validated['part_id'],
                'name'        => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active'   => $validated['is_active'] ?? $bom->is_active,
            ]);

            $bom->items()->delete();

            foreach ($validated['items'] ?? [] as $i => $item) {
                BomItem::create([
                    'bom_id'             => $bom->id,
                    'line_type'          => $item['line_type'],
                    'component_part_id'  => $item['component_part_id'] ?? null,
                    'work_center_id'     => $item['work_center_id'] ?? null,
                    'quantity'           => $item['quantity'],
                    'notes'              => $item['notes'] ?? null,
                    'sort_order'         => $item['sort_order'] ?? $i,
                ]);
            }
        });

        return to_route('bom.show', $bom->id);
    }

    /**
     * Delete BOM (items cascade).
     */
    public function destroy(Request $request, Bom $bom): RedirectResponse
    {
        $bom->delete();

        return to_route('bom.index', $request->only('search', 'page'));
    }
}
