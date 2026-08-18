<?php

namespace App\Http\Controllers;

use App\Http\Requests\Uom\StoreUomRequest;
use App\Http\Requests\Uom\UpdateUomRequest;
use App\Models\Uom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UomController extends Controller
{
    /**
     * Show UOM (unit of measure) management page.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $uoms = Uom::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('code')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('parts/Uoms', [
            'uoms' => collect($uoms->items())->map(fn (Uom $uom): array => [
                'id' => $uom->id,
                'code' => $uom->code,
                'name' => $uom->name,
            ])->values(),
            'filters' => [
                'search' => $search,
            ],
            'pagination' => [
                'current_page' => $uoms->currentPage(),
                'last_page' => $uoms->lastPage(),
                'per_page' => $uoms->perPage(),
                'total' => $uoms->total(),
                'from' => $uoms->firstItem(),
                'to' => $uoms->lastItem(),
                'prev_page_url' => $uoms->previousPageUrl(),
                'next_page_url' => $uoms->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Store a new UOM.
     */
    public function store(StoreUomRequest $request): RedirectResponse
    {
        Uom::query()->create($request->validated());

        return to_route('parts.uoms.index');
    }

    /**
     * Update UOM data.
     */
    public function update(UpdateUomRequest $request, Uom $uom): RedirectResponse
    {
        $uom->update($request->validated());

        return to_route('parts.uoms.index');
    }

    /**
     * Delete UOM (guarded by usage on parts/BOM items).
     */
    public function destroy(Uom $uom): RedirectResponse
    {
        if ($uom->parts()->exists() || $uom->bomItems()->exists()) {
            return back()->withErrors(['uom' => 'Satuan masih dipakai oleh part atau item BOM, tidak bisa dihapus.']);
        }

        $uom->delete();

        return to_route('parts.uoms.index');
    }

    /**
     * Quick-create UOM (JSON response for inline creation from other forms).
     */
    public function quickStore(StoreUomRequest $request): JsonResponse
    {
        $uom = Uom::query()->create($request->validated());

        return response()->json($uom->only(['id', 'code', 'name']), 201);
    }
}
