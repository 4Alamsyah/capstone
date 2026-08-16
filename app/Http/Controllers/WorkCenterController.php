<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkCenter\StoreWorkCenterRequest;
use App\Http\Requests\WorkCenter\UpdateWorkCenterRequest;
use App\Models\Currency;
use App\Models\WorkCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkCenterController extends Controller
{
    /**
     * Show work center management page with search + pagination.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $workCenters = WorkCenter::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('work-centers/Index', [
            'workCenters' => collect($workCenters->items())->map(fn (WorkCenter $wc): array => [
                'id'                   => $wc->id,
                'name'                 => $wc->name,
                'description'          => $wc->description,
                'price_per_operation'  => $wc->price_per_operation,
                'employee_count'       => $wc->employee_count,
                'created_at'           => (string) $wc->created_at,
            ])->values(),
            'defaultCurrency' => Currency::currentDefault(),
            'filters' => [
                'search' => $search,
            ],
            'pagination' => [
                'current_page'  => $workCenters->currentPage(),
                'last_page'     => $workCenters->lastPage(),
                'per_page'      => $workCenters->perPage(),
                'total'         => $workCenters->total(),
                'from'          => $workCenters->firstItem(),
                'to'            => $workCenters->lastItem(),
                'prev_page_url' => $workCenters->previousPageUrl(),
                'next_page_url' => $workCenters->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Store a new work center.
     */
    public function store(StoreWorkCenterRequest $request): RedirectResponse
    {
        WorkCenter::create($request->validated());

        return to_route('work-centers.index');
    }

    /**
     * Update an existing work center.
     */
    public function update(UpdateWorkCenterRequest $request, WorkCenter $workCenter): RedirectResponse
    {
        $workCenter->update($request->validated());

        return to_route('work-centers.index', $request->only('search', 'page'));
    }

    /**
     * Delete a work center.
     */
    public function destroy(Request $request, WorkCenter $workCenter): RedirectResponse
    {
        $workCenter->delete();

        return to_route('work-centers.index', $request->only('search', 'page'));
    }
}
