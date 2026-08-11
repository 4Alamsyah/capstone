<?php

namespace App\Http\Controllers;

use App\Http\Requests\Bom\StoreBomRequest;
use App\Http\Requests\Bom\UpdateBomRequest;
use App\Models\Bom;
use App\Models\BomItem;
use App\Models\Part;
use App\Models\WorkCenter;
use Illuminate\Http\JsonResponse;
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
            'parts'       => $this->partOptionsWithBomFlag(),
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
                'part_id'           => $validated['part_id'],
                'name'              => $validated['name'],
                'description'       => $validated['description'] ?? null,
                'is_active'         => $validated['is_active'] ?? true,
                'planning_strategy' => $validated['planning_strategy'],
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
        return Inertia::render('bom/Show', [
            'bom'         => $this->buildEditableBomTree($bom, ''),
            'parts'       => $this->partOptionsWithBomFlag(),
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
                'part_id'           => $validated['part_id'],
                'name'              => $validated['name'],
                'description'       => $validated['description'] ?? null,
                'is_active'         => $validated['is_active'] ?? $bom->is_active,
                'planning_strategy' => $validated['planning_strategy'],
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

        return back();
    }

    /**
     * Delete BOM (items cascade).
     */
    public function destroy(Request $request, Bom $bom): RedirectResponse
    {
        if ($bom->workOrders()->exists()) {
            return back()->withErrors(['bom' => 'BOM ini tidak dapat dihapus karena masih digunakan oleh Manufacture Order.']);
        }

        $bom->delete();

        return to_route('bom.index', $request->only('search', 'page'));
    }

    /**
     * Nested BOM hierarchy for a part, used to preview a sub-assembly's
     * own bill of materials when it's picked as a component elsewhere.
     */
    public function tree(Part $part): JsonResponse
    {
        return response()->json([
            'tree' => $this->buildPartBomTree($part),
        ]);
    }

    /**
     * Parts list annotated with whether each part has its own active BOM
     * (i.e. it's a sub-assembly rather than a raw material).
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function partOptionsWithBomFlag(): \Illuminate\Support\Collection
    {
        $partIdsWithBom = Bom::where('is_active', true)->pluck('part_id')->unique();

        return Part::orderBy('name')->get(['id', 'part_number', 'name'])
            ->map(fn (Part $part): array => [
                'id'          => $part->id,
                'part_number' => $part->part_number,
                'name'        => $part->name,
                'has_bom'     => $partIdsWithBom->contains($part->id),
            ]);
    }

    /**
     * Recursively build a part's BOM hierarchy. Returns null if the part
     * has no active BOM. Guards against circular part references.
     *
     * @param  list<int>  $visited
     * @return array<string, mixed>|null
     */
    private function buildPartBomTree(Part $part, array $visited = []): ?array
    {
        if (in_array($part->id, $visited, true)) {
            return null;
        }

        $bom = $part->activeBom();

        if (! $bom) {
            return null;
        }

        $visited[] = $part->id;

        $bom->load(['items.componentPart', 'items.workCenter']);

        return [
            'bom_id'   => $bom->id,
            'bom_name' => $bom->name,
            'items'    => $bom->items->map(fn (BomItem $item): array => [
                'id'         => $item->id,
                'line_type'  => $item->line_type,
                'label'      => $item->line_type === 'part'
                    ? ($item->componentPart?->part_number . ' – ' . $item->componentPart?->name)
                    : $item->workCenter?->name,
                'quantity'   => (string) $item->quantity,
                'notes'      => $item->notes,
                'sub_bom'    => $item->line_type === 'part' && $item->componentPart
                    ? $this->buildPartBomTree($item->componentPart, $visited)
                    : null,
            ])->values(),
        ];
    }

    /**
     * Recursively build a fully editable BOM tree: every node (root and each
     * nested sub-assembly, which belongs to its own separate Bom record) carries
     * its own header fields and full item data, so any node can be viewed or
     * edited in place. `code` is an outline position (e.g. "1", "1.1") assigned
     * among sub-assembly siblings only; the root's code is ''.
     *
     * @param  list<int>  $visited
     * @return array<string, mixed>
     */
    private function buildEditableBomTree(Bom $bom, string $code, array $visited = []): array
    {
        $bom->load(['part', 'items.componentPart', 'items.workCenter']);
        $visited = [...$visited, $bom->part_id];

        $childIndex = 1;
        $children = [];

        $items = $bom->items->map(function (BomItem $item) use (&$children, &$childIndex, $code, $visited): array {
            $subBom = null;

            if ($item->line_type === 'part' && $item->componentPart && ! in_array($item->componentPart->id, $visited, true)) {
                $subBom = $item->componentPart->activeBom();
            }

            if ($subBom) {
                $childCode = $code === '' ? (string) $childIndex : "{$code}.{$childIndex}";
                $children[] = $this->buildEditableBomTree($subBom, $childCode, $visited);
                $childIndex++;
            }

            return [
                'id'                => $item->id,
                'line_type'         => $item->line_type,
                'component_part_id' => $item->component_part_id,
                'work_center_id'    => $item->work_center_id,
                'quantity'          => (string) $item->quantity,
                'notes'             => $item->notes,
                'sort_order'        => $item->sort_order,
                'label'             => $item->line_type === 'part'
                    ? ($item->componentPart?->part_number . ' – ' . $item->componentPart?->name)
                    : $item->workCenter?->name,
                'has_sub_bom'       => $subBom !== null,
            ];
        })->values();

        return [
            'code'              => $code,
            'bom_id'            => $bom->id,
            'name'              => $bom->name,
            'description'       => $bom->description,
            'is_active'         => $bom->is_active,
            'planning_strategy' => $bom->planning_strategy,
            'part'              => [
                'id'          => $bom->part->id,
                'part_number' => $bom->part->part_number,
                'name'        => $bom->part->name,
            ],
            'items'             => $items,
            'children'          => $children,
        ];
    }
}
