<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\ToolLoan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ToolLoanController extends Controller
{
    /**
     * Borrow tools from inventory and create a tracking record.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'part_id' => [
                'required',
                'integer',
                Rule::exists('parts', 'id')->where(fn ($query) => $query->where('inventory_type', Part::INVENTORY_TYPE_TOOL)),
            ],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'borrowed_quantity' => ['required', 'integer', 'min:1'],
            'borrower_name' => ['required', 'string', 'max:255'],
            'due_at' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            $stock = Stock::query()
                ->where('part_id', $validated['part_id'])
                ->where('warehouse_id', $validated['warehouse_id'])
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Stock tool pada warehouse tersebut tidak ditemukan.',
                ]);
            }

            if ($stock->quantity < (int) $validated['borrowed_quantity']) {
                throw ValidationException::withMessages([
                    'borrowed_quantity' => 'Stock tool tidak cukup untuk dipinjam.',
                ]);
            }

            $stock->decrement('quantity', (int) $validated['borrowed_quantity']);

            $loan = ToolLoan::query()->create([
                'part_id' => $validated['part_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'created_by' => $request->user()?->id,
                'borrower_name' => $validated['borrower_name'],
                'borrowed_quantity' => $validated['borrowed_quantity'],
                'returned_quantity' => 0,
                'borrowed_at' => now(),
                'due_at' => $validated['due_at'] ?? null,
                'status' => ToolLoan::STATUS_BORROWED,
                'notes' => $validated['notes'] ?? null,
            ]);

            StockMovement::query()->create([
                'part_id' => $stock->part_id,
                'warehouse_id' => $stock->warehouse_id,
                'tool_loan_id' => $loan->id,
                'movement_type' => 'tool_borrow',
                'quantity_change' => -(int) $validated['borrowed_quantity'],
                'notes' => 'Borrowed by '.$validated['borrower_name'],
            ]);
        });

        return back();
    }

    /**
     * Return tools and close loan once fully returned.
     */
    public function update(Request $request, ToolLoan $toolLoan): RedirectResponse
    {
        $validated = $request->validate([
            'returned_quantity' => ['required', 'integer', 'min:1'],
            'return_notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($request, $validated, $toolLoan): void {
            $toolLoan = ToolLoan::query()->lockForUpdate()->findOrFail($toolLoan->id);

            if ($toolLoan->status === ToolLoan::STATUS_RETURNED) {
                throw ValidationException::withMessages([
                    'returned_quantity' => 'Tool loan ini sudah selesai dikembalikan.',
                ]);
            }

            $remaining = max(0, (int) $toolLoan->borrowed_quantity - (int) $toolLoan->returned_quantity);
            $returnQty = (int) $validated['returned_quantity'];

            if ($returnQty > $remaining) {
                throw ValidationException::withMessages([
                    'returned_quantity' => 'Qty return melebihi sisa pinjaman tool.',
                ]);
            }

            $stock = Stock::query()->firstOrCreate(
                [
                    'part_id' => $toolLoan->part_id,
                    'warehouse_id' => $toolLoan->warehouse_id,
                ],
                [
                    'quantity' => 0,
                ],
            );

            $stock->increment('quantity', $returnQty);

            $updatedReturnedQty = (int) $toolLoan->returned_quantity + $returnQty;

            $toolLoan->update([
                'returned_quantity' => $updatedReturnedQty,
                'returned_by' => $request->user()?->id,
                'returned_at' => $updatedReturnedQty >= (int) $toolLoan->borrowed_quantity ? now() : $toolLoan->returned_at,
                'status' => $updatedReturnedQty >= (int) $toolLoan->borrowed_quantity
                    ? ToolLoan::STATUS_RETURNED
                    : ToolLoan::STATUS_BORROWED,
                'notes' => $validated['return_notes'] ?? $toolLoan->notes,
            ]);

            StockMovement::query()->create([
                'part_id' => $toolLoan->part_id,
                'warehouse_id' => $toolLoan->warehouse_id,
                'tool_loan_id' => $toolLoan->id,
                'movement_type' => 'tool_return',
                'quantity_change' => $returnQty,
                'notes' => $validated['return_notes'] ?? 'Tool returned',
            ]);
        });

        return back();
    }
}
