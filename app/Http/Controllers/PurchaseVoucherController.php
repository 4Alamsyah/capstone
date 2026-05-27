<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseVoucher\ConvertToPurchaseOrderRequest;
use App\Http\Requests\PurchaseVoucher\StorePurchaseVoucherRequest;
use App\Models\AppSetting;
use App\Models\Currency;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseVoucher;
use App\Models\PurchaseVoucherItem;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseVoucherController extends Controller
{
    public function index(Request $request): Response
    {
        $query = PurchaseVoucher::with(['items', 'customerOrder', 'creator']);

        if ($request->filled('search')) {
            $query->where('pv_number', 'like', '%' . $request->input('search') . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', (int) $request->input('status'));
        }

        $vouchers = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        return Inertia::render('purchase/voucher/Index', [
            'purchaseVouchers' => $vouchers->map(fn (PurchaseVoucher $pv) => [
                'id' => $pv->id,
                'pv_number' => $pv->pv_number,
                'status' => $pv->status,
                'source' => $pv->source,
                'customer_order' => $pv->customerOrder ? ['co_number' => $pv->customerOrder->co_number] : null,
                'items_count' => $pv->items->count(),
                'creator' => $pv->creator ? ['name' => $pv->creator->name] : null,
                'created_at' => $pv->created_at?->format('Y-m-d'),
            ])->values(),
            'pagination' => [
                'current_page' => $vouchers->currentPage(),
                'last_page' => $vouchers->lastPage(),
                'per_page' => $vouchers->perPage(),
                'total' => $vouchers->total(),
                'from' => $vouchers->firstItem(),
                'to' => $vouchers->lastItem(),
                'prev_page_url' => $vouchers->previousPageUrl(),
                'next_page_url' => $vouchers->nextPageUrl(),
            ],
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
            ],
            'statusLabels' => PurchaseVoucher::statusLabels(),
            'canManageApprovals' => $request->user()->canApprovePurchaseVoucher(),
        ]);
    }

    public function create(): Response
    {
        $parts = Part::withSum('stocks as total_stock', 'quantity')
            ->select('id', 'part_number', 'name', 'safety_stock')
            ->get();

        return Inertia::render('purchase/voucher/Create', [
            'parts' => $parts,
            'nextPvNumber' => PurchaseVoucher::generateNumber(),
        ]);
    }

    public function store(StorePurchaseVoucherRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $request) {
            $pv = PurchaseVoucher::create([
                'pv_number' => PurchaseVoucher::generateNumber(),
                'status' => PurchaseVoucher::STATUS_DRAFT,
                'source' => $validated['source'],
                'created_by' => $request->user()?->id,
                'required_date' => $validated['required_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['lines'] as $line) {
                $part = Part::findOrFail($line['part_id']);
                $totalStock = Stock::where('part_id', $part->id)->sum('quantity') ?? 0;

                PurchaseVoucherItem::create([
                    'purchase_voucher_id' => $pv->id,
                    'part_id' => $part->id,
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit'] ?? 'PCS',
                    'stock_on_hand' => $totalStock,
                    'remarks' => $line['remarks'] ?? null,
                ]);
            }
        });

        return to_route('purchase.voucher.index')->with('success', 'Purchase Voucher berhasil dibuat.');
    }

    public function show(PurchaseVoucher $purchaseVoucher): Response
    {
        $purchaseVoucher->load(['items.part', 'customerOrder', 'creator']);

        return Inertia::render('purchase/voucher/Show', [
            'purchaseVoucher' => $purchaseVoucher,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'currencies' => Currency::where('is_active', true)->get(['code', 'name', 'symbol']),
            'defaultCurrency' => AppSetting::get('default_currency_code', 'IDR'),
            'canManageApprovals' => auth()->user()->canApprovePurchaseVoucher(),
            'statusLabels' => PurchaseVoucher::statusLabels(),
        ]);
    }

    public function submit(PurchaseVoucher $purchaseVoucher): RedirectResponse
    {
        if ($purchaseVoucher->status !== PurchaseVoucher::STATUS_DRAFT) {
            return back()->withErrors(['status' => 'Hanya PV dalam status Draft yang dapat disubmit.']);
        }

        $purchaseVoucher->update([
            'status' => PurchaseVoucher::STATUS_SUBMITTED,
            'submitted_by' => auth()->id(),
            'submitted_at' => now(),
        ]);

        return back()->with('success', 'Purchase Voucher berhasil disubmit untuk approval.');
    }

    public function approve(Request $request, PurchaseVoucher $purchaseVoucher): RedirectResponse
    {
        $this->ensureApprover($request);

        if ($purchaseVoucher->status !== PurchaseVoucher::STATUS_SUBMITTED) {
            return back()->withErrors(['status' => 'Hanya PV dalam status Submitted yang dapat di-approve.']);
        }

        $validated = $request->validate([
            'approval_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $purchaseVoucher->update([
            'status' => PurchaseVoucher::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'approval_notes' => $validated['approval_notes'] ?? null,
            'rejected_by' => null,
            'rejected_at' => null,
        ]);

        return back()->with('success', 'Purchase Voucher berhasil di-approve.');
    }

    public function reject(Request $request, PurchaseVoucher $purchaseVoucher): RedirectResponse
    {
        $this->ensureApprover($request);

        if ($purchaseVoucher->status !== PurchaseVoucher::STATUS_SUBMITTED) {
            return back()->withErrors(['status' => 'Hanya PV dalam status Submitted yang dapat di-reject.']);
        }

        $validated = $request->validate([
            'approval_notes' => ['required', 'string', 'max:5000'],
        ]);

        $purchaseVoucher->update([
            'status' => PurchaseVoucher::STATUS_REJECTED,
            'rejected_by' => $request->user()->id,
            'rejected_at' => now(),
            'approval_notes' => $validated['approval_notes'],
        ]);

        return back()->with('success', 'Purchase Voucher berhasil di-reject.');
    }

    public function convertToPo(ConvertToPurchaseOrderRequest $request, PurchaseVoucher $purchaseVoucher): RedirectResponse
    {
        if ($purchaseVoucher->status !== PurchaseVoucher::STATUS_APPROVED) {
            return back()->withErrors(['status' => 'Hanya PV dalam status Approved yang dapat diconvert ke PO.']);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $purchaseVoucher, $request) {
            $supplier = Supplier::findOrFail($validated['supplier_id']);
            $orderDate = $validated['order_date'];
            $expectedDate = $validated['expected_date'] ?? null;
            $currencyCode = $validated['currency_code'] ?? AppSetting::get('default_currency_code', 'IDR');

            $po = PurchaseOrder::create([
                'po_number' => PurchaseOrder::generateNumber(),
                'supplier_id' => $supplier->id,
                'status' => PurchaseOrder::STATUS_PENDING_APPROVAL,
                'order_date' => $orderDate,
                'expected_date' => $expectedDate,
                'currency_code' => $currencyCode,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $allItemsConverted = true;

            foreach ($validated['lines'] as $line) {
                $pvItem = PurchaseVoucherItem::findOrFail($line['purchase_voucher_item_id']);

                if ($pvItem->purchase_voucher_id !== $purchaseVoucher->id) {
                    throw new \InvalidArgumentException('PV item does not belong to this voucher.');
                }

                $lineTotal = (float) $line['unit_price'] * (float) $pvItem->quantity;

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'part_id' => $pvItem->part_id,
                    'quantity' => $pvItem->quantity,
                    'unit' => $pvItem->unit,
                    'unit_price' => $line['unit_price'],
                    'line_total' => $lineTotal,
                    'purchase_voucher_item_id' => $pvItem->id,
                ]);
            }

            $po->update([
                'subtotal' => $po->items->sum('line_total'),
            ]);

            // Check if all PV items have been converted to PO items
            $convertedItemIds = PurchaseOrderItem::whereIn(
                'purchase_voucher_item_id',
                $purchaseVoucher->items->pluck('id')
            )->distinct('purchase_voucher_item_id')->pluck('purchase_voucher_item_id')->toArray();

            if (count($convertedItemIds) === $purchaseVoucher->items->count()) {
                $purchaseVoucher->update(['status' => PurchaseVoucher::STATUS_CONVERTED]);
            }
        });

        return to_route('purchase.po.index')->with('success', 'PO berhasil dibuat dari Purchase Voucher.');
    }

    public function stockRecommendations(): Response
    {
        $parts = Part::where('safety_stock', '>', 0)
            ->withSum('stocks as total_stock', 'quantity')
            ->select('id', 'part_number', 'name', 'safety_stock')
            ->get()
            ->filter(function ($part) {
                $totalStock = (int) ($part->total_stock ?? 0);
                return $totalStock < $part->safety_stock;
            })
            ->map(function ($part) {
                return [
                    'id' => $part->id,
                    'part_number' => $part->part_number,
                    'name' => $part->name,
                    'safety_stock' => $part->safety_stock,
                    'total_stock' => (int) ($part->total_stock ?? 0),
                    'deficit' => $part->safety_stock - (int) ($part->total_stock ?? 0),
                ];
            })
            ->values();

        return Inertia::render('purchase/voucher/StockRecommendation', [
            'parts' => $parts,
            'nextPvNumber' => PurchaseVoucher::generateNumber(),
        ]);
    }

    public function generateFromStock(StorePurchaseVoucherRequest $request): RedirectResponse
    {
        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.part_id' => ['required', 'integer', 'exists:parts,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.0001'],
        ]);

        DB::transaction(function () use ($validated, $request) {
            $pv = PurchaseVoucher::create([
                'pv_number' => PurchaseVoucher::generateNumber(),
                'status' => PurchaseVoucher::STATUS_DRAFT,
                'source' => PurchaseVoucher::SOURCE_STOCK_RECOMMENDATION,
                'created_by' => $request->user()?->id,
                'notes' => 'Auto-generated from stock recommendation',
            ]);

            foreach ($validated['lines'] as $line) {
                $part = Part::findOrFail($line['part_id']);
                $totalStock = Stock::where('part_id', $part->id)->sum('quantity') ?? 0;

                PurchaseVoucherItem::create([
                    'purchase_voucher_id' => $pv->id,
                    'part_id' => $part->id,
                    'quantity' => $line['quantity'],
                    'unit' => 'PCS',
                    'stock_on_hand' => $totalStock,
                ]);
            }
        });

        return to_route('purchase.voucher.index')->with('success', 'Purchase Voucher dari stock recommendation berhasil dibuat.');
    }

    public function destroy(PurchaseVoucher $purchaseVoucher): RedirectResponse
    {
        if (!in_array($purchaseVoucher->status, [
            PurchaseVoucher::STATUS_DRAFT,
            PurchaseVoucher::STATUS_REJECTED,
            PurchaseVoucher::STATUS_CANCELLED,
        ])) {
            return back()->withErrors(['status' => 'Hanya PV dalam status Draft, Rejected, atau Cancelled yang dapat dihapus.']);
        }

        $hasLinkedPoItems = PurchaseOrderItem::whereIn(
            'purchase_voucher_item_id',
            $purchaseVoucher->items->pluck('id')
        )->exists();

        if ($hasLinkedPoItems) {
            return back()->withErrors(['linked' => 'PV ini sudah terikat ke PO dan tidak dapat dihapus.']);
        }

        $purchaseVoucher->delete();

        return to_route('purchase.voucher.index')->with('success', 'Purchase Voucher berhasil dihapus.');
    }

    private function ensureApprover(Request $request): void
    {
        $user = $request->user();
        if (!$user instanceof User || !$user->canApprovePurchaseVoucher()) {
            throw new AuthorizationException('Hanya user dengan permission approve.purchase_voucher yang dapat melakukan approval.');
        }
    }
}
