<?php

namespace App\Http\Controllers;

use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    /**
     * Show supplier management page.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $suppliers = Supplier::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('suppliers/Index', [
            'suppliers' => collect($suppliers->items())->map(fn (Supplier $supplier): array => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'address' => $supplier->address,
                'created_at' => (string) $supplier->created_at,
            ])->values(),
            'filters' => [
                'search' => $search,
            ],
            'pagination' => [
                'current_page' => $suppliers->currentPage(),
                'last_page' => $suppliers->lastPage(),
                'per_page' => $suppliers->perPage(),
                'total' => $suppliers->total(),
                'from' => $suppliers->firstItem(),
                'to' => $suppliers->lastItem(),
                'prev_page_url' => $suppliers->previousPageUrl(),
                'next_page_url' => $suppliers->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Store a new supplier.
     */
    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        Supplier::query()->create($request->validated());

        return to_route('suppliers.index');
    }

    /**
     * Update supplier data.
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return to_route('suppliers.index');
    }

    /**
     * Delete supplier.
     */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier->delete();

        return to_route('suppliers.index');
    }
}
