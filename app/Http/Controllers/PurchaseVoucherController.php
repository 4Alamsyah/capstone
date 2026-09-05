<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseVoucher\ConvertToPurchaseOrderRequest;
use App\Http\Requests\PurchaseVoucher\StorePurchaseVoucherRequest;
use App\Models\AppSetting;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\Currency;
use App\Models\Part;
use App\Models\PartSupplierPrice;
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
use Illuminate\Validation\ValidationException;
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
            ->where('category', Part::CATEGORY_PURCHASE)
            ->whereDoesntHave('boms', fn ($query) => $query->where('is_active', true))
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

                if (! $part->isPurchasable()) {
                    throw ValidationException::withMessages([
                        'lines' => "Part {$part->part_number} - {$part->name} bukan part purchase (kategori manufacture dan/atau punya BOM aktif) dan tidak boleh masuk Purchase Voucher.",
                    ]);
                }

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
        $purchaseVoucher->load([
            'items.part.supplierPrices.supplier',
            'items.part.boms' => fn ($query) => $query->where('is_active', true),
            'items.purchaseOrderItems',
            'customerOrder',
            'creator',
        ]);

        return Inertia::render('purchase/voucher/Show', [
            'purchaseVoucher' => [
                'id' => $purchaseVoucher->id,
                'pv_number' => $purchaseVoucher->pv_number,
                'status' => $purchaseVoucher->status,
                'source' => $purchaseVoucher->source,
                'customer_order' => $purchaseVoucher->customerOrder
                    ? [
                        'id' => $purchaseVoucher->customerOrder->id,
                        'co_number' => $purchaseVoucher->customerOrder->co_number,
                        'quotation_number' => $purchaseVoucher->customerOrder->quotation_number,
                    ]
                    : null,
                'created_at' => $purchaseVoucher->created_at,
                'creator' => $purchaseVoucher->creator
                    ? ['id' => $purchaseVoucher->creator->id, 'name' => $purchaseVoucher->creator->name]
                    : null,
                'submitted_at' => $purchaseVoucher->submitted_at,
                'approved_at' => $purchaseVoucher->approved_at,
                'approved_by' => $purchaseVoucher->approved_by,
                'rejected_at' => $purchaseVoucher->rejected_at,
                'rejected_by' => $purchaseVoucher->rejected_by,
                'approval_notes' => $purchaseVoucher->approval_notes,
                'required_date' => $purchaseVoucher->required_date?->format('Y-m-d'),
                'notes' => $purchaseVoucher->notes,
                // Each item carries whether its part is still purchase-type (no active BOM
                // of its own) and any supplier prices already on file for it, so the
                // Convert-to-PO screen can flag bad data and pre-fill/backfill pricing.
                'items' => $purchaseVoucher->items->map(fn (PurchaseVoucherItem $item): array => [
                    'id' => $item->id,
                    'part_id' => $item->part_id,
                    'part' => [
                        'id' => $item->part->id,
                        'part_number' => $item->part->part_number,
                        'name' => $item->part->name,
                    ],
                    'quantity' => (float) $item->quantity,
                    'unit' => $item->unit,
                    'stock_on_hand' => (float) $item->stock_on_hand,
                    'remarks' => $item->remarks,
                    'is_purchasable' => $item->part->category === Part::CATEGORY_PURCHASE && $item->part->boms->isEmpty(),
                    'already_converted' => $item->purchaseOrderItems->isNotEmpty(),
                    'supplier_prices' => $item->part->supplierPrices
                        ->map(fn (PartSupplierPrice $price): array => [
                            'supplier_id' => $price->supplier_id,
                            'supplier_name' => $price->supplier?->name,
                            'purchase_price' => (float) $price->purchase_price,
                        ])
                        ->values(),
                ])->values(),
            ],
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'currencies' => Currency::where('is_active', true)->get(['code', 'name', 'symbol']),
            'defaultCurrency' => AppSetting::get('default_currency_code', 'IDR'),
            'canManageApprovals' => auth()->user()->canApprovePurchaseVoucher(),
            'statusLabels' => PurchaseVoucher::statusLabels(),
            'quotations' => CustomerOrder::query()
                ->where('status', CustomerOrder::STATUS_QUOTATION)
                ->with('items.part:id,name')
                ->orderByDesc('order_date')
                ->get(['id', 'co_number'])
                ->map(fn (CustomerOrder $quotation): array => [
                    'quotation_number' => $quotation->co_number,
                    'items_label' => $quotation->items
                        ->map(fn (CustomerOrderItem $item): ?string => $item->part?->name)
                        ->filter()
                        ->unique()
                        ->implode(', '),
                ])
                ->values(),
        ]);
    }

    public function submit(Request $request, PurchaseVoucher $purchaseVoucher): RedirectResponse
    {
        if ($purchaseVoucher->status !== PurchaseVoucher::STATUS_DRAFT) {
            return back()->withErrors(['status' => 'Hanya PV dalam status Draft yang dapat disubmit.']);
        }

        $generatePo = $request->boolean('generate_po');
        $po = null;

        DB::transaction(function () use ($request, $purchaseVoucher, $generatePo, &$po) {
            $purchaseVoucher->update([
                'status' => PurchaseVoucher::STATUS_SUBMITTED,
                'submitted_by' => auth()->id(),
                'submitted_at' => now(),
            ]);

            if ($generatePo) {
                $po = $this->generateAutoPurchaseOrderForShortage($purchaseVoucher, $request->user());
            }
        });

        $message = 'Purchase Voucher berhasil disubmit untuk approval.';

        if ($generatePo) {
            $message .= $po
                ? " PO {$po->po_number} otomatis dibuat untuk part yang stoknya kurang."
                : ' Tidak ada PO yang dibuat otomatis (tidak ada part yang kurang stok, atau part yang kurang belum punya data harga/supplier).';
        }

        return back()->with('success', $message);
    }

    /**
     * Auto-generate a PO for PV items whose requested quantity exceeds current stock,
     * using the supplier on file for the most of those items (ties by lowest total cost) -
     * the same default-supplier heuristic used client-side on the PV detail page.
     * Returns null if nothing is short, or if none of the short parts has a supplier price on file.
     */
    private function generateAutoPurchaseOrderForShortage(PurchaseVoucher $purchaseVoucher, ?User $user): ?PurchaseOrder
    {
        $purchaseVoucher->load('items.part.supplierPrices');

        $shortageItems = $purchaseVoucher->items->filter(function (PurchaseVoucherItem $item) {
            if (! $item->part->isPurchasable()) {
                return false;
            }

            $currentStock = Stock::where('part_id', $item->part_id)->sum('quantity') ?? 0;

            return (float) $item->quantity > (float) $currentStock;
        });

        if ($shortageItems->isEmpty()) {
            return null;
        }

        $coverage = [];

        foreach ($shortageItems as $item) {
            foreach ($item->part->supplierPrices as $price) {
                $coverage[$price->supplier_id]['count'] = ($coverage[$price->supplier_id]['count'] ?? 0) + 1;
                $coverage[$price->supplier_id]['total'] = ($coverage[$price->supplier_id]['total'] ?? 0) + (float) $price->purchase_price;
            }
        }

        $supplierId = null;
        $best = null;

        foreach ($coverage as $candidateId => $stat) {
            if (! $best || $stat['count'] > $best['count'] || ($stat['count'] === $best['count'] && $stat['total'] < $best['total'])) {
                $best = $stat;
                $supplierId = $candidateId;
            }
        }

        if (! $supplierId) {
            return null;
        }

        $po = PurchaseOrder::create([
            'po_number' => PurchaseOrder::generateNumber(),
            'supplier_id' => $supplierId,
            'status' => PurchaseOrder::STATUS_PENDING_APPROVAL,
            'order_date' => now()->toDateString(),
            'currency_code' => AppSetting::get('default_currency_code', 'IDR'),
            'notes' => "Auto-generated dari {$purchaseVoucher->pv_number} untuk part yang stoknya kurang.",
            'created_by' => $user?->id,
        ]);

        foreach ($shortageItems as $item) {
            $price = $item->part->supplierPrices->firstWhere('supplier_id', $supplierId)
                ?? $item->part->supplierPrices->sortBy('purchase_price')->first();
            $unitPrice = (float) ($price->purchase_price ?? 0);

            PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'part_id' => $item->part_id,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice * (float) $item->quantity,
                'purchase_voucher_item_id' => $item->id,
            ]);

            // Backfill the part's price for this supplier if it isn't on file yet -
            // never overwrite an existing price, only fill the gap.
            PartSupplierPrice::firstOrCreate(
                ['part_id' => $item->part_id, 'supplier_id' => $supplierId],
                ['purchase_price' => $unitPrice]
            );
        }

        $po->update(['subtotal' => $po->items->sum('line_total')]);

        $convertedItemIds = PurchaseOrderItem::whereIn(
            'purchase_voucher_item_id',
            $purchaseVoucher->items->pluck('id')
        )->distinct('purchase_voucher_item_id')->pluck('purchase_voucher_item_id')->toArray();

        if (count($convertedItemIds) === $purchaseVoucher->items->count()) {
            $purchaseVoucher->update(['status' => PurchaseVoucher::STATUS_CONVERTED]);
        }

        return $po;
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
                'quo_no' => $validated['quo_no'] ?? null,
                'supplier_id' => $supplier->id,
                'status' => PurchaseOrder::STATUS_PENDING_APPROVAL,
                'order_date' => $orderDate,
                'expected_date' => $expectedDate,
                'currency_code' => $currencyCode,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['lines'] as $line) {
                $pvItem = PurchaseVoucherItem::with('part')->findOrFail($line['purchase_voucher_item_id']);

                if ($pvItem->purchase_voucher_id !== $purchaseVoucher->id) {
                    throw new \InvalidArgumentException('PV item does not belong to this voucher.');
                }

                if (! $pvItem->part->isPurchasable()) {
                    throw ValidationException::withMessages([
                        'lines' => "Part {$pvItem->part->part_number} - {$pvItem->part->name} bukan part purchase (kategori manufacture dan/atau punya BOM aktif) dan tidak boleh dibuatkan PO.",
                    ]);
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

                // Backfill the part's price for this supplier if it isn't on file yet -
                // never overwrite an existing price, only fill the gap.
                PartSupplierPrice::firstOrCreate(
                    ['part_id' => $pvItem->part_id, 'supplier_id' => $supplier->id],
                    ['purchase_price' => $line['unit_price']],
                );
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
            ->where('category', Part::CATEGORY_PURCHASE)
            ->whereDoesntHave('boms', fn ($query) => $query->where('is_active', true))
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

                if (! $part->isPurchasable()) {
                    throw ValidationException::withMessages([
                        'lines' => "Part {$part->part_number} - {$part->name} bukan part purchase (kategori manufacture dan/atau punya BOM aktif) dan tidak boleh masuk Purchase Voucher.",
                    ]);
                }

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
